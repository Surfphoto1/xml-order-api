<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/plain");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo "Invalid input";
    exit;
}

$xml_data = <<<XML
<?xml version="1.0" encoding="iso-8859-1"?>
<HPEnvelope>
<account>xxxmarketplace@gmail.com</account>
<password>Surfphoto1</password>
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

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.honeysplace.com/ws/');
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

echo "Supplier response (HTTP $http_code):\n$response";
