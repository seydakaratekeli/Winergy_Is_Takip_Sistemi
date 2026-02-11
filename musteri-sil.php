<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php'; 
require_once 'includes/csrf.php'; // CSRF Koruması
require_once 'includes/logger.php';

// ID kontrolü
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: musteriler.php");
    exit;
}

// Müşteri bilgilerini ve ilişkili iş sayısını çek
$stmt = $db->prepare("SELECT c.*, COUNT(j.id) as job_count 
                      FROM customers c 
                      LEFT JOIN jobs j ON c.id = j.customer_id 
                      WHERE c.id = ? 
                      GROUP BY c.id");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: musteriler.php?error=notfound");
    exit;
}

// Silme işlemi onaylandıysa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error(); // Geçersiz token - işlemi durdur
    }
    
    $force_delete = isset($_POST['force_delete']) ? true : false;
    
    try {
        // Eğer müşteriye ait işler varsa ve force delete seçilmediyse
        if ($customer['job_count'] > 0 && !$force_delete) {
            $error = "Bu müşteriye ait {$customer['job_count']} adet iş kaydı bulunmaktadır. Silmek için 'Yine de Sil' seçeneğini işaretleyin.";
        } else {
            // Önce müşteriye ait işleri sil (cascade)
            if ($customer['job_count'] > 0) {
                $db->prepare("DELETE FROM job_notes WHERE job_id IN (SELECT id FROM jobs WHERE customer_id = ?)")->execute([$id]);
                $db->prepare("DELETE FROM job_files WHERE job_id IN (SELECT id FROM jobs WHERE customer_id = ?)")->execute([$id]);
                $db->prepare("DELETE FROM jobs WHERE customer_id = ?")->execute([$id]);
            }
            
            // Müşteri notlarını sil
            $db->prepare("DELETE FROM customer_notes WHERE customer_id = ?")->execute([$id]);
            
            // Müşteriyi sil
            $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
            if ($stmt->execute([$id])) {
                log_activity('Müşteri Silindi', "Silinen Müşteri: {$customer['name']} (ID: {$customer['id']})", 'SUCCESS');
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Silme hatası: " . $e->getMessage();
    }
}

include 'includes/header.php'; 
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-exclamation-triangle"></i> Müşteri Silme Onayı
                </h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="card border-danger mb-3">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-exclamation-circle"></i> Hata</h6>
                        </div>
                        <div class="card-body">
                            <?php echo $error; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card border-warning mb-3">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle"></i> Müşteri Bilgileri</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Aşağıdaki müşteriyi silmek üzeresiniz:</p>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="fw-bold" width="150">Müşteri Adı:</td>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            </tr>
                            <?php if($customer['contact_name']): ?>
                            <tr>
                                <td class="fw-bold">İlgili Kişi:</td>
                                <td><?php echo htmlspecialchars($customer['contact_name']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if($customer['phone']): ?>
                            <tr>
                                <td class="fw-bold">Telefon:</td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="fw-bold">İlişkili İş Sayısı:</td>
                                <td>
                                    <span class="badge <?php echo $customer['job_count'] > 0 ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo $customer['job_count']; ?> iş
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($customer['job_count'] > 0): ?>
                    <div class="card border-danger mb-3">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle"></i> Dikkat</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Bu müşteriye ait <strong><?php echo $customer['job_count']; ?> adet iş kaydı</strong> bulunmaktadır.</li>
                                <li>Bu işlere ait <strong>notlar, dosyalar ve tüm veriler silinecektir!</strong></li>
                                <li>Bu işlem geri alınamaz.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- İlişkili işleri göster -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold">Silinecek İşler:</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $jobs_stmt = $db->prepare("SELECT id, title, status FROM jobs WHERE customer_id = ? ORDER BY created_at DESC");
                            $jobs_stmt->execute([$id]);
                            $jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach($jobs as $j): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($j['title']); ?></span>
                                        <span class="badge bg-secondary"><?php echo $j['status']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('Bu müşteriyi ve tüm ilişkili verileri kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem GERİ ALINAMAZ!');">
                    <?php echo csrf_input(); // CSRF Token ?>
                    <?php if ($customer['job_count'] > 0): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="force_delete" id="force_delete" required>
                            <label class="form-check-label fw-bold text-danger" for="force_delete">
                                Evet, <?php echo $customer['job_count']; ?> işi ve tüm verilerini silmek istiyorum
                            </label>
                        </div>
                    <?php endif; ?>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="musteriler.php" class="btn btn-secondary px-4">
                            <i class="bi bi-x-circle"></i> İptal
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger px-4">
                            <i class="bi bi-trash"></i> Müşteriyi Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-info mt-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Alternatif</h6>
            </div>
            <div class="card-body">
                Müşteriyi silmek yerine, <a href="musteri-duzenle.php?id=<?php echo $id; ?>" class="fw-bold">düzenleyebilirsiniz</a>.
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
