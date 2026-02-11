@echo off
REM =========================================================================
REM Winergy İş Takip Sistemi - Windows Backup Script
REM 
REM Kullanım:
REM   backup.bat                    # Tam yedekleme
REM   backup.bat db                 # Sadece veritabanı
REM   backup.bat files              # Sadece dosyalar
REM
REM Windows Task Scheduler için:
REM   Gün Ayarları: Her gün 02:00
REM   Program: C:\xampp\htdocs\Winergy_Is_Takip_Sistemi\scripts\backup.bat
REM =========================================================================

SETLOCAL EnableDelayedExpansion

REM Yapılandırma
SET PROJECT_DIR=C:\xampp\htdocs\Winergy_Is_Takip_Sistemi
SET BACKUP_DIR=%PROJECT_DIR%\backups
SET DB_NAME=winergy_is_takip
SET DB_USER=root
SET DB_PASS=
SET MYSQL_BIN=C:\xampp\mysql\bin

REM Tarih damgası (YYYYMMDD_HHMMSS formatında)
for /f "tokens=1-4 delims=/ " %%a in ('date /t') do (set mydate=%%c%%b%%a)
for /f "tokens=1-2 delims=: " %%a in ('time /t') do (set mytime=%%a%%b)
SET TIMESTAMP=%mydate%_%mytime::=%
SET BACKUP_NAME=winergy_backup_%TIMESTAMP%

REM Backup dizinini oluştur
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

echo ============================================
echo Winergy Backup Script - Windows
echo ============================================
echo Başlangıç: %date% %time%
echo.

REM Parametreye göre yedekleme tipi
SET ACTION=%1
if "%ACTION%"=="" SET ACTION=full

REM Veritabanı Yedekleme
if "%ACTION%"=="full" (
    echo [1/2] Veritabanı yedekleniyor...
    "%MYSQL_BIN%\mysqldump.exe" -u %DB_USER% --password=%DB_PASS% %DB_NAME% > "%BACKUP_DIR%\%BACKUP_NAME%_database.sql"
    
    if errorlevel 1 (
        echo HATA: Veritabanı yedeği alınamadı!
        goto :error
    )
    echo OK - Veritabanı yedeği tamamlandı.
)

if "%ACTION%"=="db" (
    echo Sadece veritabanı yedekleniyor...
    "%MYSQL_BIN%\mysqldump.exe" -u %DB_USER% --password=%DB_PASS% %DB_NAME% > "%BACKUP_DIR%\%BACKUP_NAME%_database.sql"
    
    if errorlevel 1 (
        echo HATA: Veritabanı yedeği alınamadı!
        goto :error
    )
    echo OK - Veritabanı yedeği tamamlandı.
    goto :success
)

REM Dosya Yedekleme
if "%ACTION%"=="full" (
    echo [2/2] Dosyalar yedekleniyor...
    
    cd /d "%PROJECT_DIR%"
    powershell -Command "Compress-Archive -Path 'uploads','config\db.php' -DestinationPath '%BACKUP_DIR%\%BACKUP_NAME%_files.zip' -Force" 2>nul
    
    if exist "%BACKUP_DIR%\%BACKUP_NAME%_files.zip" (
        echo OK - Dosya yedeği tamamlandı.
    ) else (
        echo UYARI: Dosya yedeği alınamadı!
    )
)

if "%ACTION%"=="files" (
    echo Sadece dosyalar yedekleniyor...
    
    cd /d "%PROJECT_DIR%"
    powershell -Command "Compress-Archive -Path 'uploads','config\db.php' -DestinationPath '%BACKUP_DIR%\%BACKUP_NAME%_files.zip' -Force" 2>nul
    
    if exist "%BACKUP_DIR%\%BACKUP_NAME%_files.zip" (
        echo OK - Dosya yedeği tamamlandı.
    ) else (
        echo HATA: Dosya yedeği alınamadı!
        goto :error
    )
    
    goto :success
)

REM Eski yedekleri temizle (son 7 yedek kalsın)
echo.
echo Eski yedekler temizleniyor...
for /f "skip=7 delims=" %%F in ('dir /b /o-d "%BACKUP_DIR%\winergy_backup_*_database.sql" 2^>nul') do (
    del "%BACKUP_DIR%\%%F"
    echo Silindi: %%F
)

for /f "skip=7 delims=" %%F in ('dir /b /o-d "%BACKUP_DIR%\winergy_backup_*_files.zip" 2^>nul') do (
    del "%BACKUP_DIR%\%%F"
    echo Silindi: %%F
)

:success
echo.
echo ============================================
echo BAŞARILI! Yedekleme tamamlandı.
echo Backup adı: %BACKUP_NAME%
echo Bitiş: %date% %time%
echo ============================================
exit /b 0

:error
echo.
echo ============================================
echo HATA! Yedekleme başarısız.
echo ============================================
exit /b 1
