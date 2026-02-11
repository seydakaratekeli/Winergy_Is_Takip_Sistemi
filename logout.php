<?php
session_start();
require_once 'includes/logger.php';

// Log aktivitesi
if (isset($_SESSION['user_name'])) {
    log_activity('Çıkış Yapıldı', "Kullanıcı: {$_SESSION['user_name']}", 'INFO');
}

// Session verilerini temizle
$_SESSION = array();

// Session cookie'sini sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Session'ı yok et
session_destroy();

header("Location: login.php");
exit;