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
require_once 'includes/csrf.php'; 

// ID kontrolü
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: kullanicilar.php");
    exit;
}

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: kullanicilar.php?error=notfound");
    exit;
}

$error = "";

// Form gönderildiğinde güncelle
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $change_password = !empty($_POST['new_password']);
    
    // Validasyon
    if (empty($name) || empty($email)) {
        $error = "Ad ve e-posta alanları zorunludur.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta adresi girin.";
    } else {
        // E-posta kontrolü (başkası kullanıyor mu?)
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        
        if ($check->fetch()) {
            $error = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.";
        } else {
            try {
                // Şifre değişikliği varsa
                if ($change_password) {
                    $new_password = $_POST['new_password'];
                    $new_password_confirm = $_POST['new_password_confirm'];
                    
                    if (strlen($new_password) < 6) {
                        $error = "Şifre en az 6 karakter olmalıdır.";
                    } elseif ($new_password !== $new_password_confirm) {
                        $error = "Şifreler eşleşmiyor.";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $hashed_password, $role, $is_active, $id]);
                    }
                } else {
                    // Şifre değişikliği yok
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role, $is_active, $id]);
                }
                
                if (!$error) {
                    header("Location: kullanicilar.php?updated=1");
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Güncelleme hatası: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php'; 

$role_tr = [
    'admin' => 'Yönetici',
    'operasyon' => 'Operasyon',
    'danisman' => 'Danışman'
];
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold" style="color: var(--dt-sec-color);">
                    <i class="bi bi-pencil-square"></i> Kullanıcı Bilgilerini Düzenle
                </h3>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['name']); ?></p>
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
        
        <?php if($id == $_SESSION['user_id']): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Kendi hesabınızı düzenliyorsunuz.
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <div class="row">
                        <!-- Ad Soyad -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Ad Soyad *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" 
                                   required>
                        </div>
                        
                        <!-- E-Posta -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">E-Posta Adresi *</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   required>
                        </div>
                        
                        <!-- Rol -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Kullanıcı Rolü *</label>
                            <select name="role" class="form-select" required>
                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>
                                    🔴 Yönetici
                                </option>
                                <option value="operasyon" <?php echo $user['role'] == 'operasyon' ? 'selected' : ''; ?>>
                                    🔵 Operasyon
                                </option>
                                <option value="danisman" <?php echo $user['role'] == 'danisman' ? 'selected' : ''; ?>>
                                    🟢 Danışman
                                </option>
                            </select>
                        </div>
                        
                        <!-- Durum -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold d-block">Hesap Durumu</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                       <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    <strong><?php echo $user['is_active'] ? 'Aktif' : 'Pasif'; ?></strong>
                                </label>
                            </div>
                            <small class="text-muted">Pasif kullanıcılar giriş yapamaz</small>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Şifre Değiştirme (Opsiyonel) -->
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-lock"></i> Şifre Değiştir (Opsiyonel)
                    </h6>
                    <p class="text-muted small">Şifre değiştirmek istemiyorsanız aşağıdaki alanları boş bırakın.</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" 
                                   placeholder="En az 6 karakter" 
                                   minlength="6" 
                                   autocomplete="new-password">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Yeni Şifre Tekrar</label>
                            <input type="password" name="new_password_confirm" class="form-control" 
                                   placeholder="Şifreyi tekrar girin" 
                                   minlength="6" 
                                   autocomplete="new-password">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Kullanıcı İstatistikleri -->
                    <?php 
                    // İstatistikleri bir kere çek ve değişkenlere kaydet
                    $assigned_jobs_count = 0;
                    $created_jobs_count = 0;
                    $updated_jobs_count = 0;
                    
                    try {
                        $stmt_stats = $db->prepare("SELECT COUNT(*) FROM jobs WHERE assigned_user_id = ?");
                        $stmt_stats->execute([$id]);
                        $assigned_jobs_count = $stmt_stats->fetchColumn();
                        
                        $stmt_stats = $db->prepare("SELECT COUNT(*) FROM jobs WHERE created_by = ?");
                        $stmt_stats->execute([$id]);
                        $created_jobs_count = $stmt_stats->fetchColumn();
                        
                        $stmt_stats = $db->prepare("SELECT COUNT(*) FROM jobs WHERE updated_by = ?");
                        $stmt_stats->execute([$id]);
                        $updated_jobs_count = $stmt_stats->fetchColumn();
                    } catch (Exception $e) {
                        // Hata durumunda varsayılan değerler kalır (0)
                    }
                    ?>
                    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                        <div class="card-header border-0" style="background: transparent;">
                            <h6 class="mb-0 fw-bold" style="color: var(--dt-sec-color);">
                                <i class="bi bi-graph-up"></i> Kullanıcı İstatistikleri
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="p-3 bg-white rounded shadow-sm">
                                        <h3 class="mb-1" style="color: var(--dt-pri-color);"><?php echo $assigned_jobs_count; ?></h3>
                                        <small class="text-muted">Atanan İşler</small>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="p-3 bg-white rounded shadow-sm">
                                        <h3 class="mb-1 text-success"><?php echo $created_jobs_count; ?></h3>
                                        <small class="text-muted">Oluşturduğu İşler</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-white rounded shadow-sm">
                                        <h3 class="mb-1 text-info"><?php echo $updated_jobs_count; ?></h3>
                                        <small class="text-muted">Güncellediği İşler</small>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="bi bi-calendar-check"></i> Kayıt Tarihi: 
                                    <strong><?php echo $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : '-'; ?></strong>
                                </small>
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
                            <i class="bi bi-check-circle"></i> Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
