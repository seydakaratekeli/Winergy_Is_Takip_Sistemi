<?php
/**
 * CSRF (Cross-Site Request Forgery) Koruma Fonksiyonları
 * 
 * CSRF NEDİR?
 * Kötü niyetli bir sitenin, sizin oturumunuzu kullanarak
 * sisteminizde istek göndermesine dayanır.
 * 
 * Örnek: Bir kullanıcı sisteme giriş yapmış, sonra başka bir
 * siteye giriyor. O site gizlice sizin sisteminize silme isteği
 * gönderebilir. CSRF token bunu engeller.
 */

/**
 * CSRF Token Oluştur
 * Her form için benzersiz bir güvenlik token'ı üretir
 */
function csrf_generate_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 32 byte rastgele, güvenli token
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    
    return $token;
}

/**
 * CSRF Token Doğrula
 * Formdan gelen token ile session'daki token'ı karşılaştırır
 * 
 * @param string $token - Formdan gelen token
 * @return bool - Token geçerli mi?
 */
function csrf_validate_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Token var mı?
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Token eşleşiyor mu? (timing attack'a karşı hash_equals kullanıyoruz)
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    
    // Kullan-at prensibi: Kullanıldıktan sonra sil
    // (Opsiyonel - eğer aynı formda birden fazla submit istiyorsanız bunu kaldırın)
    unset($_SESSION['csrf_token']);
    
    return $valid;
}

/**
 * CSRF Input Field Oluştur
 * Form içine eklenecek hidden input
 * 
 * Kullanım:
 * <form method="POST">
 *     <?php echo csrf_input(); ?>
 *     <!-- Diğer form alanları -->
 * </form>
 */
function csrf_input() {
    $token = csrf_generate_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * CSRF Hatası Göster ve Programı Durdur
 */
function csrf_error() {
    http_response_code(403);
    die('
        <div style="font-family: Arial; max-width: 600px; margin: 100px auto; text-align: center;">
            <h1 style="color: #dc3545;">🚫 Güvenlik Hatası</h1>
            <p style="color: #666;">Geçersiz istek tespit edildi.</p>
            <p style="color: #999; font-size: 14px;">
                Bu hata, formun süresi dolduğunda veya güvenlik nedenleriyle oluşabilir.
            </p>
            <a href="javascript:history.back()" style="display: inline-block; 
                   margin-top: 20px; padding: 10px 20px; 
                   background: #14b8a6; color: white; 
                   text-decoration: none; border-radius: 5px;">
                Geri Dön
            </a>
        </div>
    ');
}

/**
 * KULLANIM ÖRNEĞİ:
 * 
 * // Form sayfasında (örn: kullanici-sil.php)
 * require_once 'includes/csrf.php';
 * 
 * <form method="POST">
 *     <?php echo csrf_input(); ?>
 *     <button type="submit">Sil</button>
 * </form>
 * 
 * // Form işleyicide (POST alındığında)
 * if ($_SERVER['REQUEST_METHOD'] == 'POST') {
 *     if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
 *         csrf_error(); // Geçersiz token - işlemi durdur
 *     }
 *     
 *     // Token geçerli - işleme devam et
 *     // ... silme işlemi
 * }
 */
?>
