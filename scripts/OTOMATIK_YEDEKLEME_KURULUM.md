# ====================================================================
# Winergy İş Takip Sistemi - Otomatik Yedekleme Kurulum Kılavuzu
# ====================================================================

## MEVCUT DURUM
❌ Otomatik yedekleme YOK - Manuel çalıştırmanız gerekiyor
✅ Backup scripti hazır ve çalışıyor

## OTOMATIK YEDEKLEME KURULUMU

### Yöntem 1: Task Scheduler XML Import (EN KOLAY)

1. **Yönetici olarak PowerShell açın** (sağ tık → Run as administrator)

2. Aşağıdaki komutu çalıştırın:
```powershell
schtasks /create /tn "WinergyBackup" /xml "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\WinergyBackup.xml"
```

3. Başarılı mesajını görmelisiniz:
```
SUCCESS: The scheduled task "WinergyBackup" has successfully been created.
```

4. **Kontrol edin:**
```powershell
schtasks /query /tn "WinergyBackup" /fo LIST /v
```

### Yöntem 2: Manuel Kurulum (Grafik Arayüz)

1. **Windows Tuşu + R** → `taskschd.msc` yazın

2. Sağ tarafta **"Create Basic Task"** tıklayın

3. **Name:** `WinergyBackup`
   **Description:** Winergy günlük otomatik yedekleme

4. **Trigger:** "Daily" seçin
   - **Start:** Bugün
   - **Start time:** `02:00:00 AM`
   - **Recur every:** 1 days

5. **Action:** "Start a program" seçin
   - **Program/script:** `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat`
   - **Start in:** `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi`

6. **Finish** tıklayın

7. Task'a sağ tık → **Properties** → **Settings** sekmesi:
   - ✅ "Allow task to be run on demand"
   - ✅ "Run task as soon as possible after a scheduled start is missed"
   - ✅ "If the task fails, restart every:" 10 minutes
   - ❌ "Stop the task if it runs longer than:" 1 hour

### Yöntem 3: PowerShell (Tek Komut)

Yönetici PowerShell'de:
```powershell
$action = New-ScheduledTaskAction -Execute "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat" -WorkingDirectory "C:\xampp\htdocs\Winergy_Is_Takip_Sistemi"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "WinergyBackup" -Description "Winergy günlük otomatik yedekleme" -Settings $settings
```

## KURULUMU TEST EDIN

### Manuel Test (hemen çalıştır):
```powershell
schtasks /run /tn "WinergyBackup"
```

### Durumu Kontrol Et:
```powershell
schtasks /query /tn "WinergyBackup"
```

### Son Çalışma Zamanını Gör:
```powershell
Get-ScheduledTask -TaskName "WinergyBackup" | Get-ScheduledTaskInfo
```

## ZAMANLAMA DEĞİŞTİRME

### Her 6 saatte bir:
```powershell
schtasks /change /tn "WinergyBackup" /ri 360
```

### Sadece hafta içi:
Task Scheduler GUI'den:
- Task'a sağ tık → Properties
- Triggers sekmesi → Edit
- Advanced settings → Repeat task every: 1 day
- Sadece Pazartesi-Cuma seç

### Farklı saat:
```powershell
schtasks /change /tn "WinergyBackup" /st 03:00
```

## YEDEK STRATEJİSİ

Kurulum sonrası otomatik olarak:
- ✅ Her gece saat 02:00'de yedek alınır
- ✅ Veritabanı + Dosyalar yedeklenir
- ✅ Son 7 yedek saklanır, eskiler silinir
- ✅ Bilgisayar kapalıysa açılınca çalışır

## LOG KONTROLÜ

Backup log dosyası oluşturmak için:

**backup.bat dosyasını şöyle çalıştırın:**
```powershell
schtasks /create /tn "WinergyBackup" /tr "cmd /c C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat >> C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\logs\backup.log 2>&1" /sc daily /st 02:00
```

Log görüntüleme:
```powershell
Get-Content C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\logs\backup.log -Tail 50
```

## TASK'I SİLME

```powershell
schtasks /delete /tn "WinergyBackup" /f
```

## SORUN GİDERME

### Task çalışmıyor?

1. **Event Viewer** kontrol edin:
   - Win + R → `eventvwr.msc`
   - Task Scheduler → History

2. **Manuel test yapın:**
```powershell
schtasks /run /tn "WinergyBackup"
```

3. **İzinleri kontrol edin:**
   - backup.bat dosyasına çift tıklayarak manuel çalışıp çalışmadığını test edin

### Yedekler alınmıyor?

```powershell
# Son çalışmayı kontrol et
Get-ScheduledTaskInfo -TaskName "WinergyBackup"

# Task durumunu kontrol et
Get-ScheduledTask -TaskName "WinergyBackup" | Select State,LastRunTime,NextRunTime
```

## BAŞKA BİLGİSAYARA TAŞIMA

XML dosyasını kullanarak başka bilgisayara taşıyabilirsiniz:
```powershell
# Export
schtasks /query /tn "WinergyBackup" /xml > WinergyBackup.xml

# Import (başka PC'de)
schtasks /create /tn "WinergyBackup" /xml WinergyBackup.xml
```

## ÖNEMLİ NOTLAR

⚠️ **Bilgisayar kapalıysa yedek alınamaz!**
   - Sunucu kullanıyorsanız sorun yok
   - PC kullanıyorsanız gece açık bırakın veya zamanı değiştirin

✅ **Task başarıyla kurulduysa:**
   - Artık otomatik yedek alınıyor
   - Manuel script çalıştırmanıza gerek yok
   - Yedekler: `C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\backups\`

📧 **E-posta bildirimi eklemek için:**
   - backup.bat içine mail gönderme kodu ekleyin
   - Veya PowerShell ile Send-MailMessage kullanın

---

**Kurulum sonrası test etmeyi unutmayın!**
