<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

require_once 'vendor/autoload.php';
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Kreait\Firebase\Factory;

// Initialize Firebase Admin SDK
$factory = (new Factory)
    ->withServiceAccount('path/to/firebase-credentials.json')
    ->withDatabaseUri('https://quick-test-aac28.firebaseio.com');

$database = $factory->createDatabase();

// PayPal Configuration
$paypalClientId = "AYmP4Sr0rcHo8I15l50jjrb702Kjg-Qf5LsnzBd-uTM_DEDqmToDElVSxnGoZnRvX7GpEU1onKP70xqu";
$paypalSecret = "ELlYhWtUnMGIFtgFndqTVxJs0yd3s7J_tnybDlr18X6Hju_wWtXxyCMh0G7pZsq1vdju-us9DTOluEhM";
$webhookId = "71N90972X54331806";

// Log request method and data
$method = $_SERVER['REQUEST_METHOD'];
error_log('PayPal Webhook Request Method: ' . $method);

if ($method !== 'POST') {
    error_log('Invalid request method: ' . $method);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$requestBody = file_get_contents('php://input');
$headers = getallheaders();

// Log raw request
error_log('Raw PayPal Webhook Request: ' . $requestBody);
error_log('PayPal Headers: ' . json_encode($headers));

// Verify PayPal webhook signature
$verified = verifyWebhookSignature($requestBody, $headers, $paypalClientId, $paypalSecret);

if ($verified) {
    $data = json_decode($requestBody, true);
    
    // Handle different subscription events
    switch ($data['event_type']) {
        case 'BILLING.SUBSCRIPTION.CANCELLED':
            handleSubscriptionCancelled($data);
            break;
        case 'BILLING.SUBSCRIPTION.EXPIRED':
            handleSubscriptionExpired($data);
            break;
        case 'BILLING.SUBSCRIPTION.SUSPENDED':
            handleSubscriptionSuspended($data);
            break;
        // Add more event types as needed
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature']);
}

function verifyWebhookSignature($requestBody, $headers, $clientId, $secret) {
    try {
        $apiContext = new ApiContext(
            new OAuthTokenCredential($clientId, $secret)
        );
        
        // Change to live mode
        $apiContext->setConfig(['mode' => 'live']);

        $signatureVerification = new VerifyWebhookSignature();
        $signatureVerification->setAuthAlgo($headers['PAYPAL-AUTH-ALGO']);
        $signatureVerification->setTransmissionId($headers['PAYPAL-TRANSMISSION-ID']);
        $signatureVerification->setCertUrl($headers['PAYPAL-CERT-URL']);
        $signatureVerification->setWebhookId($GLOBALS['webhookId']); // Using the configured webhook ID
        $signatureVerification->setTransmissionSig($headers['PAYPAL-TRANSMISSION-SIG']);
        $signatureVerification->setTransmissionTime($headers['PAYPAL-TRANSMISSION-TIME']);

        $signatureVerification->setRequestBody($requestBody);
        
        $output = $signatureVerification->post($apiContext);
        
        return $output->getVerificationStatus() === 'SUCCESS';
    } catch (Exception $e) {
        error_log('PayPal Webhook Verification Error: ' . $e->getMessage());
        return false;
    }
}

function handleSubscriptionCancelled($event) {
    global $database;
    $subscriptionId = $event['resource']['id'];
    $customId = $event['resource']['custom_id']; // This should be the Firebase user ID

    // Update Firebase
    try {
        $database->getReference('users/' . $customId)->update([
            'plan' => 'free',
            'subscriptionStatus' => 'CANCELLED',
            'subscriptionCancelDate' => time() * 1000, // Convert to milliseconds for JavaScript
        ]);
    } catch (Exception $e) {
        error_log('Firebase update failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Firebase update failed');
    }
}

function handleSubscriptionExpired($event) {
    // Similar to cancelled handler
    handleSubscriptionCancelled($event);
}

function handleSubscriptionSuspended($event) {
    global $database;
    $customId = $event['resource']['custom_id'];

    try {
        $database->getReference('users/' . $customId)->update([
            'subscriptionStatus' => 'SUSPENDED',
            'suspensionDate' => time() * 1000,
        ]);
    } catch (Exception $e) {
        error_log('Firebase update failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Firebase update failed');
    }
}

// Add error logging
if (!$verified) {
    error_log('PayPal Webhook Verification Failed: ' . json_encode([
        'headers' => $headers,
        'body' => $requestBody
    ]));
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

// Send success response
http_response_code(200);
echo 'Webhook processed successfully';
?>
