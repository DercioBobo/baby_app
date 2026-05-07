<?php
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? '';

// ── Register ──────────────────────────────────────────────────────────────────
if (method() === 'POST' && $action === 'register') {
  $b     = body();
  $phone = preg_replace('/\D/', '', $b['phone'] ?? '');
  $pass  = $b['password'] ?? '';

  if (!$phone)          error('Número de telefone obrigatório.');
  if (strlen($phone) < 7) error('Número de telefone inválido.');
  if (!$pass)           error('Senha obrigatória.');
  if (strlen($pass) < 6) error('A senha deve ter pelo menos 6 caracteres.');

  $db   = db();
  $stmt = $db->prepare('SELECT id FROM users WHERE phone = ?');
  $stmt->execute([$phone]);
  if ($stmt->fetch()) error('Este número já está registado.');

  $role = ($phone === preg_replace('/\D/', '', ADMIN_PHONE)) ? 'admin' : 'user';
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $stmt = $db->prepare('INSERT INTO users (phone, password_hash, role) VALUES (?, ?, ?)');
  $stmt->execute([$phone, $hash, $role]);
  $userId = (int)$db->lastInsertId();

  $token   = bin2hex(random_bytes(32));
  $expires = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME);
  $stmt    = $db->prepare('INSERT INTO tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
  $stmt->execute([$userId, $token, $expires]);

  respond(['token' => $token, 'role' => $role, 'phone' => $phone]);
}

// ── Login ─────────────────────────────────────────────────────────────────────
if (method() === 'POST' && $action === 'login') {
  $b     = body();
  $phone = preg_replace('/\D/', '', $b['phone'] ?? '');
  $pass  = $b['password'] ?? '';

  if (!$phone) error('Número de telefone obrigatório.');
  if (!$pass)  error('Senha obrigatória.');

  $stmt = db()->prepare('SELECT * FROM users WHERE phone = ?');
  $stmt->execute([$phone]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($pass, $user['password_hash'])) {
    error('Número ou senha incorretos.', 401);
  }

  $db      = db();
  $token   = bin2hex(random_bytes(32));
  $expires = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME);
  $stmt    = $db->prepare('INSERT INTO tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
  $stmt->execute([$user['id'], $token, $expires]);

  respond(['token' => $token, 'role' => $user['role'], 'phone' => $user['phone']]);
}

// ── Logout ────────────────────────────────────────────────────────────────────
if (method() === 'POST' && $action === 'logout') {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
    $stmt = db()->prepare('DELETE FROM tokens WHERE token = ?');
    $stmt->execute([$m[1]]);
  }
  respond(['ok' => true]);
}

error('Acção desconhecida.');
