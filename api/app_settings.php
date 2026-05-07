<?php
require_once __DIR__ . '/helpers.php';

// Public endpoint — returns only non-sensitive operational settings.
// WA credentials (url, apiKey, instance) are never exposed here.
if (method() !== 'GET') error('Método inválido.', 405);

$rows = db()->query("SELECT key_name, value FROM app_settings")->fetchAll();
$raw  = [];
foreach ($rows as $r) {
  $raw[$r['key_name']] = json_decode($r['value'], true);
}

$waFull  = $raw['wa_config'] ?? [];
$wa_safe = [
  'enabled'         => (bool)($waFull['enabled']         ?? false),
  'welcome_enabled' => (bool)($waFull['welcome_enabled'] ?? true),
  'status'          => $waFull['status'] ?? 'unknown',
];

respond([
  'wa_config' => $wa_safe,
  'templates' => $raw['templates'] ?? (object)[],
]);
