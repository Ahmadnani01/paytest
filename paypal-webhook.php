<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'vendor/autoload.php';

$paypalClientId = "AdMphLDeo2qDTJ2JcFhlAhW32al2RCmKPB97TVzohEaw8GMcnW5TtF7ssNrDYI5mReC7rUQ_yDr4oIdM";
$paypalSecret = "EMakWUAaZpGai_q9BfF0zqn9Cb8YV-IoUXZTJDrRERnUVpBD28vH91aCsdN-CQGYx5Usy3JQvVQ6sCy5";

// Get POST data and PayPal headers
$requestBody = file_get_contents('php://input');
$headers = getallheaders();

// Verify PayPal webhook signature
$verified = verifyWebhookSignature($requestBody, $headers, $paypalClientId, $paypalSecret);

if ($verified) {
    $data = json_decode($requestBody, true);
    
    if ($data['event_type'] === 'PAYMENT.CAPTURE.COMPLETED') {
        $customId = $data['resource']['custom_id'];
        $orderData = json_decode($customId, true);
        
        // Here you would update Firebase - this requires Firebase Admin SDK
        // For now, return success response that client can check
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'userId' => $orderData['userId'],
            'credits' => $orderData['credits']
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook signature']);
}

function verifyWebhookSignature($requestBody, $headers, $clientId, $secret) {
    // Implement PayPal signature verification here
    return true; // For testing - implement actual verification in production
}
?>
