<?php
require_once __DIR__ . '/helpers.php';
auth();

if (method() !== 'POST') error('Método inválido.', 405);

$b     = body();
$phone = preg_replace('/\D/', '', $b['phone'] ?? '');
$text  = trim($b['text']  ?? '');
$photo = $b['photo'] ?? null; // base64 data URL or null

if (!$phone) error('Número de destino em falta.');
if (!$text)  error('Texto da mensagem em falta.');

// Load admin WA config
$stmt = db()->prepare("SELECT value FROM app_settings WHERE key_name = 'wa_config'");
$stmt->execute();
$row = $stmt->fetch();
if (!$row)                       error('WhatsApp não configurado.', 503);

$wa = json_decode($row['value'], true);
if (!($wa['enabled']  ?? false)) error('WhatsApp desactivado.', 503);
if (!($wa['url']      ?? ''))    error('URL do servidor não configurado.', 503);
if (!($wa['instance'] ?? ''))    error('Instância não configurada.', 503);
if (!($wa['apiKey']   ?? ''))    error('API Key não configurada.', 503);

$url      = rtrim($wa['url'], '/');
$instance = $wa['instance'];
$apiKey   = $wa['apiKey'];

// Build payload — image or plain text
if ($photo && str_starts_with($photo, 'data:')) {
  $parts = explode(',', $photo, 2);
  $b64   = $parts[1] ?? '';
  preg_match('/data:([^;]+);base64/', $photo, $mm);
  $mime = $mm[1] ?? 'image/jpeg';

  $payload  = json_encode([
    'number'    => $phone,
    'mediatype' => 'image',
    'mimetype'  => $mime,
    'caption'   => $text,
    'media'     => $b64,
  ], JSON_UNESCAPED_UNICODE);
  $endpoint = "$url/message/sendMedia/$instance";
} else {
  $payload  = json_encode(['number' => $phone, 'text' => $text], JSON_UNESCAPED_UNICODE);
  $endpoint = "$url/message/sendText/$instance";
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ["apikey: $apiKey", 'Content-Type: application/json'],
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_SSL_VERIFYPEER => false,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
  respond(['ok' => true]);
} else {
  error("Falha ao enviar (HTTP $code)", 502);
}
