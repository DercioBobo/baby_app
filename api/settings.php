<?php
require_once __DIR__ . '/helpers.php';

$db = db();

// ── GET ───────────────────────────────────────────────────────────────────────
// Public: GET /api/settings.php?key=waEnabled  → single value
// Admin:  GET /api/settings.php                → all settings
if (method() === 'GET') {
  $key = $_GET['key'] ?? null;

  if ($key) {
    // Anyone can read a single setting (e.g. to check if WA is enabled)
    $stmt = $db->prepare('SELECT value FROM app_settings WHERE key_name = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    respond($row ? json_decode($row['value'], true) : null);
  }

  // Reading all settings requires admin
  require_admin();
  $rows     = $db->query('SELECT key_name, value FROM app_settings')->fetchAll();
  $settings = [];
  foreach ($rows as $r) {
    $settings[$r['key_name']] = json_decode($r['value'], true);
  }
  respond($settings);
}

// ── POST ──────────────────────────────────────────────────────────────────────
// Admin only. Body: { key: value, key2: value2, ... }
if (method() === 'POST') {
  require_admin();
  $b = body();

  $stmt = $db->prepare(
    'INSERT INTO app_settings (key_name, value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
  );

  foreach ($b as $key => $value) {
    $stmt->execute([$key, json_encode($value, JSON_UNESCAPED_UNICODE)]);
  }

  respond(['ok' => true]);
}

error('Método inválido.', 405);
