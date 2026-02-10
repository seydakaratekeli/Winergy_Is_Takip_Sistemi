<?php
$host = "localhost";
$dbname = "winergy_is_takip";
$username = "root";
$password = ""; 

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->exec("SET NAMES utf8mb4");
    $db->exec("SET CHARACTER SET utf8mb4");
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>