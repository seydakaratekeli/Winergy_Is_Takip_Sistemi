<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php'; 
require_once 'includes/csrf.php'; // CSRF Koruması

// Cache engelleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include 'includes/header.php'; 

$today = date('Y-m-d');

// --- FİLTRELEME MANTIĞI ---
$where_clauses = [];
$params = [];

// Hizmet Türü Filtresi [cite: 47]
if (!empty($_GET['service_type'])) {
    $where_clauses[] = "j.service_type = ?";
    $params[] = $_GET['service_type'];
}

// Durum Filtresi [cite: 50]
if (!empty($_GET['status'])) {
    $where_clauses[] = "j.status = ?";
    $params[] = $_GET['status'];
}

// SQL Sorgusunu Oluştur
$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
$query_sql = "
    SELECT j.*, c.name as customer_name, u.name as staff_name, u.role as staff_role 
    FROM jobs j 
    LEFT JOIN customers c ON j.customer_id = c.id 
    LEFT JOIN users u ON j.assigned_user_id = u.id 
    $where_sql
    ORDER BY j.due_date ASC
";

$stmt = $db->prepare($query_sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sadece boşlukları temizle, otomatik düzeltme yapma
foreach($jobs as &$job) {
    $job['status'] = trim($job['status']);
}
unset($job); // Referansı temizle

// İstatistikler (Filtrelerden bağımsız genel toplamlar)
$stats_res = $db->query("SELECT status, COUNT(*) as count FROM jobs GROUP BY status");
$stats = $stats_res->fetchAll(PDO::FETCH_KEY_PAIR);

// Toplu işlemler için kullanıcıları çek
$users = $db->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Toplu İşlem Bildirimleri -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Başarılı!</strong>
        <?php 
        $count = $_GET['count'] ?? 0;
        if ($_GET['success'] == 'status_updated') {
            echo "$count iş kaydının durumu güncellendi.";
        } elseif ($_GET['success'] == 'assigned') {
            echo "$count iş kaydı atandı.";
        } elseif ($_GET['success'] == 'deleted') {
            echo "$count iş kaydı silindi.";
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Hata!</strong>
        <?php 
        if ($_GET['error'] == 'no_selection') {
            echo "Lütfen en az bir iş seçin.";
        } elseif ($_GET['error'] == 'no_status') {
            echo "Lütfen bir durum seçin.";
        } elseif ($_GET['error'] == 'no_user') {
            echo "Lütfen bir kullanıcı seçin.";
        } elseif ($_GET['error'] == 'no_permission') {
            echo "Bu işlem için yetkiniz bulunmamaktadır. Sadece yöneticiler toplu silme yapabilir.";
        } elseif ($_GET['error'] == 'invalid_action') {
            echo "Geçersiz işlem.";
        } else {
            echo "Bir hata oluştu.";
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Dashboard Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: var(--dt-priGrd-color); color: var(--dt-whi-color);">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="fw-bold mb-2">
                            <i class="bi bi-speedometer2 me-2"></i>İş Takip Dashboard
                        </h2>
                        <p class="mb-0 opacity-90">
                            <i class="bi bi-lightning-charge-fill me-1"></i>
                            Winergy Technologies - Enerji Verimliliği Projeleri
                        </p>
                    </div>
                    <div class="col-md-4 text-end mt-3 mt-md-0">
                        <div class="d-flex gap-2 justify-content-end align-items-center flex-wrap">
                            <span class="badge bg-white text-info px-3 py-2">
                                <i class="bi bi-folder-plus me-1"></i>Açıldı: <strong><?php echo $stats['Açıldı'] ?? 0; ?></strong>
                            </span>
                            <span class="badge bg-white text-warning px-3 py-2">
                                <i class="bi bi-hourglass-split me-1"></i>Çalışılıyor: <strong><?php echo $stats['Çalışılıyor'] ?? 0; ?></strong>
                            </span>
                            <span class="badge bg-white text-success px-3 py-2">
                                <i class="bi bi-check-circle-fill me-1"></i>Tamamlandı: <strong><?php echo $stats['Tamamlandı'] ?? 0; ?></strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtre Bölümü -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pb-0">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-funnel-fill me-2 text-primary"></i>Filtreler
        </h6>
    </div>
    <div class="card-body pt-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">
                    <i class="bi bi-gear me-1"></i>Hizmet Türü
                </label>
                <select name="service_type" class="form-select">
                    <option value="">Tümünü Göster</option>
                    <option value="Enerji Etüdü" <?php echo ($_GET['service_type'] ?? '') == 'Enerji Etüdü' ? 'selected' : ''; ?>>Enerji Etüdü</option>
                    <option value="ISO 50001" <?php echo ($_GET['service_type'] ?? '') == 'ISO 50001' ? 'selected' : ''; ?>>ISO 50001</option>
                    <option value="EKB" <?php echo ($_GET['service_type'] ?? '') == 'EKB' ? 'selected' : ''; ?>>Enerji Kimlik Belgesi (EKB)</option>
                    <option value="Enerji Yöneticisi" <?php echo ($_GET['service_type'] ?? '') == 'Enerji Yöneticisi' ? 'selected' : ''; ?>>Enerji Yöneticisi</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">
                    <i class="bi bi-flag me-1"></i>İş Durumu
                </label>
                <select name="status" class="form-select">
                    <option value="">Tümünü Göster</option>
                    <option value="Açıldı" <?php echo ($_GET['status'] ?? '') == 'Açıldı' ? 'selected' : ''; ?>>Açıldı</option>
                    <option value="Çalışılıyor" <?php echo ($_GET['status'] ?? '') == 'Çalışılıyor' ? 'selected' : ''; ?>>Çalışılıyor</option>
                    <option value="Beklemede" <?php echo ($_GET['status'] ?? '') == 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                    <option value="Tamamlandı" <?php echo ($_GET['status'] ?? '') == 'Tamamlandı' ? 'selected' : ''; ?>>Tamamlandı</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-winergy flex-grow-1">
                    <i class="bi bi-search me-1"></i>Filtrele
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Toplu İşlemler Paneli -->
<div class="card border-0 shadow-sm mb-3" id="bulkActionsPanel" style="display: none;">
    <div class="card-body py-3">
        <form?php echo csrf_input(); // CSRF Token ?>
            < action="toplu-islemler.php" method="POST" id="bulkActionsForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Toplu İşlem Seç</label>
                    <select name="action" id="bulkAction" class="form-select form-select-sm" required>
                        <option value="">-- İşlem Seçin --</option>
                        <option value="change_status">Durum Değiştir</option>
                        <option value="assign_user">Personel Ata</option>
                        <?php if($_SESSION['user_role'] === 'admin'): ?>
                            <option value="delete">Sil</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Durum değiştirme -->
                <div class="col-md-3" id="statusSelectDiv" style="display: none;">
                    <label class="form-label small fw-bold">Yeni Durum</label>
                    <select name="new_status" class="form-select form-select-sm">
                        <option value="Açıldı">Açıldı</option>
                        <option value="Çalışılıyor">Çalışılıyor</option>
                        <option value="Beklemede">Beklemede</option>
                        <option value="Tamamlandı">Tamamlandı</option>
                        <option value="İptal">İptal</option>
                    </select>
                </div>
                
                <!-- Personel atama -->
                <div class="col-md-3" id="userSelectDiv" style="display: none;">
                    <label class="form-label small fw-bold">Personel</label>
                    <select name="assign_user_id" class="form-select form-select-sm">
                        <option value="">Atanmadı</option>
                        <?php 
                        $role_tr = [
                            'admin' => 'Yönetici',
                            'operasyon' => 'Operasyon',
                            'danisman' => 'Danışman'
                        ];
                        foreach($users as $u): 
                        ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo $u['name']; ?> (<?php echo $role_tr[$u['role']] ?? $u['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-winergy btn-sm w-100" onclick="return confirm('Seçili işleri güncellemek istediğinize emin misiniz?');">
                        <i class="bi bi-check-circle me-1"></i>Uygula (<span id="selectedCount">0</span> İş)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- İş Listesi Tablosu -->
<form id="jobsTableForm">
<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-list-check me-2 text-primary"></i>İş Listesi
        </h5>
        <span class="badge bg-primary px-3 py-2">
            <i class="bi bi-folder2-open me-1"></i>Toplam: <?php echo count($jobs); ?> İş
        </span>
    </div>
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 40px;">
                    <input type="checkbox" class="form-check-input" id="selectAll">
                </th>
                <th><i class="bi bi-building"></i> Müşteri</th>
                <th><i class="bi bi-gear"></i> Hizmet</th>
                <th><i class="bi bi-person"></i> Sorumlu</th>
                <th><i class="bi bi-calendar"></i> Teslim Tarihi</th>
                <th><i class="bi bi-flag"></i> Durum</th>
                <th class="text-center"><i class="bi bi-tools"></i> İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($jobs as $job): 
                $is_completed = in_array($job['status'], ['Tamamlandı', 'İptal']);
                
                // Gün farkını hesapla
                $due_date_obj = new DateTime($job['due_date']);
                $today_obj = new DateTime($today);
                $diff = $today_obj->diff($due_date_obj);
                $days_diff = (int)$diff->format('%r%a'); // + gelecek, - geçmiş
                
                // Durum belirle
                $is_delayed = ($days_diff < 0 && !$is_completed);
                $is_today = ($days_diff == 0 && !$is_completed);
                $is_urgent = ($days_diff > 0 && $days_diff <= 3 && !$is_completed); // 1-3 gün kaldı
                $is_soon = ($days_diff > 3 && $days_diff <= 7 && !$is_completed); // 4-7 gün kaldı
                
                // Satır rengini belirle
                $row_class = '';
                if ($is_delayed) $row_class = 'table-danger';
                elseif ($is_today) $row_class = 'table-warning';
                elseif ($is_urgent) $row_class = 'table-warning bg-opacity-50';
            ?>
            <tr class="<?php echo $row_class; ?>">
                <td>
                    <input type="checkbox" class="form-check-input job-checkbox" name="job_ids[]" value="<?php echo $job['id']; ?>">
                </td>
                <td><strong><?php echo htmlspecialchars($job['customer_name']); ?></strong></td>
                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($job['service_type']); ?></span></td>
                <td>
                    <?php if($job['staff_name']): ?>
                        <?php echo htmlspecialchars($job['staff_name']); ?>
                        <br><small class="text-muted"><?php 
                            $role_tr = [
                                'admin' => 'Yönetici',
                                'operasyon' => 'Operasyon',
                                'danisman' => 'Danışman'
                            ];
                            echo $role_tr[$job['staff_role']] ?? $job['staff_role'];
                        ?></small>
                    <?php else: ?>
                        <span class="text-muted">Atanmadı</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo date('d.m.Y', strtotime($job['due_date'])); ?>
                    <?php if(!$is_completed): ?>
                        <br>
                        <?php if($is_delayed): ?>
                            <small class="text-danger fw-bold">! Gecikme (<?php echo abs($days_diff); ?> gün)</small>
                        <?php elseif($is_today): ?>
                            <small class="text-warning fw-bold">! Bugün teslim</small>
                        <?php elseif($is_urgent): ?>
                            <small class="text-warning fw-bold">⚠ <?php echo $days_diff; ?> gün kaldı</small>
                        <?php elseif($is_soon): ?>
                            <small class="text-info fw-bold"><?php echo $days_diff; ?> gün kaldı</small>
                        <?php else: ?>
                            <small class="text-muted"><?php echo $days_diff; ?> gün kaldı</small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?php 
                        $current_status = trim($job['status']);
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
                    ?>">
                        <?php echo htmlspecialchars($job['status']); ?>
                    </span>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <a href="is-detay.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary" title="Detay">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="is-duzenle.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Düzenle">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="is-sil.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-danger" title="Sil">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($jobs)): ?>
                <tr><td colspan="7" class="text-center py-4">Filtreye uygun iş bulunamadı.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</form>

<script>
// Tüm seçimi yönet
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.job-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
    toggleBulkPanel();
});

// Tek checkbox değişimini izle
document.querySelectorAll('.job-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        updateSelectedCount();
        toggleBulkPanel();
        
        // Eğer tümü seçiliyse, selectAll'ı işaretle
        const allChecked = document.querySelectorAll('.job-checkbox:checked').length === document.querySelectorAll('.job-checkbox').length;
        document.getElementById('selectAll').checked = allChecked;
    });
});

// Seçili iş sayısını güncelle
function updateSelectedCount() {
    const count = document.querySelectorAll('.job-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

// Toplu işlem panelini göster/gizle
function toggleBulkPanel() {
    const count = document.querySelectorAll('.job-checkbox:checked').length;
    const panel = document.getElementById('bulkActionsPanel');
    panel.style.display = count > 0 ? 'block' : 'none';
}

// İşlem tipine göre ek alanları göster
document.getElementById('bulkAction').addEventListener('change', function() {
    const statusDiv = document.getElementById('statusSelectDiv');
    const userDiv = document.getElementById('userSelectDiv');
    
    statusDiv.style.display = 'none';
    userDiv.style.display = 'none';
    
    if (this.value === 'change_status') {
        statusDiv.style.display = 'block';
    } else if (this.value === 'assign_user') {
        userDiv.style.display = 'block';
    }
});

// Form gönderiminde seçili ID'leri aktar
document.getElementById('bulkActionsForm').addEventListener('submit', function(e) {
    // Seçili checkbox'ların değerlerini form'a ekle
    const checkedBoxes = document.querySelectorAll('.job-checkbox:checked');
    if (checkedBoxes.length === 0) {
        e.preventDefault();
        alert('Lütfen en az bir iş seçin!');
        return false;
    }
    
    // Mevcut job_ids inputlarını temizle
    this.querySelectorAll('input[name="job_ids[]"]').forEach(input => input.remove());
    
    // Yeni inputları ekle
    checkedBoxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'job_ids[]';
        input.value = cb.value;
        this.appendChild(input);
    });
});
</script>

<?php include 'includes/footer.php'; ?>