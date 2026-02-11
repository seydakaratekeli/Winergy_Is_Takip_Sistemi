# Loglama Sistemi ve Backup Otomasyonu

Bu klasörde loglama ve yedekleme sistemleri için scriptler bulunmaktadır.

## 📁 Dosyalar

### Loglama
- `includes/logger.php` - Ana loglama kütüphanesi
- `admin-logs.php` - Log görüntüleme sayfası (admin paneli)
- `cleanup_logs.php` - Eski logları temizleme scripti

### Yedekleme
- `backup.sh` - Otomatik yedekleme scripti (Linux/Mac)
- `restore.sh` - Yedek geri yükleme scripti
- `crontab.example` - Cron job örnekleri

## 🚀 Kurulum

### 1. Loglama Sistemi

Loglama sistemi otomatik olarak çalışır. Kullanmak için:

```php
require_once 'includes/logger.php';

// Kullanıcı aktivitesi logla
log_activity('İş Eklendi', 'Başlık: Tespit, Müşteri: ABC Ltd.', 'SUCCESS');

// Hata logla
log_error('Veritabanı bağlantı hatası', ['file' => __FILE__, 'line' => __LINE__]);
```

**Log Seviyeleri:**
- `INFO` - Bilgilendirme
- `SUCCESS` - Başarılı işlem
- `WARNING` - Uyarı
- `ERROR` - Hata

**Log Dosyaları:**
- `logs/activity_YYYY-MM.log` - Kullanıcı aktiviteleri
- `logs/error_YYYY-MM.log` - Sistem hataları

### 2. Log Görüntüleme

Admin kullanıcılar için log görüntüleme paneli:
- URL: `http://localhost/Winergy_Is_Takip_Sistemi/admin-logs.php`
- Menü: **Sistem Logları** (admin menüsünde)

**Özellikler:**
- Filtreleme (kullanıcı, seviye, tarih)
- İstatistikler
- Eski log temizleme

### 3. Otomatik Log Temizleme

**Manuel çalıştırma:**
```bash
php scripts/cleanup_logs.php 30  # 30 günden eski logları sil
```

**Cron job ile otomatik:**
```bash
# Her ayın ilk günü eski logları temizle
0 4 1 * * php /var/www/html/Winergy_Is_Takip_Sistemi/scripts/cleanup_logs.php 30
```

### 4. Backup Sistemi Kurulumu

#### Linux/Mac

**1. Script izinlerini ayarlayın:**
```bash
chmod +x scripts/backup.sh
chmod +x scripts/restore.sh
```

**2. Yapılandırma:**
`scripts/backup.sh` dosyasını açın ve düzenleyin:
```bash
PROJECT_DIR="/var/www/html/Winergy_Is_Takip_Sistemi"
DB_USER="root"
DB_PASS="your_password"  # Şifrenizi girin
```

**3. Manuel test:**
```bash
./scripts/backup.sh                # Tam yedek
./scripts/backup.sh --db-only      # Sadece DB
./scripts/backup.sh --files-only   # Sadece dosyalar
```

**4. Cron job kurulumu:**
```bash
crontab -e
# Aşağıdaki satırı ekleyin:
0 2 * * * /var/www/html/Winergy_Is_Takip_Sistemi/scripts/backup.sh >> /var/log/winergy_backup.log 2>&1
```

#### Windows

**1. Git Bash yükleyin** (MySQL komutları için)

**2. Task Scheduler ile otomatik yedekleme:**
- Task Scheduler'ı açın
- "Create Basic Task" seçin
- **Trigger:** Daily, 02:00 AM
- **Action:** Start a program
  - Program: `C:\Program Files\Git\bin\bash.exe`
  - Arguments: `C:/xampp/htdocs/Winergy_Is_Takip_Sistemi/scripts/backup.sh`

### 5. Yedek Geri Yükleme

**Mevcut yedekleri listele:**
```bash
./scripts/restore.sh --list
```

**Yedekten geri yükle:**
```bash
./scripts/restore.sh winergy_backup_20260210_120000
```

**Önemli:** 
- Geri yükleme öncesi otomatik güvenlik yedeği alınır
- Mevcut veriler üzerine yazılır
- İşlem geri alınamaz!

## 📊 Kullanım Örnekleri

### Loglama Örnekleri

```php
// Başarılı işlem
log_activity('İş Tamamlandı', 'İş ID: 123', 'SUCCESS');

// Uyarı
log_activity('Yetki Hatası', 'Kullanıcı admin olmadan kullanıcı silmeye çalıştı', 'WARNING');

// Hata
log_error('Dosya yükleme hatası: ' . $error_msg, [
    'file' => __FILE__,
    'line' => __LINE__,
    'user_id' => $_SESSION['user_id']
]);

// Belirli kullanıcının logları
$user_logs = get_user_logs($user_id, 100);

// İstatistikler
$stats = get_log_statistics();
echo "Bugün: " . $stats['today'];
echo "Bu hafta: " . $stats['this_week'];
```

### Backup Örnekleri

```bash
# Tam yedekleme (günlük)
./backup.sh

# Sadece veritabanı (her 6 saatte)
./backup.sh --db-only

# Yedek kontrol
ls -lh backups/

# Geri yükleme
./restore.sh winergy_backup_20260210_120000
```

## 🔧 Bakım

### Log Dosyası Boyutu

Log dosyaları otomatik olarak rotate edilir:
- Dosya 10MB'ı geçerse otomatik yeni dosya başlatılır
- Eski dosyalar `.bak` uzantısıyla saklanır
- Script, 100 istekte bir boyut kontrolü yapar

### Yedek Saklama

Varsayılan olarak son **7 yedek** saklanır. Değiştirmek için:

`backup.sh` dosyasında:
```bash
KEEP_BACKUPS=7  # Bu sayıyı değiştirin
```

### Disk Alanı

Disk alanı kontrolü için cron job:
```bash
# Her gün %90 doluysa e-posta gönder
0 8 * * * df -h | grep -vE '^Filesystem' | awk '{ print $5 " " $1 }' | while read output; do usep=$(echo $output | awk '{ print $1}' | cut -d'%' -f1); if [ $usep -ge 90 ]; then echo "Disk %$usep dolu" | mail -s "Disk Uyarısı" admin@example.com; fi; done
```

## 📈 İzleme

### Log İstatistikleri

Admin panelinde (`admin-logs.php`):
- Bugün, bu hafta, bu ay aktiviteleri
- Kullanıcı bazlı istatistikler
- Hata seviyeleri
- En çok yapılan işlemler

### Backup İzleme

Backup log dosyası:
```bash
tail -f /var/log/winergy_backup.log
```

E-posta bildirimi (opsiyonel):
```bash
# backup.sh içinde send_notification fonksiyonunu aktif edin
```

## ⚠️ Önemli Notlar

1. **Güvenlik:**
   - Log dosyaları `.htaccess` ile korunur
   - Hassas bilgileri loglama
   - Şifreleri log dosyalarına yazmayın

2. **Performance:**
   - Log yazma operasyonu hafiftir
   - Asenkron loglama gerekirse queue sistemi ekleyin
   - Büyük log dosyaları performansı etkileyebilir

3. **Backup:**
   - Yedekleri farklı bir sunucuya da kopyalayın
   - Test edin! Yedekten geri yükleme yapın
   - Veritabanı şifresini script içine yazmak yerine environment variable kullanın

4. **GDPR/KVKK:**
   - Kullanıcı IP adreslerini logluyorsunuz
   - Gerekirse anonimleştirme ekleyin
   - Log saklama sürelerini yasal gerekliliklere uygun ayarlayın

## 🔗 Kaynaklar

- **Cron Job Generator:** https://crontab.guru/
- **Log Best Practices:** PSR-3 Logging Standard
- **Backup Strategy:** 3-2-1 Rule (3 kopya, 2 farklı ortam, 1 off-site)

---

© 2026 Winergy Technologies
