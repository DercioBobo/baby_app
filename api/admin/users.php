<?php
require_once dirname(__DIR__) . '/helpers.php';
$me = require_admin();

$db = db();

// ── List all users ─────────────────────────────────────────────────────────────
if (method() === 'GET') {
  $stmt = $db->query(
    'SELECT u.id, u.phone, u.role, u.created_at,
            b.name AS baby_name, b.birth_date, b.mom_name,
            COUNT(l.id)    AS log_count,
            MAX(l.time_ts) AS last_activity
     FROM users u
     LEFT JOIN babies b ON b.user_id = u.id
     LEFT JOIN logs   l ON l.user_id = u.id
     GROUP BY u.id, u.phone, u.role, u.created_at, b.name, b.birth_date, b.mom_name
     ORDER BY u.created_at DESC'
  );
  respond($stmt->fetchAll());
}

// ── Reset password ─────────────────────────────────────────────────────────────
if (method() === 'POST' && ($_GET['action'] ?? '') === 'reset_password') {
  $b    = body();
  $id   = (int)($b['id']       ?? 0);
  $pass = $b['password'] ?? '';

  if (!$id)             error('ID inválido.');
  if (strlen($pass) < 6) error('Senha deve ter pelo menos 6 caracteres.');

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
  $db->prepare('DELETE FROM tokens WHERE user_id = ?')->execute([$id]);

  respond(['ok' => true]);
}

// ── Delete user ────────────────────────────────────────────────────────────────
if (method() === 'DELETE') {
  $id = (int)($_GET['id'] ?? 0);
  if (!$id) error('ID inválido.');
  if ($id === (int)$me['id']) error('Não pode eliminar a sua própria conta.');

  $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
  respond(['ok' => true]);
}

error('Método inválido.', 405);
