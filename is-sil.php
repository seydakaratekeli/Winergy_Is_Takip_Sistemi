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
    header("Location: index.php");
    exit;
}

// İş var mı kontrol et
$stmt = $db->prepare("SELECT title FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header("Location: index.php?error=notfound");
    exit;
}

// Onay işlemi
if (isset($_POST['confirm_delete'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error(); // Geçersiz token - işlemi durdur
    }
    
    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        log_activity('İş silindi', "Silinen İş: {$job['title']} (ID: {$id})", 'SUCCESS');
        header("Location: index.php?deleted=1");
        exit;
    } else {
        log_activity('İş Silinemedi', "İş ID: $id silinemedi.", 'ERROR');
        header("Location: is-detay.php?id=$id&error=delete_failed");
        exit;
    }
}

include 'includes/header.php'; 
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle-fill"></i> İş Kaydını Sil
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-info-circle-fill"></i> 
                    <strong>Dikkat:</strong> Bu işlem iş kaydını tamamen silecektir. İş geri alınamaz.
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold">Silinecek iş:</h6>
                    <p class="mb-0"><strong><?php echo htmlspecialchars($job['title']); ?></strong></p>
                </div>

                <form method="POST">
                    <?php echo csrf_input(); // CSRF Token ?>
                    <div class="d-flex justify-content-between gap-2">
                        <a href="is-detay.php?id=<?php echo $id; ?>" class="btn btn-secondary px-4">
                            <i class="bi bi-x-circle"></i> Vazgeç
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger px-4" 
                                onclick="return confirm('Bu işi silmek istediğinize emin misiniz?');">
                            <i class="bi bi-trash-fill"></i> Evet, Sil
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
