<?php

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Sadece POST kabul edilir.']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (empty($data['text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'text alanı boş.']);
    exit;
}

$text  = mb_substr(trim($data['text']), 0, 3000, 'UTF-8');
$voice = isset($data['voice']) ? $data['voice'] : 'tr-TR-EmelNeural';

try {
    $audio = edgeTtsGenerate($text, $voice);
    if (strlen($audio) < 100) throw new Exception('Ses verisi çok kısa, TTS başarısız.');
    echo json_encode(['audio' => base64_encode($audio)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Edge TTS hatası: ' . $e->getMessage()]);
}

// Text to Speech 
function edgeTtsGenerate(string $text, string $voice): string
{
    $token = '6A5AA1D4EAFF4E9FB37E23D68491D6F4';
    $host  = 'speech.platform.bing.com';
    $path  = '/consumer/speech/synthesize/readaloud/edge/v1'
           . '?TrustedClientToken=' . $token
           . '&Retry-After=200&ConnectionId=' . generateUuid();

    // SSL
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ]);

    $sock = @stream_socket_client(
        "ssl://{$host}:443",
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx
    );

    if (!$sock) {
        throw new Exception("Bağlantı kurulamadı: {$errstr} (kod: {$errno})");
    }

    stream_set_timeout($sock, 20);

    // WebSocket EL Sıkışması
    $wsKey = base64_encode(random_bytes(16));

    $handshake = "GET {$path} HTTP/1.1\r\n"
        . "Host: {$host}\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Key: {$wsKey}\r\n"
        . "Sec-WebSocket-Version: 13\r\n"
        . "Origin: chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold\r\n"
        . "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        .   "AppleWebKit/537.36 (KHTML, like Gecko) "
        .   "Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0\r\n"
        . "\r\n";

    fwrite($sock, $handshake);

    // HTTP yanıtını oku
    $httpResp = '';
    while (!str_contains($httpResp, "\r\n\r\n")) {
        $chunk = @fread($sock, 1024);
        if ($chunk === false || $chunk === '') break;
        $httpResp .= $chunk;
    }

    if (!str_contains($httpResp, '101')) {
        fclose($sock);
        throw new Exception('WebSocket yükseltme başarısız. Yanıt: ' . substr($httpResp, 0, 120));
    }

    // Speech Config Mesajı
    $reqId = generateUuid(true); 
    $ts    = gmdate("D, d M Y H:i:s") . " GMT";

    $cfgJson = json_encode([
        'context' => [
            'synthesis' => [
                'audio' => [
                    'metadataoptions' => [
                        'sentenceBoundaryEnabled' => 'false',
                        'wordBoundaryEnabled'     => 'false',
                    ],
                    'outputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $cfgMsg = "Path: speech.config\r\n"
            . "X-RequestId: {$reqId}\r\n"
            . "X-Timestamp: {$ts}\r\n"
            . "Content-Type: application/json; charset=utf-8\r\n"
            . "\r\n"
            . $cfgJson;

    wsSendText($sock, $cfgMsg);

    // SSML Mesajı
    $safeText  = htmlspecialchars($text,  ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $safeVoice = htmlspecialchars($voice, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $ssml = "<?xml version='1.0'?>"
          . "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='tr-TR'>"
          . "<voice name='{$safeVoice}'>"
          . "<prosody pitch='+0Hz' rate='+0%' volume='+0%'>{$safeText}</prosody>"
          . "</voice></speak>";

    $ssmlMsg = "Path: ssml\r\n"
             . "X-RequestId: {$reqId}\r\n"
             . "X-Timestamp: {$ts}\r\n"
             . "Content-Type: application/ssml+xml\r\n"
             . "\r\n"
             . $ssml;

    wsSendText($sock, $ssmlMsg);

    // Ses verisini toplama
    $audioBuffer = '';
    $deadline    = time() + 25;

    while (time() < $deadline) {
        $frame = wsReadFrame($sock);
        if ($frame === null) break;

        $opcode  = $frame['opcode'];
        $payload = $frame['data'];

        // Bağlantı kapatma çerçevesi
        if ($opcode === 0x08) break;

        // Ping Pong
        if ($opcode === 0x09) {
            wsSendRaw($sock, 0x8A, $payload);
            continue;
        }

        if ($opcode === 0x02) {
            if (strlen($payload) < 2) continue;
            $headerLen = unpack('n', substr($payload, 0, 2))[1];
            $header    = substr($payload, 2, $headerLen);
            $audio     = substr($payload, 2 + $headerLen);
            if (strpos($header, 'Path:audio') !== false && strlen($audio) > 0) {
                $audioBuffer .= $audio;
            }
        } elseif ($opcode === 0x01) {
            // Metin çerçevesi 
            if (strpos($payload, 'Path:turn.end') !== false) break;
        }
    }

    fclose($sock);
    return $audioBuffer;
}

// Metin WebSocket çerçevesi gönderme
function wsSendText($sock, string $text): void
{
    wsSendRaw($sock, 0x81, $text);
}

// Verilen opcode ile maskeli WebSocket çerçevesi gönderme 
function wsSendRaw($sock, int $opcode, string $payload): void
{
    $len  = strlen($payload);
    $mask = random_bytes(4);

    $masked = '';
    for ($i = 0; $i < $len; $i++) {
        $masked .= $payload[$i] ^ $mask[$i % 4];
    }

    $frame = chr($opcode);

    if ($len <= 125) {
        $frame .= chr($len | 0x80);
    } elseif ($len <= 65535) {
        $frame .= chr(0x80 | 126) . pack('n', $len);
    } else {
        $frame .= chr(0x80 | 127) . pack('J', $len);
    }

    $frame .= $mask . $masked;
    fwrite($sock, $frame);
}

// WebSocket çerçevesi okuma
function wsReadFrame($sock): ?array
{
    $h = wsReadExact($sock, 2);
    if ($h === null) return null;

    $b0 = ord($h[0]);
    $b1 = ord($h[1]);

    $opcode   = $b0 & 0x0f;
    $isMasked = ($b1 & 0x80) !== 0;
    $payLen   = $b1 & 0x7f;

    if ($payLen === 126) {
        $ext = wsReadExact($sock, 2);
        if ($ext === null) return null;
        $payLen = unpack('n', $ext)[1];
    } elseif ($payLen === 127) {
        $ext = wsReadExact($sock, 8);
        if ($ext === null) return null;
        $payLen = unpack('J', $ext)[1];
    }

    $maskBytes = '';
    if ($isMasked) {
        $maskBytes = wsReadExact($sock, 4);
        if ($maskBytes === null) return null;
    }

    $data = $payLen > 0 ? wsReadExact($sock, (int)$payLen) : '';
    if ($data === null) return null;

    if ($isMasked && $maskBytes !== '') {
        $out = '';
        for ($i = 0, $l = strlen($data); $i < $l; $i++) {
            $out .= $data[$i] ^ $maskBytes[$i % 4];
        }
        $data = $out;
    }

    return ['opcode' => $opcode, 'data' => $data];
}

function wsReadExact($sock, int $n): ?string
{
    $buf  = '';
    $left = $n;

    while ($left > 0) {
        if (feof($sock)) return null;

        $chunk = @fread($sock, $left);

        if ($chunk === false || $chunk === '') {
            $meta = stream_get_meta_data($sock);
            if ($meta['timed_out'] ?? false) return null;
            // Kısa bekleme
            usleep(1000);
            continue;
        }

        $buf  .= $chunk;
        $left -= strlen($chunk);
    }

    return $buf;
}

function generateUuid(bool $noHyphens = false): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    $uuid = sprintf(
        '%08s-%04s-%04s-%04s-%12s',
        bin2hex(substr($bytes, 0, 4)),
        bin2hex(substr($bytes, 4, 2)),
        bin2hex(substr($bytes, 6, 2)),
        bin2hex(substr($bytes, 8, 2)),
        bin2hex(substr($bytes, 10, 6))
    );

    return $noHyphens ? str_replace('-', '', $uuid) : $uuid;
}
