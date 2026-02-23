<?php
/**
 * Müşteri Listesi Excel Export
 * Müşteri bilgilerini CSV formatında export eder
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Yetkisiz erişim');
}

require_once 'config/db.php';
require_once 'includes/export.php';
require_once 'includes/csrf.php';

// POST ile seçili kayıtlar gönderiliyorsa CSRF kontrolü yap
if (isset($_POST['selected_customers'])) {
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        die('Geçersiz istek tespit edildi');
    }
}

// Arama filtresi
$search = $_GET['search'] ?? '';

// Seçili müşteri ID'leri
$selected_ids = null;
if (isset($_POST['selected_customers']) && is_array($_POST['selected_customers'])) {
    $selected_ids = array_map('intval', $_POST['selected_customers']);
}

// Export fonksiyonunu çağır
export_customers($db, $search, $selected_ids);
?>
