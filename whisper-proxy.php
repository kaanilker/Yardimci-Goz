<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);

define('GROQ_WHISPER_URL', 'https://api.groq.com/openai/v1/audio/transcriptions');
define('MAX_FILE_SIZE', 25 * 1024 * 1024);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Yalnızca POST destekleniyor.']);
    exit;
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Ses dosyası alınamadı.']);
    exit;
}

$file = $_FILES['audio'];

if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(413);
    echo json_encode(['error' => 'Dosya 25 MB sınırını aşıyor.']);
    exit;
}

// Groq'a Gönderme
$curl = curl_init();

$cfile = new CURLFile(
    $file['tmp_name'],
    $file['type'] ?: 'audio/webm',
    $file['name'] ?: 'recording.webm'
);

curl_setopt_array($curl, [
    CURLOPT_URL            => GROQ_WHISPER_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_POSTFIELDS => [
        'file'            => $cfile,
        'model'           => 'whisper-large-v3-turbo',
        'language'        => 'tr',
        'response_format' => 'json',
        'temperature'     => '0',
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

@unlink($file['tmp_name']);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Sunucu bağlantı hatası: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

$data = json_decode($response, true);

if (!isset($data['text'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Whisper yanıtı beklenmedik formatta.']);
    exit;
}

echo json_encode(['text' => $data['text']]);
