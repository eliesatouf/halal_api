<?php
namespace App\tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

class PasswordResetTest extends WebTestCase
{
    private $client;
    
    protected function setUp(): void
    {
        $this->client = HttpClient::create();
    }
    
    public function testCompletePasswordResetFlow()
    {
        $email = 'elie.satouf@gmail.com';
        
        echo "\n=== Testing Complete Password Reset Flow ===\n\n";
        
        // 1. Request password reset
        echo "1. Requesting password reset...\n";
        $response = $this->client->request('POST', 'http://localhost:7071/api/password-reset-request', [
            'json' => ['email' => $email],
            'headers' => ['Content-Type' => 'application/json']
        ]);
        
        $status = $response->getStatusCode();
        $content = json_decode($response->getContent(), true);
        
        echo "   Status: $status\n";
        echo "   Response: " . json_encode($content, JSON_PRETTY_PRINT) . "\n\n";
        
        $this->assertEquals(200, $status);
        $this->assertTrue($content['success']);
        
        // 2. Check database for token
        echo "2. Checking database for token...\n";
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        
        $resetRequest = $em->getRepository(\App\Entity\PasswordResetRequest::class)
            ->findOneBy(['email' => $email], ['createdAt' => 'DESC']);
        
        if (!$resetRequest) {
            echo "   ✗ No reset request found in database\n";
            return;
        }
        
        $token = $resetRequest->getToken();
        echo "   ✓ Token found: " . substr($token, 0, 20) . "...\n";
        echo "   Expires: " . $resetRequest->getExpiresAt()->format('Y-m-d H:i:s') . "\n\n";
        
        // 3. Test reset with token
        echo "3. Testing password reset with token...\n";
        $newPassword = 'TestPassword123!';
        
        $response = $this->client->request('POST', 'http://localhost:7071/api/password-reset', [
            'json' => [
                'token' => $token,
                'password' => $newPassword,
                'confirmPassword' => $newPassword
            ],
            'headers' => ['Content-Type' => 'application/json']
        ]);
        
        $status = $response->getStatusCode();
        $content = json_decode($response->getContent(), true);
        
        echo "   Status: $status\n";
        echo "   Response: " . json_encode($content, JSON_PRETTY_PRINT) . "\n\n";
        
        if ($status === 200) {
            echo "   ✓ Password reset successful!\n";
        } else {
            echo "   ✗ Password reset failed\n";
        }
        
        // 4. Verify token is marked as used
        $em->refresh($resetRequest);
        echo "4. Token usage status: " . ($resetRequest->isIsUsed() ? 'USED' : 'NOT USED') . "\n";
        
        echo "\n=== Test Complete ===\n";
    }
}