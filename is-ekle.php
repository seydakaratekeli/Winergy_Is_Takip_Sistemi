<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/csrf.php';

$success_message = "";
$error_message = "";

// Türk formatından (11.250,00) İngilizce formata (11250.00) çevir
function convertTurkishToDecimal($value) {
    if (empty($value)) return null;
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? $value : null;
}

// Form gönderildiğinde veritabanına kayıt yapalım
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $customer_id = $_POST['customer_id'];
    $service_types = $_POST['service_type'] ?? [];

    // "Diğer" seçeneği işaretlenmişse ve metin girilmişse listeye ekle
    if (!empty($_POST['service_type_other'])) {
        $other_service = trim($_POST['service_type_other']);
        if ($other_service !== '') {
            $service_types[] = $other_service;
        }
    }
    
    // En az bir hizmet türü seçildiğini kontrol et
    if (empty($service_types)) {
        $error_message = "En az bir hizmet türü seçmelisiniz!";
    } else {
        $service_type = json_encode($service_types, JSON_UNESCAPED_UNICODE);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $assigned_user_id = !empty($_POST['assigned_user_id']) ? $_POST['assigned_user_id'] : null;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $invoice_amount = convertTurkishToDecimal($_POST['invoice_amount'] ?? '');
        $invoice_vat_included = isset($_POST['invoice_vat_included']) ? (int)$_POST['invoice_vat_included'] : null;
        $invoice_date = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : null;
        $invoice_total_amount = convertTurkishToDecimal($_POST['invoice_total_amount'] ?? '');
        $invoice_withholding = !empty($_POST['invoice_withholding']) ? $_POST['invoice_withholding'] : 'belirtilmedi';

        // TARİH VALİDASYONU: Başlangıç tarihi bitiş tarihinden önce olmalı
        if (!empty($start_date) && !empty($due_date) && $start_date > $due_date) {
            $error_message = "Başlangıç tarihi, bitiş tarihinden sonra olamaz!";
        } else {
            try {
                $sql = "INSERT INTO jobs (customer_id, service_type, title, description, assigned_user_id, start_date, due_date, invoice_amount, invoice_vat_included, invoice_date, invoice_total_amount, invoice_withholding, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Açıldı', ?)";
                
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$customer_id, $service_type, $title, $description, $assigned_user_id, $start_date, $due_date, $invoice_amount, $invoice_vat_included, $invoice_date, $invoice_total_amount, $invoice_withholding, $_SESSION['user_id']])) {
                    log_activity('İş Açıldı', "Yeni İş: $title", 'SUCCESS');
                    header("Location: index.php?added=1");
                    exit;
                }
            } catch (PDOException $e) {
                log_error("İş ekleme hatası", ['message' => $e->getMessage(), 'title' => $title]);
                $error_message = "İş kaydı oluşturulurken bir sorun oluştu. Lütfen sistem yöneticisine danışın.";
            }
        }
    }
}

// HTML çıktısı başlat
include 'includes/header.php';

// Dropdown menüler için verileri çekelim
$customers = $db->query("SELECT id, name, contact_name FROM customers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$users = $db->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-primary">Yeni İş Kaydı Oluştur</h5>
            </div>
            <div class="card-body">
                <form action="is-ekle.php" method="POST" id="jobForm">
                    <?php echo csrf_input(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="cursor: pointer;" onclick="showCustomerSelection()">
                                Müşteri Seçin <span class="text-danger">*</span>
                                <i class="bi bi-pencil-square text-muted ms-1" style="font-size: 0.9rem;"></i>
                            </label>
                            
                            <!-- Seçilen Müşteri Gösterimi -->
                            <div id="selectedCustomerDisplay" class="d-none">
                                <div class="card border-success" style="cursor: pointer;" onclick="showCustomerSelection()">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <strong id="selectedCustomerName" class="flex-grow-1"></strong>
                                            <small class="text-muted">
                                                <i class="bi bi-pencil"></i> Değiştir
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Müşteri Seçim Alanı -->
                            <div id="customerSelectionArea">
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
                                        <option value="" disabled selected>Seçiniz...</option>
                                        <?php foreach($customers as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" 
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
                                        <i class="bi bi-person-plus"></i> Yeni Müşteri Ekle
                                    </a>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-info-circle"></i> Arama yaparak müşteriyi hızlıca bulabilirsiniz
                                </small>
                            </div>
                            
                            <!-- Validation Mesajı -->
                            <div id="customerValidationMsg" class="invalid-feedback d-none">
                                Lütfen bir müşteri seçin
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Hizmet Türü <span class="text-danger">*</span></label>
                            <div class="border rounded p-3 bg-light" style="max-height: 320px; overflow-y: auto;">
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Enerji Etüdü" id="service_1">
                                    <label class="form-check-label" for="service_1">
                                        Enerji Etüdü
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="ISO 50001" id="service_2">
                                    <label class="form-check-label" for="service_2">
                                        ISO 50001
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="EKB" id="service_3">
                                    <label class="form-check-label" for="service_3">
                                        Enerji Kimlik Belgesi (EKB)
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Enerji Yöneticisi" id="service_4">
                                    <label class="form-check-label" for="service_4">
                                        Enerji Yöneticisi
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="VAP" id="service_5">
                                    <label class="form-check-label" for="service_5">
                                        VAP (Verimlilik Artırıcı Proje)
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Danışmanlık" id="service_6">
                                    <label class="form-check-label" for="service_6">
                                        Danışmanlık
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Rapor Onay" id="service_7">
                                    <label class="form-check-label" for="service_7">
                                        Rapor Onay
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input service-type-check" type="checkbox" name="service_type[]" value="Bakım" id="service_8">
                                    <label class="form-check-label" for="service_8">
                                        Bakım
                                    </label>
                                </div>
                                
                                <div class="form-check mt-2 pt-2 border-top">
                                    <input class="form-check-input service-type-check" type="checkbox" id="check_other" onclick="toggleOtherInput()">
                                    <label class="form-check-label fw-bold" for="check_other">
                                        <i class="bi bi-plus-circle"></i> Diğer (Özel Hizmet)
                                    </label>
                                </div>
                                <div id="div_other_input" class="mt-2" style="display:none;">
                                    <input type="text" name="service_type_other" id="input_other" class="form-control form-control-sm" placeholder="Hizmet türünü yazınız...">
                                    <small class="text-muted"><i class="bi bi-info-circle"></i> Listede olmayan özel hizmet türünü girebilirsiniz</small>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> En az bir hizmet türü seçmelisiniz
                            </small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">İş / Proje Başlığı</label>
                            <input type="text" name="title" class="form-control" placeholder="Örn: Fabrika Verimlilik Artırıcı Proje">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Açıklama / Notlar</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sorumlu Personel</label>
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
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($role_label); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Söz. Başlangıç Tarihi</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Söz. Bitiş Tarihi</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-receipt me-2"></i>Fatura Bilgileri</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kesilecek Fatura Tutarı (₺)</label>
                            <input type="text" name="invoice_amount" id="invoice_amount" class="form-control" placeholder="0,00" onkeyup="formatCurrency(this)">
                            <small class="text-muted">KDV hariç/dahil tutar (örn: 11.250,00)</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Toplam Tutar (₺)</label>
                            <input type="text" name="invoice_total_amount" id="invoice_total_amount" class="form-control" placeholder="0,00" onkeyup="formatCurrency(this)">
                            <small class="text-muted">Nihai ödenecek tutar (örn: 11.250,00)</small>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">KDV Durumu</label>
                            <select name="invoice_vat_included" class="form-select">
                                <option value="">Belirtilmedi</option>
                                <option value="1">KDV Dahil</option>
                                <option value="0">KDV Hariç</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Tevkifat</label>
                            <select name="invoice_withholding" class="form-select">
                                <option value="belirtilmedi">Belirtilmedi</option>
                                <option value="var">Var</option>
                                <option value="yok">Yok</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Fatura Tarihi</label>
                            <input type="date" name="invoice_date" class="form-control">
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end">
                        <a href="index.php" class="btn btn-light me-2">İptal</a>
                        <button type="submit" class="btn btn-winergy px-4"><i class="bi bi-check-circle me-1"></i>İşi Kaydet ve Başlat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation - Alert kullanmadan
document.getElementById('jobForm').addEventListener('submit', function(e) {
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
    
    // Müşteri seçim kontrolü
    const customerSelect = document.getElementById('customerSelect');
    const customerValidationMsg = document.getElementById('customerValidationMsg');
    
    if (!customerSelect.value) {
        e.preventDefault();
        isValid = false;
        
        customerValidationMsg.classList.remove('d-none');
        customerValidationMsg.classList.add('d-block');
        customerSelect.classList.add('is-invalid');
    }
});

// Müşteri Arama ve Seçim Fonksiyonları
const customerSearch = document.getElementById('customerSearch');
const customerSelect = document.getElementById('customerSelect');
const selectedCustomerDisplay = document.getElementById('selectedCustomerDisplay');
const selectedCustomerName = document.getElementById('selectedCustomerName');
const customerSelectionArea = document.getElementById('customerSelectionArea');
const customerValidationMsg = document.getElementById('customerValidationMsg');

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
            
            // Validation mesajını gizle
            customerValidationMsg.classList.add('d-none');
            customerValidationMsg.classList.remove('d-block');
            this.classList.remove('is-invalid');
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
            
            // Validation mesajını gizle
            customerValidationMsg.classList.add('d-none');
            customerSelect.classList.remove('is-invalid');
        }
    });
    
    // Enter tuşuyla arama yaptığında form submit olmasın
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
    
    // Tüm seçenekleri göster
    const options = select.options;
    for (let i = 1; i < options.length; i++) {
        options[i].style.display = '';
    }
    
    searchInput.focus();
}

// Hizmet türü - diğer seçeneği
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