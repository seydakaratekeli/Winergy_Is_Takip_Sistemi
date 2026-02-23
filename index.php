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
$active_filters = []; // Aktif filtreleri takip et

// Hizmet Türü Filtresi
if (!empty($_GET['service_type'])) {
    // JSON array içinde hizmet türü arama
    $where_clauses[] = "j.service_type LIKE ?";
    $params[] = '%' . $_GET['service_type'] . '%';
    $active_filters['service_type'] = $_GET['service_type'];
}

// Durum Filtresi - İptal dahil
if (!empty($_GET['status'])) {
    $where_clauses[] = "j.status = ?";
    $params[] = $_GET['status'];
    $active_filters['status'] = $_GET['status'];
}

// Müşteri Filtresi
if (!empty($_GET['customer_id'])) {
    $where_clauses[] = "j.customer_id = ?";
    $params[] = $_GET['customer_id'];
    $active_filters['customer_id'] = $_GET['customer_id'];
}

// Atanan Personel Filtresi
if (!empty($_GET['assigned_user_id'])) {
    if ($_GET['assigned_user_id'] == 'unassigned') {
        $where_clauses[] = "j.assigned_user_id IS NULL";
    } else {
        $where_clauses[] = "j.assigned_user_id = ?";
        $params[] = $_GET['assigned_user_id'];
    }
    $active_filters['assigned_user_id'] = $_GET['assigned_user_id'];
}

// Fatura Durumu Filtresi
if (!empty($_GET['invoice_status'])) {
    if ($_GET['invoice_status'] == 'with_invoice') {
        $where_clauses[] = "(j.invoice_amount IS NOT NULL OR j.invoice_total_amount IS NOT NULL)";
    } elseif ($_GET['invoice_status'] == 'without_invoice') {
        $where_clauses[] = "(j.invoice_amount IS NULL AND j.invoice_total_amount IS NULL)";
    }
    $active_filters['invoice_status'] = $_GET['invoice_status'];
}

// Müşterileri çek (dropdown için)
$customers = $db->query("SELECT id, name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Başarılı!</strong> Yeni iş kaydı başarıyla oluşturuldu.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
<div class="row mb-5">
    <div class="col-12">
        <div class="card border-0" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(20, 184, 166, 0.15);">
            <div class="card-body p-5">
                <div class="row align-items-center">
                    <div class="col-lg-5 mb-3 mb-lg-0">
                        <h2 class="fw-bold mb-2" style="color: white; letter-spacing: -0.5px;">
                            <i class="bi bi-clipboard-check me-2"></i>İş Yönetim Paneli
                        </h2>
                        <p class="mb-0" style="opacity: 0.9; font-size: 0.95rem;">
                            Merhaba, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> — <?php echo date('d.m.Y \c\u\m\a H:i'); ?>
                        </p>
                    </div>
                    
                    <div class="col-lg-7">
                        <div class="row g-2 justify-content-lg-end">
                            <!-- Listelenen (Tüm İşler) -->
                            <div class="col-auto">
                                <a href="index.php" class="text-decoration-none text-white" title="Tüm işleri göster">
                                    <div class="d-flex align-items-center status-card" style="background: rgba(255,255,255,<?php echo !isset($_GET['status']) ? '0.35' : '0.15'; ?>); padding: 0.75rem 1.25rem; border-radius: 0.5rem; border: 2px solid rgba(255,255,255,<?php echo !isset($_GET['status']) ? '0.6' : '0'; ?>); cursor: pointer; transition: all 0.3s ease;">
                                        <i class="bi bi-list-check" style="color: white; font-size: 1.25rem; margin-right: 0.5rem;"></i>
                                        <div>
                                            <small style="opacity: 0.85; display: block; font-size: 0.8rem;">Listelenen</small>
                                            <span class="fw-bold" style="font-size: 1.1rem;"><?php echo count($jobs); ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- Açıldı -->
                            <div class="col-auto">
                                <a href="?status=Açıldı" class="text-decoration-none text-white" title="Açıldı durumundaki işleri göster">
                                    <div class="d-flex align-items-center status-card" style="background: rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Açıldı' ? '0.35' : '0.15'; ?>); padding: 0.75rem 1.25rem; border-radius: 0.5rem; border: 2px solid rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Açıldı' ? '0.6' : '0'; ?>); cursor: pointer; transition: all 0.3s ease;">
                                        <i class="bi bi-folder-check" style="color: white; font-size: 1.25rem; margin-right: 0.5rem;"></i>
                                        <div>
                                            <small style="opacity: 0.85; display: block; font-size: 0.8rem;">Açıldı</small>
                                            <span class="fw-bold" style="font-size: 1.1rem;"><?php echo $stats['Açıldı'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- Çalışılıyor -->
                            <div class="col-auto">
                                <a href="?status=Çalışılıyor" class="text-decoration-none text-white" title="Çalışılıyor durumundaki işleri göster">
                                    <div class="d-flex align-items-center status-card" style="background: rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Çalışılıyor' ? '0.35' : '0.15'; ?>); padding: 0.75rem 1.25rem; border-radius: 0.5rem; border: 2px solid rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Çalışılıyor' ? '0.6' : '0'; ?>); cursor: pointer; transition: all 0.3s ease;">
                                        <i class="bi bi-hourglass-split" style="color: white; font-size: 1.25rem; margin-right: 0.5rem;"></i>
                                        <div>
                                            <small style="opacity: 0.85; display: block; font-size: 0.8rem;">Çalışılıyor</small>
                                            <span class="fw-bold" style="font-size: 1.1rem;"><?php echo $stats['Çalışılıyor'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- Beklemede -->
                            <div class="col-auto">
                                <a href="?status=Beklemede" class="text-decoration-none text-white" title="Beklemede durumundaki işleri göster">
                                    <div class="d-flex align-items-center status-card" style="background: rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Beklemede' ? '0.35' : '0.15'; ?>); padding: 0.75rem 1.25rem; border-radius: 0.5rem; border: 2px solid rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Beklemede' ? '0.6' : '0'; ?>); cursor: pointer; transition: all 0.3s ease;">
                                        <i class="bi bi-pause-circle" style="color: white; font-size: 1.25rem; margin-right: 0.5rem;"></i>
                                        <div>
                                            <small style="opacity: 0.85; display: block; font-size: 0.8rem;">Beklemede</small>
                                            <span class="fw-bold" style="font-size: 1.1rem;"><?php echo $stats['Beklemede'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- Tamamlandı -->
                            <div class="col-auto">
                                <a href="?status=Tamamlandı" class="text-decoration-none text-white" title="Tamamlanmış işleri göster">
                                    <div class="d-flex align-items-center status-card" style="background: rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Tamamlandı' ? '0.35' : '0.15'; ?>); padding: 0.75rem 1.25rem; border-radius: 0.5rem; border: 2px solid rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'Tamamlandı' ? '0.6' : '0'; ?>); cursor: pointer; transition: all 0.3s ease;">
                                        <i class="bi bi-check-circle" style="color: white; font-size: 1.25rem; margin-right: 0.5rem;"></i>
                                        <div>
                                            <small style="opacity: 0.85; display: block; font-size: 0.8rem;">Tamamlandı</small>
                                            <span class="fw-bold" style="font-size: 1.1rem;"><?php echo $stats['Tamamlandı'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- İptal -->
                            <div class="col-auto">
                                <a href="?status=İptal" class="text-decoration-none text-white" title="İptal edilen işleri göster">
                                    <div class="d-flex align-items-center status-card" style="background: rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'İptal' ? '0.35' : '0.15'; ?>); padding: 0.75rem 1.25rem; border-radius: 0.5rem; border: 2px solid rgba(255,255,255,<?php echo ($_GET['status'] ?? '') == 'İptal' ? '0.6' : '0'; ?>); cursor: pointer; transition: all 0.3s ease;">
                                        <i class="bi bi-x-circle" style="color: white; font-size: 1.25rem; margin-right: 0.5rem;"></i>
                                        <div>
                                            <small style="opacity: 0.85; display: block; font-size: 0.8rem;">İptal</small>
                                            <span class="fw-bold" style="font-size: 1.1rem;"><?php echo $stats['İptal'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtre Bölümü -->
<div class="card border-0 mb-4" style="border-radius: 0.75rem; background: #f8f9fa; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0" style="font-size: 1rem; letter-spacing: 0.5px; color: #0d9488;">
                <i class="bi bi-funnel me-2"></i>GELIŞMIŞ FILTRELEME
            </h6>
            <?php if(!empty($active_filters)): ?>
                <a href="index.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Tüm Filtreleri Temizle
                </a>
            <?php endif; ?>
        </div>

        <!-- Aktif Filtreleri Göster -->
        <?php if(!empty($active_filters)): ?>
            <div class="mb-3 p-3" style="background-color: #f0f9ff; border-radius: 0.75rem; border-left: px solid var(--dt-pri-color);">
                <small class="text-muted d-block mb-2"><i class="bi bi-check-circle me-1"></i>Aktif Filtreler:</small>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach($active_filters as $key => $value): ?>
                        <span class="badge bg-info text-white py-2 px-3">
                            <?php 
                            $filter_labels = [
                                'service_type' => 'Hizmet',
                                'status' => 'Durum',
                                'customer_id' => 'Müşteri',
                                'assigned_user_id' => 'Personel',
                                'invoice_status' => 'Fatura'
                            ];
                            echo $filter_labels[$key] ?? $key; 
                            ?>: 
                            <strong><?php 
                                if ($key === 'customer_id') {
                                    foreach($customers as $c) {
                                        if ($c['id'] == $value) {
                                            echo htmlspecialchars($c['name']);
                                            break;
                                        }
                                    }
                                } elseif ($key === 'assigned_user_id') {
                                    if ($value == 'unassigned') {
                                        echo 'Atanmadı';
                                    } else {
                                        foreach($users as $u) {
                                            if ($u['id'] == $value) {
                                                echo htmlspecialchars($u['name']);
                                                break;
                                            }
                                        }
                                    }
                                } elseif ($key === 'invoice_status') {
                                    echo $value == 'with_invoice' ? 'Faturalı' : 'Faturasız';
                                } else {
                                    echo htmlspecialchars($value);
                                }
                            ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="GET" class="row g-2 align-items-end">
            <!-- Hizmet Türü -->
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-muted mb-2">
                    <i class="bi bi-gear me-1"></i>Hizmet
                </label>
                <select name="service_type" class="form-select form-select-sm border-light-subtle" style="border-radius: 0.6rem;">
                    <option value="">Tüm Hizmetler</option>
                    <option value="Enerji Etüdü" <?php echo ($_GET['service_type'] ?? '') == 'Enerji Etüdü' ? 'selected' : ''; ?>>Enerji Etüdü</option>
                    <option value="ISO 50001" <?php echo ($_GET['service_type'] ?? '') == 'ISO 50001' ? 'selected' : ''; ?>>ISO 50001</option>
                    <option value="EKB" <?php echo ($_GET['service_type'] ?? '') == 'EKB' ? 'selected' : ''; ?>>EKB</option>
                    <option value="Enerji Yöneticisi" <?php echo ($_GET['service_type'] ?? '') == 'Enerji Yöneticisi' ? 'selected' : ''; ?>>Enerji Yöneticisi</option>
                    <option value="VAP" <?php echo ($_GET['service_type'] ?? '') == 'VAP' ? 'selected' : ''; ?>>VAP</option>
                    <option value="Danışmanlık" <?php echo ($_GET['service_type'] ?? '') == 'Danışmanlık' ? 'selected' : ''; ?>>Danışmanlık</option>
                    <option value="Rapor Onay" <?php echo ($_GET['service_type'] ?? '') == 'Rapor Onay' ? 'selected' : ''; ?>>Rapor Onay</option>
                    <option value="Bakım" <?php echo ($_GET['service_type'] ?? '') == 'Bakım' ? 'selected' : ''; ?>>Bakım</option>
                </select>
            </div>

            <!-- İş Durumu -->
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-muted mb-2">
                    <i class="bi bi-flag me-1"></i>Durum
                </label>
                <select name="status" class="form-select form-select-sm border-light-subtle" style="border-radius: 0.6rem;">
                    <option value="">Tüm Durumlar</option>
                    <option value="Açıldı" <?php echo ($_GET['status'] ?? '') == 'Açıldı' ? 'selected' : ''; ?>>Açıldı</option>
                    <option value="Çalışılıyor" <?php echo ($_GET['status'] ?? '') == 'Çalışılıyor' ? 'selected' : ''; ?>>Çalışılıyor</option>
                    <option value="Beklemede" <?php echo ($_GET['status'] ?? '') == 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                    <option value="Tamamlandı" <?php echo ($_GET['status'] ?? '') == 'Tamamlandı' ? 'selected' : ''; ?>>Tamamlandı</option>
                    <option value="İptal" <?php echo ($_GET['status'] ?? '') == 'İptal' ? 'selected' : ''; ?>>İptal</option>
                </select>
            </div>

            <!-- Müşteri -->
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-muted mb-2">
                    <i class="bi bi-building me-1"></i>Müşteri
                </label>
                <select name="customer_id" class="form-select form-select-sm border-light-subtle" style="border-radius: 0.6rem;">
                    <option value="">Tüm Müşteriler</option>
                    <?php foreach($customers as $cust): ?>
                        <option value="<?php echo $cust['id']; ?>" <?php echo ($_GET['customer_id'] ?? '') == $cust['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(substr($cust['name'], 0, 20)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Atanan Personel -->
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-muted 1mb-2">
                    <i class="bi bi-person me-1"></i>Personel
                </label>
                <select name="assigned_user_id" class="form-select form-select-sm border-light-subtle" style="border-radius: 0.6rem;">
                    <option value="">Hepsi</option>
                    <option value="unassigned" <?php echo ($_GET['assigned_user_id'] ?? '') == 'unassigned' ? 'selected' : ''; ?>>Atanmadı</option>
                    <?php 
                    $role_tr = [
                        'admin' => 'Y',
                        'operasyon' => 'O',
                        'danisman' => 'D'
                    ];
                    foreach($users as $u): 
                    ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($_GET['assigned_user_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(substr($u['name'], 0, 15)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fatura Durumu -->
            <div class="col-lg-2">
                <label class="form-label small fw-bold text-muted 211mb-2">
                    <i class="bi bi-receipt me-1"></i>Fatura
                </label>
                <select name="invoice_status" class="form-select form-select-sm border-light-subtle" style="border-radius: 0.6rem;">
                    <option value="">Tüm İşler</option>
                    <option value="with_invoice" <?php echo ($_GET['invoice_status'] ?? '') == 'with_invoice' ? 'selected' : ''; ?>>Faturalı</option>
                    <option value="without_invoice" <?php echo ($_GET['invoice_status'] ?? '') == 'without_invoice' ? 'selected' : ''; ?>>Faturasız</option>
                </select>
            </div>

            <!-- Arama Butonları -->
            <div class="col-lg-2 d-flex gap-1 align-items-end">
                <button type="submit" class="btn btn-winergy btn-sm fw-bold shadow-sm flex-grow-1" style="border-radius: 0.3rem;">
                    <i class="bi bi-search me-1"></i>Filtrele
                </button>
                <a href="index.php" class="btn btn-outline-secondary btn-sm px-2 shadow-sm" style="border-radius: 0.6rem;" title="Filtreleri Temizle">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>
<!-- Toplu İşlemler Paneli -->
<div class="card border-0 mb-3" id="bulkActionsPanel" style="display: none; border-radius: 0.75rem; border-left: 4px solid var(--dt-pri-color); background: #f0f9ff; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
    <div class="card-body py-3 px-4">
        <form action="toplu-islemler.php" method="POST" id="bulkActionsForm">
            <?php echo csrf_input(); // CSRF Token ?>
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold" style="font-size: 0.9rem; color: #0d9488;">Toplu İşlem</label>
                    <select name="action" id="bulkAction" class="form-select form-select-sm" required>
                        <option value="">-- İşlem Seçin --</option>
                        <option value="change_status">Durum Değiştir</option>
                        <option value="assign_user">Personel Ata</option>
                        <?php if($_SESSION['user_role'] === 'admin'): ?>
                            <option value="delete">İptal Et (Soft Delete)</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Durum değiştirme -->
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
                
                <!-- Personel atama -->
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
                
                <!-- Hizmet türü değiştirme -->
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

<!-- İş Listesi Tablosu -->
<form id="jobsTableForm">
<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <h6 class="mb-0 fw-bold" style="font-size: 1rem; letter-spacing: 0.5px; color: #0d9488;">
                <i class="bi bi-list-ul me-2"></i>İŞ KAYITLARI
            </h6>
            <a href="is-ekle.php" class="btn btn-winergy btn-sm fw-bold shadow" style="border-radius: 0.5rem; padding: 0.5rem 1.25rem;">
                <i class="bi bi-plus-circle-fill me-1"></i>Yeni İş Ekle
            </a>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <!-- Export Butonları -->
            <button type="button" class="btn btn-success" id="exportSelectedBtn" style="display: none;" onclick="exportSelected()">
                <i class="bi bi-file-earmark-excel me-2"></i>Seçilenleri Aktar (<span id="exportCount">0</span>)
            </button>
            <a href="export-isler.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel me-2"></i>Excel'e Aktar
            </a>
        </div>
    </div>
    <table class="table table-hover align-middle">
        <thead style="background-color: #f8f9fa; border-top: 2px solid #14b8a6;">
            <tr>
                <th style="width: 40px; color: #0d9488;">
                    <input type="checkbox" class="form-check-input" id="selectAll">
                </th>
                <th style="color: #white;"><i class="bi bi-building"></i> Müşteri</th>
                <th style="color: #white;"><i class="bi bi-gear"></i> Hizmet</th>
                <th style="color: #white;"><i class="bi bi-person"></i> Sorumlu</th>
                <th style="color: #white;"><i class="bi bi-calendar"></i> Teslim Tarihi</th>
                <th style="color: #white;"><i class="bi bi-receipt"></i> Fatura</th>
                <th style="color: #white;"><i class="bi bi-flag"></i> Durum</th>
                 <th style="color: #white;"><i class="text-center"><i class="bi bi-tools"></i> İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($jobs as $job): 
                $is_completed = in_array($job['status'], ['Tamamlandı', 'İptal']);
                
                // Gün farkını hesapla
                $days_diff = null;
                if (!empty($job['due_date'])) {
                    $due_date_obj = new DateTime($job['due_date']);
                    $today_obj = new DateTime($today);
                    $diff = $today_obj->diff($due_date_obj);
                    $days_diff = (int)$diff->format('%r%a'); // + gelecek, - geçmiş
                }
                
                // Durum belirle
                $is_delayed = ($days_diff !== null && $days_diff < 0 && !$is_completed);
                $is_today = ($days_diff !== null && $days_diff == 0 && !$is_completed);
                $is_urgent = ($days_diff !== null && $days_diff > 0 && $days_diff <= 3 && !$is_completed); // 1-3 gün kaldı
                $is_soon = ($days_diff !== null && $days_diff > 3 && $days_diff <= 7 && !$is_completed); // 4-7 gün kaldı
                
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
                <td>
                    <?php 
                    $service_types = [];
                    if (!empty($job['service_type'])) {
                        // JSON decode et
                        $decoded = json_decode($job['service_type'], true);
                        
                        // Decode başarılı ve array mı kontrol et
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $service_types = $decoded;
                        } else {
                            // JSON değilse, virgülle ayrılmış string olabilir
                            if (strpos($job['service_type'], ',') !== false) {
                                $service_types = array_map('trim', explode(',', $job['service_type']));
                            } else {
                                // Tek bir değer
                                $service_types = [$job['service_type']];
                            }
                        }
                    }
                    
                    if (!empty($service_types)) {
                        foreach($service_types as $service): 
                            if (!empty(trim($service))):
                    ?>
                        <span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 0.85rem;">
                            <?php echo htmlspecialchars(trim($service)); ?>
                        </span>
                    <?php 
                            endif;
                        endforeach;
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    ?>
                </td>
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
                    <?php echo !empty($job['due_date']) ? date('d.m.Y', strtotime($job['due_date'])) : '-'; ?>
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
                    <?php if(!empty($job['invoice_amount']) || !empty($job['invoice_total_amount'])): ?>
                        <?php if(!empty($job['invoice_total_amount'])): ?>
                            <span class="text-success fw-bold" data-bs-toggle="tooltip" title="Toplam Tutar">
                                <?php echo number_format($job['invoice_total_amount'], 2, ',', '.'); ?> ₺
                            </span>
                        <?php elseif(!empty($job['invoice_amount'])): ?>
                            <span class="text-info fw-bold" data-bs-toggle="tooltip" title="<?php echo isset($job['invoice_vat_included']) && $job['invoice_vat_included'] == 1 ? 'KDV Dahil' : 'KDV Hariç'; ?>">
                                <?php echo number_format($job['invoice_amount'], 2, ',', '.'); ?> ₺
                            </span>
                        <?php endif; ?>
                        <?php if(!empty($job['invoice_date'])): ?>
                            <br><small class="text-muted"><i class="bi bi-calendar-event"></i> <?php echo date('d.m.Y', strtotime($job['invoice_date'])); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">-</span>
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
                <tr><td colspan="8" class="text-center py-4">Filtreye uygun iş bulunamadı.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</form>

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

// Sayfa tamamen yüklendiğinde event listener'ları ekle
window.addEventListener('load', function() {
    console.log('Sayfa yüklendi, event listener\'lar ekleniyor...');
    
    // Tüm seçimi yönet
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        console.log('selectAll checkbox bulundu');
        selectAllCheckbox.addEventListener('change', function() {
            console.log('selectAll değişti:', this.checked);
            const checkboxes = document.querySelectorAll('.job-checkbox');
            console.log('Toplam checkbox sayısı:', checkboxes.length);
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
            toggleBulkPanel();
        });
    } else {
        console.error('selectAll checkbox bulunamadı!');
    }

    // Parent element üzerinden event delegation
    const jobsForm = document.getElementById('jobsTableForm');
    if (jobsForm) {
        console.log('jobsTableForm bulundu');
        jobsForm.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('job-checkbox')) {
                console.log('Bir checkbox değişti:', e.target.value);
                updateSelectedCount();
                toggleBulkPanel();
                
                // Eğer tümü seçiliyse, selectAll'ı işaretle
                const allCheckboxes = document.querySelectorAll('.job-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.job-checkbox:checked');
                const selectAll = document.getElementById('selectAll');
                
                if (selectAll && allCheckboxes.length > 0) {
                    selectAll.checked = allCheckboxes.length === checkedCheckboxes.length;
                }
            }
        });
    } else {
        console.error('jobsTableForm bulunamadı!');
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
            // Seçili checkbox'ların değerlerini form'a ekle
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

// Seçili kayıtları export et (global fonksiyon - button onclick'ten çağrılıyor)
function exportSelected() {
    const checkedBoxes = document.querySelectorAll('.job-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Lütfen en az bir iş seçin!');
        return;
    }
    
    // Form oluştur ve gönder
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export-isler.php<?php echo !empty($_GET) ? '?' . http_build_query($_GET) : ''; ?>';
    
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