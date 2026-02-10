<?php
session_start();

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