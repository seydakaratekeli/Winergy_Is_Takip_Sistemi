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
require_once 'includes/logger.php';

// ID kontrolü
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: kullanicilar.php");
    exit;
}

// Kendi kendini silmeyi engelle
if ($id == $_SESSION['user_id']) {
    header("Location: kullanicilar.php?error=self_delete");
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

// Kullanıcıya ait iş sayısını kontrol et
$job_count_stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_user_id = ? OR created_by = ? OR updated_by = ?");
$job_count_stmt->execute([$id, $id, $id]);
$job_count = $job_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Müşteri sayısı
$customer_count_stmt = $db->prepare("SELECT COUNT(*) as count FROM customers WHERE created_by = ? OR updated_by = ?");
$customer_count_stmt->execute([$id, $id]);
$customer_count = $customer_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Not sayısı
$note_count_stmt = $db->prepare("SELECT COUNT(*) as count FROM job_notes WHERE user_id = ?");
$note_count_stmt->execute([$id]);
$note_count = $note_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Silme işlemi onaylandıysa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error(); // Geçersiz token - işlemi durdur
    }
    
    try {
        // Kullanıcıyı sil
        // NOT: Foreign key'ler SET NULL olduğu için ilişkili kayıtlar korunur
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            log_activity('Kullanıcı Silindi', "ID: $id, Ad: {$user['name']}, İlişkili kayıt: İş=$job_count, Müşteri=$customer_count, Not=$note_count", 'WARNING');
            header("Location: kullanicilar.php?deleted=1");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Silme işlemi sırasında hata: " . $e->getMessage();
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
    <div class="col-md-7">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle-fill"></i> Kullanıcı Silme Onayı
                </h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <strong>Dikkat!</strong> Bu işlem geri alınamaz. Kullanıcı kalıcı olarak silinecektir.
                </div>
                
                <h6 class="fw-bold mb-3">Silinecek Kullanıcı Bilgileri:</h6>
                
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th width="30%">Ad Soyad</th>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>E-Posta</th>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Rol</th>
                            <td>
                                <?php 
                                $role_colors = [
                                    'admin' => 'danger',
                                    'operasyon' => 'primary',
                                    'danisman' => 'info'
                                ];
                                $color = $role_colors[$user['role']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo $role_tr[$user['role']] ?? $user['role']; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Durum</th>
                            <td>
                                <?php if($user['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Kayıt Tarihi</th>
                            <td><?php echo $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : '-'; ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- İlişkili Kayıtlar -->
                <div class="alert alert-info">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle"></i> İlişkili Kayıtlar</h6>
                    <ul class="mb-0">
                        <li><strong><?php echo $job_count; ?></strong> iş kaydıyla ilişkili (atanan, oluşturan veya güncelleyen)</li>
                        <li><strong><?php echo $customer_count; ?></strong> müşteri kaydıyla ilişkili</li>
                        <li><strong><?php echo $note_count; ?></strong> not kaydı</li>
                    </ul>
                    <small class="d-block mt-2 text-muted">
                        <i class="bi bi-shield-check"></i> Bu kullanıcı silindiğinde ilişkili kayıtlar korunur, sadece kullanıcı referansı kaldırılır.
                    </small>
                </div>
                
                <form method="POST" onsubmit="return confirm('Bu kullanıcıyı kalıcı olarak silmek istediğinizden emin misiniz?');">
                    <?php echo csrf_input(); // CSRF Token ?>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="kullanicilar.php" class="btn btn-light">
                            <i class="bi bi-arrow-left"></i> İptal Et
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger px-4">
                            <i class="bi bi-trash-fill"></i> Kullanıcıyı Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Alternatif: Pasif Yapma Önerisi -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-lightbulb"></i> Alternatif Öneri</h6>
                <p class="mb-2 small text-muted">
                    Kullanıcıyı silmek yerine <strong>pasif</strong> yapabilirsiniz. 
                    Bu sayede kullanıcı sisteme giriş yapamaz ancak geçmiş kayıtlar korunur.
                </p>
                <a href="kullanici-duzenle.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> Kullanıcıyı Düzenle (Pasif Yap)
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
