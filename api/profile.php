<?php
require_once __DIR__ . '/helpers.php';

$user = auth();
$db   = db();

if (method() === 'GET') {
  $stmt = $db->prepare('SELECT * FROM babies WHERE user_id = ?');
  $stmt->execute([$user['id']]);
  $row = $stmt->fetch();
  if (!$row) { respond(null); }

  respond([
    'name'       => $row['name'],
    'birth'      => $row['birth_date'],
    'mom'        => $row['mom_name'],
    'photo'      => $row['photo'],
    'themeColor' => $row['theme_color'],
  ]);
}

if (method() === 'POST') {
  $b = body();

  $stmt = $db->prepare('SELECT id FROM babies WHERE user_id = ?');
  $stmt->execute([$user['id']]);
  $exists = $stmt->fetch();

  if ($exists) {
    $stmt = $db->prepare(
      'UPDATE babies SET name=?, birth_date=?, mom_name=?, photo=?, theme_color=? WHERE user_id=?'
    );
    $stmt->execute([
      $b['name']       ?? '',
      $b['birth']      ?? '',
      $b['mom']        ?? null,
      $b['photo']      ?? null,
      $b['themeColor'] ?? null,
      $user['id'],
    ]);
  } else {
    $stmt = $db->prepare(
      'INSERT INTO babies (user_id, name, birth_date, mom_name, photo, theme_color) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
      $user['id'],
      $b['name']       ?? '',
      $b['birth']      ?? '',
      $b['mom']        ?? null,
      $b['photo']      ?? null,
      $b['themeColor'] ?? null,
    ]);
  }

  respond(['ok' => true]);
}

error('Método inválido.', 405);
