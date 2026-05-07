<?php
require_once dirname(__DIR__) . '/helpers.php';
require_admin();

$db = db();

$totalUsers     = (int) $db->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn();
$totalBabies    = (int) $db->query('SELECT COUNT(*) FROM babies')->fetchColumn();
$activeSessions = (int) $db->query('SELECT COUNT(DISTINCT user_id) FROM tokens WHERE expires_at > NOW()')->fetchColumn();
$newThisWeek    = (int) $db->query('SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND role = "user"')->fetchColumn();

// Logs by type
$logCounts = ['sleep' => 0, 'feed' => 0, 'diaper' => 0, 'growth' => 0, 'med' => 0];
$stmt = $db->query('SELECT type, COUNT(*) AS c FROM logs GROUP BY type');
foreach ($stmt->fetchAll() as $r) {
  if (isset($logCounts[$r['type']])) $logCounts[$r['type']] = (int)$r['c'];
}
$totalLogs = array_sum($logCounts);

$totalMilestones = (int) $db->query('SELECT COUNT(*) FROM milestones')->fetchColumn();
$totalMedLogs    = (int) $db->query('SELECT COUNT(*) FROM med_logs')->fetchColumn();

// Users who created at least one log in the last 24 h
$since24h = (time() - 86400) * 1000;
$stmt = $db->prepare('SELECT COUNT(DISTINCT user_id) FROM logs WHERE time_ts > ?');
$stmt->execute([$since24h]);
$activeToday = (int) $stmt->fetchColumn();

// Registrations per day — last 14 days
$stmt = $db->query(
  'SELECT DATE(created_at) AS day, COUNT(*) AS c
   FROM users WHERE role = "user" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
   GROUP BY DATE(created_at) ORDER BY day ASC'
);
$regPerDay = $stmt->fetchAll();

respond([
  'totalUsers'      => $totalUsers,
  'totalBabies'     => $totalBabies,
  'activeSessions'  => $activeSessions,
  'newThisWeek'     => $newThisWeek,
  'totalLogs'       => $totalLogs,
  'logCounts'       => $logCounts,
  'totalMilestones' => $totalMilestones,
  'totalMedLogs'    => $totalMedLogs,
  'activeToday'     => $activeToday,
  'regPerDay'       => $regPerDay,
]);
