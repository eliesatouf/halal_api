<?php
// src/Service/OrderEmailService.php
namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;

class OrderEmailService
{
    private $mailer;
    private $twig;
    private $logger;
    private $settingsService;

    public function __construct(MailerInterface $mailer, Environment $twig, LoggerInterface $logger, SettingsService $settingsService)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->settingsService = $settingsService;
    }

    public function sendOrderConfirmation(Order $order, $status = null): string
    {
        $orderStatus = $order->getOrderStatus();
        $this->logger->info("sendOrderConfirmation called, status = $orderStatus");
        try {
            $emailTemplate = 'email/order_confirmation.html.twig';
            $header = "We've received your order and it's now being prepared. Below are your order details:";
            $footer = "We'll notify you when your order is ready for pickup.";

            if (strtolower($order->getOrderType()) == 'delivery') {
                $footer = "We'll notify you when your order is out for delivery";
            }

            if ($orderStatus == 3) {
                if (strtolower($order->getOrderType()) == 'delivery') {
                    $header = 'Your order is out for delivery to: ' . $order->getCustomer()->getAddress();
                } else {
                    $header = 'Your order is ready to pickup!';
                }
                $emailTemplate = 'email/order_ready.html.twig';

            }
            $customer = $order->getCustomer();
            if (!$customer) {
                $this->logger->error("Could not send email: No customer associated with order");
                $err = 'Could not send email: No customer associated with order';
                return $err;
            }

            // Get customer email - adjust based on your Customer entity
            $customerEmail = method_exists($customer, 'getEmail') ? $customer->getEmail() : null;
            if (!$customerEmail) {
                $this->logger->error("Could not send email: Customer email not found");
                $err = 'Could not send email: Customer email not found';
                return $err;
            }

            $customerName = $customer->getName();

            $logoPath = $this->settingsService->get('LOGO_PATH');
            $brandName = $this->settingsService->get('BRAND_NAME');
            $location = $this->settingsService->get('BRAND_LOCATION');
            $contactNumber = $this->settingsService->get('CONTACT_NUMBER');
            $contactEmail = $this->settingsService->get('CONTACT_EMAIL');

            $this->logger->info("CreateEmail");
            // Create email content
            $htmlContent = $this->twig->render($emailTemplate, [
                'customer_name' => $customerName,
                'order_details' => $this->formatOrderDetails($order),
                'order' => $order,
                'logo_url' => $logoPath,
                'restaurant_name' => $brandName,
                'restaurant_address' => $location,
                'restaurant_phone' => $contactNumber,
                'order_type' => $order->getOrderType(),
                'header' => $header,
                'footer' => $footer
            ]);

            $this->logger->info("htmlContent $htmlContent");

            // Create and send the email
            $senderName = $this->settingsService->get('APP_NAME', '');
            $email = (new TemplatedEmail())
                ->from(new Address($contactEmail, $senderName))
                ->to($customerEmail)
                ->subject('Order Confirmation #' . $order->getOrderNumber())
                ->html($htmlContent);

            $email->getHeaders()
                ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

            try {
                $this->logger->info("SendingEmail");
                $emailRes = $this->mailer->send($email);
                $this->logger->info("EmailSent Res: $emailRes");
            } catch (TransportExceptionInterface $e) {
                if ($e)
                    return $e;
            }


            // $email = $customer->getEmail();

            return ('Order confirmation email sent successfully for order: ' . $emailRes);

        } catch (\Exception $e) {
            return ('Failed to send order confirmation email: ' . $e->getMessage());
        }
        return $emailRes;
    }

    private function formatOrderDetails(Order $order): string
    {
        // Simple formatting - adjust based on your Order entity structure
        $html = '<div class="order-summary">';
        $html .= '<h3>Order #' . $order->getOrderNumber() . '</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr style="background-color: #f8f8f8;">';
        $html .= '<th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Item</th>';
        $html .= '<th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Qty</th>';
        $html .= '<th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Price</th>';
        $html .= '</tr>';

        // Add order items
        foreach ($order->getOrderItem() as $item) {
            $html .= '<tr>';

            // Get item name
            $itemName = 'Unknown Item';
            if ($item->getDish()) {
                $itemName = $item->getDish()->getName();
                $originalPrice = $item->getDish()->getPrice();
                $variantName = $item->getVariantName();
            } elseif ($item->getBundle()) {
                $itemName = $item->getBundle()->getName() . ' (Bundle)';
                $originalPrice = $item->getBundle()->getOriginalPrice() ?? 0;

            }

            $html .= '<td style="padding: 10px;">' . $itemName . ' -' . $variantName . '</td>';

            $html .= '<td style="padding: 10px; text-align: right;">' . $item->getQuantity() . '</td>';

            $price = $item->getTotalPrice() ?? 0;
            $html .= '<td style="padding: 10px; text-align: right; ">₱' . number_format($price, 2) . '</td>';
            $html .= '</tr>';

            $modifiers = $item->getLineModifiers();
            if ($modifiers && count($modifiers) > 0) {
                $html .= '<tr>';
                foreach ($modifiers as $modifier) {
                    $html .= '<td style="padding: 10px;">' . $modifier['name'] . ' -  ₱' . $modifier['price'] . '</td>';
                }
                $html .= '</tr>';
            }


            $html .= '<tr>';

            // Get item name
            $itemName = 'Unknown Item';
            if ($item->getDish()) {
                $itemName = $item->getDish()->getName();
            } elseif ($item->getBundle()) {
                $itemName = $item->getBundle()->getName() . ' (Bundle)';
            }

            $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . '</td>';
            $html .= '<td style="padding: 10px; border-bottom: 1px solid #eee;" text-align: right;">' . '</td>';

            if ($price < $originalPrice && $originalPrice > 0) {
                $html .= '<td style="padding: 10px; text-align: right; border-bottom: 1px solid #eee; text-decoration-line: line-through;">₱' . number_format($originalPrice, 2) . '</td>';
            }


            $html .= '</tr>';


        }

        // Add order totals
        $subtotal = $order->getSubtotal() ?? 0;
        $deliveryFee = $order->getDeliveryFee() ?? 0;
        $totalDiscount = $order->getTotalDiscount() ?? 0;
        $grandTotal = $order->getGrandTotal() ?? 0;

        $html .= '<tr style="font-weight: bold;">';
        $html .= '<td colspan="2" style="padding: 10px; border-top: 2px solid #ddd;">Subtotal</td>';
        $html .= '<td style="padding: 10px; text-align: right; border-top: 2px solid #ddd;">₱' . number_format($subtotal, 2) . '</td>';
        $html .= '</tr>';

        if ((int) $deliveryFee > 0) {
            $html .= '<tr style="font-weight: bold;">';
            $html .= '<td colspan="2" style="padding: 10px;">Delivery Fee</td>';
            $html .= '<td style="padding: 10px; text-align: right;">₱' . number_format($deliveryFee, 2) . '</td>';
            $html .= '</tr>';
        }

        if ($totalDiscount > 0) {
            $html .= '<tr style="font-weight: bold; color: green;">';
            $html .= '<td colspan="2" style="padding: 10px;">Amount saved</td>';
            $html .= '<td style="padding: 10px; text-align: right;">₱' . number_format($totalDiscount, 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr style="font-weight: bold; font-size: 1.1em;">';
        $html .= '<td colspan="2" style="padding: 10px; border-top: 2px solid #ddd;">Total</td>';
        $html .= '<td style="padding: 10px; text-align: right; border-top: 2px solid #ddd;">₱' . number_format($grandTotal, 2) . '</td>';
        $html .= '</tr>';

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }
}