<?php
require_once __DIR__ . '/helpers.php';

$user = auth();
$db   = db();

if (method() === 'GET') {
  $stmt = $db->prepare('SELECT * FROM meds WHERE user_id = ? ORDER BY created_at ASC');
  $stmt->execute([$user['id']]);
  $rows = $stmt->fetchAll();

  $meds = array_map(fn($r) => [
    'id'        => $r['id'],
    'name'      => $r['name'],
    'dose'      => $r['dose'],
    'intervalH' => (float)$r['interval_h'],
  ], $rows);

  respond($meds);
}

if (method() === 'POST') {
  $b  = body();
  $id = $b['id'] ?? bin2hex(random_bytes(8));

  $stmt = $db->prepare('INSERT INTO meds (id, user_id, name, dose, interval_h) VALUES (?,?,?,?,?)');
  $stmt->execute([$id, $user['id'], $b['name'] ?? '', $b['dose'] ?? null, $b['intervalH'] ?? 8]);

  respond(['id' => $id]);
}

if (method() === 'DELETE') {
  $id = $_GET['id'] ?? null;
  if (!$id) error('ID obrigatório.');

  $stmt = $db->prepare('DELETE FROM meds WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $user['id']]);

  respond(['ok' => true]);
}

error('Método inválido.', 405);
