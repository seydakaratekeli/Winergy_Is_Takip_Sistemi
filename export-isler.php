<?php
/**
 * İş Listesi Excel Export
 * Ana iş listesini CSV formatında export eder
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Yetkisiz erişim');
}

require_once 'config/db.php';
require_once 'includes/export.php';

// Filtreleri al
$filters = [
    'service_type' => $_GET['service_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'date_start' => $_GET['date_start'] ?? '',
    'date_end' => $_GET['date_end'] ?? '',
    'assigned_user' => $_GET['assigned_user'] ?? ''
];

// Seçili ID'leri al (checkbox ile seçilenler)
$selected_ids = null;
if (isset($_POST['selected_jobs']) && is_array($_POST['selected_jobs'])) {
    $selected_ids = array_map('intval', $_POST['selected_jobs']);
}

// Export fonksiyonunu çağır
export_jobs($db, $filters, $selected_ids);
?>
