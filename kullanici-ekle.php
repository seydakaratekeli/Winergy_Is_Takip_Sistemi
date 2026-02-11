<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Sadece admin erişebilir
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

$error = "";
$success = ""; //silebilirsin

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasyon
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Ad, e-posta ve şifre alanları zorunludur.";
    } elseif (strlen($password) < 6) {
        $error = "Şifre en az 6 karakter olmalıdır.";
    } elseif ($password !== $password_confirm) {
        $error = "Şifreler eşleşmiyor.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta adresi girin.";
    } else {
        // E-posta kontrolü
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->fetch()) {
            $error = "Bu e-posta adresi zaten kullanılıyor.";
        } else {
            // Şifreyi hashle
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $email, $hashed_password, $role, $is_active])) {
                    log_activity('Kullanıcı Eklendi', "Yeni Kullanıcı: $name (E-Posta: $email, Rol: $role)", 'SUCCESS');
                    header("Location: kullanicilar.php?added=1");
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Kullanıcı eklenirken hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold" style="color: var(--dt-sec-color);">
                    <i class="bi bi-person-plus-fill"></i> Yeni Kullanıcı Ekle
                </h3>
                <p class="text-muted mb-0">Sisteme yeni kullanıcı kaydı oluşturun</p>
            </div>
            <a href="kullanicilar.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Geri
            </a>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" autocomplete="off">
                    <?php echo csrf_input(); ?>
                    <div class="row">
                        <!-- Ad Soyad -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Ad Soyad *</label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="Örn: Ahmet Yılmaz" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <!-- E-Posta -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">E-Posta Adresi *</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="ornek@winergy.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   required>
                            <small class="text-muted">Sisteme giriş için kullanılacak</small>
                        </div>
                        
                        <!-- Rol -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Kullanıcı Rolü *</label>
                            <select name="role" class="form-select" required>
                                <option value="">-- Rol Seçin --</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>
                                    🔴 Yönetici - Tüm yetkilere sahip
                                </option>
                                <option value="operasyon" <?php echo (isset($_POST['role']) && $_POST['role'] == 'operasyon') ? 'selected' : ''; ?>>
                                    🔵 Operasyon - İş ve müşteri yönetimi
                                </option>
                                <option value="danisman" <?php echo (isset($_POST['role']) && $_POST['role'] == 'danisman') ? 'selected' : ''; ?>>
                                    🟢 Danışman - İş görüntüleme ve not ekleme
                                </option>
                            </select>
                        </div>
                        
                        <!-- Şifre -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Şifre *</label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="En az 6 karakter" 
                                   minlength="6" 
                                   autocomplete="new-password"
                                   required>
                            <small class="text-muted">Minimum 6 karakter</small>
                        </div>
                        
                        <!-- Şifre Tekrar -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Şifre Tekrar *</label>
                            <input type="password" name="password_confirm" class="form-control" 
                                   placeholder="Şifreyi tekrar girin" 
                                   minlength="6" 
                                   autocomplete="new-password"
                                   required>
                        </div>
                        
                        <!-- Durum -->
                        <div class="col-md-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                       <?php echo (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="is_active">
                                    Kullanıcı Aktif
                                </label>
                                <small class="d-block text-muted">Pasif kullanıcılar sisteme giriş yapamaz</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    
                    
                    <!-- Buttons -->
                    <div class="d-flex justify-content-between">
                        <a href="kullanicilar.php" class="btn btn-light">
                            <i class="bi bi-x-circle"></i> İptal
                        </a>
                        <button type="submit" class="btn btn-winergy px-4">
                            <i class="bi bi-check-circle"></i> Kullanıcı Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
