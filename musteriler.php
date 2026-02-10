<?php 
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}
require_once 'config/db.php'; 
include 'includes/header.php'; 

// --- ARAMA MANTIĞI ---
$search = $_GET['search'] ?? '';
$query_sql = "SELECT c.*, 
    u1.name as creator_name, 
    u2.name as updater_name 
    FROM customers c
    LEFT JOIN users u1 ON c.created_by = u1.id
    LEFT JOIN users u2 ON c.updated_by = u2.id";
$params = [];

if (!empty($search)) {
    // Firma adı veya İlgili Kişi isminde arama yap 
    $query_sql .= " WHERE c.name LIKE ? OR c.contact_name LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$query_sql .= " ORDER BY c.name ASC";

$stmt = $db->prepare($query_sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- YENİ MÜŞTERİ EKLEME ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $contact = $_POST['contact_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $created_by = $_SESSION['user_id'];

    $ins = $db->prepare("INSERT INTO customers (name, contact_name, phone, email, created_by) VALUES (?, ?, ?, ?, ?)");
    if ($ins->execute([$name, $contact, $phone, $email, $created_by])) {
        echo "<div class='alert alert-success'>Müşteri başarıyla kaydedildi.</div>";
        // Listeyi yenilemek için sayfayı tekrar yükle
        echo "<script>window.location.href='musteriler.php';</script>";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold" style="color: var(--dt-sec-color);">Μüşteri Yönetimi</h3>
       <p class="text-muted">Tüm kurumsal paydaşlar ve iletişim bilgileri.</p>
    </div>
    <div class="col-md-4 text-end">
        <button class="btn btn-winergy" data-bs-toggle="collapse" data-bs-target="#addCustomerForm">+ Yeni Müşteri</button>
    </div>
</div>

<div class="collapse mb-4" id="addCustomerForm">
    <div class="card card-body shadow-sm border-0">
        <h5 class="fw-bold mb-3">Yeni Müşteri Kaydı Oluştur</h5>
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="name" class="form-control form-control-sm" placeholder="Firma Adı" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="contact_name" class="form-control form-control-sm" placeholder="İlgili Kişi">
            </div>
            <div class="col-md-2">
                <input type="text" name="phone" class="form-control form-control-sm" placeholder="Telefon">
            </div>
            <div class="col-md-2">
                <input type="email" name="email" class="form-control form-control-sm" placeholder="E-Posta">
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_customer" class="btn btn-winergy btn-sm w-100"><i class="bi bi-check-circle me-1"></i>Kaydet</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control" placeholder="Firma adı veya yetkili kişi ile ara..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary px-4">Ara</button>
            <?php if(!empty($search)): ?>
                <a href="musteriler.php" class="btn btn-outline-secondary">Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-container shadow-sm bg-white p-0 rounded overflow-hidden">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="ps-4">Müşteri / Firma Adı</th>
                <th>İlgili Kişi</th>
                <th>İletişim Bilgileri</th>
                <th>Kayıt Bilgileri</th>
                <th class="text-center">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($customers as $c): ?>
            <tr>
                <td class="ps-4"><strong><?php echo $c['name']; ?></strong></td>
                <td><?php echo $c['contact_name'] ?: '<span class="text-muted">Belirtilmedi</span>'; ?></td>
                <td>
                    <div class="small">
                        <i class="bi bi-telephone"></i> <?php echo $c['phone']; ?><br>
                        <i class="bi bi-envelope"></i> <?php echo $c['email']; ?>
                    </div>
                </td>
                <td>
                    <div class="small text-muted">
                        <div class="mb-1">
                            <i class="bi bi-person-plus"></i> <strong>Oluşturan:</strong><br>
                            <?php echo htmlspecialchars($c['creator_name'] ?: 'Bilinmiyor'); ?>
                            <br><small><?php echo $c['created_at'] ? date('d.m.Y', strtotime($c['created_at'])) : '-'; ?></small>
                        </div>
                        <?php if($c['updated_by']): ?>
                        <div>
                            <i class="bi bi-pencil"></i> <strong>Güncelleyen:</strong><br>
                            <?php echo htmlspecialchars($c['updater_name']); ?>
                            <br><small><?php echo date('d.m.Y', strtotime($c['updated_at'])); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center">
                    <div class="btn-group" role="group">
                        <a href="is-ekle.php?customer_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-success" title="Yeni İş Aç">
                            <i class="bi bi-plus-circle"></i>
                        </a>
                        <a href="musteri-duzenle.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="musteri-sil.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" title="Sil">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($customers)): ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">Aranan kriterlere uygun müşteri bulunamadı.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>