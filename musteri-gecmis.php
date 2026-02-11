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
    header("Location: musteriler.php");
    exit;
}

// Müşteri bilgilerini çek
$stmt = $db->prepare("
    SELECT c.*, 
           creator.name as creator_name,
           updater.name as updater_name
    FROM customers c 
    LEFT JOIN users creator ON c.created_by = creator.id
    LEFT JOIN users updater ON c.updated_by = updater.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: musteriler.php");
    exit;
}

// Tüm aktiviteleri topla
$activities = [];

// 1. Müşteri oluşturma kaydı
if ($customer['created_at']) {
    $creator_name = $customer['creator_name'];
    // Eğer creator_name null ise, varsayılan bir isim göster
    if (!$creator_name) {
        // Eski kayıtlar için ilk admin kullanıcıyı bul
        $default_creator = $db->query("SELECT name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $creator_name = $default_creator ?? 'Bilinmeyen Kullanıcı';
    }
    
    $activities[] = [
        'type' => 'created',
        'user' => $creator_name,
        'action' => 'Müşteri kaydı oluşturuldu',
        'details' => 'Firma: ' . $customer['name'] . ($customer['contact_name'] ? ', İlgili Kişi: ' . $customer['contact_name'] : ''),
        'timestamp' => $customer['created_at'],
        'icon' => 'bi-building-fill-add',
        'color' => 'success'
    ];
}

// 2. Son güncelleme kaydı (eğer varsa)
if ($customer['updated_at'] && $customer['updated_by']) {
    $activities[] = [
        'type' => 'updated',
        'user' => $customer['updater_name'],
        'action' => 'Müşteri kaydı güncellendi',
        'details' => 'Müşteri bilgileri düzenlendi',
        'timestamp' => $customer['updated_at'],
        'icon' => 'bi-pencil-fill',
        'color' => 'primary'
    ];
}

// 3. Müşteri notlarını çek
$notes_stmt = $db->prepare("
    SELECT cn.*, u.name as user_name 
    FROM customer_notes cn
    LEFT JOIN users u ON cn.user_id = u.id
    WHERE cn.customer_id = ?
    ORDER BY cn.created_at DESC
");
$notes_stmt->execute([$id]);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($notes as $note) {
    $note_type_labels = [
        'genel' => 'Genel',
        'anlaşma' => 'Anlaşma',
        'önemli' => 'Önemli',
        'toplantı' => 'Toplantı'
    ];
    
    $activities[] = [
        'type' => 'note',
        'user' => $note['user_name'] ?? 'Bilinmeyen',
        'action' => 'Müşteri notu ekledi',
        'note_type' => $note_type_labels[$note['note_type']] ?? 'Genel',
        'details' => substr($note['note'], 0, 100) . (strlen($note['note']) > 100 ? '...' : ''),
        'full_content' => $note['note'],
        'timestamp' => $note['created_at'],
        'icon' => 'bi-chat-left-text-fill',
        'color' => 'info'
    ];
}

// 4. Müşteriye eklenen işleri çek
$jobs_stmt = $db->prepare("
    SELECT j.*, 
           u1.name as creator_name,
           u2.name as assigned_name
    FROM jobs j
    LEFT JOIN users u1 ON j.created_by = u1.id
    LEFT JOIN users u2 ON j.assigned_user_id = u2.id
    WHERE j.customer_id = ?
    ORDER BY j.created_at DESC
");
$jobs_stmt->execute([$id]);
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($jobs as $job) {
    $activities[] = [
        'type' => 'job',
        'user' => $job['creator_name'] ?? 'Bilinmeyen',
        'action' => 'Yeni iş kaydı oluşturdu',
        'details' => $job['title'],
        'job_id' => $job['id'],
        'job_status' => $job['status'],
        'job_assigned' => $job['assigned_name'],
        'timestamp' => $job['created_at'],
        'icon' => 'bi-briefcase-fill',
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
                    <i class="bi bi-clock-history me-2"></i>Müşteri Geçmişi
                </h3>
                <p class="text-muted mb-0">
                    <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                    <?php if($customer['contact_name']): ?>
                        - <?php echo htmlspecialchars($customer['contact_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="musteriler.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Müşteri Listesine Dön
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
                <small class="text-muted">Müşteri Notu</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center">
                <h2 class="mb-0 fw-bold text-warning"><?php echo count($jobs); ?></h2>
                <small class="text-muted">İş Kaydı</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Müşteri Bilgileri</h6>
                <div class="small">
                    <div class="mb-2">
                        <i class="bi bi-building me-1 text-muted"></i>
                        <strong>Firma:</strong><br>
                        <span class="ms-3"><?php echo htmlspecialchars($customer['name']); ?></span>
                    </div>
                    <?php if($customer['contact_name']): ?>
                    <div class="mb-2">
                        <i class="bi bi-person me-1 text-muted"></i>
                        <strong>İlgili Kişi:</strong><br>
                        <span class="ms-3"><?php echo htmlspecialchars($customer['contact_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($customer['phone']): ?>
                    <div class="mb-2">
                        <i class="bi bi-telephone me-1 text-muted"></i>
                        <strong>Telefon:</strong><br>
                        <span class="ms-3"><?php echo htmlspecialchars($customer['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($customer['email']): ?>
                    <div class="mb-2">
                        <i class="bi bi-envelope me-1 text-muted"></i>
                        <strong>E-posta:</strong><br>
                        <span class="ms-3"><?php echo htmlspecialchars($customer['email']); ?></span>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="mb-2">
                        <i class="bi bi-calendar-event me-1 text-muted"></i>
                        <strong>Oluşturma:</strong><br>
                        <span class="ms-3"><?php echo date('d.m.Y H:i', strtotime($customer['created_at'])); ?></span>
                    </div>
                    <?php if($customer['updated_at']): ?>
                    <div>
                        <i class="bi bi-clock-history me-1 text-muted"></i>
                        <strong>Son Güncelleme:</strong><br>
                        <span class="ms-3"><?php echo date('d.m.Y H:i', strtotime($customer['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
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
                                                <?php if ($activity['type'] == 'note'): ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo $activity['note_type']; ?></span>
                                                <?php endif; ?>
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
                                                <?php elseif ($activity['type'] == 'job'): ?>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="bi bi-briefcase me-1"></i>
                                                            <strong><?php echo htmlspecialchars($activity['details']); ?></strong>
                                                            <div class="mt-1">
                                                                <span class="badge bg-primary"><?php echo $activity['job_status']; ?></span>
                                                                <?php if (!empty($activity['job_assigned'])): ?>
                                                                    <span class="text-muted small ms-2">
                                                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($activity['job_assigned']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <a href="is-detay.php?id=<?php echo $activity['job_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> Görüntüle
                                                        </a>
                                                    </div>
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
