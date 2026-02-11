<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Sadece admin erişebilir
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

// Filtreleme parametreleri
$type = $_GET['type'] ?? 'activity';
$month = $_GET['month'] ?? date('Y-m');
$user_filter = $_GET['user'] ?? '';
$level_filter = $_GET['level'] ?? '';
$action_filter = $_GET['action'] ?? '';

// Logları oku
$logs = read_logs($type, $month, 2000);

// Filtreleme uygula
if ($user_filter) {
    $logs = array_filter($logs, fn($log) => stripos($log['user_name'] ?? '', $user_filter) !== false);
}

if ($level_filter) {
    $logs = array_filter($logs, fn($log) => ($log['level'] ?? '') === $level_filter);
}

if ($action_filter) {
    $logs = array_filter($logs, fn($log) => stripos($log['action'] ?? '', $action_filter) !== false);
}

// İstatistikler
$stats = get_log_statistics();

// Log temizleme
if (isset($_POST['cleanup_logs'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $days = intval($_POST['cleanup_days'] ?? 30);
    $deleted = cleanup_old_logs($days);
    $success_message = "$deleted adet eski log dosyası temizlendi.";
}
?>

<!-- İstatistikler -->
<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="fw-bold mb-3" style="color: var(--dt-sec-color);">
            <i class="bi bi-bar-chart-line"></i> Sistem Logları ve İstatistikler
        </h3>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-1" style="color: var(--dt-pri-color);"><?php echo $stats['today']; ?></h2>
                <small class="text-muted">Bugün</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-1 text-info"><?php echo $stats['this_week']; ?></h2>
                <small class="text-muted">Bu Hafta</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-1 text-warning"><?php echo $stats['this_month']; ?></h2>
                <small class="text-muted">Bu Ay</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-1 text-success"><?php echo $stats['total']; ?></h2>
                <small class="text-muted">Toplam</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtreler ve Log Tablosu -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-filter"></i> Log Kayıtları
                </h5>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                    <i class="bi bi-trash"></i> Eski Logları Temizle
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtreler -->
        <form method="GET" action="" class="row g-3 mb-4">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Log Tipi</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="activity" <?php echo $type === 'activity' ? 'selected' : ''; ?>>Aktivite</option>
                    <option value="error" <?php echo $type === 'error' ? 'selected' : ''; ?>>Hata</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold">Ay</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?php echo $month; ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold">Kullanıcı</label>
                <input type="text" name="user" class="form-control form-control-sm" placeholder="Kullanıcı adı" value="<?php echo htmlspecialchars($user_filter); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold">Seviye</label>
                <select name="level" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <option value="INFO" <?php echo $level_filter === 'INFO' ? 'selected' : ''; ?>>Bilgi</option>
                    <option value="SUCCESS" <?php echo $level_filter === 'SUCCESS' ? 'selected' : ''; ?>>Başarılı</option>
                    <option value="WARNING" <?php echo $level_filter === 'WARNING' ? 'selected' : ''; ?>>Uyarı</option>
                    <option value="ERROR" <?php echo $level_filter === 'ERROR' ? 'selected' : ''; ?>>Hata</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold">İşlem</label>
                <input type="text" name="action" class="form-control form-control-sm" placeholder="İşlem ara" value="<?php echo htmlspecialchars($action_filter); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small fw-bold">&nbsp;</label>
                <button type="submit" class="btn btn-winergy btn-sm w-100">
                    <i class="bi bi-search"></i> Filtrele
                </button>
            </div>
        </form>
        
        <!-- Log Tablosu -->
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th width="140">Zaman</th>
                        <th width="80">Seviye</th>
                        <th width="120">Kullanıcı</th>
                        <th width="100">IP</th>
                        <th>İşlem</th>
                        <th>Detay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> Log kaydı bulunamadı
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($logs, 0, 500) as $log): ?>
                            <?php
                            $level = $log['level'] ?? 'INFO';
                            $color = get_log_level_color($level);
                            $level_tr = translate_log_level($level);
                            ?>
                            <tr>
                                <td class="small text-muted">
                                    <?php echo date('d.m.Y H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo $level_tr; ?></span>
                                </td>
                                <td class="small">
                                    <strong><?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['user_role'] ?? ''); ?></small>
                                </td>
                                <td class="small text-muted">
                                    <?php echo htmlspecialchars($log['ip'] ?? 'unknown'); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['action'] ?? ''); ?></strong>
                                </td>
                                <td class="small">
                                    <?php 
                                    $details = $log['details'] ?? $log['message'] ?? '';
                                    echo htmlspecialchars(substr($details, 0, 100));
                                    if (strlen($details) > 100) echo '...';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($logs) > 500): ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i> İlk 500 kayıt gösteriliyor. Toplam: <?php echo count($logs); ?> kayıt
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Temizleme Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Eski Logları Temizle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <div class="modal-body">
                    <p>Kaç günden eski logları temizlemek istersiniz?</p>
                    <div class="mb-3">
                        <label class="form-label">Gün Sayısı</label>
                        <input type="number" name="cleanup_days" class="form-control" value="30" min="1" required>
                        <small class="text-muted">Örn: 30 gün öncesinin logları silinir</small>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Bu işlem geri alınamaz!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="cleanup_logs" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Temizle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
