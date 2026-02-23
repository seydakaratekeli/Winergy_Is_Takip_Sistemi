<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';
require_once 'includes/csrf.php';
require_once 'includes/logger.php';

$message = "";

// Form gönderildiğinde veritabanına kayıt yapalım 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    // Verileri trim() ile temizleyerek alalım
    $name = trim($_POST['name']);
    $contact_name = trim($_POST['contact_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address'] ?? '');
    $created_by = $_SESSION['user_id'];

    try {
        $sql = "INSERT INTO customers (name, contact_name, phone, email, address, created_by) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute([$name, $contact_name, $phone, $email, $address, $created_by])) {
            log_activity('Müşteri Eklendi', "Yeni Müşteri: $name", 'SUCCESS');
            header("Location: musteriler.php?added=1");
            exit;
        }
    } catch (PDOException $e) {
        // Hatayı arka planda kaydet
        log_error("Müşteri ekleme hatası", ['message' => $e->getMessage(), 'customer' => $name]);
        // Kullanıcıya temiz bir hata mesajı ver
        $message = "Müşteri kaydedilirken teknik bir sorun oluştu. Lütfen bilgileri kontrol edip tekrar deneyin.";
    }
}

// HTML çıktısı başlat
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <?php if ($message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary">Yeni Müşteri / Firma Kaydı</h5>
            </div>
            <div class="card-body">
                <form action="musteri-ekle.php" method="POST">
                    <?php echo csrf_input(); ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Firma / Müşteri Adı *</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: Winergy Tech" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">İlgili Kişi (Kontak)</label>
                        <input type="text" name="contact_name" class="form-control" placeholder="Ad Soyad">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Telefon</label>
                            <input type="text" name="phone" class="form-control phone-input" placeholder="0555 123 4567" maxlength="14">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">E-Posta</label>
                            <input type="email" name="email" class="form-control" placeholder="mail@firma.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Adres</label>
                        <textarea name="address" class="form-control" placeholder="Firma adresi" rows="3"></textarea>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="musteriler.php" class="text-muted small text-decoration-none">← Listeye Geri Dön</a>
                        <button type="submit" class="btn btn-winergy px-4 fw-bold"><i class="bi bi-check-circle me-1"></i>Müşteriyi Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>