<?php
require_once dirname(__DIR__) . '/helpers.php';
require_admin();

$db = db();

$totalUsers      = (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn();
$totalBabies     = (int) $db->query('SELECT COUNT(*) FROM babies')->fetchColumn();
$activeSessions  = (int) $db->query('SELECT COUNT(DISTINCT user_id) FROM tokens WHERE expires_at > NOW()')->fetchColumn();
$newThisWeek     = (int) $db->query('SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND role = "user"')->fetchColumn();

respond([
  'totalUsers'      => $totalUsers,
  'totalBabies'     => $totalBabies,
  'activeSessions'  => $activeSessions,
  'newThisWeek'     => $newThisWeek,
]);
