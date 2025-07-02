<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain");

// Parse incoming JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo "Invalid input: JSON payload is missing or malformed.";
    exit;
}

// Required fields for the order
$required_fields = [
    'reference', 'shipby', 'date', 'sku', 'qty',
    'last', 'first', 'address1', 'city', 'state',
    'zip', 'country', 'phone', 'emailaddress'
];

// Validate required fields
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo "Missing or empty required field: $field";
        exit;
    }
}

// Validate email format
if (!filter_var($data['emailaddress'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Invalid email address format.";
    exit;
}

// Load credentials from environment
$account = getenv("HP_ACCOUNT") ?: "MISSING_ACCOUNT";
$password = getenv("HP_PASSWORD") ?: "MISSING_PASSWORD";

// Build XML
$xml_data = <<<XML
<?xml version="1.0" encoding="iso-8859-1"?>
<HPEnvelope>
<account>{$account}</account>
<password>{$password}</password>
<order>
<reference>{$data['reference']}</reference>
<shipby>{$data['shipby']}</shipby>
<date>{$data['date']}</date>
<items>
  <item>
    <sku>{$data['sku']}</sku>
    <qty>{$data['qty']}</qty>
  </item>
</items>
<last>{$data['last']}</last>
<first>{$data['first']}</first>
<address1>{$data['address1']}</address1>
<address2>{$data['address2']}</address2>
<city>{$data['city']}</city>
<state>{$data['state']}</state>
<zip>{$data['zip']}</zip>
<country>{$data['country']}</country>
<phone>{$data['phone']}</phone>
<emailaddress>{$data['emailaddress']}</emailaddress>
<instructions>{$data['instructions']}</instructions>
</order>
</HPEnvelope>
XML;

// Send XML to Honey's Place
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.honeysplace.com/ws/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/xml",
    "Content-Length: " . strlen($xml_data)
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);

// Handle curl errors
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    http_response_code(502);
    echo "Curl error: $error_msg";
    curl_close($ch);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Final response
echo "Supplier response (HTTP $http_code):\n$response";
?>
