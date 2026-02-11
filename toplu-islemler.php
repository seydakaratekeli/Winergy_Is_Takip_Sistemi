<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/csrf.php'; // CSRF Koruması
require_once 'includes/logger.php'; // Sayfanın başına ekleyin

// Sadece POST isteklerine izin ver
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// CSRF Token Kontrolü
if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
    csrf_error(); // Geçersiz token - işlemi durdur
}

// Seçili iş ID'lerini al
$job_ids = $_POST['job_ids'] ?? [];
$action = $_POST['action'] ?? '';

if (empty($job_ids) || empty($action)) {
    log_activity('Toplu İşlem Hatası', "Seçili işler veya işlem türü belirtilmediği için işlem yapılamadı.", 'ERROR');
    header("Location: index.php?error=no_selection");
    exit;
}

// ID'leri güvenlik için integer'a çevir
$job_ids = array_map('intval', $job_ids);
$placeholders = str_repeat('?,', count($job_ids) - 1) . '?';

try {
    switch ($action) {
        case 'change_status':
            $new_status = $_POST['new_status'] ?? '';
            if (empty($new_status)) {
                log_activity('İş Durumu Güncelleme Hatası', "Yeni durum belirtilmediği için güncelleme yapılamadı.", 'ERROR');
                header("Location: index.php?error=no_status");
                exit;
            }
            
            $sql = "UPDATE jobs SET status = ?, updated_by = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$new_status, $_SESSION['user_id']], $job_ids);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            log_activity('İş Durumu Güncellendi', "Seçili işler için yeni durum: '$new_status'", 'INFO');
            header("Location: index.php?success=status_updated&count=" . $stmt->rowCount());
            break;
            
        case 'assign_user':
            $user_id = $_POST['assign_user_id'] ?? '';
            if (empty($user_id)) {
                log_activity('İş Atama Hatası', "Kullanıcı ID belirtilmediği için atama yapılamadı.", 'ERROR');
                header("Location: index.php?error=no_user");
                exit;
            }
            
            $sql = "UPDATE jobs SET assigned_user_id = ?, updated_by = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$user_id, $_SESSION['user_id']], $job_ids);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            log_activity('İşler Atandı', "Seçili işler kullanıcı ID: $user_id ile atandı: " . implode(', ', $job_ids), 'INFO');
            header("Location: index.php?success=assigned&count=" . $stmt->rowCount());
            break;
            
        case 'delete':
            // Soft delete - İşleri "İptal" durumuna al (Veri kaybı önlenir)
            $sql = "UPDATE jobs SET status = 'İptal', updated_by = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$_SESSION['user_id']], $job_ids);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            log_activity('İşler İptal Edildi', "Seçili işler iptal edildi (soft delete): " . implode(', ', $job_ids), 'INFO');
            header("Location: index.php?success=cancelled&count=" . $stmt->rowCount());
            break;
            
        default:
            log_activity('Toplu İşlem Hatası', "Geçersiz işlem türü: $action", 'ERROR');
            header("Location: index.php?error=invalid_action");
    }
} catch (PDOException $e) {
    error_log("Toplu işlem hatası: " . $e->getMessage());
    header("Location: index.php?error=database");
}
exit;
?>
