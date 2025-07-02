<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain");

// Parse incoming Shopify webhook
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['shipping_address']) || empty($data['line_items'])) {
    http_response_code(400);
    error_log("❌ Invalid Shopify webhook payload.");
    echo "Invalid Shopify webhook payload.";
    exit;
}

error_log("✅ Parsed Shopify order data:\n" . print_r($data, true));

// Map Shopify fields to supplier format
$shipping = $data['shipping_address'];
$item = $data['line_items'][0]; // Only sending first item

$reference = "ORDER" . $data['id'];
$shipby = "U004"; // Update this with your shipping code mapping
$date = date("m/d/y", strtotime($data['created_at'] ?? "now"));
$sku = $item['sku'];
$qty = $item['quantity'];
$last = $shipping['last_name'];
$first = $shipping['first_name'];
$address1 = $shipping['address1'];
$address2 = $shipping['address2'] ?? '';
$city = $shipping['city'];
$state = $shipping['province'];
$zip = $shipping['zip'];
$country = $shipping['country_code'];
$phone = $shipping['phone'] ?? '000-000-0000';
$emailaddress = $data['email'] ?? 'noemail@example.com';
$instructions = $data['note'] ?? '';

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
<reference>{$reference}</reference>
<shipby>{$shipby}</shipby>
<date>{$date}</date>
<items>
  <item>
    <sku>{$sku}</sku>
    <qty>{$qty}</qty>
  </item>
</items>
<last>{$last}</last>
<first>{$first}</first>
<address1>{$address1}</address1>
<address2>{$address2}</address2>
<city>{$city}</city>
<state>{$state}</state>
<zip>{$zip}</zip>
<country>{$country}</country>
<phone>{$phone}</phone>
<emailaddress>{$emailaddress}</emailaddress>
<instructions>{$instructions}</instructions>
</order>
</HPEnvelope>
XML;

// Log the final XML
error_log("📦 Sending XML to supplier:\n$xml_data");

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

// Handle cURL errors
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    http_response_code(502);
    error_log("❌ cURL error: $error_msg");
    echo "Curl error: $error_msg";
    curl_close($ch);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log final response
error_log("✅ Supplier response (HTTP $http_code):\n$response");

// Echo response to caller (Shopify will ignore it, but it helps for manual tests)
echo "Supplier response (HTTP $http_code):\n$response";
?>
