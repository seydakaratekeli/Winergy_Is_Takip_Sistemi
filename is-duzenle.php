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
    header("Location: index.php");
    exit;
}

// İş bilgilerini çek
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header("Location: index.php");
    exit;
}

// Form gönderildiğinde güncelle
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    

    // Türk formatından (11.250,00) İngilizce formata (11250.00) çevir
    function convertTurkishToDecimal($value) {
        if (empty($value)) return null;
        // Nokta (binlik ayırıcı) kaldır, virgül (ondalık ayırıcı) noktaya çevir
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? $value : null;
    }
    
    $customer_id = $_POST['customer_id'];
    // service_type array olarak gelecek
    $service_types = $_POST['service_type'] ?? [];

    // "Diğer" seçeneği işaretlenmişse ve metin girilmişse listeye ekle
    if (!empty($_POST['service_type_other'])) {
        $other_service = trim($_POST['service_type_other']);
        if ($other_service !== '') {
            $service_types[] = $other_service;
        }
    }
    
    if (empty($service_types)) {
        $_SESSION['temp_error'] = "En az bir hizmet türü seçmelisiniz!";
    } else {
        $service_type = json_encode($service_types, JSON_UNESCAPED_UNICODE);
        $title = trim($_POST['title']); // İş başlığını temizle
        $description = trim($_POST['description']); // Açıklamayı temizle
        $assigned_user_id = !empty($_POST['assigned_user_id']) ? $_POST['assigned_user_id'] : null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $invoice_amount = convertTurkishToDecimal($_POST['invoice_amount'] ?? '');
        $invoice_vat_included = isset($_POST['invoice_vat_included']) ? (int)$_POST['invoice_vat_included'] : null;
        $invoice_date = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : null;
        $invoice_total_amount = convertTurkishToDecimal($_POST['invoice_total_amount'] ?? '');
        $invoice_withholding = !empty($_POST['invoice_withholding']) ? $_POST['invoice_withholding'] : 'belirtilmedi';
        $status = $_POST['status'];

        
        // TARİH VALİDASYONU: Başlangıç tarihi bitiş tarihinden önce olmalı
        $error = "";
        if (!empty($start_date) && !empty($due_date) && $start_date > $due_date) {
            $error = "Başlangıç tarihi, bitiş tarihinden sonra olamaz!";
        }
        
        if ($error) {
            // Hata varsa sayfada göster (header ekledikten sonra)
            $_SESSION['temp_error'] = $error;
        } else {
            $sql = "UPDATE jobs SET 
                    customer_id = ?, 
                    service_type = ?, 
                    title = ?, 
                    description = ?, 
                    assigned_user_id = ?, 
                    start_date = ?, 
                    due_date = ?,
                    invoice_amount = ?,
                    invoice_vat_included = ?,
                    invoice_date = ?,
                    invoice_total_amount = ?,
                    invoice_withholding = ?,
                    status = ?,
                    updated_by = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$customer_id, $service_type, $title, $description, $assigned_user_id, $start_date, $due_date, $invoice_amount, $invoice_vat_included, $invoice_date, $invoice_total_amount, $invoice_withholding, $status, $_SESSION['user_id'], $id])) {
            log_activity('İş Güncellendi', "İş Başlığı: $title (ID: $id)", 'INFO');    
            header("Location: is-detay.php?id=$id&updated=1");
                exit;
            }
        }
    }
}

// Dropdown menüler için verileri çekelim
$customers = $db->query("SELECT id, name, contact_name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $db->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php'; 
?>

<?php if(isset($_SESSION['temp_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['temp_error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['temp_error']); ?>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="bi bi-pencil-square"></i> İş Kaydını Düzenle
                    </h5>
                    <a href="is-detay.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Geri
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="jobEditForm">
                    <?php echo csrf_input(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" style="cursor: pointer;" onclick="showCustomerSelection()">
                                Müşteri <span class="text-danger">*</span>
                                <i class="bi bi-pencil-square text-muted ms-1" style="font-size: 0.9rem;"></i>
                            </label>
                            
                            <!-- Seçilen Müşteri Gösterimi -->
                            <div id="selectedCustomerDisplay">
                                <div class="card border-success" style="cursor: pointer;" onclick="showCustomerSelection()">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <strong id="selectedCustomerName" class="flex-grow-1">
                                                <?php 
                                                $current_customer = array_filter($customers, fn($c) => $c['id'] == $job['customer_id']);
                                                $current_customer = reset($current_customer);
                                                if ($current_customer) {
                                                    echo htmlspecialchars($current_customer['name']);
                                                    if (!empty($current_customer['contact_name'])) {
                                                        echo ' - ' . htmlspecialchars($current_customer['contact_name']);
                                                    }
                                                }
                                                ?>
                                            </strong>
                                            <small class="text-muted">
                                                <i class="bi bi-pencil"></i> Değiştir
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Müşteri Seçim Alanı -->
                            <div id="customerSelectionArea" class="d-none">
                                <!-- Müşteri Arama -->
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" id="customerSearch" class="form-control" placeholder="Müşteri veya ilgili kişi ara..." autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary" onclick="clearCustomerSearch()" title="Aramayı Temizle">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                
                                <!-- Müşteri Select -->
                                <div class="input-group">
                                    <select name="customer_id" id="customerSelect" class="form-select" required size="6" style="height: auto;">
                                        <option value="">--- Müşteri Seçiniz ---</option>
                                        <?php foreach($customers as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" 
                                                    <?php echo $c['id'] == $job['customer_id'] ? 'selected' : ''; ?>
                                                    data-name="<?php echo htmlspecialchars(strtolower($c['name'])); ?>"
                                                    data-contact="<?php echo htmlspecialchars(strtolower($c['contact_name'] ?? '')); ?>"
                                                    data-fullname="<?php echo htmlspecialchars($c['name']); ?><?php echo !empty($c['contact_name']) ? ' - ' . htmlspecialchars($c['contact_name']) : ''; ?>">
                                                <?php echo htmlspecialchars($c['name']); ?>
                                                <?php if(!empty($c['contact_name'])): ?>
                                                    - <?php echo htmlspecialchars($c['contact_name']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="musteri-ekle.php" class="btn btn-outline-primary" title="Yeni Müşteri Ekle">
                                        <i class="bi bi-person-plus"></i>
                                    </a>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-info-circle"></i> Arama yaparak müşteriyi hızlıca bulabilirsiniz
                                </small>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Hizmet Türü <span class="text-danger">*</span></label>
                            <div class="border rounded p-3 bg-light" style="max-height: 320px; overflow-y: auto;">
                                <?php 
                                // Job'dan service_type'ı decode et
                                $selected_services = [];
                                if (!empty($job['service_type'])) {
                                    $selected_services = json_decode($job['service_type'], true);
                                    if (!is_array($selected_services)) {
                                        $selected_services = [$job['service_type']];
                                    }
                                }
                                
                                $services_data = [
                                    ['id' => 'service_1', 'value' => 'Enerji Etüdü', 'label' => 'Enerji Etüdü'],
                                    ['id' => 'service_2', 'value' => 'ISO 50001', 'label' => 'ISO 50001'],
                                    ['id' => 'service_3', 'value' => 'EKB', 'label' => 'Enerji Kimlik Belgesi (EKB)'],
                                    ['id' => 'service_4', 'value' => 'Enerji Yöneticisi', 'label' => 'Enerji Yöneticisi'],
                                    ['id' => 'service_5', 'value' => 'VAP', 'label' => 'VAP (Verimlilik Artırıcı Proje)'],
                                    ['id' => 'service_6', 'value' => 'Danışmanlık', 'label' => 'Danışmanlık'],
                                    ['id' => 'service_7', 'value' => 'Rapor Onay', 'label' => 'Rapor Onay'],
                                    ['id' => 'service_8', 'value' => 'Bakım', 'label' => 'Bakım']
                                ];
                                
                                // Standart hizmetleri göster
                                foreach($services_data as $idx => $service):
                                ?>
                                <div class="form-check mb-2">
                                    <input 
                                        class="form-check-input service-type-check" 
                                        type="checkbox" 
                                        name="service_type[]" 
                                        value="<?php echo htmlspecialchars($service['value']); ?>" 
                                        id="<?php echo $service['id']; ?>"
                                        <?php echo in_array($service['value'], $selected_services) ? 'checked' : ''; ?>
                                    >
                                    <label class="form-check-label" for="<?php echo $service['id']; ?>">
                                        <?php echo htmlspecialchars($service['label']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php
                                // Standart hizmet listesini al
                                $standard_services = array_column($services_data, 'value');
                                // Seçili hizmetlerden standart olmayanı bul ("Diğer" hizmet)
                                $other_service = '';
                                foreach($selected_services as $service) {
                                    if (!in_array($service, $standard_services)) {
                                        $other_service = $service;
                                        break;
                                    }
                                }
                                ?>
                                
                                <div class="form-check mt-2 pt-2 border-top">
                                    <input 
                                        class="form-check-input service-type-check" 
                                        type="checkbox" 
                                        id="check_other" 
                                        onclick="toggleOtherInput()"
                                        <?php echo !empty($other_service) ? 'checked' : ''; ?>
                                    >
                                    <label class="form-check-label fw-bold" for="check_other">
                                        <i class="bi bi-plus-circle"></i> Diğer (Özel Hizmet)
                                    </label>
                                </div>
                                <div id="div_other_input" class="mt-2" style="display:<?php echo !empty($other_service) ? 'block' : 'none'; ?>;">
                                    <input 
                                        type="text" 
                                        name="service_type_other" 
                                        id="input_other" 
                                        class="form-control form-control-sm" 
                                        placeholder="Hizmet türünü yazınız..."
                                        value="<?php echo htmlspecialchars($other_service); ?>"
                                    >
                                    <small class="text-muted"><i class="bi bi-info-circle"></i> Listede olmayan özel hizmet türünü girebilirsiniz</small>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> En az bir hizmet türü seçmelisiniz
                            </small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">İş / Proje Başlığı</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($job['title']); ?>">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Açıklama / Notlar</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($job['description']); ?></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Sorumlu Personel</label>
                            <select name="assigned_user_id" class="form-select">
                                <option value="">Atama Yapılmadı</option>
                                <?php 
                                $role_tr = [
                                    'admin' => 'Yönetici',
                                    'operasyon' => 'Operasyon',
                                    'danisman' => 'Danışman'
                                ];
                                foreach($users as $u): 
                                    $role_label = $role_tr[$u['role']] ?? $u['role'];
                                ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $job['assigned_user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['name']); ?> (<?php echo $role_label; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">İş Durumu</label>
                            <select name="status" class="form-select" required>
                                <?php 
                                $statuses = ['Açıldı', 'Çalışılıyor', 'Beklemede', 'Tamamlandı', 'İptal'];
                                foreach($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo trim($job['status']) == $s ? 'selected' : ''; ?>>
                                        <?php echo $s; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Söz. Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $job['start_date']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Söz. Bitiş Tarihi</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo $job['due_date']; ?>">
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-receipt me-2"></i>Fatura Bilgileri</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Kesilecek Fatura Tutarı (₺)</label>
                            <input type="text" name="invoice_amount" id="invoice_amount" class="form-control" placeholder="0,00" value="<?php echo !empty($job['invoice_amount']) ? number_format($job['invoice_amount'], 2, ',', '.') : ''; ?>" onkeyup="formatCurrency(this)">
                            <small class="text-muted">KDV hariç/dahil tutar (örn: 11.250,00)</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Toplam Tutar (₺)</label>
                            <input type="text" name="invoice_total_amount" id="invoice_total_amount" class="form-control" placeholder="0,00" value="<?php echo !empty($job['invoice_total_amount']) ? number_format($job['invoice_total_amount'], 2, ',', '.') : ''; ?>" onkeyup="formatCurrency(this)">
                            <small class="text-muted">Nihai ödenecek tutar (örn: 11.250,00)</small>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">KDV Durumu</label>
                            <select name="invoice_vat_included" class="form-select">
                                <option value="">Belirtilmedi</option>
                                <option value="1" <?php echo (isset($job['invoice_vat_included']) && $job['invoice_vat_included'] == 1) ? 'selected' : ''; ?>>KDV Dahil</option>
                                <option value="0" <?php echo (isset($job['invoice_vat_included']) && $job['invoice_vat_included'] == 0) ? 'selected' : ''; ?>>KDV Hariç</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">Tevkifat</label>
                            <select name="invoice_withholding" class="form-select">
                                <option value="belirtilmedi" <?php echo (($job['invoice_withholding'] ?? 'belirtilmedi') == 'belirtilmedi') ? 'selected' : ''; ?>>Belirtilmedi</option>
                                <option value="var" <?php echo (($job['invoice_withholding'] ?? '') == 'var') ? 'selected' : ''; ?>>Var</option>
                                <option value="yok" <?php echo (($job['invoice_withholding'] ?? '') == 'yok') ? 'selected' : ''; ?>>Yok</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">Fatura Tarihi</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?php echo $job['invoice_date'] ?? ''; ?>">
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="is-detay.php?id=<?php echo $id; ?>" class="btn btn-light">
                            <i class="bi bi-x-circle"></i> İptal
                        </a>
                        <button type="submit" class="btn btn-winergy px-4">
                            <i class="bi bi-check-circle"></i> Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation - Alert kullanmadan
document.getElementById('jobEditForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Hizmet türü kontrolü
    const checkedCount = document.querySelectorAll('.service-type-check:checked').length;
    const serviceValidation = document.querySelector('.service-type-check').closest('.col-md-6').querySelector('.text-muted');
    
    if (checkedCount === 0) {
        e.preventDefault();
        isValid = false;
        
        // Hata mesajı göster
        if (serviceValidation) {
            serviceValidation.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> <strong class="text-danger">En az bir hizmet türü seçmelisiniz!</strong>';
            serviceValidation.classList.add('text-danger');
        }
        
        // Scroll to error
        document.querySelector('.service-type-check').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// Müşteri Arama ve Seçim Fonksiyonları
const customerSearch = document.getElementById('customerSearch');
const customerSelect = document.getElementById('customerSelect');
const selectedCustomerDisplay = document.getElementById('selectedCustomerDisplay');
const selectedCustomerName = document.getElementById('selectedCustomerName');
const customerSelectionArea = document.getElementById('customerSelectionArea');

// Müşteri seçildiğinde göster
if (customerSelect) {
    customerSelect.addEventListener('change', function() {
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            const fullName = selectedOption.getAttribute('data-fullname');
            
            // Seçilen müşteriyi göster
            selectedCustomerName.textContent = fullName;
            selectedCustomerDisplay.classList.remove('d-none');
            customerSelectionArea.classList.add('d-none');
        }
    });
}

// Müşteri seçim alanını göster (Label veya karta tıklandığında)
function showCustomerSelection() {
    selectedCustomerDisplay.classList.add('d-none');
    customerSelectionArea.classList.remove('d-none');
    customerSearch.focus();
}

// Müşteri Arama Fonksiyonu
if (customerSearch && customerSelect) {
    customerSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const options = customerSelect.options;
        let visibleCount = 0;
        let lastVisibleOption = null;
        
        for (let i = 1; i < options.length; i++) {
            const option = options[i];
            const name = option.getAttribute('data-name') || '';
            const contact = option.getAttribute('data-contact') || '';
            
            if (searchTerm === '' || name.includes(searchTerm) || contact.includes(searchTerm)) {
                option.style.display = '';
                visibleCount++;
                lastVisibleOption = option;
            } else {
                option.style.display = 'none';
            }
        }
        
        // Eğer arama sonucu tek bir müşteri kaldıysa otomatik seç
        if (visibleCount === 1 && lastVisibleOption) {
            customerSelect.value = lastVisibleOption.value;
            
            // Seçimi görsel olarak göster
            const fullName = lastVisibleOption.getAttribute('data-fullname');
            selectedCustomerName.textContent = fullName;
            selectedCustomerDisplay.classList.remove('d-none');
            customerSelectionArea.classList.add('d-none');
        }
    });
    
    customerSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });
}

// Aramayı temizle
function clearCustomerSearch() {
    const searchInput = document.getElementById('customerSearch');
    const select = document.getElementById('customerSelect');
    
    searchInput.value = '';
    
    const options = select.options;
    for (let i = 1; i < options.length; i++) {
        options[i].style.display = '';
    }
    
    searchInput.focus();
}

function toggleOtherInput() {
    const checkBox = document.getElementById('check_other');
    const inputDiv = document.getElementById('div_other_input');
    const inputField = document.getElementById('input_other');
    
    if (checkBox.checked) {
        inputDiv.style.display = 'block';
        inputField.required = true;
        inputField.focus();
    } else {
        inputDiv.style.display = 'none';
        inputField.required = false;
        inputField.value = '';
    }
}

// Türk para formatı (11.250,00)
function formatCurrency(input) {
    let value = input.value;
    
    // Sadece rakam, virgül ve noktaya izin ver
    value = value.replace(/[^0-9.,]/g, '');
    
    // Sadece bir virgül olsun
    const parts = value.split(',');
    if (parts.length > 2) {
        value = parts[0] + ',' + parts.slice(1).join('');
    }
    
    // Tam kısmı formatla
    if (parts[0]) {
        // Önce tüm noktaları kaldır
        let integerPart = parts[0].replace(/\./g, '');
        
        // Binlik ayırıcı ekle
        if (integerPart.length > 3) {
            integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        
        value = integerPart;
        if (parts.length > 1) {
            // Ondalık kısım max 2 basamak
            let decimalPart = parts[1].substring(0, 2);
            value += ',' + decimalPart;
        }
    }
    
    input.value = value;
}

// Input alanına odaklandığında virgül varsa otomatik ondalık ekle
document.getElementById('invoice_amount')?.addEventListener('blur', function() {
    if (this.value && !this.value.includes(',')) {
        this.value += ',00';
    }
});

document.getElementById('invoice_total_amount')?.addEventListener('blur', function() {
    if (this.value && !this.value.includes(',')) {
        this.value += ',00';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
