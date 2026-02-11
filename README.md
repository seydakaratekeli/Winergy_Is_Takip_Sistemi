# Winergy İş Takip Sistemi

Winergy Technologies için geliştirilmiş kapsamlı iş ve müşteri takip sistemi.

## 🚀 Özellikler

- **İş Yönetimi**: İş oluşturma, düzenleme, durum takibi
- **Müşteri Yönetimi**: Müşteri bilgileri, notlar, iş geçmişi
- **Kullanıcı Yönetimi**: Rol tabanlı yetkilendirme (Admin, Operasyon, Danışman)
- **Dosya Yönetimi**: Güvenli dosya yükleme ve saklama
- **Raporlama**: Detaylı istatistikler ve performans raporları
- **Toplu İşlemler**: Çoklu iş kayıtlarını aynı anda yönetme
- **Gelişmiş Arama**: Çok kriterli arama ve filtreleme
- **Güvenlik**: CSRF koruması, SQL injection koruması, güvenli dosya yükleme

## 📋 Gereksinimler

- PHP 8.0 veya üzeri
- MySQL 5.7 veya MariaDB 10.3+
- Apache/Nginx web sunucusu
- mod_rewrite etkin (Apache için)

## 🔧 Kurulum

### 1. Dosyaları Yerleştirin
```bash
# Projeyi web sunucusu dizinine kopyalayın
cp -r Winergy_Is_Takip_Sistemi /var/www/html/
# veya XAMPP için
cp -r Winergy_Is_Takip_Sistemi C:/xampp/htdocs/
```

### 2. Veritabanını Oluşturun
```sql
CREATE DATABASE winergy_is_takip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Veritabanı Yapısını İçe Aktarın
SQL dump dosyasını içe aktarın (eğer varsa) veya aşağıdaki tabloları oluşturun:

**Gerekli Tablolar:**
- `users` - Kullanıcı yönetimi
- `customers` - Müşteri bilgileri
- `customer_notes` - Müşteri notları
- `jobs` - İş kayıtları
- `job_notes` - İş notları
- `job_files` - İş dosyaları

### 4. Veritabanı Bağlantısını Yapılandırın

`config/db.php.example` dosyasını `config/db.php` olarak kopyalayın ve düzenleyin:

```php
<?php
$host = "localhost";
$dbname = "winergy_is_takip";
$username = "root"; // Veritabanı kullanıcı adı
$password = "";     // Veritabanı şifresi
```

**⚠️ ÖNEMLİ**: Production ortamında `config/db.php` dosyasını `.gitignore`'a ekleyin!

### 5. Klasör İzinlerini Ayarlayın

```bash
# Linux/Mac için
chmod 755 uploads/
chmod 644 uploads/.htaccess
chmod 755 config/
chmod 600 config/db.php

# Windows için dosya özelliklerinden izinleri ayarlayın
```

### 6. İlk Kullanıcıyı Oluşturun

Veritabanına ilk admin kullanıcıyı ekleyin:

```sql
INSERT INTO users (name, email, password, role, is_active, created_at) 
VALUES (
    'Admin', 
    'admin@winergytech.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- şifre: password
    'admin', 
    1, 
    NOW()
);
```

İlk girişten sonra **mutlaka şifrenizi değiştirin**!

## 🔐 Güvenlik

### Production Ortamı İçin Öneriler

1. **Güçlü şifreler kullanın**
2. **HTTPS kullanın** (SSL/TLS sertifikası)
3. **PHP error display'i kapatın**:
   ```php
   // php.ini veya .htaccess
   display_errors = Off
   error_reporting = E_ALL
   log_errors = On
   ```
4. **Veritabanı kullanıcısına minimum yetki verin**
5. **Düzenli yedek alın**
6. **Güncellemeleri takip edin**

### Güvenlik Özellikleri

- ✅ CSRF token koruması
- ✅ Prepared statements (SQL injection koruması)
- ✅ Password hashing (bcrypt)
- ✅ Dosya yükleme güvenliği (MIME type check, boyut limitleri)
- ✅ .htaccess ile dizin koruması
- ✅ Session güvenliği
- ✅ XSS koruması (htmlspecialchars)

## 👥 Kullanıcı Rolleri

### Admin
- Tüm sistem yetkilerine sahip
- Kullanıcı ekleme/düzenleme/silme
- Tüm işleri görüntüleme ve düzenleme

### Operasyon
- İş ve müşteri yönetimi
- Raporları görüntüleme
- Dosya yükleme

### Danışman
- Atanan işleri görüntüleme ve düzenleme
- Müşteri bilgilerini görüntüleme
- Not ekleme

## 📱 Kullanım

1. **Giriş Yapın**: http://localhost/Winergy_Is_Takip_Sistemi/login.php
2. **Dashboard**: Ana sayfada tüm açık işleri görün
3. **İş Ekle**: Yeni iş kaydı oluşturun
4. **Müşteri Ekle**: Yeni müşteri ekleyin
5. **Raporlar**: Detaylı istatistikleri görüntüleyin

##  Destek

**Winergy Technologies**
- 📞 Tel: 0312 395 68 28
- 🌐 Web: https://winergytechnologies.com
- 📧 E-posta: info@winergytechnologies.com

## 📝 Lisans

© 2026 Winergy Technologies. Tüm hakları saklıdır.

---

**Geliştirici Notları**: Bu sistem PHP 8+ ve modern güvenlik standartlarıyla geliştirilmiştir.
