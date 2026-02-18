<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php'; 

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

// Kullanıcı listesi (filtre için)
$users = $db->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

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

<!-- Arama Sonuçları -->
<?php if (!empty($search_query) || !empty($start_date) || !empty($assigned_user) || !empty($status_filter)): ?>
    <?php if ($total_count > 0): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-list-check me-2"></i>Arama Sonuçları
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
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

<?php include 'includes/footer.php'; ?>
