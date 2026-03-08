<?php

require_once 'config.php';

// CORS başlıkları
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: '  . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight isteğini geçme
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Sadece POST kabul etme
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Sadece POST isteği kabul edilir.']);
    exit;
}

// Gelen JSON'u okuma
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'messages alanı eksik.']);
    exit;
}

// Gönderilecek Payload
$payload = json_encode([
    'model'       => GROQ_MODEL,
    'messages'    => $data['messages'],
    'temperature' => isset($data['temperature']) ? (float)$data['temperature'] : 0.35,
    'max_tokens'  => isset($data['max_tokens'])  ? (int)$data['max_tokens']    : 1024,
]);

// cURL API Çağrısı
$ch = curl_init(GROQ_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Hata Kontrolü
if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Groq bağlantı hatası: ' . $curlError]);
    exit;
}

// Cevabı İletme
http_response_code($httpCode);
echo $response;
