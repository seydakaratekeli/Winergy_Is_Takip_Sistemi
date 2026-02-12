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

// 1. Hizmet Türü Dağılımı
$service_stats = $db->query("SELECT service_type, COUNT(*) as total FROM jobs GROUP BY service_type")->fetchAll(PDO::FETCH_ASSOC);

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
            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-file-earmark-excel me-1"></i>Excel'e Aktar
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

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">Hizmet Türü Dağılımı</div>
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

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">Genel İş Durumları</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <h4 class="fw-bold text-info"><?php echo $status_stats['Açıldı'] ?? 0; ?></h4>
                        <small class="text-muted">Açıldı</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="fw-bold text-warning"><?php echo $status_stats['Çalışılıyor'] ?? 0; ?></h4>
                        <small class="text-muted">Çalışılıyor</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="fw-bold text-secondary"><?php echo $status_stats['Beklemede'] ?? 0; ?></h4>
                        <small class="text-muted">Beklemede</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="fw-bold text-success"><?php echo $status_stats['Tamamlandı'] ?? 0; ?></h4>
                        <small class="text-muted">Tamamlandı</small>
                    </div>
                </div>
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

    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Personel İş Yükü ve Performans</div>
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
</script>

<?php include 'includes/footer.php'; ?>