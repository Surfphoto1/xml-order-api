<?php
date_default_timezone_set("UTC");

// === CONFIG ===
define("LOG_FILE", __DIR__ . "/logs/orders.log");
define("ALLOWED_SKU_PREFIX", "CC-"); // adjust for Honey’s Place
define("EMAIL_TO", "your@email.com"); // change to your notification email
define("SHOPIFY_SHARED_SECRET", getenv("SHOPIFY_SECRET") ?: ""); // set this in Render

// === HEADERS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Shopify-Hmac-Sha256");
header("Content-Type: text/plain");

// === VERIFY SHOPIFY HMAC ===
$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$raw_input = file_get_contents("php://input");

$calculated_hmac = base64_encode(hash_hmac('sha256', $raw_input, SHOPIFY_SHARED_SECRET, true));
if (!hash_equals($hmac_header, $calculated_hmac)) {
    http_response_code(401);
    error_log("❌ Invalid HMAC signature. Rejecting webhook.");
    echo "Unauthorized";
    exit;
}

// === PARSE PAYLOAD ===
$data = json_decode($raw_input, true);
if (!$data || !isset($data['shipping_address']) || empty($data['line_items'])) {
    http_response_code(400);
    error_log("❌ Invalid Shopify webhook payload.");
    echo "Invalid webhook payload.";
    exit;
}

$shipping = $data['shipping_address'];
$items_xml = "";
foreach ($data['line_items'] as $item) {
    $sku = $item['sku'];
    $qty = $item['quantity'];

    // Filter: only send items with matching prefix
    if (stripos($sku, ALLOWED_SKU_PREFIX) !== 0) continue;

    $items_xml .= "<item><sku>{$sku}</sku><qty>{$qty}</qty></item>\n";
}

// If no valid items to send, skip submission
if (empty(trim($items_xml))) {
    error_log("⚠️ No eligible items to send. Order skipped.");
    echo "No valid items to submit.";
    exit;
}

// === BUILD XML ===
$account = getenv("HP_ACCOUNT") ?: "MISSING_ACCOUNT";
$password = getenv("HP_PASSWORD") ?: "MISSING_PASSWORD";
$reference = "ORDER" . $data['id'];
$shipby = "U004"; // You can dynamically map this if needed
$date = date("m/d/y", strtotime($data['created_at'] ?? "now"));

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
{$items_xml}</items>
<last>{$shipping['last_name']}</last>
<first>{$shipping['first_name']}</first>
<address1>{$shipping['address1']}</address1>
<address2>{$shipping['address2']}</address2>
<city>{$shipping['city']}</city>
<state>{$shipping['province']}</state>
<zip>{$shipping['zip']}</zip>
<country>{$shipping['country_code']}</country>
<phone>{$shipping['phone']}</phone>
<emailaddress>{$data['email']}</emailaddress>
<instructions>{$data['note']}</instructions>
</order>
</HPEnvelope>
XML;

// === LOG TO FILE ===
file_put_contents(LOG_FILE, date("Y-m-d H:i:s") . "\nXML Sent:\n$xml_data\n\n", FILE_APPEND);

// === SUBMIT TO HONEY'S PLACE ===
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.honeysplace.com/ws/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/xml",
    "Content-Length: " . strlen($xml_data)
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// === LOG RESPONSE ===
file_put_contents(LOG_FILE, "Supplier response (HTTP $http_code):\n$response\n\n", FILE_APPEND);

// === EMAIL CONFIRMATION ===
$subject = "✅ Order Submitted: $reference";
$message = "Response:\n$response\n\nSent XML:\n$xml_data";
$headers = "From: orders@xxxmarketplace.net";
@mail(EMAIL_TO, $subject, $message, $headers);

// === OUTPUT ===
echo "Supplier response (HTTP $http_code):\n$response";
?>
