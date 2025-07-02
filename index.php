<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain");

// Get raw POST data
$data = file_get_contents("php://input");

// Submit the order to the supplier API
$url = "https://supplier-api.example.com/submit-order"; // replace with actual URL
$options = [
    'http' => [
        'header'  => "Content-Type: application/xml\r\n",
        'method'  => 'POST',
        'content' => $data,
    ],
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

// Extract the order reference from the response (example for XML)
$orderReference = null;
if ($response) {
    $xml = simplexml_load_string($response);
    if ($xml && isset($xml->reference)) {
        $orderReference = (string)$xml->reference;
    }
}

// ===== STEP 3: LOGGING =====

// Create a log entry
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'order_data' => $data, // what you submitted
    'response' => $response, // what you got back
    'reference' => $orderReference ?? null
];

// Convert to a readable format (you can also use JSON or CSV)
$logLine = json_encode($logEntry) . PHP_EOL;

// Save to a log file (e.g., "order_log.txt")
file_put_contents('order_log.txt', $logLine, FILE_APPEND);

// Output response to the client
echo $response;
