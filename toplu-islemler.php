<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/csrf.php'; // CSRF Koruması
require_once 'includes/logger.php'; 

// Referrer'dan dönüş sayfasını belirle
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$return_page = (strpos($referer, 'arama.php') !== false) ? 'arama.php' : 'index.php';

// Sadece POST isteklerine izin ver
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $return_page");
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
    header("Location: $return_page?error=no_selection");
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
                header("Location: $return_page?error=no_status");
                exit;
            }
            
            $sql = "UPDATE jobs SET status = ?, updated_by = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$new_status, $_SESSION['user_id']], $job_ids);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            log_activity('İş Durumu Güncellendi', "Seçili işler için yeni durum: '$new_status'", 'INFO');
            header("Location: $return_page?success=status_updated&count=" . $stmt->rowCount());
            break;
            
        case 'assign_user':
            $user_id = $_POST['assign_user_id'] ?? '';
            if (empty($user_id)) {
                log_activity('İş Atama Hatası', "Kullanıcı ID belirtilmediği için atama yapılamadı.", 'ERROR');
                header("Location: $return_page?error=no_user");
                exit;
            }
            
            $sql = "UPDATE jobs SET assigned_user_id = ?, updated_by = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$user_id, $_SESSION['user_id']], $job_ids);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            log_activity('İşler Atandı', "Seçili işler kullanıcı ID: $user_id ile atandı: " . implode(', ', $job_ids), 'INFO');
            header("Location: $return_page?success=assigned&count=" . $stmt->rowCount());
            break;
            
        case 'change_service_type':
            $service_types = $_POST['service_type'] ?? [];
            if (empty($service_types)) {
                log_activity('Hizmet Türü Güncelleme Hatası', "Hizmet türü belirtilmediği için güncelleme yapılamadı.", 'ERROR');
                header("Location: $return_page?error=no_service_type");
                exit;
            }
            
            // Hizmet türlerini JSON formatına çevir
            $service_type_json = json_encode($service_types, JSON_UNESCAPED_UNICODE);
            
            $sql = "UPDATE jobs SET service_type = ?, updated_by = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            $params = array_merge([$service_type_json, $_SESSION['user_id']], $job_ids);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            log_activity('Hizmet Türü Güncellendi', "Seçili işler için yeni hizmet türleri: " . implode(', ', $service_types), 'INFO');
            header("Location: $return_page?success=service_type_updated&count=" . $stmt->rowCount());
            break;
       
    case 'delete':
    // Hard delete - İşleri veritabanından tamamen siler
    $sql = "DELETE FROM jobs WHERE id IN ($placeholders)";
    $params = $job_ids; // updated_by parametresine ihtiyaç kalmadığı için direkt listeyi gönder
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    log_activity('İşler Silindi', "Seçili işler kalıcı olarak silindi: " . implode(', ', $job_ids), 'WARNING');
    header("Location: $return_page?success=deleted&count=" . $stmt->rowCount());
    break;
            
        default:
            log_activity('Toplu İşlem Hatası', "Geçersiz işlem türü: $action", 'ERROR');
            header("Location: $return_page?error=invalid_action");
    }
} catch (PDOException $e) {
    error_log("Toplu işlem hatası: " . $e->getMessage());
    header("Location: $return_page?error=database");
}
exit;
?>
