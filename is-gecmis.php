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

// ID kontrolü
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// İş bilgilerini çek
$stmt = $db->prepare("
    SELECT j.*, 
           c.name as customer_name,
           creator.name as creator_name,
           updater.name as updater_name
    FROM jobs j 
    LEFT JOIN customers c ON j.customer_id = c.id 
    LEFT JOIN users creator ON j.created_by = creator.id
    LEFT JOIN users updater ON j.updated_by = updater.id
    WHERE j.id = ?
");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header("Location: index.php");
    exit;
}

// Tüm aktiviteleri topla
$activities = [];

// 1. İş oluşturma kaydı
if ($job['created_at']) {
    $creator_name = $job['creator_name'];
    // Eğer creator_name null ise, varsayılan bir isim göster
    if (!$creator_name) {
        // Eski kayıtlar için ilk admin kullanıcıyı bul
        $default_creator = $db->query("SELECT name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $creator_name = $default_creator ?? 'Bilinmeyen Kullanıcı';
    }
    
    $activities[] = [
        'type' => 'created',
        'user' => $creator_name,
        'action' => 'İş kaydı oluşturuldu',
        'details' => 'Başlangıç durumu: ' . $job['status'],
        'timestamp' => $job['created_at'],
        'icon' => 'bi-plus-circle-fill',
        'color' => 'success'
    ];
}

// 2. Son güncelleme kaydı (eğer varsa)
if ($job['updated_at'] && $job['updated_by']) {
    $activities[] = [
        'type' => 'updated',
        'user' => $job['updater_name'],
        'action' => 'İş kaydı güncellendi',
        'details' => 'Mevcut durum: ' . $job['status'],
        'timestamp' => $job['updated_at'],
        'icon' => 'bi-pencil-fill',
        'color' => 'primary'
    ];
}

// 3. Notları çek
$notes_stmt = $db->prepare("
    SELECT jn.*, u.name as user_name 
    FROM job_notes jn
    LEFT JOIN users u ON jn.user_id = u.id
    WHERE jn.job_id = ?
    ORDER BY jn.created_at DESC
");
$notes_stmt->execute([$id]);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($notes as $note) {
    $activities[] = [
        'type' => 'note',
        'user' => $note['user_name'] ?? 'Bilinmeyen',
        'action' => 'Not ekledi',
        'details' => substr($note['note'], 0, 100) . (strlen($note['note']) > 100 ? '...' : ''),
        'full_content' => $note['note'],
        'timestamp' => $note['created_at'],
        'icon' => 'bi-chat-left-text-fill',
        'color' => 'info'
    ];
}

// 4. Dosyaları çek
$files_stmt = $db->prepare("
    SELECT jf.*, u.name as uploader_name 
    FROM job_files jf
    LEFT JOIN users u ON jf.uploaded_by = u.id
    WHERE jf.job_id = ?
    ORDER BY jf.uploaded_at DESC
");
$files_stmt->execute([$id]);
$files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($files as $file) {
    $activities[] = [
        'type' => 'file',
        'user' => $file['uploader_name'] ?? 'Bilinmeyen',
        'action' => 'Dosya yükledi',
        'details' => $file['file_name'],
        'file_path' => $file['file_path'],
        'timestamp' => $file['uploaded_at'],
        'icon' => 'bi-file-earmark-arrow-up-fill',
        'color' => 'warning'
    ];
}

// Tüm aktiviteleri zamana göre sırala (en yeni üstte)
usort($activities, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

include 'includes/header.php'; 
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-1" style="color: var(--dt-sec-color);">
                    <i class="bi bi-clock-history me-2"></i>İş Geçmişi
                </h3>
                <p class="text-muted mb-0">
                    <strong><?php echo htmlspecialchars($job['title']); ?></strong> - 
                    <?php echo htmlspecialchars($job['customer_name']); ?>
                </p>
            </div>
            <div>
                <a href="is-detay.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> İş Detayına Dön
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <!-- İstatistik Kartları -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center">
                <h2 class="mb-0 fw-bold" style="color: var(--dt-pri-color);"><?php echo count($activities); ?></h2>
                <small class="text-muted">Toplam Aktivite</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center">
                <h2 class="mb-0 fw-bold text-info"><?php echo count($notes); ?></h2>
                <small class="text-muted">Not</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center">
                <h2 class="mb-0 fw-bold text-warning"><?php echo count($files); ?></h2>
                <small class="text-muted">Dosya</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">İş Bilgileri</h6>
                <div class="small">
                    <div class="mb-2">
                        <i class="bi bi-calendar-event me-1 text-muted"></i>
                        <strong>Oluşturma:</strong><br>
                        <span class="ms-3"><?php echo date('d.m.Y H:i', strtotime($job['created_at'])); ?></span>
                    </div>
                    <?php if($job['updated_at']): ?>
                    <div class="mb-2">
                        <i class="bi bi-clock-history me-1 text-muted"></i>
                        <strong>Son Güncelleme:</strong><br>
                        <span class="ms-3"><?php echo date('d.m.Y H:i', strtotime($job['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div>
                        <i class="bi bi-flag-fill me-1 text-muted"></i>
                        <strong>Durum:</strong><br>
                        <span class="ms-3 badge bg-primary"><?php echo $job['status']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-activity me-2"></i>Aktivite Akışı
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($activities)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <p class="text-muted mt-3">Henüz aktivite kaydı bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($activities as $index => $activity): ?>
                            <div class="timeline-item mb-4 pb-4 <?php echo $index < count($activities) - 1 ? 'border-bottom' : ''; ?>">
                                <div class="d-flex align-items-start">
                                    <!-- İkon -->
                                    <div class="flex-shrink-0">
                                        <div class="timeline-icon bg-<?php echo $activity['color']; ?> bg-opacity-10 text-<?php echo $activity['color']; ?> rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 48px; height: 48px;">
                                            <i class="<?php echo $activity['icon']; ?> fs-5"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- İçerik -->
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div>
                                                <strong class="text-<?php echo $activity['color']; ?>">
                                                    <?php echo htmlspecialchars($activity['user']); ?>
                                                </strong>
                                                <span class="text-muted ms-1"><?php echo $activity['action']; ?></span>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php 
                                                $time_diff = time() - strtotime($activity['timestamp']);
                                                if ($time_diff < 60) {
                                                    echo 'Az önce';
                                                } elseif ($time_diff < 3600) {
                                                    echo floor($time_diff / 60) . ' dakika önce';
                                                } elseif ($time_diff < 86400) {
                                                    echo floor($time_diff / 3600) . ' saat önce';
                                                } elseif ($time_diff < 604800) {
                                                    echo floor($time_diff / 86400) . ' gün önce';
                                                } else {
                                                    echo date('d.m.Y H:i', strtotime($activity['timestamp']));
                                                }
                                                ?>
                                            </small>
                                        </div>
                                        
                                        <?php if (!empty($activity['details'])): ?>
                                            <div class="mt-2 p-3 rounded" style="background-color: var(--dt-gray2-color);">
                                                <?php if ($activity['type'] == 'note' && !empty($activity['full_content'])): ?>
                                                    <div class="small text-muted mb-1">
                                                        <i class="bi bi-chat-quote me-1"></i>Not İçeriği:
                                                    </div>
                                                    <div><?php echo nl2br(htmlspecialchars($activity['full_content'])); ?></div>
                                                <?php elseif ($activity['type'] == 'file'): ?>
                                                    <i class="bi bi-file-earmark me-1"></i>
                                                    <strong><?php echo htmlspecialchars($activity['details']); ?></strong>
                                                    <?php if (!empty($activity['file_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars($activity['file_path']); ?>" 
                                                           class="btn btn-sm btn-winergy ms-2" download>
                                                            <i class="bi bi-download"></i> İndir
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($activity['details']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?php echo date('d.m.Y H:i:s', strtotime($activity['timestamp'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-item {
    position: relative;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 24px;
    top: 48px;
    bottom: -16px;
    width: 2px;
    background: linear-gradient(180deg, var(--dt-pri-color) 0%, var(--dt-gray-color) 100%);
    opacity: 0.3;
}

.timeline-icon {
    position: relative;
    z-index: 1;
}
</style>

<?php include 'includes/footer.php'; ?>
