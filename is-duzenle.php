<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/csrf.php'; 

// ID kontrolü
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// İş bilgilerini çek
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header("Location: index.php");
    exit;
}

// Form gönderildiğinde güncelle
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    

    $customer_id = $_POST['customer_id'];
    $service_type = $_POST['service_type'];
    $title = trim($_POST['title']); // İş başlığını temizle
    $description = trim($_POST['description']); // Açıklamayı temizle
    $assigned_user_id = !empty($_POST['assigned_user_id']) ? $_POST['assigned_user_id'] : null;
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];


    

    
    // TARİH VALİDASYONU: Başlangıç tarihi bitiş tarihinden önce olmalı
    $error = "";
    if (!empty($start_date) && !empty($due_date) && $start_date > $due_date) {
        $error = "Başlangıç tarihi, bitiş tarihinden sonra olamaz!";
    }
    
    if ($error) {
        // Hata varsa sayfada göster (header ekledikten sonra)
        $_SESSION['temp_error'] = $error;
    } else {
        $sql = "UPDATE jobs SET 
                customer_id = ?, 
                service_type = ?, 
                title = ?, 
                description = ?, 
                assigned_user_id = ?, 
                start_date = ?, 
                due_date = ?,
                status = ?,
                updated_by = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        if ($stmt->execute([$customer_id, $service_type, $title, $description, $assigned_user_id, $start_date, $due_date, $status, $_SESSION['user_id'], $id])) {
            header("Location: is-detay.php?id=$id&updated=1");
            exit;
        }
    }
}

// Dropdown menüler için verileri çekelim
$customers = $db->query("SELECT id, name, contact_name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $db->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php'; 
?>

<?php if(isset($_SESSION['temp_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['temp_error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['temp_error']); ?>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="bi bi-pencil-square"></i> İş Kaydını Düzenle
                    </h5>
                    <a href="is-detay.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Geri
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Müşteri</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">--- Müşteri Seçiniz ---</option>
                                <?php foreach($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $job['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                        <?php if(!empty($c['contact_name'])): ?>
                                            - <?php echo htmlspecialchars($c['contact_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Hizmet Türü</label>
                            <select name="service_type" class="form-select" required>
                                <?php 
                                $services = ['Enerji Etüdü', 'ISO 50001', 'EKB', 'Enerji Yöneticisi'];
                                foreach($services as $service): ?>
                                    <option value="<?php echo $service; ?>" <?php echo $service == $job['service_type'] ? 'selected' : ''; ?>>
                                        <?php echo $service; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">İş / Proje Başlığı</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Açıklama / Notlar</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($job['description']); ?></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Sorumlu Personel</label>
                            <select name="assigned_user_id" class="form-select">
                                <option value="">Atama Yapılmadı</option>
                                <?php 
                                $role_tr = [
                                    'admin' => 'Yönetici',
                                    'operasyon' => 'Operasyon',
                                    'danisman' => 'Danışman'
                                ];
                                foreach($users as $u): 
                                    $role_label = $role_tr[$u['role']] ?? $u['role'];
                                ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $job['assigned_user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['name']); ?> (<?php echo $role_label; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">İş Durumu</label>
                            <select name="status" class="form-select" required>
                                <?php 
                                $statuses = ['Açıldı', 'Çalışılıyor', 'Beklemede', 'Tamamlandı', 'İptal'];
                                foreach($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo trim($job['status']) == $s ? 'selected' : ''; ?>>
                                        <?php echo $s; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $job['start_date']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Teslim Tarihi</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo $job['due_date']; ?>" required>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="is-detay.php?id=<?php echo $id; ?>" class="btn btn-light">
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
