<?php 
session_start();
// Tüm kullanıcılar raporlara erişebilir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php'; 

// Cache engelleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'includes/header.php'; 

// 1. Hizmet Türü Dağılımı - service_type JSON array olduğu için parse edip sayalım
$service_stats_raw = $db->query("SELECT service_type FROM jobs WHERE service_type IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$service_counts = [];

foreach ($service_stats_raw as $service_json) {
    $services = json_decode($service_json, true);
    if (is_array($services)) {
        foreach ($services as $service) {
            $service = trim($service);
            if (!empty($service)) {
                if (!isset($service_counts[$service])) {
                    $service_counts[$service] = 0;
                }
                $service_counts[$service]++;
            }
        }
    }
}

// En çok kullanılandan en aza sırala
arsort($service_counts);

// Format için yeniden düzenle
$service_stats = [];
foreach ($service_counts as $service => $count) {
    $service_stats[] = ['service_type' => $service, 'total' => $count];
}

// 1.5 Hizmet Türüne Göre Finansal Analiz
$service_financial_raw = $db->query("
    SELECT service_type, invoice_total_amount 
    FROM jobs 
    WHERE service_type IS NOT NULL 
        AND invoice_total_amount IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

$service_financial = [];
foreach ($service_financial_raw as $row) {
    $services = json_decode($row['service_type'], true);
    $amount = floatval($row['invoice_total_amount']);
    
    if (is_array($services)) {
        foreach ($services as $service) {
            $service = trim($service);
            if (!empty($service)) {
                if (!isset($service_financial[$service])) {
                    $service_financial[$service] = ['total_amount' => 0, 'job_count' => 0];
                }
                $service_financial[$service]['total_amount'] += $amount / count($services); // Tutarı hizmet sayısına böl
                $service_financial[$service]['job_count']++;
            }
        }
    }
}

// En yüksek gelirden en düşüğe sırala
uasort($service_financial, function($a, $b) {
    return $b['total_amount'] <=> $a['total_amount'];
});

// 2. Personel İş Yükü (Kimin üzerinde kaç iş var?) 
$staff_stats = $db->query("
    SELECT u.id, u.name, u.role, 
    COUNT(j.id) as total_jobs,
    COALESCE(SUM(CASE WHEN j.status = 'Tamamlandı' THEN 1 ELSE 0 END), 0) as completed_jobs
    FROM users u
    LEFT JOIN jobs j ON u.id = j.assigned_user_id
    WHERE u.is_active = 1
    GROUP BY u.id, u.name, u.role
    ORDER BY total_jobs DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Genel Durum Özeti
$status_stats = $db->query("SELECT status, COUNT(*) as total FROM jobs GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// 3.5 Finansal Analiz
$financial_stats = $db->query("
    SELECT 
        COUNT(*) as total_jobs,
        COUNT(CASE WHEN status = 'Tamamlandı' THEN 1 END) as completed_jobs,
        COALESCE(SUM(invoice_amount), 0) as total_invoice_amount,
        COALESCE(SUM(invoice_total_amount), 0) as total_amount,
        COALESCE(AVG(invoice_total_amount), 0) as avg_amount,
        COALESCE(SUM(CASE WHEN invoice_vat_included = 1 THEN 1 ELSE 0 END), 0) as vat_included_count,
        COALESCE(SUM(CASE WHEN invoice_vat_included = 0 THEN 1 ELSE 0 END), 0) as vat_excluded_count
    FROM jobs
    WHERE invoice_amount IS NOT NULL OR invoice_total_amount IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);

// Bu ay kesilen faturalar (invoice_date varsa onu kullan, yoksa created_at)
$this_month_invoices = $db->query("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(invoice_total_amount), 0) as total
    FROM jobs
    WHERE (
        (invoice_date IS NOT NULL AND MONTH(invoice_date) = MONTH(NOW()) AND YEAR(invoice_date) = YEAR(NOW()))
        OR
        (invoice_date IS NULL AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()))
    )
    AND invoice_total_amount IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);

// Bu ay tamamlanan işler
$this_month_completed = $db->query("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(invoice_total_amount), 0) as total
    FROM jobs
    WHERE status = 'Tamamlandı'
        AND MONTH(updated_at) = MONTH(NOW()) 
        AND YEAR(updated_at) = YEAR(NOW())
")->fetch(PDO::FETCH_ASSOC);

// 3.6 Müşteri Bazlı Gelir Analizi (Top 10)
$top_customers = $db->query("
    SELECT 
        c.name as customer_name,
        COUNT(j.id) as job_count,
        COALESCE(SUM(j.invoice_total_amount), 0) as total_revenue,
        COALESCE(AVG(j.invoice_total_amount), 0) as avg_revenue
    FROM customers c
    LEFT JOIN jobs j ON c.id = j.customer_id
    WHERE j.invoice_total_amount IS NOT NULL
    GROUP BY c.id, c.name
    HAVING total_revenue > 0
    ORDER BY total_revenue DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 3.65 Aylık Gelir Trendi (Son 12 Ay)
$monthly_revenue = $db->query("
    SELECT 
        DATE_FORMAT(COALESCE(invoice_date, created_at), '%Y-%m') as month,
        COUNT(*) as invoice_count,
        COALESCE(SUM(invoice_total_amount), 0) as total_revenue
    FROM jobs
    WHERE invoice_total_amount IS NOT NULL
        AND (invoice_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH) 
             OR (invoice_date IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)))
    GROUP BY DATE_FORMAT(COALESCE(invoice_date, created_at), '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3.7 Gecikmiş İşler
$delayed_jobs = $db->query("
    SELECT 
        j.id, j.title, j.due_date,
        c.name as customer_name,
        DATEDIFF(NOW(), j.due_date) as days_late
    FROM jobs j
    LEFT JOIN customers c ON j.customer_id = c.id
    WHERE j.due_date < NOW() 
        AND j.status NOT IN ('Tamamlandı', 'İptal')
    ORDER BY j.due_date ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Son 6 Ay Tamamlama Trendi
$monthly_trend = $db->query("
    SELECT 
        DATE_FORMAT(updated_at, '%Y-%m') as month,
        COUNT(*) as completed_count
    FROM jobs 
    WHERE status = 'Tamamlandı' 
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Boş ayları doldur (son 6 ay)
$months = [];
$counts = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months")); // Görsel format: "Jan 2026"
    
    // Bu ay için veri var mı?
    $found = false;
    foreach ($monthly_trend as $data) {
        if ($data['month'] == $month) {
            $counts[] = (int)$data['completed_count'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $counts[] = 0;
    }
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold" style="color: var(--dt-sec-color);">Yönetici Raporları</h2>
        <p class="text-muted">Winergy Technologies Genel Performans Analizi</p>
    </div>
    <div class="col-md-6 text-end">
        <!-- Export Butonları -->
        <div class="btn-group me-2" role="group">
            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-file-earmark-excel me-2"></i>Excel'e Aktar
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export-raporlar.php?type=overview">
                    <i class="bi bi-graph-up me-2"></i>Genel Durum Özeti
                </a></li>
                <li><a class="dropdown-item" href="export-raporlar.php?type=staff">
                    <i class="bi bi-people me-2"></i>Personel İş Yükü
                </a></li>
                <li><a class="dropdown-item" href="export-raporlar.php?type=service">
                    <i class="bi bi-gear me-2"></i>Hizmet Türü Dağılımı
                </a></li>
            </ul>
        </div>
        <button onclick="location.reload();" class="btn btn-winergy">
            <i class="bi bi-arrow-clockwise"></i> Yenile
        </button>
        <small class="d-block text-muted mt-2">Son Güncelleme: <?php echo date('d.m.Y H:i:s'); ?></small>
    </div>
</div>

<!-- Tab Navigasyonu -->
<ul class="nav nav-pills mb-4" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="financial-tab" data-bs-toggle="pill" data-bs-target="#financial" type="button" role="tab">
            <i class="bi bi-cash-coin me-2"></i>Finansal Raporlar
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="status-tab" data-bs-toggle="pill" data-bs-target="#status" type="button" role="tab">
            <i class="bi bi-kanban me-2"></i>İş Durumu
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="team-tab" data-bs-toggle="pill" data-bs-target="#team" type="button" role="tab">
            <i class="bi bi-people me-2"></i>Hizmet & Personel
        </button>
    </li>
</ul>

<!-- Tab İçerikleri -->
<div class="tab-content" id="reportTabsContent">
    
    <!-- 1. FİNANSAL RAPORLAR TAB -->
    <div class="tab-pane fade show active" id="financial" role="tabpanel">
<div class="row">
    <!-- Finansal Özet Kartları -->
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: white; border-radius: 0.75rem;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="opacity-75">Toplam Fatura Tutarı</small>
                        <h3 class="fw-bold mt-2">
                            <?php echo number_format($financial_stats['total_amount'], 2, ',', '.'); ?> ₺
                        </h3>
                        <small class="opacity-75">
                            <?php 
                            $invoiced_count = $financial_stats['total_jobs'] ?? 0;
                            echo "$invoiced_count İş";
                            ?>
                        </small>
                    </div>
                    <i class="bi bi-cash-coin" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #0d9488 0%, #0d7a70 100%); color: white; border-radius: 0.75rem;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="opacity-75">Bu Ay Kesilen Faturalar</small>
                        <h3 class="fw-bold mt-2">
                            <?php echo number_format($this_month_invoices['total'], 2, ',', '.'); ?> ₺
                        </h3>
                        <small class="opacity-75">
                            <?php echo $this_month_invoices['count']; ?> Fatura
                        </small>
                    </div>
                    <i class="bi bi-calendar-check" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; border-radius: 0.75rem;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="opacity-75">Bu Ay Tamamlanan</small>
                        <h3 class="fw-bold mt-2"><?php echo $this_month_completed['count']; ?></h3>
                        <small class="opacity-75">
                            <?php 
                            if ($this_month_completed['total'] > 0) {
                                echo number_format($this_month_completed['total'], 0, ',', '.') . ' ₺';
                            } else {
                                echo 'Değer girilmemiş';
                            }
                            ?>
                        </small>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; border-radius: 0.75rem;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="opacity-75">Tamamlanma Oranı</small>
                        <?php 
                        $total_jobs = (int)$financial_stats['total_jobs'];
                        $completed = (int)$financial_stats['completed_jobs'];
                        $completion_rate = $total_jobs > 0 ? ($completed / $total_jobs) * 100 : 0;
                        ?>
                        <h3 class="fw-bold mt-2"><?php echo number_format($completion_rate, 1); ?>%</h3>
                        <small class="opacity-75"><?php echo "$completed / $total_jobs İş"; ?></small>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2.5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Aylık Gelir Trendi Grafiği -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-graph-up-arrow me-2"></i>Aylık Gelir Trendi (Son 12 Ay)
            </div>
            <div class="card-body">
                <canvas id="monthlyRevenueChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Ortalama Fatura Tutarı -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-calculator me-2"></i>Fatura İstatistikleri
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Ortalama Fatura Tutarı</small>
                    <h4 class="fw-bold text-primary mb-0">
                        <?php echo number_format($financial_stats['avg_amount'], 2, ',', '.'); ?> ₺
                    </h4>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">En Yüksek Aylık Gelir</small>
                    <h5 class="fw-bold mb-0">
                        <?php 
                        $max_revenue = !empty($monthly_revenue) ? max(array_column($monthly_revenue, 'total_revenue')) : 0;
                        echo number_format($max_revenue, 2, ',', '.'); 
                        ?> ₺
                    </h5>
                </div>
                <div>
                    <small class="text-muted">Toplam Faturalı İş Sayısı</small>
                    <h5 class="fw-bold mb-0"><?php echo $financial_stats['total_jobs']; ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 Müşteriler (Gelir Bazlı) -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-trophy me-2"></i>En Çok Gelir Getiren Müşteriler
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" width="5%">#</th>
                                <th width="35%">Müşteri Adı</th>
                                <th width="15%">İş Sayısı</th>
                                <th width="20%">Toplam Gelir</th>
                                <th width="25%">Ortalama Fatura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($top_customers)): ?>
                                <?php 
                                $rank = 1;
                                foreach($top_customers as $customer): 
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if($rank <= 3): ?>
                                            <span class="badge <?php 
                                                echo $rank == 1 ? 'bg-warning text-dark' : ($rank == 2 ? 'bg-secondary' : 'bg-danger');
                                            ?>"><?php echo $rank; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo $rank; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong></td>
                                    <td><span class="badge bg-primary"><?php echo $customer['job_count']; ?></span></td>
                                    <td>
                                        <strong class="text-success">
                                            <?php echo number_format($customer['total_revenue'], 2, ',', '.'); ?> ₺
                                        </strong>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo number_format($customer['avg_revenue'], 2, ',', '.'); ?> ₺
                                    </td>
                                </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Henüz faturalı iş kaydı yok.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
    </div> <!-- Finansal tab sonu -->
    
    <!-- 2. İŞ DURUMU TAB -->
    <div class="tab-pane fade" id="status" role="tabpanel">
<div class="row">
    <!-- İş Durumu Özeti (Birleştirilmiş Kart) -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pie-chart me-2"></i>İş Durumu Dağılımı
            </div>
            <div class="card-body">
                <!-- Durum Sayıları -->
                <div class="row text-center mb-3">
                    <div class="col-3">
                        <div class="p-2 rounded" style="background: rgba(13, 202, 240, 0.1);">
                            <h5 class="fw-bold mb-1" style="color: #0dcaf0;"><?php echo $status_stats['Açıldı'] ?? 0; ?></h5>
                            <small class="text-muted">Açıldı</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded" style="background: rgba(255, 193, 7, 0.1);">
                            <h5 class="fw-bold mb-1" style="color: #ffc107;"><?php echo $status_stats['Çalışılıyor'] ?? 0; ?></h5>
                            <small class="text-muted">Çalışılıyor</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded" style="background: rgba(108, 117, 125, 0.1);">
                            <h5 class="fw-bold mb-1" style="color: #6c757d;"><?php echo $status_stats['Beklemede'] ?? 0; ?></h5>
                            <small class="text-muted">Beklemede</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 rounded" style="background: rgba(25, 135, 84, 0.1);">
                            <h5 class="fw-bold mb-1" style="color: #198754;"><?php echo $status_stats['Tamamlandı'] ?? 0; ?></h5>
                            <small class="text-muted">Tamamlandı</small>
                        </div>
                    </div>
                </div>
                
                <!-- Grafik -->
                <div class="mt-3">
                    <canvas id="statusDistributionChart" style="max-height: 220px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Gecikmiş İşler -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-exclamation-triangle me-2"></i>Gecikmiş İşler (Son 10)
            </div>
            <div class="card-body p-0">
                <?php if(!empty($delayed_jobs)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Proje Adı</th>
                                    <th>Müşteri</th>
                                    <th>Son Tarih</th>
                                    <th class="text-danger fw-bold text-end pe-3">Gecikme</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($delayed_jobs as $job): ?>
                                    <tr class="<?php echo $job['days_late'] >= 7 ? 'table-danger' : ($job['days_late'] >= 3 ? 'table-warning' : ''); ?>">
                                        <td class="ps-3">
                                            <a href="is-detay.php?id=<?php echo $job['id']; ?>" class="text-decoration-none" title="Detay Görüntüle">
                                                <?php echo htmlspecialchars(substr($job['title'], 0, 20)); ?>...
                                            </a>
                                        </td>
                                        <td><small><?php echo htmlspecialchars(substr($job['customer_name'] ?? 'N/A', 0, 15)); ?></small></td>
                                        <td><small><?php echo date('d.m', strtotime($job['due_date'])); ?></small></td>
                                        <td class="text-danger fw-bold text-end pe-3">
                                            <span class="badge" style="background-color: <?php echo $job['days_late'] >= 7 ? '#dc3545' : '#ff6b6b'; ?>">
                                                <?php echo $job['days_late']; ?> gün
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted py-5">
                        <i class="bi bi-check-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                        <br>Gecikmiş Proje Yok
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Aylık Tamamlama Trendi -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up-arrow me-2"></i>Son 6 Ay İş Tamamlama Trendi</span>
                <span class="badge bg-success"><?php echo array_sum($counts); ?> Toplam Tamamlanan</span>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>
    </div> <!-- İş Durumu tab sonu -->
    
    <!-- 3. HİZMET & PERSONEL TAB -->
    <div class="tab-pane fade" id="team" role="tabpanel">
<div class="row">
    <!-- Hizmet Türü Dağılımı -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-gear me-2"></i>Hizmet Türü Dağılımı
            </div>
            <div class="card-body">
                <?php if(!empty($service_stats)): ?>
                    <?php 
                    $total_services = array_sum(array_column($service_stats, 'total'));
                    foreach($service_stats as $s): 
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small fw-bold"><?php echo htmlspecialchars($s['service_type']); ?></span>
                                <span class="small text-muted"><?php echo $s['total']; ?> İş</span>
                            </div>
                            <div class="progress" style="height: 1rem;">
                                <div class="progress-bar" style="width: <?php echo ($s['total'] / $total_services) * 100; ?>%; background: var(--dt-priGrd-color);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Henüz iş kaydı yok.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Hizmet Türüne Göre Finansal Analiz -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-cash-coin me-2"></i>Hizmet Türü Gelir Analizi
            </div>
            <div class="card-body p-3" style="max-height: 400px; overflow-y: auto;">
                <?php if(!empty($service_financial)): ?>
                    <?php foreach($service_financial as $service => $data): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background: #f8f9fa;">
                            <div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($service); ?></div>
                                <small class="text-muted"><?php echo $data['job_count']; ?> İş</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success" style="font-size: 1.1rem;">
                                    <?php echo number_format($data['total_amount'], 2, ',', '.'); ?> ₺
                                </div>
                                <small class="text-muted">
                                    Ort: <?php echo number_format($data['total_amount'] / $data['job_count'], 2, ',', '.'); ?> ₺
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-4">Henüz faturalı iş kaydı yok.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Personel İş Yükü -->
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-people me-2"></i>Personel İş Yükü ve Performans
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Personel Adı</th>
                            <th>Rol</th>
                            <th>Toplam Atanan İş</th>
                            <th>Tamamlanan İş</th>
                            <th>Başarı Oranı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($staff_stats)): ?>
                            <?php foreach($staff_stats as $staff): 
                                $total = intval($staff['total_jobs'] ?? 0);
                                $completed = intval($staff['completed_jobs'] ?? 0);
                                $rate = $total > 0 ? ($completed / $total) * 100 : 0;
                            ?>
                            <tr>
                                <td class="ps-4"><strong><?php echo htmlspecialchars($staff['name']); ?></strong></td>
                                <td>
                                    <span class="badge <?php 
                                        $role_colors = [
                                            'admin' => 'bg-danger',
                                            'operasyon' => 'bg-warning text-dark',
                                            'danisman' => 'bg-info'
                                        ];
                                        echo $role_colors[$staff['role']] ?? 'bg-secondary';
                                    ?>">
                                        <?php 
                                        $role_tr = [
                                            'admin' => 'Yönetici',
                                            'operasyon' => 'Operasyon',
                                            'danisman' => 'Danışman'
                                        ];
                                        echo $role_tr[$staff['role']] ?? $staff['role'];
                                        ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-primary"><?php echo $total; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $completed; ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 small fw-bold"><?php echo number_format($rate, 1); ?>%</span>
                                        <div class="progress w-100" style="height: 0.8rem;">
                                            <div class="progress-bar" style="width: <?php echo $rate; ?>%; background: var(--dt-priGrd-color);"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Henüz personel verisi yok.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
    </div> <!-- Hizmet & Personel tab sonu -->
    
</div> <!-- Tab content sonu -->

<!-- Chart.js Kütüphanesi -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Aylık Tamamlama Trendi Grafiği
const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
const monthlyTrendChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Tamamlanan İşler',
            data: <?php echo json_encode($counts); ?>,
            backgroundColor: 'rgba(20, 184, 166, 0.1)',
            borderColor: 'rgba(20, 184, 166, 1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgba(20, 184, 166, 1)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(14, 20, 34, 0.9)',
                padding: 12,
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(20, 184, 166, 1)',
                borderWidth: 1,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + ' İş Tamamlandı';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    color: '#666666',
                    font: {
                        size: 12
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                ticks: {
                    color: '#666666',
                    font: {
                        size: 12,
                        weight: 'bold'
                    }
                },
                grid: {
                    display: false
                }
            }
        }
    }
});

// İş Durumu Pie Chart
const pieCtx = document.getElementById('statusDistributionChart').getContext('2d');
const statusDistributionChart = new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['Açıldı', 'Çalışılıyor', 'Beklemede', 'Tamamlandı', 'İptal'],
        datasets: [{
            data: [
                <?php echo $status_stats['Açıldı'] ?? 0; ?>,
                <?php echo $status_stats['Çalışılıyor'] ?? 0; ?>,
                <?php echo $status_stats['Beklemede'] ?? 0; ?>,
                <?php echo $status_stats['Tamamlandı'] ?? 0; ?>,
                <?php echo $status_stats['İptal'] ?? 0; ?>
            ],
            backgroundColor: [
                'rgba(13, 202, 240, 0.8)',      // Açıldı - info
                'rgba(255, 193, 7, 0.8)',       // Çalışılıyor - warning
                'rgba(108, 117, 125, 0.8)',    // Beklemede - secondary
                'rgba(40, 167, 69, 0.8)',      // Tamamlandı - success
                'rgba(220, 53, 69, 0.8)'       // İptal - danger
            ],
            borderColor: [
                'rgba(13, 202, 240, 1)',
                'rgba(255, 193, 7, 1)',
                'rgba(108, 117, 125, 1)',
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 15,
                    font: {
                        size: 12,
                        weight: 'bold'
                    },
                    usePointStyle: true
                }
            },
            tooltip: {
                backgroundColor: 'rgba(14, 20, 34, 0.9)',
                padding: 12,
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(20, 184, 166, 1)',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const value = context.parsed;
                        const percentage = ((value / total) * 100).toFixed(1);
                        return value + ' İş (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Aylık Gelir Trendi Grafiği
const revenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
const monthlyRevenueChart = new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: <?php 
            $revenue_months = array_column($monthly_revenue, 'month');
            // Ay isimlerini Türkçe formatla
            $formatted_months = array_map(function($month) {
                $date = DateTime::createFromFormat('Y-m', $month);
                return $date ? $date->format('M Y') : $month;
            }, $revenue_months);
            echo json_encode($formatted_months);
        ?>,
        datasets: [{
            label: 'Gelir (₺)',
            data: <?php echo json_encode(array_column($monthly_revenue, 'total_revenue')); ?>,
            backgroundColor: 'rgba(20, 184, 166, 0.8)',
            borderColor: 'rgba(20, 184, 166, 1)',
            borderWidth: 2,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(14, 20, 34, 0.9)',
                padding: 12,
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(20, 184, 166, 1)',
                borderWidth: 1,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        const value = context.parsed.y;
                        return value.toLocaleString('tr-TR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }) + ' ₺';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#666666',
                    font: {
                        size: 11
                    },
                    callback: function(value) {
                        return value.toLocaleString('tr-TR') + ' ₺';
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                ticks: {
                    color: '#666666',
                    font: {
                        size: 11,
                        weight: 'bold'
                    }
                },
                grid: {
                    display: false
                }
            }
        }
    }
});

// Tab değişiminde grafikleri yeniden çiz
document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(button => {
    button.addEventListener('shown.bs.tab', () => {
        monthlyTrendChart.resize();
        statusDistributionChart.resize();
        monthlyRevenueChart.resize();
    });
});
</script>

<style>
.nav-pills .nav-link {
    border-radius: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s ease;
}

.nav-pills .nav-link:hover {
    background-color: rgba(20, 184, 166, 0.1);
    color: #14b8a6;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
}

.tab-content {
    margin-top: 1rem;
}
</style>

<?php include 'includes/footer.php'; ?>