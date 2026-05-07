<?php
require_once __DIR__ . '/helpers.php';

$user = auth();
$db   = db();

// ── GET: return this user's settings ─────────────────────────────────────────
if (method() === 'GET') {
  $stmt = $db->prepare('SELECT settings FROM users WHERE id = ?');
  $stmt->execute([$user['id']]);
  $row  = $stmt->fetch();
  $data = ($row && $row['settings']) ? json_decode($row['settings'], true) : [];
  respond($data ?: (object)[]);
}

// ── POST: merge and save settings ────────────────────────────────────────────
if (method() === 'POST') {
  $b = body();

  $stmt = $db->prepare('SELECT settings FROM users WHERE id = ?');
  $stmt->execute([$user['id']]);
  $row     = $stmt->fetch();
  $current = ($row && $row['settings']) ? json_decode($row['settings'], true) : [];

  $merged = array_merge($current ?: [], $b);

  $stmt = $db->prepare('UPDATE users SET settings = ? WHERE id = ?');
  $stmt->execute([json_encode($merged, JSON_UNESCAPED_UNICODE), $user['id']]);

  respond(['ok' => true]);
}

error('Método inválido.', 405);
