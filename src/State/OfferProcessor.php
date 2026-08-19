<?php
// src/State/OfferProcessor.php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Service\OfferStrategy\OfferStrategyInterface;
use Psr\Log\LoggerInterface;
use App\Repository\OfferTypeRepository;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\OrderEmailService;
use App\Service\LoyverseSyncService;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use App\Service\LoyaltyService;
use App\Service\SettingsService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Entity\Settings;
use App\Repository\SettingsRepository;

class OfferProcessor implements ProcessorInterface
{
    /** @var OfferStrategyInterface[] */
    private iterable $strategies;
    private $entityManager;
    private $orderEmailService;
    private bool $mailSent;
    private HubInterface $mercureHub;
   
    public function __construct(
        private ProcessorInterface $persistProcessor,
        iterable $strategies,
        private LoggerInterface $logger,
        private OfferTypeRepository $offerTypeRepository,
        private Security $security,
        EntityManagerInterface $entityManager,
        OrderEmailService $orderEmailService,
        private LoyverseSyncService $loyverseSyncService,
        HubInterface $mercureHub,
        private LoyaltyService $loyaltyService,
        private SettingsService $settingsService,
        private AuthorizationCheckerInterface $authorizationChecker,
        private SettingsRepository $settingsRepository
    ) {
        $this->strategies = $strategies;
        $this->orderEmailService = $orderEmailService;
        $this->mailSent = 0;
        $this->entityManager = $entityManager;
        $this->mercureHub = $mercureHub;
    }

    public function process($order, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->logger->error("OfferProcessor process called");
        $isNew = null === $order->getId();

        // Check if user has admin role
        $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');

        // Get closing date from settings
        $closingDateString = $this->settingsRepository->getValue('closing_date');
        $closingDate = new \DateTime($closingDateString);
        $today = new \DateTime();

        $this->logger->info("Closing date: " . $closingDate->format('Y-m-d H:i:s'));
        $this->logger->info("Today: " . $today->format('Y-m-d H:i:s'));
        $this->logger->info("Is admin: " . ($isAdmin ? 'YES' : 'NO'));

        $user = $this->security->getUser();

        if (!$isAdmin && $today < $closingDate) {
            // Deny access - after closing date and not admin
            $this->logger->warning("Order denied - after closing date and user is not admin");
            throw new AccessDeniedException(
                sprintf('Orders cannot be created after closing date (%s). Only administrators can create orders now.', 
                    $closingDate->format('Y-m-d H:i:s'))
            );
        }

        // Allow if: User is admin OR we're before closing date
        if ($user && method_exists($user, 'getCustomer')) {
            $this->logger->info("Order allowed for user: " . $user->getUserIdentifier());
        } else if (!$user) {
            throw new \RuntimeException('No authenticated user found.');
        } else {
            throw new \RuntimeException('The authenticated user does not have a linked Customer.');
        }

        if ($operation instanceof Patch) {
            $this->logger->error("OfferProcessor PATCH ");

            if($order->getOrderStatus() == 2){ //-- if confirmed by staff 
                // Send confirmation email if web order
                if ($order->getOrderChannel() == '1') {
                    $res = $this->orderEmailService->sendOrderConfirmation($order);

                    if (str_contains($res, 'successfully')) {
                        $order->setOrderStatus(2); // Confirmed
                    } else if (str_contains($res, 'Failed')) {
                        $order->setOrderStatus(7); // Email failed
                    }
                } else {
                    $order->setOrderStatus(2); // Confirmed for non-manual orders
                }

                // Push to Loyverse
                $pushToLoyverse = $this->settingsService->get('PUSH_RECEIPT_TO_LOYVERSE');

                if($pushToLoyverse == '1')
                {
                    try {
                        $this->logger->info("Pushing order to Loyverse");
                        $loyverseResult = $this->loyverseSyncService->pushOrderToLoyverse($order);

                        if ($loyverseResult) {
                            $this->logger->info("Order successfully pushed to Loyverse");
                        } else {
                            $this->logger->error("Failed to push order to Loyverse");
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('Exception pushing order to Loyverse: ' . $e->getMessage());
                    }
                }
            }

            if ($order->getOrderStatus() == 3) { //-- order is ready
                $this->logger->error("getOrderStatus " . $order->getOrderStatus());
                $res = $this->orderEmailService->sendOrderConfirmation($order, 3);
                $this->logger->error("emailStatus " . $res);

                if (str_contains($res, 'successfully')) {
                    $order->setOrderStatus(3);
                } else if (str_contains($res, 'Failed')) {
                    $order->setOrderStatus(7);
                }
            }
        }

        if ($operation instanceof Post) {
            $this->logger->error("OfferProcessor POST ");

            $user = $this->security->getUser();
            $customer = $user->getCustomer();

            $order->setCustomer($user->getCustomer());
            $order->setDeliveryAddress($user->getCustomer()->getAddress());
            $items = $order->getOrderItem()->toArray();

            foreach ($order->getOrderItem()->toArray() as $item) {
                $customer->incrementVisitCount();
                $variantId = $item->getVariantId();
            }
        }

        $this->processOrder($order);
        $order->calculateTotals();

        if ($isNew && $order instanceof Order) {
            $this->logger->info("Processing new order");

            // First save the order to get an ID
            $order = $this->persistProcessor->process($order, $operation, $uriVariables, $context);
            $this->entityManager->flush();

            // Try to publish to Mercure, but don't fail if it doesn't work
            try {
                $this->publishToMercure($order);
            } catch (\Exception $e) {
                $this->logger->error('Mercure publishing failed but order was saved: ' . $e->getMessage());
                // Continue processing - order is already saved
            }

            if ($order->getOrderStatus() == 1 || $order->getOrderStatus() === '1') {
                $this->logger->info("Order is completed - checking for rewards to claim");
                
                foreach ($order->getOrderItem() as $item) {
                    $reward = $item->getAppliedReward();
                    if ($reward) {
                        $this->logger->info("Found reward ID: " . $reward->getId() . " with status: " . $reward->getStatus());
                        
                        if ($reward->getStatus() === 'active') {
                            $reward->setStatus('claimed');
                            $reward->setClaimedAt(new \DateTime());
                            $this->entityManager->persist($reward);
                            $this->logger->info("✅ Reward ID " . $reward->getId() . " claimed successfully");
                        } else {
                            $this->logger->info("❌ Reward not active, status: " . $reward->getStatus());
                        }
                    }
                }
                $this->entityManager->flush();
            } else {
                $this->logger->info("Order not completed, status: " . $order->getOrderStatus() . " - skipping reward claim");
            }

            if ($order->getAppliedReward()) {
                $this->loyaltyService->useReward($order->getAppliedReward());
            }

            if ($order->getOrderStatus() == 6) { // Cancelled order
                foreach ($order->getOrderItem() as $item) {
                    $reward = $item->getAppliedReward();
                    if ($reward && $reward->getStatus() === 'claimed') {
                        $reward->setStatus('active');
                        $reward->setClaimedAt(null);
                        $this->entityManager->persist($reward);
                        $this->logger->info("Reward ID " . $reward->getId() . " reactivated due to cancellation");
                    }
                }
                $this->entityManager->flush();
            }

            // Check for new rewards AFTER persistence
            $this->loyaltyService->checkAndCreateRewards($customer);

            if ($order->getAppliedReward()) {
                $this->loyaltyService->useReward($order->getAppliedReward());
            }

            return $order;
        } else {
            $orderPost = $this->persistProcessor->process($order, $operation, $uriVariables, $context);
            return $orderPost;
        }
    }


    private function publishToMercure(Order $order): void
    {
        $mercureUrl = $this->settingsService->get('MERCURE_URL');
        
        if (!$mercureUrl || $mercureUrl === 'disabled') {
            $this->logger->info('Mercure is disabled, skipping publishing');
            return;
        }
        
        try {
            // Publish minimal data instead of full serialization
            $update1 = new Update(
                '/api/orders/' . $order->getId(),
                json_encode(['id' => $order->getId(), 'status' => $order->getOrderStatus()])
            );
            
            $update2 = new Update(
                '/tidybubbles_orders',
                json_encode(['id' => $order->getId(), 'status' => $order->getOrderStatus()])
            );
            
            $this->mercureHub->publish($update1);
            $this->mercureHub->publish($update2);
            
            $this->logger->info('Successfully published to Mercure');
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish to Mercure: ' . $e->getMessage());
            throw $e; // Re-throw to be caught in the main process method
        }
    }

    private function processOrder(Order $order): void
    {
        $this->logger->error("Processing order with " . $order->getOrderItem()->count() . " items");

        // Reset processed offers for all strategies
        foreach ($this->strategies as $strategy) {
            if (method_exists($strategy, 'resetProcessedOffers')) {
                $strategy->resetProcessedOffers();
            }
            if (method_exists($strategy, 'resetProcessedCustomers')) {
                $strategy->resetProcessedCustomers();
            }
        }

        // Process item-level offers first (BOGO, BuyXGetY, etc.)
        foreach ($order->getOrderItem()->toArray() as $item) {
            $dish = $item->getDish();
            if (!$dish)
                continue;

            $offer = $dish->getActiveOffer();
            if (!$offer)
                continue;

            $offerType = $this->getOfferType($offer);
            $this->logger->error("Detected offer type: " . $offerType . " for dish: " . $dish->getName());

            foreach ($this->strategies as $strategy) {
                if ($strategy->supports($offerType)) {
                    $this->logger->error("Applying strategy: " . get_class($strategy));
                    $strategy->apply($order, $item, $offer);
                    break;
                }
            }
        }

        // Apply order-level discounts (like first order discount)
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports('first_order_discount')) {
                $dummyItem = new \App\Entity\OrderItem();
                $dummyItem->setOrder($order);
                
                $this->logger->error("Applying FirstOrderDiscountStrategy");
                $strategy->apply($order, $dummyItem, null);
                break;
            }
        }

        // Ensure all prices are set correctly after processing
        foreach ($order->getOrderItem() as $item) {
            $reward = $item->getAppliedReward();
            if ($reward) {
                $this->logger->info("appliedReward ID: " . $reward->getId());
            } else {
                $this->logger->info("appliedReward: null");
            }

            if ($item->getDish()) {
                $item->setPriceFromDish();
            }
        }
    }

    private function getOfferType(object $offer): string
    {
        // Handle Doctrine proxy objects
        if ($offer instanceof \Doctrine\ORM\Proxy\Proxy) {
            if (!$offer->__isInitialized()) {
                $offer->__load();
            }
        }

        // Check if this is actually a Buy X Get Y offer
        if (method_exists($offer, 'getDiscount')) {
            $discount = $offer->getDiscount();

            if ($discount && method_exists($discount, 'getBuyQuantity') && method_exists($discount, 'getGetQuantity')) {
                $buyQty = $discount->getBuyQuantity() ?? 0;
                $getQty = $discount->getGetQuantity() ?? 0;

                if ($buyQty > 0 && $getQty > 0) {
                    $this->logger->error("Detected as Buy X Get Y offer");
                    return 'buy_x_get_y';
                }
            }

            // Check if this is traditional BOGO
            if ($discount && method_exists($discount, 'getBogoBuyQuantity') && method_exists($discount, 'getBogoGetQuantity')) {
                $bogoBuyQty = $discount->getBogoBuyQuantity() ?? 0;
                $bogoGetQty = $discount->getBogoGetQuantity() ?? 0;

                if ($bogoBuyQty > 0 && $bogoGetQty > 0) {
                    $this->logger->error("Detected as traditional BOGO offer");
                    return 'bogo';
                }
            }
        }

        // Fallback to database type
        if (method_exists($offer, 'getType')) {
            $typeIri = $offer->getType();
            if ($typeIri) {
                try {
                    $typeId = explode('/', $typeIri)[3];
                    $offerType = $this->offerTypeRepository->find($typeId);
                    if ($offerType) {
                        $this->logger->error("Using database offer type: " . $offerType->getName());
                        return $offerType->getName();
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error getting offer type from DB: ' . $e->getMessage());
                }
            }
        }

        $this->logger->error("Could not determine offer type, defaulting to 'regular'");
        return 'regular';
    }

    private function getOfferTypeName(?string $typeIri): ?string
    {
        $typeId = explode('/', $typeIri)[3];
        $offerType = $this->offerTypeRepository->find($typeId)->getName();
        return $offerType;
    }
}