<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';
require_once 'includes/logger.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

$success_count = 0;
$error_count = 0;
$errors = [];
$preview_data = [];

// Excel/CSV işleme fonksiyonu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
    if (!csrf_validate_token($_POST['csrf_token'] ?? '')) {
        csrf_error();
    }
    
    $file = $_FILES['import_file'];
    
    if ($file['error'] == 0) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_ext == 'csv') {
            // CSV dosyasını oku
            $handle = fopen($file['tmp_name'], 'r');
            
            // Encoding algılama ve düzeltme
            $first_line = fgets($handle);
            rewind($handle);
            
            // UTF-8'e çevir
            if (!mb_check_encoding($first_line, 'UTF-8')) {
                $contents = file_get_contents($file['tmp_name']);
                $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1254');
                file_put_contents($file['tmp_name'], $contents);
                $handle = fopen($file['tmp_name'], 'r');
            }
            
            // Başlık satırını atla
            $headers = fgetcsv($handle, 0, ';');
            
            $row_number = 1;
            while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                $row_number++;
                
                // Boş satırları atla
                if (empty(array_filter($data))) continue;
                
                // Müşteri adı ve iş adı boşsa atla
                if (empty($data[0]) || empty($data[1])) continue;
                
                try {
                    $company_name = trim($data[0]);
                    $service_type = trim($data[1]);
                    $start_date = !empty($data[2]) ? parseDate($data[2]) : null;
                    $due_date = !empty($data[3]) ? parseDate($data[3]) : null;
                    $invoice_amount_raw = !empty($data[4]) ? trim($data[4]) : '';
                    $invoice_date_raw = !empty($data[5]) ? trim($data[5]) : '';
                    $total_amount = !empty($data[6]) ? parseAmount($data[6]) : null;
                    
                    // Tevkifat durumunu kontrol et
                    $withholding_raw = !empty($data[7]) ? mb_strtoupper(trim($data[7]), 'UTF-8') : '';
                    if ($withholding_raw === 'VAR' || $withholding_raw === 'YOK') {
                        $withholding = $withholding_raw;
                    } else {
                        $withholding = 'belirtilmedi';
                    }
                    
                    // 8. sütun - Ek notlar
                    $extra_notes = !empty($data[8]) ? trim($data[8]) : '';
                    
                    // Fatura tutarını parse et (5.000,00+KDV formatı)
                    $invoice_amount = parseAmount($invoice_amount_raw);
                    $vat_included = (stripos($invoice_amount_raw, '+KDV') !== false || stripos($invoice_amount_raw, '+kdv') !== false) ? 0 : 1;
                    
                    // Fatura tarihini parse et - tarih değilse (BEKLEMEDE, AY SONU gibi) null yap
                    $invoice_date = parseDate($invoice_date_raw);
                    
                    // Müşteriyi kontrol et veya ekle
                    $customer_id = getOrCreateCustomer($db, $company_name);
                    
                    if (!$customer_id) {
                        throw new Exception("Müşteri oluşturulamadı: $company_name");
                    }
                    
                    // Açıklama alanını oluştur - tüm ek bilgileri ekle
                    $description_parts = ["Excel'den toplu import edildi"];
                    
                    if ($invoice_amount_raw) {
                        $description_parts[] = "Fatura: $invoice_amount_raw";
                    }
                    
                    if ($total_amount) {
                        $description_parts[] = "Toplam Tutar: " . number_format($total_amount, 2, ',', '.') . " TL";
                    }
                    
                    if ($withholding && $withholding !== 'belirtilmedi') {
                        $description_parts[] = "Tevkifat: $withholding";
                    }
                    
                    if ($invoice_date_raw && !$invoice_date) {
                        // Tarih parse edilemedi, muhtemelen metin (BEKLEMEDE, AY SONU vs)
                        $description_parts[] = "Fatura Notu: $invoice_date_raw";
                    }
                    
                    if ($extra_notes) {
                        $description_parts[] = "Not: $extra_notes";
                    }
                    
                    $description = implode("\n", $description_parts);
                    
                    // İş kaydını ekle
                    $sql = "INSERT INTO jobs (
                        customer_id, service_type, title, description, 
                        assigned_user_id, start_date, due_date, 
                        invoice_amount, invoice_vat_included, invoice_date, 
                        invoice_total_amount, invoice_withholding, 
                        status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $title = "$company_name - $service_type";
                    
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        $customer_id,
                        json_encode([$service_type], JSON_UNESCAPED_UNICODE),
                        $title,
                        $description,
                        null, // Atanmamış
                        $start_date,
                        $due_date,
                        $invoice_amount,
                        $vat_included,
                        $invoice_date,
                        $total_amount,
                        $withholding,
                        'Açıldı',
                        $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        $success_count++;
                    } else {
                        throw new Exception("Veritabanı kaydı oluşturulamadı");
                    }
                    
                } catch (Exception $e) {
                    $error_count++;
                    $errors[] = "Satır $row_number: " . $e->getMessage() . " - Müşteri: " . ($company_name ?? 'bilinmiyor');
                }
            }
            
            fclose($handle);
            
            if ($success_count > 0) {
                log_activity('Toplu Import', "$success_count iş kaydı başarıyla import edildi", 'SUCCESS');
            }
        }
    }
}

// Yardımcı fonksiyonlar
function parseDate($date_str) {
    if (empty($date_str)) return null;
    
    $date_str = trim($date_str);
    
    // Tarih formatlarını dene
    $formats = ['d.m.Y', 'd.m.y', 'd/m/Y', 'd-m-Y'];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $date_str);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    return null;
}

function parseAmount($amount_str) {
    if (empty($amount_str)) return null;
    
    // KDV ifadelerini kaldır
    $amount_str = preg_replace('/\+KDV|\+kdv|TL/i', '', $amount_str);
    
    // Sadece rakam, nokta ve virgül bırak
    $amount_str = preg_replace('/[^\d.,]/', '', $amount_str);
    
    // Türk formatı: 5.000,00 -> 5000.00
    $amount_str = str_replace('.', '', $amount_str);
    $amount_str = str_replace(',', '.', $amount_str);
    
    return is_numeric($amount_str) ? floatval($amount_str) : null;
}

function getOrCreateCustomer($db, $company_name) {
    // Önce var mı kontrol et
    $stmt = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
    $stmt->execute([$company_name]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer) {
        return $customer['id'];
    }
    
    // Yoksa yeni müşteri ekle
    $stmt = $db->prepare("INSERT INTO customers (name, created_by) VALUES (?, ?)");
    if ($stmt->execute([$company_name, $_SESSION['user_id']])) {
        return $db->lastInsertId();
    }
    
    return null;
}
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-winergy text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Excel/CSV Toplu Import
                    </h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($success_count > 0 || $error_count > 0): ?>
                        <div class="alert alert-<?php echo $error_count == 0 ? 'success' : 'warning'; ?>">
                            <h5>Import Sonucu</h5>
                            <p class="mb-0">
                                <i class="bi bi-check-circle text-success"></i> <strong><?php echo $success_count; ?></strong> kayıt başarıyla eklendi<br>
                                <?php if ($error_count > 0): ?>
                                    <i class="bi bi-exclamation-triangle text-danger"></i> <strong><?php echo $error_count; ?></strong> kayıt eklenemedi
                                <?php endif; ?>
                            </p>
                            
                            <?php if (!empty($errors)): ?>
                                <hr>
                                <h6>Hatalar:</h6>
                                <ul class="small mb-0">
                                    <?php foreach (array_slice($errors, 0, 10) as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($errors) > 10): ?>
                                        <li><em>... ve <?php echo count($errors) - 10; ?> hata daha</em></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-success">İş Listesine Git</a>
                                <a href="toplu-import.php" class="btn btn-secondary">Yeni Import</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Kullanım Talimatları</h6>
                        <ol class="mb-0 small">
                            <li>CSV dosyanızı aşağıdaki forma uygun hazırlayın</li>
                            <li>Dosyayı seçin ve "Import Et" butonuna tıklayın</li>
                            <li>Sistem otomatik olarak müşterileri oluşturacak ve iş kayıtlarını ekleyecek</li>
                            <li>Mevcut müşteriler tekrar eklenmeyecek, sadece yeni iş kayıtları eklenecek</li>
                        </ol>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_input(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">CSV Dosyası Seçin</label>
                            <input type="file" name="import_file" class="form-control" accept=".csv" required>
                            <small class="text-muted">
                                Sadece .csv dosyaları kabul edilir. Excel dosyanızı CSV olarak kaydedin.
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-winergy px-4">
                            <i class="bi bi-upload"></i> Import Et
                        </button>
                        <a href="index.php" class="btn btn-light">İptal</a>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="accordion" id="formatAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#formatInfo">
                                    <i class="bi bi-question-circle me-2"></i> CSV Dosya Formatı
                                </button>
                            </h2>
                            <div id="formatInfo" class="accordion-collapse collapse" data-bs-parent="#formatAccordion">
                                <div class="accordion-body">
                                    <p><strong>CSV dosyanız şu sütunları içermelidir (noktalı virgül ";" ile ayrılmış):</strong></p>
                                    
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Sıra</th>
                                                <th>Sütun Adı</th>
                                                <th>Açıklama</th>
                                                <th>Örnek</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>Çalışılan Kurum</td>
                                                <td>Firma/Müşteri Adı</td>
                                                <td>ANKARA ÜNİVERSİTESİ</td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>İŞİN ADI</td>
                                                <td>Hizmet Türü</td>
                                                <td>ISO, ETÜD, EKB, ENERJI YÖNETİCİLİĞİ</td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>Sözleşme Başlangıç</td>
                                                <td>Başlangıç Tarihi</td>
                                                <td>1.06.2023 veya 01/06/2023</td>
                                            </tr>
                                            <tr>
                                                <td>4</td>
                                                <td>Sözleşme Bitiş</td>
                                                <td>Bitiş Tarihi</td>
                                                <td>1.06.2024</td>
                                            </tr>
                                            <tr>
                                                <td>5</td>
                                                <td>Kesilecek Fatura Tutarı</td>
                                                <td>Tutar (KDV belirtmeli)</td>
                                                <td>5.000,00+KDV veya 11.250,00</td>
                                            </tr>
                                            <tr>
                                                <td>6</td>
                                                <td>Fatura Tarihi</td>
                                                <td>Fatura Kesim Tarihi</td>
                                                <td>1.07.2024</td>
                                            </tr>
                                            <tr>
                                                <td>7</td>
                                                <td>TOPLAM</td>
                                                <td>Toplam Tutar</td>
                                                <td>72.000,00</td>
                                            </tr>
                                            <tr>
                                                <td>8</td>
                                                <td>TEVKİFAT</td>
                                                <td>Tevkifat Durumu</td>
                                                <td>VAR, YOK veya boş</td>
                                            </tr>
                                            <tr>
                                                <td>9</td>
                                                <td>Ek Notlar</td>
                                                <td>Özel durumlar, açıklamalar (opsiyonel)</td>
                                                <td>ISO için kesilmiş fatura, vb.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    
                                    <div class="alert alert-success mt-3">
                                        <h6><i class="bi bi-check-circle"></i> Sistemin Otomatik Yaptıkları</h6>
                                        <ul class="mb-0 small">
                                            <li><strong>Müşteri Kontrolü:</strong> Aynı isimdeki müşteri varsa tekrar eklenmez</li>
                                            <li><strong>İş Başlığı:</strong> Otomatik oluşturulur (Müşteri Adı - Hizmet Türü)</li>
                                            <li><strong>KDV Durumu:</strong> Fatura tutarında "+KDV" varsa "KDV Hariç", yoksa "KDV Dahil"</li>
                                            <li><strong>Açıklama:</strong> Tüm ek bilgiler (fatura notları, özel durumlar) açıklama alanına eklenir</li>
                                            <li><strong>Fatura Tarihi:</strong> "BEKLEMEDE", "AY SONU" gibi metinler varsa not olarak kaydedilir</li>
                                            <li><strong>Durum:</strong> Tüm işler "Açıldı" durumunda başlar</li>
                                            <li><strong>Atanan Personel:</strong> Başlangıçta boş (admin sonradan atayacak)</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="alert alert-warning mt-3">
                                        <h6><i class="bi bi-exclamation-triangle"></i> Önemli Notlar</h6>
                                        <ul class="mb-0 small">
                                            <li>CSV dosyası <strong>noktalı virgül (;)</strong> ile ayrılmış olmalı</li>
                                            <li>Türkçe karakterler düzgün görünmüyorsa, dosyayı <strong>UTF-8</strong> veya <strong>Windows-1254</strong> olarak kaydedin</li>
                                            <li>Excel'den CSV'ye dönüştürürken: Dosya → Farklı Kaydet → CSV (Virgülle Ayrılmış)</li>
                                            <li>İlk satır başlık satırıdır, atlanacaktır</li>
                                            <li>Boş satırlar otomatik olarak atlanır</li>
                                            <li><strong>Zorunlu Sütunlar:</strong> Sadece 1. (Müşteri) ve 2. (Hizmet Türü) - diğerleri opsiyonel</li>
                                            <li>Fatura tarihi metin olabilir (örn: "BEKLEMEDE", "AY SONU 31.12.2024")</li>
                                            <li>9. sütun (Ek Notlar) varsa kayıt açıklamasına eklenir</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form submit animasyonu
document.querySelector('form').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Import ediliyor...';
    btn.disabled = true;
});
</script>

<?php include 'includes/footer.php'; ?>
