#!/bin/bash
###############################################################################
# Winergy İş Takip Sistemi - Otomatik Yedekleme Scripti
# 
# Kullanım:
#   ./backup.sh                    # Tam yedekleme (DB + dosyalar)
#   ./backup.sh --db-only          # Sadece veritabanı
#   ./backup.sh --files-only       # Sadece dosyalar
#
# Cron Job için:
#   0 2 * * * /path/to/backup.sh > /dev/null 2>&1
###############################################################################

# Yapılandırma
PROJECT_DIR="/var/www/html/Winergy_Is_Takip_Sistemi"  # Linux
# PROJECT_DIR="C:/xampp/htdocs/Winergy_Is_Takip_Sistemi"  # Windows (Git Bash)

BACKUP_DIR="${PROJECT_DIR}/backups"
DB_NAME="winergy_is_takip"
DB_USER="root"
DB_PASS=""  # Şifrenizi buraya yazın
DB_HOST="localhost"

# Yedek dosya adı (tarih damgalı)
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="winergy_backup_${TIMESTAMP}"

# Saklanacak yedek sayısı (eski yedekleri siler)
KEEP_BACKUPS=7

# Renkli çıktı
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

###############################################################################
# Fonksiyonlar
###############################################################################

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[HATA]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[UYARI]${NC} $1"
}

# Backup dizinini oluştur
create_backup_dir() {
    if [ ! -d "$BACKUP_DIR" ]; then
        mkdir -p "$BACKUP_DIR"
        log "Backup dizini oluşturuldu: $BACKUP_DIR"
    fi
}

# Veritabanı yedeği al
backup_database() {
    log "Veritabanı yedeği alınıyor..."
    
    DB_FILE="${BACKUP_DIR}/${BACKUP_NAME}_database.sql"
    
    if [ -z "$DB_PASS" ]; then
        mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$DB_FILE" 2>&1
    else
        mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_FILE" 2>&1
    fi
    
    if [ $? -eq 0 ]; then
        # Sıkıştır
        gzip "$DB_FILE"
        log "✓ Veritabanı yedeği tamamlandı: ${DB_FILE}.gz"
        echo "$(du -h ${DB_FILE}.gz | cut -f1)"
    else
        error "Veritabanı yedeği alınamadı!"
        return 1
    fi
}

# Dosya yedeği al
backup_files() {
    log "Dosya yedeği alınıyor..."
    
    FILES_TAR="${BACKUP_DIR}/${BACKUP_NAME}_files.tar.gz"
    
    # Yedeklenecek klasörler
    cd "$PROJECT_DIR" || exit 1
    
    tar -czf "$FILES_TAR" \
        --exclude='backups' \
        --exclude='logs/*.log' \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='vendor' \
        uploads/ \
        config/db.php \
        2>&1
    
    if [ $? -eq 0 ]; then
        log "✓ Dosya yedeği tamamlandı: $FILES_TAR"
        echo "$(du -h $FILES_TAR | cut -f1)"
    else
        error "Dosya yedeği alınamadı!"
        return 1
    fi
}

# Eski yedekleri temizle
cleanup_old_backups() {
    log "Eski yedekler temizleniyor (son $KEEP_BACKUPS yedek korunuyor)..."
    
    cd "$BACKUP_DIR" || return
    
    # Database yedekleri
    ls -t winergy_backup_*_database.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm
    
    # Dosya yedekleri
    ls -t winergy_backup_*_files.tar.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm
    
    log "✓ Temizleme tamamlandı"
}

# Yedek doğrulama
verify_backup() {
    log "Yedek doğrulanıyor..."
    
    DB_FILE="${BACKUP_DIR}/${BACKUP_NAME}_database.sql.gz"
    FILES_TAR="${BACKUP_DIR}/${BACKUP_NAME}_files.tar.gz"
    
    # Dosya boyutlarını kontrol et
    if [ -f "$DB_FILE" ]; then
        DB_SIZE=$(stat -f%z "$DB_FILE" 2>/dev/null || stat -c%s "$DB_FILE" 2>/dev/null)
        if [ "$DB_SIZE" -lt 1024 ]; then
            error "Veritabanı yedeği çok küçük! Muhtemelen hatalı."
            return 1
        fi
    fi
    
    if [ -f "$FILES_TAR" ]; then
        FILES_SIZE=$(stat -f%z "$FILES_TAR" 2>/dev/null || stat -c%s "$FILES_TAR" 2>/dev/null)
        if [ "$FILES_SIZE" -lt 1024 ]; then
            error "Dosya yedeği çok küçük! Muhtemelen hatalı."
            return 1
        fi
    fi
    
    log "✓ Yedek doğrulama başarılı"
}

# E-posta bildirimi (opsiyonel)
send_notification() {
    # Eğer mail komutu varsa bildirim gönder
    if command -v mail &> /dev/null; then
        echo "Winergy yedekleme tamamlandı: $BACKUP_NAME" | \
            mail -s "Yedekleme Başarılı" admin@winergytech.com
    fi
}

###############################################################################
# Ana Program
###############################################################################

log "=== Winergy İş Takip Sistemi Yedekleme Başlatıldı ==="

# Parametreleri kontrol et
DB_ONLY=false
FILES_ONLY=false

for arg in "$@"; do
    case $arg in
        --db-only)
            DB_ONLY=true
            ;;
        --files-only)
            FILES_ONLY=true
            ;;
    esac
done

# Backup dizinini oluştur
create_backup_dir

# Yedekleme işlemleri
if [ "$FILES_ONLY" = false ]; then
    backup_database || exit 1
fi

if [ "$DB_ONLY" = false ]; then
    backup_files || exit 1
fi

# Doğrulama
verify_backup || warning "Yedek doğrulama başarısız!"

# Eski yedekleri temizle
cleanup_old_backups

# İstatistikler
log "=== Yedekleme Tamamlandı ==="
log "Backup adı: $BACKUP_NAME"
log "Backup dizini: $BACKUP_DIR"
log "Toplam yedek sayısı: $(ls -1 $BACKUP_DIR/*.gz 2>/dev/null | wc -l)"

# Bildirim gönder (opsiyonel)
# send_notification

exit 0
