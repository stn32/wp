<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$client_id     = 'xxx';
$client_secret = 'xxx';

$api_base = 'https://api.cdek.ru/v2';
$action   = $_GET['action'] ?? '';

function getAuthToken() {
    global $client_id, $client_secret, $api_base;

    $ch = curl_init($api_base . '/oauth/token');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }

    curl_close($ch);

    $data = json_decode($response, true);

    return $data['access_token'] ?? null;
}

if ($action === 'offices') {

    $token = getAuthToken();

    if (!$token) {
        echo json_encode(['error' => 'Auth failed']);
        exit;
    }

    $params = [
        'is_handout'   => 'true',
        'is_reception' => 'true'
    ];

    if (!empty($_GET['city_code'])) {
        $params['city_code'] = (int) $_GET['city_code'];
    }

    $url = $api_base . '/deliverypoints?' . http_build_query($params);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['error' => curl_error($ch)]);
    } else {
        echo $response;
    }

    curl_close($ch);
    exit;
}

if ($action === 'calculate') {
    echo json_encode(['tariff_list' => []]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
