<?php
/**
 * Admin Logları Excel Export
 * Log kayıtlarını CSV formatında export eder
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Yetkisiz erişim');
}

// Sadece admin erişebilir
if ($_SESSION['user_role'] !== 'admin') {
    die('Bu işlem için yetkiniz yok');
}

require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/export.php';

// Filtreleme parametreleri (admin-logs.php'den)
$type = $_GET['type'] ?? 'activity';
$month = $_GET['month'] ?? date('Y-m');
$user_filter = $_GET['user'] ?? '';
$level_filter = $_GET['level'] ?? '';
$action_filter = $_GET['action'] ?? '';

// Logları oku
$logs = read_logs($type, $month, 5000); // Daha fazla kayıt

// Filtreleme uygula
if ($user_filter) {
    $logs = array_filter($logs, fn($log) => stripos($log['user_name'] ?? '', $user_filter) !== false);
}

if ($level_filter) {
    $logs = array_filter($logs, fn($log) => ($log['level'] ?? '') === $level_filter);
}

if ($action_filter) {
    $logs = array_filter($logs, fn($log) => stripos($log['action'] ?? '', $action_filter) !== false);
}

// Export fonksiyonunu çağır
export_logs($logs);
?>
