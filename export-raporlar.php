<?php
/**
 * Raporlar Excel Export
 * Rapor verilerini CSV formatında export eder
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Yetkisiz erişim');
}

require_once 'config/db.php';
require_once 'includes/export.php';

// Rapor tipi
$report_type = $_GET['type'] ?? 'overview';

// Export fonksiyonunu çağır
export_reports($db, $report_type);
?>
