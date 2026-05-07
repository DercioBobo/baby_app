<?php
require_once __DIR__ . '/helpers.php';

$user = auth();
$db   = db();

// ── GET all logs ──────────────────────────────────────────────────────────────
if (method() === 'GET') {
  $stmt = $db->prepare('SELECT * FROM logs WHERE user_id = ? ORDER BY time_ts DESC');
  $stmt->execute([$user['id']]);
  $rows = $stmt->fetchAll();

  $logs = array_map(function ($row) {
    $extra = json_decode($row['data'], true) ?? [];
    return array_merge(
      ['id' => $row['id'], 'type' => $row['type'], 'time' => (int)$row['time_ts'], 'date' => $row['date_str']],
      $extra
    );
  }, $rows);

  respond($logs);
}

// ── POST create log ───────────────────────────────────────────────────────────
if (method() === 'POST') {
  $b    = body();
  $id   = $b['id']   ?? bin2hex(random_bytes(8));
  $type = $b['type'] ?? '';
  $time = $b['time'] ?? (int)(microtime(true) * 1000);
  $date = $b['date'] ?? date('D M d Y');

  // Everything except the envelope fields goes into the JSON data blob
  $extra = $b;
  unset($extra['id'], $extra['type'], $extra['time'], $extra['date']);

  $stmt = $db->prepare(
    'INSERT INTO logs (id, user_id, type, time_ts, date_str, data) VALUES (?,?,?,?,?,?)'
  );
  $stmt->execute([$id, $user['id'], $type, $time, $date, json_encode($extra, JSON_UNESCAPED_UNICODE)]);

  respond(['id' => $id]);
}

// ── PUT update log ────────────────────────────────────────────────────────────
if (method() === 'PUT') {
  $id = $_GET['id'] ?? null;
  if (!$id) error('ID obrigatório.');

  $stmt = $db->prepare('SELECT * FROM logs WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $user['id']]);
  $row = $stmt->fetch();
  if (!$row) error('Registo não encontrado.', 404);

  $b       = body();
  $time    = $b['time'] ?? (int)$row['time_ts'];
  $date    = $b['date'] ?? $row['date_str'];
  $existing = json_decode($row['data'], true) ?? [];

  $extra = $b;
  unset($extra['id'], $extra['type'], $extra['time'], $extra['date']);
  $data = array_merge($existing, $extra);

  $stmt = $db->prepare('UPDATE logs SET time_ts=?, date_str=?, data=? WHERE id=? AND user_id=?');
  $stmt->execute([$time, $date, json_encode($data, JSON_UNESCAPED_UNICODE), $id, $user['id']]);

  respond(['ok' => true]);
}

// ── DELETE log ────────────────────────────────────────────────────────────────
if (method() === 'DELETE') {
  $id = $_GET['id'] ?? null;
  if (!$id) error('ID obrigatório.');

  $stmt = $db->prepare('DELETE FROM logs WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $user['id']]);

  respond(['ok' => true]);
}

error('Método inválido.', 405);
