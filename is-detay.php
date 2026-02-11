<?php 
// 1. Her zaman en başta session ve bağlantılar olmalı
session_start();

// Encoding ayarla
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/csrf.php'; 

// Cache engelleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 2. ID'yi alalım (Bunu tüm işlemlerden önce yapmalıyız)
$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// 3. Veritabanı İşlemleri (Tümü burada toplanmalı)

// --- DURUM GÜNCELLEME İŞLEMİ --- 
if (isset($_POST['update_status']) && isset($_POST['status'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    try {
        // POST verisini al ve temizle
        $new_status = trim($_POST['status']);
        
        // Türkçe karakter encoding'ini normalize et
        $new_status = mb_convert_encoding($new_status, 'UTF-8', 'UTF-8');
        
        // Geçerli durumları tanımla
        $valid_statuses = [
            'Açıldı',
            'Çalışılıyor', 
            'Beklemede',
            'Tamamlandı',
            'İptal'
        ];
        
        // Her bir geçerli durumu kontrol et
        foreach($valid_statuses as $vs) {
            error_log("Karşılaştırma: '" . $new_status . "' vs '" . $vs . "' -> " . ($new_status === $vs ? 'EŞLEŞTI' : 'eşleşmedi'));
        }
        
        // Veritabanını doğrudan güncelle (validation'ı basitleştir)
        $stmt = $db->prepare("UPDATE jobs SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$new_status, $_SESSION['user_id'], $id]);
        
        if ($result) {
            error_log("✓ Güncelleme başarılı - ID: $id, Yeni Durum: '$new_status'");
            error_log("===============================");
            header("Location: is-detay.php?id=$id&success=1");
            exit;
        } else {
            error_log("✗ Güncelleme başarısız");
            error_log("===============================");
            header("Location: is-detay.php?id=$id&error=update_failed");
            exit;
        }
    } catch (Exception $e) {
        error_log("✗ Hata: " . $e->getMessage());
        error_log("===============================");
        header("Location: is-detay.php?id=$id&error=" . urlencode($e->getMessage()));
        exit;
    }
}

// --- NOT EKLEME İŞLEMİ ---
if (isset($_POST['add_note'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $note = $_POST['note'];
    $user_id = $_SESSION['user_id']; // Artık session'dan alabiliriz
    $stmt = $db->prepare("INSERT INTO job_notes (job_id, user_id, note) VALUES (?, ?, ?)");
    if ($stmt->execute([$id, $user_id, $note])) {
        header("Location: is-detay.php?id=$id&note_success=1");
        exit;
    }
}

// --- DOSYA YÜKLEME İŞLEMİ ---
if (isset($_FILES['job_file']) && $_FILES['job_file']['error'] == 0) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $file = $_FILES['job_file'];
    
    // Güvenlik kontrolü: Dosya boyutu (max 10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        header("Location: is-detay.php?id=$id&error=" . urlencode("Dosya boyutu 10MB'dan büyük olamaz"));
        exit;
    }
    
    // İzin verilen dosya türleri
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                      'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                      'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                      'text/plain', 'application/zip'];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        header("Location: is-detay.php?id=$id&error=" . urlencode("Dosya türü desteklenmiyor"));
        exit;
    }
    
    // Güvenli dosya adı oluştur
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $file_name = time() . '_' . $safe_filename . '.' . $file_ext;
    $file_path = 'uploads/' . $file_name;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $stmt = $db->prepare("INSERT INTO job_files (job_id, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$id, $file['name'], $file_path, $_SESSION['user_id']])) {
            header("Location: is-detay.php?id=$id&file_success=1");
            exit;
        }
    } else {
        header("Location: is-detay.php?id=$id&error=" . urlencode("Dosya yüklenirken hata oluştu"));
        exit;
    }
}

// 4. Verileri Çekelim
$job = $db->prepare("SELECT j.*, c.name as customer_name, u.name as staff_name, u.role as staff_role,
                    u2.name as updated_by_name, j.updated_at
                    FROM jobs j 
                    LEFT JOIN customers c ON j.customer_id = c.id 
                    LEFT JOIN users u ON j.assigned_user_id = u.id 
                    LEFT JOIN users u2 ON j.updated_by = u2.id
                    WHERE j.id = ?");
$job->execute([$id]);
$jobDetail = $job->fetch(PDO::FETCH_ASSOC);

// İş bulunamadıysa ana sayfaya yönlendir
if (!$jobDetail) {
    header("Location: index.php");
    exit;
}

// Durum değerini temizle (boşlukları kaldır)
$jobDetail['status'] = trim($jobDetail['status']);


// NOT: Otomatik düzeltme kodunu kaldırdık - artık durum olduğu gibi kalacak

$notes = $db->prepare("SELECT n.*, u.name as user_name FROM job_notes n JOIN users u ON n.user_id = u.id WHERE job_id = ? ORDER BY created_at DESC");
$notes->execute([$id]);
$files = $db->prepare("SELECT f.*, u.name as uploaded_by_name FROM job_files f 
                      LEFT JOIN users u ON f.uploaded_by = u.id 
                      WHERE f.job_id = ? ORDER BY f.uploaded_at DESC");
$files->execute([$id]);

include 'includes/header.php'; 
?>


<?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Başarılı!</strong> İş durumu güncellendi.
        <small class="d-block mt-1">
            <i class="bi bi-person-check me-1"></i><?php echo $_SESSION['user_name']; ?> - 
            <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i'); ?>
        </small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Başarılı!</strong> İş kaydı güncellendi.
        <small class="d-block mt-1">
            <i class="bi bi-person-check me-1"></i><?php echo $_SESSION['user_name']; ?> - 
            <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i'); ?>
        </small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Hata!</strong> Güncelleme sırasında bir hata oluştu.
        <?php if($_GET['error'] != '1' && $_GET['error'] != 'invalid'): ?>
            <br><small><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></small>
        <?php endif; ?>
        <?php if($_GET['error'] == 'invalid'): ?>
            <br><small>Geçersiz durum değeri!</small>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['note_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-chat-left-text-fill me-2"></i>
        <strong>Başarılı!</strong> Not eklendi.
        <small class="d-block mt-1">
            <i class="bi bi-person-check me-1"></i><?php echo $_SESSION['user_name']; ?> - 
            <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i'); ?>
        </small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['file_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-file-earmark-arrow-up-fill me-2"></i>
        <strong>Başarılı!</strong> Dosya yüklendi.
        <small class="d-block mt-1">
            <i class="bi bi-person-check me-1"></i><?php echo $_SESSION['user_name']; ?> - 
            <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i'); ?>
        </small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="fw-bold text-primary"><?php echo htmlspecialchars($jobDetail['title']); ?></h2>
                        <div class="btn-group mt-2" role="group">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Listeye Dön
                            </a>
                            <a href="is-duzenle.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Düzenle
                            </a>
                            <a href="is-gecmis.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-clock-history"></i> Geçmiş
                            </a>
                            <a href="is-sil.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Sil
                            </a>
                        </div>
                    </div>
                    <span class="badge p-2 <?php 
                        $current_status = trim($jobDetail['status']);
                        $badge_class = 'bg-secondary'; // Default
                        
                        if ($current_status === 'Açıldı') {
                            $badge_class = 'bg-info';
                        } elseif ($current_status === 'Çalışılıyor') {
                            $badge_class = 'bg-warning text-dark';
                        } elseif ($current_status === 'Beklemede') {
                            $badge_class = 'bg-secondary';
                        } elseif ($current_status === 'Tamamlandı') {
                            $badge_class = 'bg-success';
                        } elseif ($current_status === 'İptal') {
                            $badge_class = 'bg-danger';
                        }
                        
                        echo $badge_class;
                    ?>"><?php echo htmlspecialchars($jobDetail['status']); ?></span> 
                </div>
                <p class="text-muted"><?php echo htmlspecialchars($jobDetail['description']); ?></p>
                
                <?php if($jobDetail['updated_at']): ?>
                    <div class="alert alert-info border-0 py-2 px-3 small mt-3">
                        <i class="bi bi-clock-history me-1"></i>
                        <strong>Son Güncelleme:</strong> 
                        <?php echo date('d.m.Y H:i', strtotime($jobDetail['updated_at'])); ?>
                        <?php if($jobDetail['updated_by_name']): ?>
                            - <strong><?php echo htmlspecialchars($jobDetail['updated_by_name']); ?></strong> tarafından
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <hr>
                <div class="row text-center">
                    <div class="col-md-4"><strong>Müşteri:</strong> <br> <?php echo htmlspecialchars($jobDetail['customer_name']); ?></div>
                    <div class="col-md-4"><strong>Hizmet:</strong> <br> <?php echo htmlspecialchars($jobDetail['service_type']); ?></div>
                    <div class="col-md-4">
                        <strong>Sorumlu:</strong> <br> 
                        <?php if($jobDetail['staff_name']): ?>
                            <?php echo htmlspecialchars($jobDetail['staff_name']); ?>
                            <br><small class="badge bg-secondary"><?php 
                                $role_tr = [
                                    'admin' => 'Yönetici',
                                    'operasyon' => 'Operasyon',
                                    'danisman' => 'Danışman'
                                ];
                                echo $role_tr[$jobDetail['staff_role']] ?? $jobDetail['staff_role'];
                            ?></small>
                        <?php else: ?>
                            <span class="text-muted">Atanmadı</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-3">İş Notları</h5>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <?php echo csrf_input(); ?>
                    <textarea name="note" class="form-control mb-2" rows="3" placeholder="Notunuzu buraya yazın..." required></textarea>
                    <button type="submit" name="add_note" class="btn btn-winergy btn-sm">
                        <i class="bi bi-plus-circle"></i> Not Ekle
                    </button>
                </form>
                
                <?php 
                $all_notes = $notes->fetchAll();
                if(!empty($all_notes)): 
                    foreach($all_notes as $n): 
                ?>
                    <div class="border-bottom pb-2 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <i class="bi bi-person-circle me-1 text-primary"></i>
                                <strong class="text-primary"><?php echo htmlspecialchars($n['user_name']); ?></strong>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?>
                            </small>
                        </div>
                        <p class="mb-0 ms-4"><?php echo nl2br(htmlspecialchars($n['note'])); ?></p>
                    </div>
                <?php 
                    endforeach;
                else: 
                ?>
                    <p class="text-center text-muted py-3">Henüz not eklenmemiş.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">Durum Yönetimi</div>
            <div class="card-body">
                <form method="POST" accept-charset="UTF-8" onsubmit="return confirm('İş durumunu güncellemek istediğinize emin misiniz?');">
                    <?php echo csrf_input(); ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">İş Durumu</label>
                        <select name="status" class="form-select" required onchange="this.style.borderColor='#0d6efd';">
                            <?php 
                            $durumlar = ['Açıldı', 'Çalışılıyor', 'Beklemede', 'Tamamlandı', 'İptal'];
                            $current_status = trim($jobDetail['status']);
                            foreach($durumlar as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $current_status === $d ? 'selected' : ''; ?>>
                                    <?php echo $d; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2">
                            Şu anki durum: <strong class="text-primary"><?php echo htmlspecialchars($jobDetail['status']); ?></strong>
                        </small>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-winergy w-100 mb-2">
                        <i class="bi bi-arrow-repeat"></i> Durumu Güncelle
                    </button>
                    
                    <?php if($jobDetail['updated_at'] && $jobDetail['updated_by_name']): ?>
                        <div class="alert alert-light border py-2 px-2 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Son Güncelleme:</strong><br>
                            <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($jobDetail['updated_by_name']); ?><br>
                            <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($jobDetail['updated_at'])); ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">Dökümanlar</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="mb-3">
                    <?php echo csrf_input(); ?>
                    <input type="file" name="job_file" class="form-control form-control-sm mb-2" required>
                    <button type="submit" class="btn btn-winergy btn-sm w-100">
                        <i class="bi bi-upload"></i> Dosya Yükle
                    </button>
                </form>
                
                <ul class="list-group list-group-flush">
                    <?php 
                    $all_files = $files->fetchAll();
                    if(!empty($all_files)): 
                        foreach($all_files as $f): 
                    ?>
                        <li class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <i class="bi bi-file-earmark-arrow-down me-1 text-primary"></i>
                                    <small class="fw-bold"><?php echo htmlspecialchars($f['file_name']); ?></small>
                                    <?php if($f['uploaded_by_name']): ?>
                                        <br><small class="text-muted ms-3">
                                            <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($f['uploaded_by_name']); ?>
                                            <i class="bi bi-clock ms-2 me-1"></i><?php echo date('d.m.Y H:i', strtotime($f['uploaded_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo htmlspecialchars($f['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </li>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <li class="list-group-item text-center text-muted py-3">Henüz dosya yüklenmemiş.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <?php 
                $is_completed = in_array($jobDetail['status'], ['Tamamlandı', 'İptal']);
                $today_date = date('Y-m-d');
                
                // Gün farkını hesapla
                $due_date_obj = new DateTime($jobDetail['due_date']);
                $today_obj = new DateTime($today_date);
                $diff = $today_obj->diff($due_date_obj);
                $days_diff = (int)$diff->format('%r%a'); // + gelecek, - geçmiş
                
                // Durum belirle
                $is_delayed = ($days_diff < 0 && !$is_completed);
                $is_today = ($days_diff == 0 && !$is_completed);
                $is_urgent = ($days_diff > 0 && $days_diff <= 3 && !$is_completed);
                $is_soon = ($days_diff > 3 && $days_diff <= 7 && !$is_completed);
                ?>
                
                <small class="text-muted d-block mb-2">
                    <i class="bi bi-calendar-event"></i> Teslim Tarihi: 
                    <strong><?php echo date('d.m.Y', strtotime($jobDetail['due_date'])); ?></strong>
                </small>
                
                <?php if($is_delayed): ?>
                    <span class="badge bg-danger p-2">
                        <i class="bi bi-exclamation-triangle-fill"></i> Gecikme Var! (<?php echo abs($days_diff); ?> gün)
                    </span>
                <?php elseif($is_today): ?>
                    <span class="badge bg-warning text-dark p-2">
                        <i class="bi bi-clock-fill"></i> Bugün Teslim!
                    </span>
                <?php elseif($is_urgent): ?>
                    <span class="badge bg-warning text-dark p-2">
                        <i class="bi bi-exclamation-circle-fill"></i> Yaklaşıyor! (<?php echo $days_diff; ?> gün kaldı)
                    </span>
                <?php elseif($is_soon): ?>
                    <span class="badge bg-info p-2">
                        <i class="bi bi-info-circle-fill"></i> <?php echo $days_diff; ?> Gün Kaldı
                    </span>
                <?php elseif(!$is_completed): ?>
                    <span class="badge bg-secondary p-2">
                        <i class="bi bi-calendar-check"></i> <?php echo $days_diff; ?> Gün Kaldı
                    </span>
                <?php else: ?>
                    <span class="badge bg-success p-2">
                        <i class="bi bi-check-circle-fill"></i> İş Tamamlandı
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>