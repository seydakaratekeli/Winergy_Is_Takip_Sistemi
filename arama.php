<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/csrf.php'; 

// Cache engelleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include 'includes/header.php'; 

$today = date('Y-m-d');

// Arama parametreleri
$search_query = $_GET['q'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$assigned_user = $_GET['assigned_user'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$results = [];
$total_count = 0;

// Arama yapılmışsa
if (!empty($search_query) || !empty($start_date) || !empty($assigned_user) || !empty($status_filter)) {
    
    $where_conditions = [];
    $params = [];
    
    // Metin araması (müşteri, başlık, açıklama, notlarda)
    if (!empty($search_query)) {
        $search_term = "%$search_query%";
        $where_conditions[] = "(
            c.name LIKE ? OR 
            j.title LIKE ? OR 
            j.description LIKE ? OR
            j.service_type LIKE ? OR
            EXISTS (
                SELECT 1 FROM job_notes jn 
                WHERE jn.job_id = j.id AND jn.note LIKE ?
            )
        )";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    }
    
    // Tarih aralığı
    if (!empty($start_date)) {
        $where_conditions[] = "j.created_at >= ?";
        $params[] = $start_date . ' 00:00:00';
    }
    
    if (!empty($end_date)) {
        $where_conditions[] = "j.created_at <= ?";
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Sorumlu personel
    if (!empty($assigned_user)) {
        $where_conditions[] = "j.assigned_user_id = ?";
        $params[] = $assigned_user;
    }
    
    // Durum filtresi
    if (!empty($status_filter)) {
        $where_conditions[] = "j.status = ?";
        $params[] = $status_filter;
    }
    
    // SQL oluştur
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT j.*, 
               c.name as customer_name,
               c.contact_name,
               u.name as staff_name,
               u.role as staff_role
        FROM jobs j
        LEFT JOIN customers c ON j.customer_id = c.id
        LEFT JOIN users u ON j.assigned_user_id = u.id
        $where_sql
        ORDER BY j.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_count = count($results);
}

// Kullanıcı listesi (filtre ve toplu işlem için)
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
        } elseif ($_GET['success'] == 'service_type_updated') {
            echo "$count iş kaydının hizmet türü güncellendi.";
        } elseif ($_GET['success'] == 'deleted' || $_GET['success'] == 'cancelled') {
            echo "$count iş kaydı iptal edildi.";
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
        } elseif ($_GET['error'] == 'no_service_type') {
            echo "Lütfen en az bir hizmet türü seçin.";
        } elseif ($_GET['error'] == 'invalid_action') {
            echo "Geçersiz işlem.";
        } else {
            echo "Bir hata oluştu.";
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-1" style="color: var(--dt-sec-color);">
                    <i class="bi bi-search me-2"></i>Gelişmiş Arama
                </h3>
                <p class="text-muted mb-0">İş kayıtlarında detaylı arama yapın</p>
            </div>
            <?php if ($total_count > 0): ?>
            <div>
                <span class="badge bg-success px-3 py-2 fs-6">
                    <i class="bi bi-check-circle me-1"></i><?php echo $total_count; ?> Sonuç Bulundu
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Arama Formu -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-funnel-fill me-2 text-primary"></i>Arama Filtreleri
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="arama.php">
            <div class="row g-3">
                <!-- Genel Arama -->
                <div class="col-md-12">
                    <label class="form-label fw-bold">
                        <i class="bi bi-search me-1"></i>Arama Metni
                    </label>
                    <input type="text" name="q" class="form-control form-control-lg" 
                           placeholder="Müşteri adı, iş başlığı, açıklama, not içeriği..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Müşteri adı, iş başlığı, hizmet türü, açıklama ve notlarda arama yapar
                    </small>
                </div>
                
                <!-- Tarih Aralığı -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-calendar-event me-1"></i>Başlangıç Tarihi
                    </label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-calendar-check me-1"></i>Bitiş Tarihi
                    </label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                
                <!-- Sorumlu Personel -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-person me-1"></i>Sorumlu Personel
                    </label>
                    <select name="assigned_user" class="form-select">
                        <option value="">Tümü</option>
                        <?php 
                        $role_tr = [
                            'admin' => 'Yönetici',
                            'operasyon' => 'Operasyon',
                            'danisman' => 'Danışman'
                        ];
                        foreach($users as $u): 
                        ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $assigned_user == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?> (<?php echo $role_tr[$u['role']] ?? $u['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Durum -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-flag me-1"></i>İş Durumu
                    </label>
                    <select name="status_filter" class="form-select">
                        <option value="">Tümü</option>
                        <?php
                        $statuses = ['Açıldı', 'Çalışılıyor', 'Beklemede', 'Tamamlandı', 'İptal'];
                        foreach($statuses as $s):
                        ?>
                            <option value="<?php echo $s; ?>" <?php echo $status_filter == $s ? 'selected' : ''; ?>>
                                <?php echo $s; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-winergy px-4">
                    <i class="bi bi-search me-1"></i>Ara
                </button>
                <a href="arama.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Temizle
                </a>
                <a href="index.php" class="btn btn-light ms-auto">
                    <i class="bi bi-arrow-left me-1"></i>Ana Sayfaya Dön
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Toplu İşlem Paneli -->
<div class="card border-0 shadow-sm mb-3" id="bulkActionsPanel" style="display: none; border-left: 4px solid #14b8a6;">
    <div class="card-body py-3">
        <form method="POST" action="toplu-islemler.php" id="bulkActionsForm">
            <?php echo csrf_input(); ?>
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold" style="font-size: 0.9rem; color: #0d9488;">Toplu İşlem Seç</label>
                    <select name="action" class="form-select form-select-sm" id="bulkAction" required>
                        <option value="">-- İşlem Seçin --</option>
                        <option value="change_status">Durum Değiştir</option>
                        <option value="assign_user">Personel Ata</option>
                        <option value="change_service_type">Hizmet Türü Değiştir</option>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <option value="delete">İşleri İptal Et</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-lg-3 col-md-6" id="statusSelectDiv" style="display: none;">
                    <label class="form-label small fw-bold" style="font-size: 0.9rem; color: #0d9488;">Yeni Durum</label>
                    <select name="new_status" class="form-select form-select-sm">
                        <option value="Açıldı">Açıldı</option>
                        <option value="Çalışılıyor">Çalışılıyor</option>
                        <option value="Beklemede">Beklemede</option>
                        <option value="Tamamlandı">Tamamlandı</option>
                        <option value="İptal">İptal</option>
                    </select>
                </div>
                
                <div class="col-lg-3 col-md-6" id="userSelectDiv" style="display: none;">
                    <label class="form-label small fw-bold" style="font-size: 0.9rem; color: #0d9488;">Personel</label>
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
                
                <div class="col-lg-3 col-md-6" id="serviceTypeSelectDiv" style="display: none;">
                    <label class="form-label small fw-bold" style="font-size: 0.9rem; color: #0d9488;">Hizmet Türü (Çoklu)</label>
                    <div style="max-height: 120px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.25rem; padding: 0.5rem;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="Enerji Etüdü" id="bulk_service_1">
                            <label class="form-check-label small" for="bulk_service_1">Enerji Etüdü</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="ISO 50001" id="bulk_service_2">
                            <label class="form-check-label small" for="bulk_service_2">ISO 50001</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="EKB" id="bulk_service_3">
                            <label class="form-check-label small" for="bulk_service_3">Enerji Kimlik Belgesi (EKB)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="Enerji Yöneticisi" id="bulk_service_4">
                            <label class="form-check-label small" for="bulk_service_4">Enerji Yöneticisi</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="VAP" id="bulk_service_5">
                            <label class="form-check-label small" for="bulk_service_5">VAP</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="Danışmanlık" id="bulk_service_6">
                            <label class="form-check-label small" for="bulk_service_6">Danışmanlık</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="Rapor Onay" id="bulk_service_7">
                            <label class="form-check-label small" for="bulk_service_7">Rapor Onay</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_type[]" value="Bakım" id="bulk_service_8">
                            <label class="form-check-label small" for="bulk_service_8">Bakım</label>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <button type="submit" class="btn btn-winergy w-100 btn-sm" id="bulkSubmitBtn">
                        <i class="bi bi-check-circle me-1"></i>Uygula (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Arama Sonuçları -->
<?php if (!empty($search_query) || !empty($start_date) || !empty($assigned_user) || !empty($status_filter)): ?>
    <?php if ($total_count > 0): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-list-check me-2"></i>Arama Sonuçları
                </h5>
                <button type="button" class="btn btn-success btn-sm" id="exportSelectedBtn" style="display: none;" onclick="exportSelected()">
                    <i class="bi bi-file-earmark-excel me-1"></i>Seçilenleri Aktar (<span id="exportCount">0</span>)
                </button>
            </div>
            <div class="card-body p-0">
                <form id="searchResultsForm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th><i class="bi bi-building"></i> Müşteri</th>
                                <th><i class="bi bi-briefcase"></i> İş Başlığı</th>
                                <th><i class="bi bi-gear"></i> Hizmet</th>
                                <th><i class="bi bi-person"></i> Sorumlu</th>
                                <th><i class="bi bi-calendar"></i> Oluşturma</th>
                                <th><i class="bi bi-flag"></i> Durum</th>
                                <th class="text-center"><i class="bi bi-tools"></i> İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($results as $job): 
                                // Gecikme kontrolü
                                $is_completed = in_array($job['status'], ['Tamamlandı', 'İptal']);
                                $days_diff = null;
                                if (!empty($job['due_date'])) {
                                    $due_date_obj = new DateTime($job['due_date']);
                                    $today_obj = new DateTime($today);
                                    $diff = $today_obj->diff($due_date_obj);
                                    $days_diff = (int)$diff->format('%r%a');
                                }
                                
                                $row_class = '';
                                if (!$is_completed && $days_diff !== null && $days_diff < 0) {
                                    $row_class = 'table-danger';
                                } elseif (!$is_completed && $days_diff !== null && $days_diff == 0) {
                                    $row_class = 'table-warning';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input job-checkbox" name="job_ids[]" value="<?php echo $job['id']; ?>">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($job['customer_name']); ?></strong>
                                    <?php if($job['contact_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($job['contact_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                    <?php if($job['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($job['description'], 0, 50)) . '...'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $service_types = [];
                                    if (!empty($job['service_type'])) {
                                        $service_types = json_decode($job['service_type'], true);
                                        if (!is_array($service_types)) {
                                            $service_types = [$job['service_type']];
                                        }
                                    }
                                    foreach($service_types as $service): 
                                    ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1">
                                            <?php echo htmlspecialchars($service); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if($job['staff_name']): ?>
                                        <?php echo htmlspecialchars($job['staff_name']); ?>
                                        <br><small class="text-muted">
                                            <?php 
                                            $role_tr = [
                                                'admin' => 'Yönetici',
                                                'operasyon' => 'Operasyon',
                                                'danisman' => 'Danışman'
                                            ];
                                            echo $role_tr[$job['staff_role']] ?? $job['staff_role'];
                                            ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Atanmadı</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('d.m.Y', strtotime($job['created_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        $badge_class = 'bg-secondary';
                                        if ($job['status'] === 'Açıldı') $badge_class = 'bg-info';
                                        elseif ($job['status'] === 'Çalışılıyor') $badge_class = 'bg-warning text-dark';
                                        elseif ($job['status'] === 'Tamamlandı') $badge_class = 'bg-success';
                                        elseif ($job['status'] === 'İptal') $badge_class = 'bg-danger';
                                        echo $badge_class;
                                    ?>">
                                        <?php echo htmlspecialchars($job['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="is-detay.php?id=<?php echo $job['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="is-duzenle.php?id=<?php echo $job['id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-search display-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Sonuç Bulunamadı</h5>
                <p class="text-muted">Arama kriterlerinize uygun iş kaydı bulunamadı. Lütfen farklı filtreler deneyin.</p>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-search display-1 text-primary"></i>
            <h5 class="mt-3">Arama Yapmaya Başlayın</h5>
            <p class="text-muted">Üstteki filtreleri kullanarak iş kayıtlarında detaylı arama yapabilirsiniz.</p>
            <div class="mt-4">
                <span class="badge bg-light text-dark border px-3 py-2 me-2">
                    <i class="bi bi-search me-1"></i>Metin Araması
                </span>
                <span class="badge bg-light text-dark border px-3 py-2 me-2">
                    <i class="bi bi-calendar me-1"></i>Tarih Aralığı
                </span>
                <span class="badge bg-light text-dark border px-3 py-2 me-2">
                    <i class="bi bi-person me-1"></i>Personel Filtresi
                </span>
                <span class="badge bg-light text-dark border px-3 py-2">
                    <i class="bi bi-flag me-1"></i>Durum Filtresi
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Seçili iş sayısını güncelle
function updateSelectedCount() {
    const count = document.querySelectorAll('.job-checkbox:checked').length;
    const selectedCountEl = document.getElementById('selectedCount');
    const exportCountEl = document.getElementById('exportCount');
    if (selectedCountEl) selectedCountEl.textContent = count;
    if (exportCountEl) exportCountEl.textContent = count;
}

// Toplu işlem panelini göster/gizle
function toggleBulkPanel() {
    const count = document.querySelectorAll('.job-checkbox:checked').length;
    const panel = document.getElementById('bulkActionsPanel');
    const exportBtn = document.getElementById('exportSelectedBtn');
    
    if (panel) panel.style.display = count > 0 ? 'block' : 'none';
    if (exportBtn) exportBtn.style.display = count > 0 ? 'inline-block' : 'none';
}

// Sayfa yüklendiğinde event listener'ları ekle
window.addEventListener('load', function() {
    // Tüm seçimi yönet
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.job-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
            toggleBulkPanel();
        });
    }

    // Event delegation ile checkbox değişimlerini dinle
    const searchForm = document.getElementById('searchResultsForm');
    if (searchForm) {
        searchForm.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('job-checkbox')) {
                updateSelectedCount();
                toggleBulkPanel();
                
                // Tümü seçiliyse selectAll'ı işaretle
                const allCheckboxes = document.querySelectorAll('.job-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.job-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                if (selectAll && allCheckboxes.length > 0) {
                    selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                }
            }
        });
    }

    // İşlem tipine göre ek alanları göster
    const bulkActionSelect = document.getElementById('bulkAction');
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const statusDiv = document.getElementById('statusSelectDiv');
            const userDiv = document.getElementById('userSelectDiv');
            
            const serviceDiv = document.getElementById('serviceTypeSelectDiv');
            
            if (statusDiv) statusDiv.style.display = 'none';
            if (userDiv) userDiv.style.display = 'none';
            if (serviceDiv) serviceDiv.style.display = 'none';
            
            if (this.value === 'change_status') {
                if (statusDiv) statusDiv.style.display = 'block';
            } else if (this.value === 'assign_user') {
                if (userDiv) userDiv.style.display = 'block';
            } else if (this.value === 'change_service_type') {
                if (serviceDiv) serviceDiv.style.display = 'block';
            }
        });
    }

    // Form gönderiminde seçili ID'leri aktar
    const bulkActionsForm = document.getElementById('bulkActionsForm');
    if (bulkActionsForm) {
        bulkActionsForm.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.job-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Lütfen en az bir iş seçin!');
                return false;
            }
            
            // İşleme göre özel onay mesajı
            const action = document.getElementById('bulkAction').value;
            let confirmMsg = 'Seçili işleri güncellemek istediğinize emin misiniz?';
            
            if (action === 'delete') {
                confirmMsg = `${checkedBoxes.length} adet iş kaydını iptal etmek istediğinizden emin misiniz?\n\n` +
                             `⚠️ İşler "İptal" durumuna alınacaktır.\n` +
                             `✓ Veriler silinmez, gerekirse geri alınabilir.`;
            } else if (action === 'change_status') {
                const newStatus = document.querySelector('[name="new_status"]').value;
                confirmMsg = `${checkedBoxes.length} adet işin durumunu "${newStatus}" olarak değiştirmek istiyor musunuz?`;
            } else if (action === 'assign_user') {
                const userName = document.querySelector('[name="assign_user_id"] option:checked').text;
                confirmMsg = `${checkedBoxes.length} adet işi "${userName}" kişisine atamak istiyor musunuz?`;
            } else if (action === 'change_service_type') {
                const selectedServices = document.querySelectorAll('[name="service_type[]"]:checked');
                if (selectedServices.length === 0) {
                    e.preventDefault();
                    alert('Lütfen en az bir hizmet türü seçin!');
                    return false;
                }
                confirmMsg = `${checkedBoxes.length} adet işin hizmet türünü değiştirmek istiyor musunuz?`;
            }
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
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
    }
});

// Seçili kayıtları export et
function exportSelected() {
    const checkedBoxes = document.querySelectorAll('.job-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Lütfen en az bir iş seçin!');
        return;
    }
    
    // Form oluştur ve gönder
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export-isler.php<?php echo !empty($_GET) ? "?" . http_build_query($_GET) : ""; ?>';
    
    // CSRF token ekle
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo csrf_generate_token(); ?>';
    form.appendChild(csrfInput);
    
    checkedBoxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_jobs[]';
        input.value = cb.value;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'includes/footer.php'; ?>
