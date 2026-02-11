# ====================================================================
# Winergy İş Takip Sistemi - Otomatik Yedekleme Kurulum Kılavuzu
# ====================================================================

> **Tarih:** Şubat 2026  
> **Versiyon:** 2.0  
> **Proje:** Winergy İş Takip Sistemi

## 📋 İÇİNDEKİLER
1. [Sistem Özeti](#sistem-özeti)
2. [Hızlı Başlangıç](#hızlı-başlangıç)
3. [Detaylı Kurulum](#detaylı-kurulum)
4. [Yedekleme Yapısı](#yedekleme-yapısı)
5. [Test ve Kontrol](#test-ve-kontrol)
6. [Sorun Giderme](#sorun-giderme)

---

## 📊 SİSTEM ÖZETİ

### Mevcut Durum
- ❌ Otomatik yedekleme kurulu değil
- ✅ Yedekleme scripti hazır ve çalışıyor
- ✅ Backup dizini mevcut: `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\`

### Yedeklenecek Veri
```
📦 Veritabanı: winergy_is_takip
├── users (Kullanıcılar - Admin, Operasyon, Danışman)
├── customers (Müşteriler + İletişim Bilgileri + Adres)
├── customer_notes (Müşteri Notları - Genel, Anlaşma, Önemli, Toplantı)
├── jobs (İş Kayıtları + Durum Takibi)
├── job_notes (İş Notları + Aktivite Geçmişi)
└── job_files (İş Dosyaları Metadata)

📁 Dosyalar:
├── uploads/ (Kullanıcı yüklediği dosyalar, dökümanlar)
└── config/db.php (Veritabanı yapılandırması)
```

### Proje Özellikleri
- **Modüller:** İş Yönetimi, Müşteri Yönetimi, Kullanıcı Yönetimi
- **Güvenlik:** CSRF Koruması, SQL Injection Koruması, Güvenli Dosya Yükleme
- **Özel Sayfalar:** İş Geçmişi, Müşteri Geçmişi, Admin Logları, Raporlar
- **Logger Sistemi:** Tüm aktivitelerin kaydı (logs/ dizini)

---

## 🚀 HIZLI BAŞLANGIÇ

### En Kolay Yöntem (PowerShell - Tek Komut)

**Windows PowerShell'i Yönetici olarak açın** ve aşağıdaki komutu çalıştırın:

```powershell
$action = New-ScheduledTaskAction -Execute "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat" -WorkingDirectory "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 10)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "WinergyBackup" -Description "Winergy İş Takip Sistemi - Günlük Otomatik Yedekleme (DB + Dosyalar)" -Settings $settings -Principal $principal
```

**Başarılı mesajı:**
```
TaskPath  TaskName      State
--------  --------      -----
\         WinergyBackup Ready
```

✅ **Kurulum tamamlandı!** → [Test Et](#hızlı-test)

---

## 🔧 DETAYLI KURULUM

### Yöntem 1: XML Import (Önerilen)

**Eğer WinergyBackup.xml dosyanız varsa:**

```powershell
# Yönetici PowerShell
schtasks /create /tn "WinergyBackup" /xml "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\WinergyBackup.xml"
```

✅ Başarılı: `SUCCESS: The scheduled task "WinergyBackup" has successfully been created.`

### Yöntem 2: Grafik Arayüz (GUI)

1. **Windows + R** → `taskschd.msc` → Enter

2. Sağ panel → **Create Basic Task...**

3. **Genel Bilgiler:**
   - Name: `WinergyBackup`
   - Description: `Winergy İş Takip Sistemi - Günlük Otomatik Yedekleme (Veritabanı + Dosyalar)`

4. **Trigger (Tetikleyici):**
   - ✅ Daily (Günlük)
   - Start: Bugünün tarihi
   - Recur every: **1** days
   - Start time: **02:00:00** (Gece 2'de)

5. **Action (Eylem):**
   - ✅ Start a program
   - Program/script: `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat`
   - Add arguments: (boş bırakın)
   - Start in: `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi`

6. **Finish** → Sonra task'a sağ tık → **Properties**

7. **General Tab:**
   - ✅ Run whether user is logged on or not
   - ✅ Run with highest privileges
   - Configure for: **Windows 10/11**

8. **Settings Tab:**
   - ✅ Allow task to be run on demand
   - ✅ Run task as soon as possible after a scheduled start is missed
   - ✅ If the task fails, restart every: **10 minutes**
   - ✅ Attempt to restart up to: **3 times**
   - ❌ Stop the task if it runs longer than: (kapalı)
   - ✅ If the running task does not end when requested, force it to stop

9. **Conditions Tab:**
   - ✅ Start the task only if the computer is on AC power (isteğe bağlı)
   - ✅ Wake the computer to run this task

10. **OK** → Kullanıcı şifresi isteyebilir (SYSTEM hesabı için gerek olmayabilir)

### Yöntem 3: Komut Satırı (CMD/PowerShell)

**Basit versiyon:**
```powershell
schtasks /create /tn "WinergyBackup" /tr "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat" /sc daily /st 02:00 /ru SYSTEM /rl HIGHEST /f
```

**Log kaydı ile:**
```powershell
schtasks /create /tn "WinergyBackup" /tr "cmd /c C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat >> C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\logs\backup.log 2>&1" /sc daily /st 02:00 /ru SYSTEM /rl HIGHEST /f
```

---

## 📦 YEDEKLEME YAPISI

### Ne Yedeklenir?

**1. Veritabanı (SQL Dump):**
```
winergy_backup_YYYYMMDD_HHMM_database.sql
├── Tüm tablolar (CREATE + INSERT ifadeleri)
├── İlişkiler ve indexler
└── UTF8MB4 karakter seti korumalı
```

**Tablolar:**
- `users` → Kullanıcı hesapları ve roller
- `customers` → Müşteri firma bilgileri, iletişim, adres
- `customer_notes` → Müşteriye ait notlar (genel, anlaşma, önemli, toplantı)
- `jobs` → İş kayıtları ve durum bilgileri
- `job_notes` → İşlere eklenen notlar ve aktiviteler
- `job_files` → Yüklenen dosya bilgileri

**2. Dosyalar (ZIP Arşivi):**
```
winergy_backup_YYYYMMDD_HHMM_files.zip
├── uploads/
│   ├── İş dosyaları
│   ├── Dökümanlar
│   └── Eklenen dosyalar
└── config/db.php
    └── Veritabanı bağlantı bilgileri
```

### Yedekleme Stratejisi

| Özellik | Değer |
|---------|-------|
| Sıklık | Günlük (Her gün saat 02:00) |
| Saklama | Son 7 yedek (7 gün geçmiş) |
| Boyut | ~10-100 MB (veriye göre) |
| Konum | `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\` |
| Otomatik Temizlik | ✅ Eski yedekler otomatik silinir |

### Dosya Adlandırma

```
Format: winergy_backup_YYYYMMDD_HHMM_[type].[ext]

Örnekler:
- winergy_backup_20260211_0200_database.sql
- winergy_backup_20260211_0200_files.zip
- winergy_backup_20260210_0200_database.sql (dün)
- winergy_backup_20260209_0200_database.sql (2 gün önce)
```

---

## ✅ TEST VE KONTROL

### Hızlı Test

**Task'ı hemen çalıştır:**
```powershell
schtasks /run /tn "WinergyBackup"
```

**Çıktı:**
```
SUCCESS: Attempted to run the scheduled task "WinergyBackup".
```

Sonra backups dizinine bakın:
```powershell
dir C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\
```

✅ Yeni dosyalar görüyor musunuz?
- `winergy_backup_YYYYMMDD_HHMM_database.sql` (~1-50 MB)
- `winergy_backup_YYYYMMDD_HHMM_files.zip` (~1-100 MB)

### Durum Kontrolü

**Task durumu:**
```powershell
schtasks /query /tn "WinergyBackup" /fo LIST /v
```

**Özet bilgi:**
```powershell
Get-ScheduledTask -TaskName "WinergyBackup"
```

**Son çalışma:**
```powershell
Get-ScheduledTaskInfo -TaskName "WinergyBackup" | Select LastRunTime, NextRunTime, LastTaskResult
```

**LastTaskResult açıklaması:**
- `0` → Başarılı
- `1` → Hata
- `267009` → Task henüz çalışmadı
- `267011` → Task çalışıyor

### Yedek Doğrulama

**Veritabanı yedeğini test et:**
```powershell
# SQL dosyasının ilk 50 satırını göster
Get-Content "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\winergy_backup_*_database.sql" -First 50 | Select-Object -Last 20
```

✅ Şunları görmelisiniz:
- `CREATE DATABASE IF NOT EXISTS winergy_is_takip`
- `USE winergy_is_takip;`
- `CREATE TABLE` ifadeleri

**Dosya yedeğini test et:**
```powershell
# ZIP içeriğini listele
powershell -Command "Expand-Archive -Path 'C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\winergy_backup_*_files.zip' -DestinationPath 'C:\temp\test_backup' -Force; dir C:\temp\test_backup -Recurse"
```

### Log İnceleme

**Backup logunu görüntüle (eğer kuruluysa):**
```powershell
Get-Content C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\logs\backup.log -Tail 50
```

**Event Viewer:**
```powershell
# Event Viewer'ı aç
eventvwr.msc
```
→ **Task Scheduler** → **History** → "WinergyBackup" arayın

---

## ⚙️ ZAMANLAMA ÖZELLEŞTİRME

### Farklı Saat

**Saat 03:00'e değiştir:**
```powershell
schtasks /change /tn "WinergyBackup" /st 03:00
```

### Her 6 Saatte Bir

```powershell
schtasks /change /tn "WinergyBackup" /ri 360 /du 24:00
```
(360 dakika = 6 saat, 24 saat boyunca tekrarla)

### Sadece Hafta İçi

**Task Scheduler GUI'den:**
1. Task'a sağ tık → Properties
2. **Triggers** sekmesi → Edit
3. **Weekly** seç
4. Sadece **Monday - Friday** işaretle

**PowerShell ile:**
```powershell
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday,Tuesday,Wednesday,Thursday,Friday -At 2am
Set-ScheduledTask -TaskName "WinergyBackup" -Trigger $trigger
```

### Sadece Veritabanı veya Dosya

**Sadece veritabanı:**
```powershell
# Action'ı değiştir
$action = New-ScheduledTaskAction -Execute "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat" -Argument "db" -WorkingDirectory "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi"
Set-ScheduledTask -TaskName "WinergyBackup" -Action $action
```

**Sadece dosyalar:**
```powershell
# Argument'ı "files" yap
$action = New-ScheduledTaskAction -Execute "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat" -Argument "files" -WorkingDirectory "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi"
Set-ScheduledTask -TaskName "WinergyBackup" -Action $action
```

---

## 🔧 SORUN GİDERME

### Task Çalışmıyor

**1. Manuel test:**
```powershell
schtasks /run /tn "WinergyBackup"
```

**2. Backup script'ini doğrudan test:**
```powershell
cd C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts
.\backup.bat
```

✅ Elle çalışıyor mu? → Evet: Task yapılandırması hatalı  
❌ Elle çalışmıyor mu? → Hayır: Script hatası var

**3. XAMPP MySQL çalışıyor mu?**
```powershell
# MySQL servisini kontrol et
Get-Service | Where-Object {$_.DisplayName -like "*mysql*"}
```

✅ Status: Running olmalı

**Değilse başlat:**
```powershell
net start mysql
# veya XAMPP Control Panel'den
```

**4. Yetki sorunu:**
```powershell
# Task'ı SYSTEM hesabıyla çalıştırın
schtasks /change /tn "WinergyBackup" /ru SYSTEM
```

### Yedekler Oluşmuyor

**Backups dizini var mı?**
```powershell
Test-Path "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\"
```

❌ False → Dizin yok, oluştur:
```powershell
mkdir "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups"
```

**MySQL dump çalışıyor mu?**
```powershell
C:\xampp\mysql\bin\mysqldump.exe --version
```

✅ mysqldump Ver X.X görmelisiniz

**Manuel dump test:**
```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root winergy_is_takip > test.sql
```

### Task "Ready" Ama Hiç Çalışmıyor

**History aktif mi?**
```powershell
# Task Scheduler'da history'yi aktifleştir
wevtutil sl Microsoft-Windows-TaskScheduler/Operational /e:true
```

**Task trigger'ları kontrol et:**
```powershell
Get-ScheduledTask -TaskName "WinergyBackup" | Select-Object -ExpandProperty Triggers
```

**Next Run Time gelecekte bir tarih mi?**
```powershell
(Get-ScheduledTaskInfo -TaskName "WinergyBackup").NextRunTime
```

### Hata Kodları

| Kod | Anlamı | Çözüm |
|-----|--------|-------|
| 0 | Başarılı | ✅ Sorun yok |
| 1 | Genel hata | Script'i manuel çalıştırıp hatayı gör |
| 267009 | Task henüz çalışmadı | Normal, ilk çalışmayı bekle |
| 267011 | Task şu an çalışıyor | Bitmesini bekle |
| 0x800710E0 | Operator veya admin interrupted | Task iptal edilmiş |
| 0x80041301 | Instance already running | Zaten çalışıyor, bekle |

**Hata logunu görmek için:**
```powershell
Get-WinEvent -LogName Microsoft-Windows-TaskScheduler/Operational -MaxEvents 20 | Where-Object {$_.Message -like "*WinergyBackup*"} | Format-List
```

---

## 🌐 BAŞKA SİSTEME TAŞIMA

### Task'ı Export Et

```powershell
schtasks /query /tn "WinergyBackup" /xml > C:\temp\WinergyBackup.xml
```

### Başka Bilgisayarda Import Et

**Önce proje dizinini kopyala**, sonra:
```powershell
schtasks /create /tn "WinergyBackup" /xml "C:\temp\WinergyBackup.xml"
```

⚠️ **Dikkat:** XML içindeki yol `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi` olmalı

---

## 🗑️ TASK'I KALDIR

```powershell
schtasks /delete /tn "WinergyBackup" /f
```

Onay:
```
SUCCESS: The scheduled task "WinergyBackup" was successfully deleted.
```

---

## 📚 EK BİLGİLER

### Backup Script Parametreleri

```batch
backup.bat          # Tam yedek (DB + Dosyalar)
backup.bat db       # Sadece veritabanı
backup.bat files    # Sadece dosyalar
```

### Manuel Yedekleme

**Tam yedek:**
```powershell
cd C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts
.\backup.bat
```

**Sadece DB:**
```powershell
.\backup.bat db
```

**Direct mysqldump:**
```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root winergy_is_takip > manual_backup_$(Get-Date -Format 'yyyyMMdd_HHmm').sql
```

### Kritik Dizinler

| Dizin | İçerik | Önemi |
|-------|--------|-------|
| `backups/` | Yedek dosyaları | ⭐⭐⭐ |
| `uploads/` | Kullanıcı dosyaları | ⭐⭐⭐ |
| `logs/` | Sistem logları | ⭐⭐ |
| `config/` | DB yapılandırması | ⭐⭐⭐ |

### Yedekleme Kontrol Listesi

- [ ] Task başarıyla oluşturuldu
- [ ] Task "Ready" durumunda
- [ ] Manuel test başarılı
- [ ] Backups dizininde dosyalar var
- [ ] SQL dosyası açılabiliyor ve içerik doğru
- [ ] ZIP dosyası açılabiliyor ve uploads/ içeriyor
- [ ] Log kaydı çalışıyor (opsiyonel)
- [ ] Event Viewer'da hata yok
- [ ] NextRunTime gelecek bir tarih

---

## 📞 DESTEK

### Sorun mu yaşıyorsunuz?

1. **Manuel backup.bat testi** → Çalışıyor mu?
2. **XAMPP MySQL** → Açık mı?
3. **Yetki** → Task SYSTEM hesabında mı?
4. **Event Viewer** → Hata mesajları var mı?

### Yararlı Komutlar

```powershell
# Tüm task bilgilerini göster
schtasks /query /tn "WinergyBackup" /fo LIST /v

# Task history
Get-WinEvent -LogName Microsoft-Windows-TaskScheduler/Operational | Where-Object {$_.Message -like "*WinergyBackup*"}

# XAMPP MySQL durumu
netstat -ano | findstr :3306

# Disk alanı kontrolü
Get-PSDrive C | Select-Object Used,Free
```

---

## ✅ ÖNEMLİ HATIRLATMALAR

⚠️ **Bilgisayar kapalıysa yedek alınamaz**
- Sunucu: 7/24 açık → Sorun yok
- PC: Geceleyin kapalı → Zamanı değiştirin veya açık bırakın

✅ **Task kurulumu başarılıysa:**
- Artık otomatik yedek alınıyor
- Manuel müdahale gereksiz
- Her gün saat 02:00'de çalışıyor
- Son 7 yedek saklanıyor

📊 **Yedek boyutları:**
- Küçük proje: ~1-10 MB
- Orta proje: ~10-50 MB
- Büyük proje: ~50-500 MB

🔄 **Düzenli kontrol:**
- Haftada 1: Yedeklerin alındığını kontrol et
- Ayda 1: Eski yedeği geri yükleme testi yap
- 3 Ayda 1: Yedekleri harici diske kopyala

---

**Son Güncelleme:** Şubat 2026  
**Uyumlu Sistemler:** Windows 10/11, Windows Server 2016+  
**Gereksinimler:** XAMPP, PowerShell 5.0+, MySQL 5.7+
