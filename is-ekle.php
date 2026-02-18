<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/csrf.php';
include 'includes/header.php'; 

// Form gönderildiğinde veritabanına kayıt yapalım
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    
    $customer_id = $_POST['customer_id'];
    // service_type array olarak gelecek (multiple select)
    $service_types = $_POST['service_type'] ?? [];
    
    // En az bir hizmet türü seçildiğini kontrol et
    if (empty($service_types)) {
        echo "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> En az bir hizmet türü seçmelisiniz!</div>";
    } else {
        $service_type = json_encode($service_types, JSON_UNESCAPED_UNICODE);
        $title = trim($_POST['title']); // İş başlığını temizle
        $description = trim($_POST['description']); // Açıklamayı temizle
        $assigned_user_id = !empty($_POST['assigned_user_id']) ? $_POST['assigned_user_id'] : null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        // TARİH VALİDASYONU: Başlangıç tarihi bitiş tarihinden önce olmalı
        $error = "";
        if (!empty($start_date) && !empty($due_date) && $start_date > $due_date) {
            $error = "Başlangıç tarihi, bitiş tarihinden sonra olamaz!";
        }
        
        if ($error) {
            echo "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> $error</div>";
        } else {
            try {
                $sql = "INSERT INTO jobs (customer_id, service_type, title, description, assigned_user_id, start_date, due_date, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Açıldı', ?)";
                
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$customer_id, $service_type, $title, $description, $assigned_user_id, $start_date, $due_date, $_SESSION['user_id']])) {
                    log_activity('İş Açıldı', "Yeni İş: $title", 'SUCCESS');    
                    echo "<div class='alert alert-success'>İş kaydı başarıyla açıldı! <a href='index.php'>Listeye dön</a></div>";
                }
            } catch (PDOException $e) {
                log_error("İş ekleme hatası", ['message' => $e->getMessage(), 'title' => $title]);
                echo "<div class='alert alert-danger'>İş kaydı oluşturulurken bir sorun oluştu. Lütfen sistem yöneticisine danışın.</div>";
            }
        }
    }
}

// Dropdown menüler için verileri çekelim
$customers = $db->query("SELECT id, name, contact_name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $db->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary">Yeni İş Kaydı Oluştur</h5>
            </div>
            <div class="card-body">
                <form action="is-ekle.php" method="POST" id="jobForm">
                    <?php echo csrf_input(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Müşteri Seçin</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">--- Müşteri Seçiniz ---</option>
                                <?php foreach($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                        <?php if(!empty($c['contact_name'])): ?>
                                            - <?php echo htmlspecialchars($c['contact_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Hizmet Türü <span class="text-danger">*</span></label>
                            <div class="border rounded p-3 bg-light">
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Enerji Etüdü" id="service_1">
                                    <label class="form-check-label" for="service_1">
                                        Enerji Etüdü
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="ISO 50001" id="service_2">
                                    <label class="form-check-label" for="service_2">
                                        ISO 50001
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="EKB" id="service_3">
                                    <label class="form-check-label" for="service_3">
                                        Enerji Kimlik Belgesi (EKB)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Enerji Yöneticisi" id="service_4">
                                    <label class="form-check-label" for="service_4">
                                        Enerji Yöneticisi
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> En az bir hizmet türü seçmelisiniz
                            </small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">İş / Proje Başlığı</label>
                            <input type="text" name="title" class="form-control" placeholder="Örn: Fabrika Verimlilik Artırıcı Proje">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Açıklama / Notlar</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sorumlu Personel</label>
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
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($role_label); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Teslim Tarihi</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end">
                        <a href="index.php" class="btn btn-light me-2">İptal</a>
                        <button type="submit" class="btn btn-winergy px-4"><i class="bi bi-check-circle me-1"></i>İşi Kaydet ve Başlat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('jobForm').addEventListener('submit', function(e) {
    const checkedCount = document.querySelectorAll('.service-type-check:checked').length;
    if (checkedCount === 0) {
        e.preventDefault();
        alert('⚠️ En az bir hizmet türü seçmelisiniz!');
        document.querySelector('.service-type-check').focus();
    }
});
</script>

<?php include 'includes/footer.php'; ?>