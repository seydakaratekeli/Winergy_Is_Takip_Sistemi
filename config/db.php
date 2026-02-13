<?php

$host = "localhost";
$dbname = "winergy_is_takip_db";
$username = "root";
$password = ""; 

try {
    
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Teknik hatayı log dosyasına kaydet
    require_once __DIR__ . '/../includes/logger.php';
    log_error("Veritabanı bağlantı hatası", ['message' => $e->getMessage()]);
    
    
    die("Sistem şu an hizmet veremiyor. Lütfen teknik ekibe ulaşın veya daha sonra tekrar deneyin.");
}
?>