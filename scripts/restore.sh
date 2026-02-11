#!/bin/bash
###############################################################################
# Winergy İş Takip Sistemi - Yedek Geri Yükleme Scripti
# 
# Kullanım:
#   ./restore.sh [yedek_dosyası]
#   ./restore.sh winergy_backup_20260210_120000
#
# Önce yedekleri listele:
#   ./restore.sh --list
###############################################################################

# Yapılandırma
PROJECT_DIR="/var/www/html/Winergy_Is_Takip_Sistemi"
BACKUP_DIR="${PROJECT_DIR}/backups"
DB_NAME="winergy_is_takip"
DB_USER="root"
DB_PASS=""
DB_HOST="localhost"

# Renkli çıktı
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[HATA]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[UYARI]${NC} $1"
}

info() {
    echo -e "${BLUE}[BİLGİ]${NC} $1"
}

# Mevcut yedekleri listele
list_backups() {
    echo -e "\n${BLUE}=== Mevcut Yedekler ===${NC}\n"
    
    cd "$BACKUP_DIR" || exit 1
    
    # Database yedekleri
    echo -e "${GREEN}Veritabanı Yedekleri:${NC}"
    ls -lh winergy_backup_*_database.sql.gz 2>/dev/null | awk '{print $9, "(" $5 ")"}'
    
    echo ""
    
    # Dosya yedekleri
    echo -e "${GREEN}Dosya Yedekleri:${NC}"
    ls -lh winergy_backup_*_files.tar.gz 2>/dev/null | awk '{print $9, "(" $5 ")"}'
    
    echo ""
}

# Veritabanını geri yükle
restore_database() {
    local backup_name=$1
    local db_file="${BACKUP_DIR}/${backup_name}_database.sql.gz"
    
    if [ ! -f "$db_file" ]; then
        error "Veritabanı yedek dosyası bulunamadı: $db_file"
        return 1
    fi
    
    warning "Mevcut veritabanı YEDEKLENİYOR..."
    
    # Önce mevcut DB'yi yedekle
    local safety_backup="${BACKUP_DIR}/safety_backup_$(date +%Y%m%d_%H%M%S).sql"
    if [ -z "$DB_PASS" ]; then
        mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$safety_backup" 2>&1
    else
        mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$safety_backup" 2>&1
    fi
    
    log "Güvenlik yedeği alındı: $safety_backup"
    
    # Geri yükleme
    log "Veritabanı geri yükleniyor: $db_file"
    
    if [ -z "$DB_PASS" ]; then
        gunzip < "$db_file" | mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME"
    else
        gunzip < "$db_file" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
    fi
    
    if [ $? -eq 0 ]; then
        log "✓ Veritabanı başarıyla geri yüklendi"
        info "Güvenlik yedeği: $safety_backup (sorun olursa buradan geri dönebilirsiniz)"
        return 0
    else
        error "Veritabanı geri yüklenemedi!"
        warning "Güvenlik yedeğinden geri yükleme yapılıyor..."
        
        if [ -z "$DB_PASS" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$safety_backup"
        else
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$safety_backup"
        fi
        
        return 1
    fi
}

# Dosyaları geri yükle
restore_files() {
    local backup_name=$1
    local files_tar="${BACKUP_DIR}/${backup_name}_files.tar.gz"
    
    if [ ! -f "$files_tar" ]; then
        error "Dosya yedek dosyası bulunamadı: $files_tar"
        return 1
    fi
    
    log "Dosyalar geri yükleniyor: $files_tar"
    
    cd "$PROJECT_DIR" || exit 1
    
    # Mevcut dosyaları yedekle
    warning "Mevcut dosyalar yedekleniyor..."
    tar -czf "${BACKUP_DIR}/safety_files_$(date +%Y%m%d_%H%M%S).tar.gz" \
        uploads/ config/db.php 2>/dev/null
    
    # Geri yükle
    tar -xzf "$files_tar" -C "$PROJECT_DIR"
    
    if [ $? -eq 0 ]; then
        log "✓ Dosyalar başarıyla geri yüklendi"
        return 0
    else
        error "Dosyalar geri yüklenemedi!"
        return 1
    fi
}

# Onay al
confirm() {
    read -p "$(echo -e ${YELLOW}$1 [e/H]: ${NC})" -n 1 -r
    echo
    [[ $REPLY =~ ^[Ee]$ ]]
}

###############################################################################
# Ana Program
###############################################################################

# Parametre kontrolü
if [ $# -eq 0 ]; then
    error "Kullanım: $0 <yedek_adı> veya $0 --list"
    echo ""
    echo "Örnek:"
    echo "  $0 --list                              # Yedekleri listele"
    echo "  $0 winergy_backup_20260210_120000      # Bu yedekten geri yükle"
    exit 1
fi

# Liste göster
if [ "$1" = "--list" ]; then
    list_backups
    exit 0
fi

BACKUP_NAME=$1

# Yedek var mı kontrol et
DB_FILE="${BACKUP_DIR}/${BACKUP_NAME}_database.sql.gz"
FILES_TAR="${BACKUP_DIR}/${BACKUP_NAME}_files.tar.gz"

if [ ! -f "$DB_FILE" ] && [ ! -f "$FILES_TAR" ]; then
    error "Belirtilen yedek bulunamadı: $BACKUP_NAME"
    echo ""
    info "Mevcut yedekleri görmek için: $0 --list"
    exit 1
fi

# Bilgilendirme
echo -e "\n${BLUE}=== Geri Yükleme Bilgileri ===${NC}"
echo "Yedek adı: $BACKUP_NAME"
[ -f "$DB_FILE" ] && echo "Veritabanı: $(du -h $DB_FILE | cut -f1) - $DB_FILE"
[ -f "$FILES_TAR" ] && echo "Dosyalar: $(du -h $FILES_TAR | cut -f1) - $FILES_TAR"
echo ""

# Onay
warning "⚠️  DİKKAT: Bu işlem mevcut verilerin üzerine yazacak!"
warning "Mevcut veriler otomatik olarak yedeklenecek."

if ! confirm "Devam etmek istiyor musunuz?"; then
    log "İşlem iptal edildi."
    exit 0
fi

echo ""
log "=== Geri Yükleme Başlatıldı ==="

# Veritabanını geri yükle
if [ -f "$DB_FILE" ]; then
    restore_database "$BACKUP_NAME" || exit 1
fi

# Dosyaları geri yükle
if [ -f "$FILES_TAR" ]; then
    restore_files "$BACKUP_NAME" || exit 1
fi

echo ""
log "=== Geri Yükleme Tamamlandı ==="
info "Sistemi kontrol edin ve test edin."
info "Sorun varsa güvenlik yedeklerinden geri dönebilirsiniz:"
info "  ls -lh ${BACKUP_DIR}/safety_*"

exit 0
