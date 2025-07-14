<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// MTN API configuration
$apiUrl = "https://api.mtn.com/v1/mobilemoney/payments"; // Check MTN's docs for the correct URL
$apiKey = "YOUR_MTN_API_KEY"; // Replace with your actual API key

$payload = [
    'amount' => $data['amount'],
    'currency' => 'UGX',
    'externalId' => uniqid(),
    'payer' => [
        'partyIdType' => 'MSISDN',
        'partyId' => $data['phone']
    ],
    'payerMessage' => 'Donation to Tattu Charity',
    'payeeNote' => 'Thank you!'
];

// Send request to MTN
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// Return result to frontend
echo $response;
?>