<?php
/**
 * Kullanıcı Listesi Excel Export  
 * Kullanıcı bilgilerini CSV formatında export eder
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
require_once 'includes/export.php';

// Seçili kullanıcı ID'leri
$selected_ids = null;
if (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    $selected_ids = array_map('intval', $_POST['selected_users']);
}

// Export fonksiyonunu çağır
export_users($db, $selected_ids);
?>
