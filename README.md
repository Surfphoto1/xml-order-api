# XML Order API

This is a simple PHP web service that takes JSON order data from Shopify and sends it to a supplier's API in XML format.

## Deploying on Render

1. Push this repo to GitHub
2. Go to https://render.com
3. Click "New Web Service"
4. Connect this GitHub repo
5. Use PHP environment
6. Set the Start Command to:
7. Once deployed, POST JSON data to your public URL

## Sample JSON Payload

```json
{
"reference": "TEST123",
"shipby": "Standard",
"date": "2025-06-20",
"sku": "ABC123",
"qty": 1,
"last": "Smith",
"first": "John",
"address1": "123 Anywhere St.",
"address2": "Apt 4B",
"city": "Smithtown",
"state": "NY",
"zip": "12345",
"country": "US",
"phone": "(123) 555-1212",
"emailaddress": "jsmith@email.com",
"instructions": "Leave at the door"
}

