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
include 'includes/header.php';

// Kullanıcıları çek
$stmt = $db->query("SELECT * FROM users ORDER BY name ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rol çevirileri
$role_tr = [
    'admin' => 'Yönetici',
    'operasyon' => 'Operasyon',
    'danisman' => 'Danışman'
];

// İstatistikler
$stats = [
    'total' => count($users),
    'active' => count(array_filter($users, fn($u) => $u['is_active'] == 1)),
    'inactive' => count(array_filter($users, fn($u) => $u['is_active'] == 0)),
    'admin' => count(array_filter($users, fn($u) => $u['role'] == 'admin')),
    'operasyon' => count(array_filter($users, fn($u) => $u['role'] == 'operasyon')),
    'danisman' => count(array_filter($users, fn($u) => $u['role'] == 'danisman'))
];
?>

<!-- Bildirimler -->
<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> Kullanıcı başarıyla silindi.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> Yeni kullanıcı başarıyla eklendi.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> Kullanıcı bilgileri güncellendi.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php 
        if ($_GET['error'] == 'self_delete') {
            echo "Kendi hesabınızı silemezsiniz.";
        } elseif ($_GET['error'] == 'notfound') {
            echo "Kullanıcı bulunamadı.";
        } else {
            echo "Bir hata oluştu.";
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold" style="color: var(--dt-sec-color);">
            <i class="bi bi-people-fill"></i> Kullanıcı Yönetimi
        </h3>
        <p class="text-muted">Sistem kullanıcılarını yönetin, yeni kullanıcı ekleyin veya mevcut kullanıcıları düzenleyin.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="kullanici-ekle.php" class="btn btn-winergy">
            <i class="bi bi-person-plus"></i> Yeni Kullanıcı Ekle
        </a>
    </div>
</div>

<!-- İstatistikler -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-0" style="color: var(--dt-pri-color);"><?php echo $stats['total']; ?></h2>
                <small class="text-muted">Toplam Kullanıcı</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-0 text-success"><?php echo $stats['active']; ?></h2>
                <small class="text-muted">Aktif</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-0 text-warning"><?php echo $stats['inactive']; ?></h2>
                <small class="text-muted">Pasif</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h2 class="fw-bold mb-0 text-info"><?php echo $stats['admin']; ?></h2>
                <small class="text-muted">Yönetici</small>
            </div>
        </div>
    </div>
</div>

<!-- Kullanıcı Listesi -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Kullanıcı Adı</th>
                        <th>E-Posta</th>
                        <th>Rol</th>
                        <th>Durum</th>
                        <th>Kayıt Tarihi</th>
                        <th class="text-center">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-person-fill" style="color: var(--dt-pri-color);"></i>
                                </div>
                                <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                            </div>
                        </td>
                        <td>
                            <small><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></small>
                        </td>
                        <td>
                            <?php 
                            $role_colors = [
                                'admin' => 'danger',
                                'operasyon' => 'primary',
                                'danisman' => 'info'
                            ];
                            $color = $role_colors[$user['role']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo $role_tr[$user['role']] ?? $user['role']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($user['is_active']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle-fill"></i> Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                    <i class="bi bi-x-circle-fill"></i> Pasif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo $user['created_at'] ? date('d.m.Y', strtotime($user['created_at'])) : '-'; ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="kullanici-duzenle.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <a href="kullanici-sil.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   title="Sil">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled title="Kendinizi silemezsiniz">
                                    <i class="bi bi-lock"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
