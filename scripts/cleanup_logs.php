<?php
/**
 * Log Temizleme Scripti
 * Eski log dosyalarını otomatik temizler
 * 
 * Kullanım:
 *   php cleanup_logs.php [gün_sayısı]
 *   php cleanup_logs.php 30
 * 
 * Cron:
 *   0 4 1 * * php /path/to/cleanup_logs.php 30
 */

// Script sadece CLI'dan çalışsın
if (php_sapi_name() !== 'cli') {
    die("Bu script sadece komut satırından çalıştırılabilir.\n");
}

// Proje kök dizinini bul
$project_root = dirname(__DIR__);
require_once $project_root . '/includes/logger.php';

// Parametreden gün sayısını al, yoksa varsayılan 30
$days = isset($argv[1]) ? intval($argv[1]) : 30;

if ($days < 1) {
    echo "HATA: Geçerli bir gün sayısı belirtin (minimum 1)\n";
    exit(1);
}

echo "=== Winergy Log Temizleme Scripti ===\n";
echo "Başlangıç: " . date('Y-m-d H:i:s') . "\n";
echo "Parametre: $days günden eski loglar silinecek\n\n";

try {
    // Log klasörünü kontrol et
    $log_dir = ensure_log_directory();
    echo "Log dizini: $log_dir\n";
    
    // Temizleme işlemi
    $deleted = cleanup_old_logs($days);
    
    echo "\n✓ Temizleme tamamlandı!\n";
    echo "Silinen dosya sayısı: $deleted\n";
    
    // İstatistikler
    $files = glob($log_dir . '/*.log');
    $total_size = 0;
    foreach ($files as $file) {
        $total_size += filesize($file);
    }
    
    echo "Kalan log dosyası: " . count($files) . "\n";
    echo "Toplam boyut: " . format_bytes($total_size) . "\n";
    
    echo "\nBitiş: " . date('Y-m-d H:i:s') . "\n";
    
    // Aktivite logla
    log_activity(
        'Log Temizleme',
        "$deleted adet log dosyası silindi ($days günden eski)",
        'INFO'
    );
    
    exit(0);
    
} catch (Exception $e) {
    echo "HATA: " . $e->getMessage() . "\n";
    log_error('Log temizleme hatası: ' . $e->getMessage());
    exit(1);
}

/**
 * Byte'ları okunabilir formata çevir
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
