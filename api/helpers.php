<?php
require_once __DIR__ . '/config.php';

// ── CORS ──────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Database ──────────────────────────────────────────────────────────────────
function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
          PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
      );
    } catch (\PDOException $e) {
      header('Content-Type: application/json; charset=utf-8');
      http_response_code(500);
      echo json_encode(['error' => 'Erro de ligação à base de dados: ' . $e->getMessage()]);
      exit;
    }
  }
  return $pdo;
}

// ── HTTP helpers ──────────────────────────────────────────────────────────────
function respond(mixed $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function error(string $msg, int $code = 400): void {
  respond(['error' => $msg], $code);
}

function body(): array {
  $raw = file_get_contents('php://input');
  return json_decode($raw, true) ?? [];
}

function method(): string {
  return $_SERVER['REQUEST_METHOD'];
}

// ── Auth ──────────────────────────────────────────────────────────────────────
function auth(): array {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
    error('Não autenticado', 401);
  }
  $stmt = db()->prepare(
    'SELECT u.id, u.phone, u.role FROM users u
     JOIN tokens t ON t.user_id = u.id
     WHERE t.token = ? AND t.expires_at > NOW()'
  );
  $stmt->execute([$m[1]]);
  $user = $stmt->fetch();
  if (!$user) error('Sessão expirada. Por favor inicie sessão novamente.', 401);
  return $user;
}

function require_admin(): array {
  $user = auth();
  if ($user['role'] !== 'admin') error('Acesso restrito a administradores.', 403);
  return $user;
}
