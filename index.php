<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain");

// Get raw POST XML from client
$data = file_get_contents("php://input");

// Debug: Save the raw XML input
file_put_contents('debug_input.xml', $data);

// Build POST body for Honey's Place (URL-encoded XML)
$postFields = "xmldata=" . urlencode($data);

// Debug: Save the encoded POST fields
file_put_contents('debug_postfields.txt', $postFields);

// Send to Honey's Place
$url = "https://www.honeysplace.com/ws/";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for dev/test. Set to true in production!
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; HoneyClient/1.0)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Try to extract order reference from response (optional)
$orderReference = null;
if ($response && $httpCode == 200) {
    $xml = simplexml_load_string($response);
    if ($xml && isset($xml->reference)) {
        $orderReference = (string)$xml->reference;
    }
}

// ===== STEP 3: LOGGING =====
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'order_data' => $data,
    'response' => $response ?: $curlError,
    'http_code' => $httpCode,
    'reference' => $orderReference ?? null
];

$logLine = json_encode($logEntry) . PHP_EOL;
file_put_contents('order_log.txt', $logLine, FILE_APPEND);

// Return response to caller
echo $response ?: "Error submitting order: $curlError";
