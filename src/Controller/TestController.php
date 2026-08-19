<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Service\SettingsService;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\HttpFoundation\JsonResponse;




final class TestController extends AbstractController
{
    #[Route('/api/test', name: 'app_test')]
    public function index(#[CurrentUser] ?User $user,Request $request,EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher, SettingsService $settingsService): Response
    {
        
        $firstOrderEnabled = $settingsService->getBool('FIRST_ORDER_ENABLED', false);


        return $this->json([
                    'firstOrderEnabled' => $firstOrderEnabled]);


        // $entitiesClassNames = $entityManager->getConfiguration()->getMetadataDriverImpl();

        // $entities= [];
        // $metas = $entityManager->getMetadataFactory()->getAllMetadata();
        // foreach ($metas as $meta) {
        //     $entities[] = $meta->getName();
        // }

        // $entityClassNames =[];
        // $metadataFactory = $entityManager->getMetadataFactory();
        // $allMetadata = $metadataFactory->getAllMetadata();

        // foreach ($allMetadata as $metadata) {
        //     $entityClassNames[] = $metadata->getName();
        // }


        // return $this->json([
        //         'message' => $entityClassNames]);

        $uuid = Uuid::v7();
        $password="Es@123";
        $user = new User();
            //hash the password (based on the security.yaml config for the $user class)
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $password
            );

        $user->setEmail('elie.satouf@gmail.com');
        $user->setRoles(["ROLE_ADMIN"]);
        $user->setPassword($hashedPassword);
        $user->setActive(true);

        $entityManager->persist($user);
        $entityManager->flush();
         return $this->json([
                    'message' => $uuid]);

        return $this->render('test/index.html.twig', [
            'controller_name' => 'TestController',
        ]);
    }

    #[Route('/api/debug-mercure')]
    public function debug(HubInterface $hub): JsonResponse
    {
        try {
            // Method 1: Try to get URL info
            $url = $hub->getUrl();
            $publicUrl = $hub->getPublicUrl();
            
            // Method 2: Try a simple publish
            $update = new \Symfony\Component\Mercure\Update(
                '/debug',
                json_encode(['test' => time()])
            );
            
            $messageId = $hub->publish($update);
            
            return new JsonResponse([
                'status' => 'success',
                'hub_url' => $url,
                'public_url' => $publicUrl,
                'message_id' => $messageId,
                'note' => 'Mercure appears to be working'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'hub_class' => get_class($hub)
            ], 500);
        }
    }
    
    #[Route('/api/test-mercure-simple')]
    public function testSimple(): JsonResponse
    {
        // Direct HTTP test to Mercure
        $url = $_SERVER['MERCURE_URL'] ?? 'http://localhost:3000/.well-known/mercure';
        
        // Test 1: GET request
        $getResult = @file_get_contents($url);
        $getWorks = $getResult !== false;
        
        // Test 2: POST request
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => 'topic=/test&data=' . urlencode(json_encode(['time' => time()])),
                'ignore_errors' => true,
                'timeout' => 3
            ]
        ]);
        
        $postResult = @file_get_contents($url, false, $context);
        $httpCode = $http_response_header[0] ?? 'unknown';
        
        return new JsonResponse([
            'mercure_url' => $url,
            'get_request' => $getWorks ? 'success' : 'failed',
            'post_request' => strpos($httpCode, '200') !== false ? 'success' : 'failed: ' . $httpCode,
            'post_response' => $postResult ? substr($postResult, 0, 100) : 'empty'
        ]);
    }

    #[Route('/test-first-order', name: 'test_first_order', methods: ['GET'])]
    public function testFirstOrder(SettingsService $settingsService): JsonResponse
    {
        // Test settings
        $firstOrderEnabled = $settingsService->get('FIRST_ORDER_ENABLED');
        $discountPercentage = $settingsService->getInt('FIRST_ORDER_DISCOUNT_PERCENTAGE', 0);
        
        return new JsonResponse([
            'FIRST_ORDER_ENABLED' => $firstOrderEnabled,
            'FIRST_ORDER_ENABLED_lower' => strtolower($firstOrderEnabled),
            'FIRST_ORDER_ENABLED_is_true' => strtolower($firstOrderEnabled) === 'true',
            'FIRST_ORDER_DISCOUNT_PERCENTAGE' => $discountPercentage,
            'FIRST_ORDER_DISCOUNT_PERCENTAGE_type' => gettype($discountPercentage),
        ]);
    }

}
