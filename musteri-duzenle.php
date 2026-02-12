<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/csrf.php'; 
require_once 'includes/logger.php';

// ID kontrolü
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: musteriler.php");
    exit;
}

// Müşteri bilgilerini çek
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: musteriler.php?error=notfound");
    exit;
}

// Not silme işlemi
if (isset($_GET['delete_note'])) {
    $note_id = intval($_GET['delete_note']);
    try {
        // Sadece kendi notunu veya admin olan silebilir
        $check = $db->prepare("SELECT user_id FROM customer_notes WHERE id = ? AND customer_id = ?");
        $check->execute([$note_id, $id]);
        $note = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($note && ($note['user_id'] == $_SESSION['user_id'] || $_SESSION['user_role'] == 'admin')) {
            $db->prepare("DELETE FROM customer_notes WHERE id = ?")->execute([$note_id]);
            header("Location: musteri-duzenle.php?id=$id&note_deleted=1");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Not silinirken hata: " . $e->getMessage();
    }
}

// Not ekleme işlemi
if (isset($_POST['add_note'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $note = trim($_POST['note']);
    $note_type = $_POST['note_type'];
    $user_id = $_SESSION['user_id'];
    
    if (!empty($note)) {
        try {
            $stmt = $db->prepare("INSERT INTO customer_notes (customer_id, user_id, note, note_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $user_id, $note, $note_type]);
            log_activity('Müşteri Notu Eklendi', "Müşteri ID: $id için yeni not eklendi.", 'INFO');
            header("Location: musteri-duzenle.php?id=$id&note_added=1");
            exit;
        } catch (PDOException $e) {
            $error = "Not eklenirken hata: " . $e->getMessage();
        }
    }
}

// Form gönderildiğinde güncelle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['add_note'])) {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $name = trim($_POST['name']);
    $contact_name = trim($_POST['contact_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $updated_by = $_SESSION['user_id'];

    try {
        $sql = "UPDATE customers SET 
                name = ?, 
                contact_name = ?, 
                phone = ?, 
                email = ?,
                address = ?,
                updated_by = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute([$name, $contact_name, $phone, $email, $address, $updated_by, $id])) {
        log_activity('Müşteri Güncellendi', "Firma: $name (ID: $id)", 'INFO');    
        header("Location: musteriler.php?updated=1");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Güncelleme hatası: " . $e->getMessage();
    }
}

// Müşteri notlarını çek
$notes_stmt = $db->prepare("
    SELECT cn.*, u.name as user_name 
    FROM customer_notes cn 
    LEFT JOIN users u ON cn.user_id = u.id 
    WHERE cn.customer_id = ? 
    ORDER BY cn.created_at DESC
");
$notes_stmt->execute([$id]);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php'; 
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(isset($_GET['note_added'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Not başarıyla eklendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['note_deleted'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="bi bi-trash"></i> Not silindi.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Sol Kolon: Müşteri Bilgileri -->
            <div class="col-md-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-primary">
                                <i class="bi bi-pencil-square"></i> Müşteri Bilgilerini Düzenle
                            </h5>
                            <a href="musteriler.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Geri
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrf_input(); ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Firma / Müşteri Adı *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($customer['name']); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">İlgili Kişi (Kontak)</label>
                                <input type="text" name="contact_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($customer['contact_name']); ?>" 
                                       placeholder="Ad Soyad">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Telefon</label>
                                    <input type="text" name="phone" class="form-control phone-input" 
                                           value="<?php echo htmlspecialchars($customer['phone']); ?>" 
                                           placeholder="0555 123 4567" maxlength="14">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">E-Posta</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($customer['email']); ?>" 
                                           placeholder="mail@firma.com">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Adres</label>
                                <textarea name="address" class="form-control" rows="3" 
                                          placeholder="Müşteri adresi..."><?php echo htmlspecialchars($customer['address']); ?></textarea>
                            </div>

                            <hr>
                            
                            <!-- Kullanıcı Takip Bilgileri -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="bi bi-person-plus"></i> <strong>Oluşturan:</strong> 
                                        <?php 
                                        if ($customer['created_by']) {
                                            $creator = $db->prepare("SELECT name FROM users WHERE id = ?");
                                            $creator->execute([$customer['created_by']]);
                                            $creator_name = $creator->fetchColumn();
                                            echo htmlspecialchars($creator_name ?: 'Bilinmiyor');
                                        } else {
                                            echo 'Bilinmiyor';
                                        }
                                        ?>
                                        <br>
                                        <i class="bi bi-calendar3"></i> 
                                        <?php echo $customer['created_at'] ? date('d.m.Y H:i', strtotime($customer['created_at'])) : '-'; ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="bi bi-pencil"></i> <strong>Son Güncelleyen:</strong> 
                                        <?php 
                                        if ($customer['updated_by']) {
                                            $updater = $db->prepare("SELECT name FROM users WHERE id = ?");
                                            $updater->execute([$customer['updated_by']]);
                                            $updater_name = $updater->fetchColumn();
                                            echo htmlspecialchars($updater_name ?: 'Bilinmiyor');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                        <br>
                                        <i class="bi bi-calendar3"></i> 
                                        <?php echo $customer['updated_at'] ? date('d.m.Y H:i', strtotime($customer['updated_at'])) : '-'; ?>
                                    </small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="musteriler.php" class="btn btn-light">
                                    <i class="bi bi-x-circle"></i> İptal
                                </a>
                                <button type="submit" class="btn btn-winergy px-4">
                                    <i class="bi bi-check-circle"></i> Değişiklikleri Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Müşteriye ait işler -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-briefcase"></i> Bu Müşteriye Ait İşler</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $jobs_stmt = $db->prepare("SELECT id, title, status FROM jobs WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
                        $jobs_stmt->execute([$id]);
                        $jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($jobs)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach($jobs as $j): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><?php echo htmlspecialchars($j['title']); ?></span>
                                        <div>
                                            <span class="badge bg-secondary me-2"><?php echo $j['status']; ?></span>
                                            <a href="is-detay.php?id=<?php echo $j['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Detay
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Bu müşteriye ait iş kaydı bulunmuyor.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sağ Kolon: Müşteri Notları -->
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="bi bi-journal-text"></i> Müşteri Notları
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Not Ekleme Formu -->
                        <form method="POST" class="mb-4">
                            <?php echo csrf_input(); ?>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Yeni Not Ekle</label>
                                <select name="note_type" class="form-select form-select-sm mb-2">
                                    <option value="general">📝 Genel Not</option>
                                    <option value="agreement">📋 Anlaşma Detayı</option>
                                    <option value="important">⚠️ Önemli Bilgi</option>
                                    <option value="meeting">🤝 Toplantı Notu</option>
                                </select>
                                <textarea name="note" class="form-control form-control-sm" rows="3" 
                                          placeholder="Not içeriğini yazın..." required></textarea>
                            </div>
                            <button type="submit" name="add_note" class="btn btn-sm btn-winergy w-100">
                                <i class="bi bi-plus-circle"></i> Not Ekle
                            </button>
                        </form>
                        
                        <hr>
                        
                        <!-- Notlar Listesi -->
                        <div style="max-height: 600px; overflow-y: auto;">
                            <?php if (!empty($notes)): ?>
                                <?php foreach($notes as $note): 
                                    $type_icons = [
                                        'general' => ['icon' => '📝', 'class' => 'info'],
                                        'agreement' => ['icon' => '📋', 'class' => 'primary'],
                                        'important' => ['icon' => '⚠️', 'class' => 'warning'],
                                        'meeting' => ['icon' => '🤝', 'class' => 'success']
                                    ];
                                    $type_info = $type_icons[$note['note_type']] ?? $type_icons['general'];
                                ?>
                                    <div class="card mb-2 border-start border-<?php echo $type_info['class']; ?> border-3">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <span class="badge bg-<?php echo $type_info['class']; ?> bg-opacity-10 text-<?php echo $type_info['class']; ?> small">
                                                    <?php echo $type_info['icon']; ?> 
                                                    <?php 
                                                    $types = [
                                                        'general' => 'Genel',
                                                        'agreement' => 'Anlaşma',
                                                        'important' => 'Önemli',
                                                        'meeting' => 'Toplantı'
                                                    ];
                                                    echo $types[$note['note_type']];
                                                    ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php echo date('d.m.Y H:i', strtotime($note['created_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($note['user_name']); ?>
                                                </small>
                                                <?php if($note['user_id'] == $_SESSION['user_id'] || $_SESSION['user_role'] == 'admin'): ?>
                                                    <a href="musteri-duzenle.php?id=<?php echo $id; ?>&delete_note=<?php echo $note['id']; ?>" 
                                                       class="btn btn-sm btn-link text-danger p-0" 
                                                       onclick="return confirm('Bu notu silmek istediğinize emin misiniz?');"
                                                       title="Notu Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-journal-x" style="font-size: 3rem;"></i>
                                    <p class="mb-0 mt-2">Henüz not eklenmemiş</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php include 'includes/footer.php'; ?>
