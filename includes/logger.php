<?php
/**
 * Loglama Sistemi
 * Kullanıcı aktivitelerini ve sistem hatalarını loglar
 */

/**
 * Log dizinini kontrol et ve oluştur
 */
function ensure_log_directory() {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
        
        // .htaccess ile koruma
        file_put_contents($log_dir . '/.htaccess', "Order Allow,Deny\nDeny from all");
        
        // index.html ile koruma
        file_put_contents($log_dir . '/index.html', '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Access Denied</h1></body></html>');
    }
    return $log_dir;
}

/**
 * Log seviyesini Türkçeleştir
 * 
 * @param string $level - İngilizce log seviyesi (INFO, SUCCESS, WARNING, ERROR)
 * @return string - Türkçe karşılığı
 */
function translate_log_level($level) {
    $translations = [
        'INFO' => 'Bilgi',
        'SUCCESS' => 'Başarılı',
        'WARNING' => 'Uyarı',
        'ERROR' => 'Hata'
    ];
    
    return $translations[$level] ?? $level;
}

/**
 * Log seviyesi için renk kodu döndür
 * 
 * @param string $level - Log seviyesi
 * @return string - Bootstrap renk sınıfı
 */
function get_log_level_color($level) {
    $colors = [
        'INFO' => 'primary',
        'SUCCESS' => 'success',
        'WARNING' => 'warning',
        'ERROR' => 'danger'
    ];
    
    return $colors[$level] ?? 'secondary';
}

/**
 * Kullanıcı aktivitesi logla
 * 
 * @param string $action - Yapılan işlem (örn: "İş Eklendi", "Müşteri Silindi")
 * @param string $details - İşlem detayları
 * @param string $level - Log seviyesi: INFO, SUCCESS, WARNING, ERROR
 */
function log_activity($action, $details = '', $level = 'INFO') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $log_dir = ensure_log_directory();
    $log_file = $log_dir . '/activity_' . date('Y-m') . '.log';
    
    $user_id = $_SESSION['user_id'] ?? 'guest';
    $user_name = $_SESSION['user_name'] ?? 'Guest';
    $user_role = $_SESSION['user_role'] ?? 'none';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'user_id' => $user_id,
        'user_name' => $user_name,
        'user_role' => $user_role,
        'ip' => $ip,
        'action' => $action,
        'details' => $details,
        'url' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'user_agent' => substr($user_agent, 0, 200) // İlk 200 karakter
    ];
    
    // JSON formatında kaydet
    $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    // Dosyaya yaz (thread-safe)
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    
    // Kritik hatalarda ayrıca error log'a da yaz
    if ($level === 'ERROR') {
        error_log("[$level] $action - $details (User: $user_name, IP: $ip)");
    }
}

/**
 * Sistem hatası logla
 * 
 * @param string $error_message - Hata mesajı
 * @param array $context - Ek bilgiler (opsiyonel)
 */
function log_error($error_message, $context = []) {
    $log_dir = ensure_log_directory();
    $log_file = $log_dir . '/error_' . date('Y-m') . '.log';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $error_message,
        'context' => $context,
        'file' => $context['file'] ?? 'unknown',
        'line' => $context['line'] ?? 'unknown',
        'trace' => $context['trace'] ?? []
    ];
    
    $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    
    // PHP error log'a da yaz
    error_log($error_message);
}

/**
 * Log dosyalarını oku
 * 
 * @param string $type - Log tipi: 'activity' veya 'error'
 * @param string $month - Ay (YYYY-MM formatında, varsayılan: bu ay)
 * @param int $limit - Maksimum kayıt sayısı
 * @return array - Log kayıtları
 */
function read_logs($type = 'activity', $month = null, $limit = 1000) {
    $log_dir = ensure_log_directory();
    
    if (!$month) {
        $month = date('Y-m');
    }
    
    $log_file = $log_dir . '/' . $type . '_' . $month . '.log';
    
    if (!file_exists($log_file)) {
        return [];
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Son N kaydı al (tersten)
    $lines = array_slice(array_reverse($lines), 0, $limit);
    
    $logs = [];
    foreach ($lines as $line) {
        $log = json_decode($line, true);
        if ($log) {
            $logs[] = $log;
        }
    }
    
    return $logs;
}

/**
 * Belirli bir kullanıcının loglarını getir
 * 
 * @param int $user_id - Kullanıcı ID
 * @param int $limit - Maksimum kayıt sayısı
 * @return array - Log kayıtları
 */
function get_user_logs($user_id, $limit = 100) {
    $all_logs = read_logs('activity', null, 5000);
    
    $user_logs = array_filter($all_logs, function($log) use ($user_id) {
        return isset($log['user_id']) && $log['user_id'] == $user_id;
    });
    
    return array_slice($user_logs, 0, $limit);
}

/**
 * Log istatistikleri
 * 
 * @return array - İstatistikler
 */
function get_log_statistics() {
    $logs = read_logs('activity', null, 10000);
    
    $stats = [
        'total' => count($logs),
        'by_level' => [],
        'by_user' => [],
        'by_action' => [],
        'today' => 0,
        'this_week' => 0,
        'this_month' => 0
    ];
    
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('-7 days'));
    $month_start = date('Y-m-d', strtotime('-30 days'));
    
    foreach ($logs as $log) {
        // Level bazında
        $level = $log['level'] ?? 'INFO';
        $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
        
        // Kullanıcı bazında
        $user = $log['user_name'] ?? 'Unknown';
        $stats['by_user'][$user] = ($stats['by_user'][$user] ?? 0) + 1;
        
        // Action bazında
        $action = $log['action'] ?? 'Unknown';
        $stats['by_action'][$action] = ($stats['by_action'][$action] ?? 0) + 1;
        
        // Tarih bazında
        $log_date = substr($log['timestamp'], 0, 10);
        if ($log_date === $today) $stats['today']++;
        if ($log_date >= $week_start) $stats['this_week']++;
        if ($log_date >= $month_start) $stats['this_month']++;
    }
    
    return $stats;
}

/**
 * Eski log dosyalarını temizle (30 günden eski)
 */
function cleanup_old_logs($days = 30) {
    $log_dir = ensure_log_directory();
    $cutoff_time = time() - ($days * 24 * 60 * 60);
    
    $files = glob($log_dir . '/*.log');
    $deleted = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
            $deleted++;
        }
    }
    
    return $deleted;
}

/**
 * Log dosyası boyutunu kontrol et ve rotate et
 */
function rotate_logs_if_needed($max_size_mb = 10) {
    $log_dir = ensure_log_directory();
    $max_size = $max_size_mb * 1024 * 1024; // MB to bytes
    
    $files = glob($log_dir . '/*.log');
    
    foreach ($files as $file) {
        if (filesize($file) > $max_size) {
            // Yedek al ve yeni dosya başlat
            $backup = $file . '.' . date('YmdHis') . '.bak';
            rename($file, $backup);
            
            // Eski yedekleri sıkıştır (opsiyonel)
            if (function_exists('gzcompress')) {
                $compressed = $backup . '.gz';
                file_put_contents($compressed, gzcompress(file_get_contents($backup)));
                unlink($backup);
            }
        }
    }
}

// Otomatik log rotation kontrolü (her 100 istekte bir)
if (rand(1, 100) === 1) {
    rotate_logs_if_needed();
}
?>
