<?php
require_once __DIR__ . '/helpers.php';

$user = auth();
$db   = db();

if (method() === 'GET') {
  $stmt = $db->prepare('SELECT * FROM milestones WHERE user_id = ? ORDER BY saved_at DESC');
  $stmt->execute([$user['id']]);
  $rows = $stmt->fetchAll();

  $items = array_map(fn($r) => [
    'id'      => $r['id'],
    'label'   => $r['label'],
    'emoji'   => $r['emoji'],
    'photo'   => $r['photo'],
    'savedAt' => (int)$r['saved_at'],
  ], $rows);

  respond($items);
}

if (method() === 'POST') {
  $b  = body();
  $id = $b['id'] ?? bin2hex(random_bytes(8));

  $stmt = $db->prepare(
    'INSERT INTO milestones (id, user_id, label, emoji, photo, saved_at) VALUES (?,?,?,?,?,?)'
  );
  $stmt->execute([
    $id,
    $user['id'],
    $b['label']   ?? null,
    $b['emoji']   ?? null,
    $b['photo']   ?? null,
    $b['savedAt'] ?? (int)(microtime(true) * 1000),
  ]);

  respond(['id' => $id]);
}

if (method() === 'DELETE') {
  $id = $_GET['id'] ?? null;
  if (!$id) error('ID obrigatório.');

  $stmt = $db->prepare('DELETE FROM milestones WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $user['id']]);

  respond(['ok' => true]);
}

error('Método inválido.', 405);
