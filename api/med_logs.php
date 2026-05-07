<?php
require_once __DIR__ . '/helpers.php';

$user = auth();
$db   = db();

if (method() === 'GET') {
  $stmt = $db->prepare('SELECT * FROM med_logs WHERE user_id = ? ORDER BY time_ts DESC');
  $stmt->execute([$user['id']]);
  $rows = $stmt->fetchAll();

  $logs = array_map(fn($r) => [
    'id'      => $r['id'],
    'medId'   => $r['med_id'],
    'medName' => $r['med_name'],
    'dose'    => $r['dose'],
    'time'    => (int)$r['time_ts'],
    'date'    => $r['date_str'],
  ], $rows);

  respond($logs);
}

if (method() === 'POST') {
  $b  = body();
  $id = $b['id'] ?? bin2hex(random_bytes(8));

  $stmt = $db->prepare(
    'INSERT INTO med_logs (id, user_id, med_id, med_name, dose, time_ts, date_str) VALUES (?,?,?,?,?,?,?)'
  );
  $stmt->execute([
    $id,
    $user['id'],
    $b['medId']   ?? '',
    $b['medName'] ?? null,
    $b['dose']    ?? null,
    $b['time']    ?? (int)(microtime(true) * 1000),
    $b['date']    ?? date('D M d Y'),
  ]);

  respond(['id' => $id]);
}

error('Método inválido.', 405);
