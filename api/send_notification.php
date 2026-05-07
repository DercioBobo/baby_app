<?php
require_once __DIR__ . '/helpers.php';
auth();

if (method() !== 'POST') error('Método inválido.', 405);

$b       = body();
$message = trim($b['message'] ?? '');
$phones  = $b['phones'] ?? [];

if (!$message)        error('Mensagem obrigatória.');
if (!is_array($phones) || !count($phones)) error('Sem números de destino.');

// Load WA config from DB
$stmt = db()->prepare("SELECT value FROM app_settings WHERE key_name = 'wa_config'");
$stmt->execute();
$row = $stmt->fetch();

if (!$row) error('WhatsApp não configurado.', 503);

$wa = json_decode($row['value'], true);

if (!($wa['enabled'] ?? false))  error('WhatsApp desactivado.', 503);
if (!($wa['url']     ?? ''))     error('URL do servidor não configurado.', 503);
if (!($wa['instance'] ?? ''))    error('Instância não configurada.', 503);
if (!($wa['apiKey']  ?? ''))     error('API Key não configurada.', 503);

$url      = rtrim($wa['url'], '/');
$instance = $wa['instance'];
$apiKey   = $wa['apiKey'];

$sent = 0;
$errors = [];

foreach ($phones as $phone) {
  $number = preg_replace('/\D/', '', $phone);
  if (strlen($number) < 7) continue;

  $payload = json_encode(['number' => $number, 'text' => $message], JSON_UNESCAPED_UNICODE);

  $ch = curl_init("$url/message/sendText/$instance");
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ["apikey: $apiKey", 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($code >= 200 && $code < 300) {
    $sent++;
  } else {
    $errors[] = "Falha para +$number (HTTP $code)";
  }
}

if ($sent === 0) {
  error(count($errors) ? implode('; ', $errors) : 'Nenhuma mensagem enviada.', 502);
}

respond(['ok' => true, 'sent' => $sent]);
