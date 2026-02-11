<?php
session_start();
require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/csrf.php';

// Zaten giriş yapılmışsa doğrudan ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. CSRF Token Kontrolü
    if (!isset($_POST['csrf_token']) || !csrf_validate_token($_POST['csrf_token'])) {
        csrf_error();
    }
    
    // Veri temizliği: E-postadaki gereksiz boşlukları temizleyelim
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 2. Kullanıcıyı sorgula (Sadece gerekli sütunları çekmek performansı artırır)
    $stmt = $db->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Kullanıcı var mı ve Şifre doğru mu? (Tek blokta kontrol)
    if ($user && password_verify($password, $user['password'])) {
        
        // 4. BAŞARILI GİRİŞ: Oturum güvenliğini yenile (Session Fixation koruması)
        session_regenerate_id(true); 

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role']; // admin, operasyon, danisman 
        
        // Başarılı giriş günlüğünü kaydet
        log_activity('Giriş Yapıldı', "Kullanıcı: {$user['name']} ({$user['role']})", 'SUCCESS');
        
        header("Location: index.php");
        exit;
    } else {
        // 5. BAŞARISIZ GİRİŞ: Generic hata mesajı vererek bilgi sızmasını önle
        $error = "Hatalı e-posta veya şifre.";
        log_activity('Başarısız Giriş Denemesi', "E-posta: $email", 'WARNING');
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winergy Technologies - Giriş Yap</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    
    <style>
        /* Login sayfası için body override */
        html, body { 
            height: 100%;
        }
        
        body { 
            background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
            display: flex !important;
            flex-direction: column !important;
            align-items: center; 
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .login-header {
            background: #ffffff;
            padding: 3rem 2rem 2rem;
            text-align: center;
            border-bottom: 4px solid #14b8a6;
        }
        
        .login-header img {
            max-width: 280px;
            margin-bottom: 1.5rem;
        }
        
        .login-header h5 {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .login-body {
            padding: 2.5rem;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.875rem 1.125rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.15);
        }
        
        .btn-login {
            background: #14b8a6;
            color: white;
            font-weight: 800;
            padding: 1rem;
            border-radius: 10px;
            border: none;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
        }
        
        .btn-login:hover {
            background: #0d9488;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(20, 184, 166, 0.4);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: #1f2937;
        }
        
        .login-footer a {
            color: #14b8a6;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <!-- Winergy Logo -->
            <img src="assets/images/winergy-logo.png" alt="Winergy Technologies" style="max-width: 280px; margin-bottom: 1rem;">
            <h5>İş Takip Sistemi</h5>
        </div>

        <div class="login-body">
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_input(); ?>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark">
                        <i class="bi bi-envelope-fill me-2" style="color: #14b8a6;"></i>E-Posta Adresi
                    </label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="kullanici@winergytech.com" 
                           required 
                           autofocus>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">
                        <i class="bi bi-lock-fill me-2" style="color: #14b8a6;"></i>Şifre
                    </label>
                    <input type="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="••••••••" 
                           required>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sisteme Giriş Yap
                </button>
            </form>
        </div>
    </div>
    
    <div class="login-footer">
        <p class="mb-2">
            <i class="bi bi-telephone-fill me-2"></i>
            <a href="tel:03123956828">0312 395 68 28</a>
        </p>
        <p class="small">
            <a href="https://winergytechnologies.com" target="_blank">winergytechnologies.com</a>
        </p>
        <p class="small opacity-75">
            &copy; <?php echo date('Y'); ?> Winergy Technologies | Tüm Hakları Saklıdır
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>