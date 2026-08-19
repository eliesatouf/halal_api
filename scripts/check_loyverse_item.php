#!/usr/bin/env php
<?php
// Script to check Loyverse item details

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

// Get Loyverse credentials from database
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s',
    $_ENV['DATABASE_HOST'],
    $_ENV['DATABASE_PORT'] ?? '3306',
    $_ENV['DATABASE_NAME']
);

$pdo = new PDO($dsn, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']);

// Get access token
$stmt = $pdo->query("SELECT access_token FROM loyverse_token LIMIT 1");
$token = $stmt->fetchColumn();

if (!$token) {
    die("No Loyverse token found\n");
}

// Query Loyverse API for item
$itemId = '1daa29ee-71ed-4d5c-9478-5e8c1786ce2f';
$url = "https://api.loyverse.com/v1.0/items/{$itemId}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "\n";
