<?php
/**
 * Winergy İş Takip Sistemi - Excel Export Fonksiyonları
 * CSV formatında veri export işlemleri
 */

/**
 * CSV export başlatır ve header ayarlarını yapar
 * @param string $filename Dosya adı (otomatik .csv eklenir)
 */
function export_csv_start($filename = 'export') {
    // Otomatik dosya adlandırma: tarih-saat ile
    $timestamp = date('Y-m-d_H-i-s');
    $filename = sanitize_filename($filename) . '_' . $timestamp . '.csv';
    
    // UTF-8 BOM ekle (Türkçe karakterler için)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
}

/**
 * Dosya adını temizle (güvenlik)
 * @param string $filename
 * @return string
 */
function sanitize_filename($filename) {
    // Türkçe karakterleri koru, sadece zararlı karakterleri temizle
    $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
    return $filename;
}

/**
 * CSV satırı yazar
 * @param array $row Veri dizisi
 */
function export_csv_row($row) {
    $output = fopen('php://output', 'w');
    fputcsv($output, $row, ';'); // Türkiye'de Excel için ';' kullanılır
    fclose($output);
}

/**
 * İş listesi verilerini export eder
 * @param mysqli|PDO $db Veritabanı bağlantısı
 * @param array $filters Filtreleme parametreleri
 * @param array $selected_ids Seçili iş IDs (opsiyonel)
 */
function export_jobs($db, $filters = [], $selected_ids = null) {
    // Filtreleme mantığı
    $where_clauses = [];
    $params = [];
    
    // Seçili kayıtlar varsa sadece onları al
    if ($selected_ids && is_array($selected_ids) && count($selected_ids) > 0) {
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $where_clauses[] = "j.id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
    }
    
    // Hizmet Türü Filtresi
    if (!empty($filters['service_type'])) {
        $where_clauses[] = "j.service_type LIKE ?";
        $params[] = '%' . $filters['service_type'] . '%';
    }
    
    // Durum Filtresi
    if (!empty($filters['status'])) {
        $where_clauses[] = "j.status = ?";
        $params[] = $filters['status'];
    }
    
    // Tarih Aralığı Filtresi
    if (!empty($filters['date_start'])) {
        $where_clauses[] = "j.created_at >= ?";
        $params[] = $filters['date_start'] . ' 00:00:00';
    }
    if (!empty($filters['date_end'])) {
        $where_clauses[] = "j.created_at <= ?";
        $params[] = $filters['date_end'] . ' 23:59:59';
    }
    
    // Atanan Kullanıcı Filtresi
    if (!empty($filters['assigned_user'])) {
        $where_clauses[] = "j.assigned_user_id = ?";
        $params[] = $filters['assigned_user'];
    }
    
    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $query_sql = "
        SELECT 
            j.id,
            j.service_type,
            c.name as customer_name,
            c.contact_name as customer_contact,
            c.phone as customer_phone,
            c.email as customer_email,
            j.description,
            j.status,
            u.name as staff_name,
            u.role as staff_role,
            u.email as staff_email,
            j.due_date,
            j.invoice_amount,
            j.invoice_vat_included,
            j.invoice_date,
            j.invoice_total_amount,
            j.invoice_withholding,
            j.created_at,
            j.updated_at,
            creator.name as created_by_name,
            DATEDIFF(j.due_date, CURDATE()) as days_remaining,
            (SELECT COUNT(*) FROM job_notes WHERE job_id = j.id) as note_count,
            (SELECT COUNT(*) FROM job_files WHERE job_id = j.id) as file_count
        FROM jobs j 
        LEFT JOIN customers c ON j.customer_id = c.id 
        LEFT JOIN users u ON j.assigned_user_id = u.id 
        LEFT JOIN users creator ON j.created_by = creator.id
        $where_sql
        ORDER BY j.id DESC
    ";
    
    $stmt = $db->prepare($query_sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV başlat
    export_csv_start('is_listesi_detayli');
    
    // Başlıklar (Detaylı)
    export_csv_row([
        'İş No',
        'Hizmet Türü',
        'Durum',
        'Müşteri Firma',
        'Müşteri İlgili Kişi',
        'Müşteri Telefon',
        'Müşteri Email',
        'Açıklama',
        'Atanan Personel',
        'Personel Rol',
        'Personel Email',
        'Teslim Tarihi',
        'Kalan Gün',
        'Gecikme Durumu',
        'Fatura Tutarı',
        'Toplam Tutar',
        'KDV Durumu',
        'Tevkifat',
        'Fatura Tarihi',
        'Not Sayısı',
        'Dosya Sayısı',
        'Oluşturan',
        'Oluşturulma Tarihi',
        'Son Güncelleme'
    ]);
    
    // Veriler
    foreach ($jobs as $job) {
        // Rol çevirisi
        $role_tr = [
            'admin' => 'Yönetici',
            'operasyon' => 'Operasyon',
            'danisman' => 'Danışman'
        ];
        
        // Gecikme durumu
        $days_remaining = (int)($job['days_remaining'] ?? 0);
        $status = $job['status'] ?? '';
        $is_completed = in_array($status, ['Tamamlandı', 'İptal']);
        
        if ($is_completed) {
            $delay_status = 'Tamamlandı';
        } elseif ($days_remaining < 0) {
            $delay_status = abs($days_remaining) . ' gün gecikme';
        } elseif ($days_remaining == 0) {
            $delay_status = 'Bugün teslim';
        } elseif ($days_remaining <= 3) {
            $delay_status = 'Acil (' . $days_remaining . ' gün)';
        } else {
            $delay_status = $days_remaining . ' gün kaldı';
        }
        
        // Telefon formatı düzelt (Excel için)
        $phone = $job['customer_phone'] ?? '';
        if (!empty($phone)) {
            $phone = "'" . $phone;
        }
        
        // service_type'ı JSON'dan decode et ve virgülle birleştir
        $service_types_str = '';
        if (!empty($job['service_type'])) {
            $service_types = json_decode($job['service_type'], true);
            if (is_array($service_types)) {
                $service_types_str = implode(', ', $service_types);
            } else {
                $service_types_str = $job['service_type'];
            }
        }
        
        // Tevkifat etiketi
        $withholding_label = '-';
        if (!empty($job['invoice_withholding'])) {
            $withholding_labels = [
                'var' => 'Var',
                'yok' => 'Yok',
                'belirtilmedi' => 'Belirtilmedi'
            ];
            $withholding_label = $withholding_labels[$job['invoice_withholding']] ?? '-';
        }
        
        // Tutar formatlama - gereksiz sıfırları kaldır
        $format_amount = function($amount) {
            if (empty($amount)) return '-';
            return rtrim(rtrim(number_format($amount, 2, ',', '.'), '0'), ',') . ' TL';
        };
        
        export_csv_row([
            $job['id'],
            $service_types_str,
            $status,
            $job['customer_name'] ?? '',
            $job['customer_contact'] ?? '-',
            $phone,
            $job['customer_email'] ?? '-',
            $job['description'] ?? '',
            $job['staff_name'] ?? 'Atanmamış',
            $job['staff_name'] ? ($role_tr[$job['staff_role']] ?? $job['staff_role']) : '-',
            $job['staff_email'] ?? '-',
            $job['due_date'] ?? '',
            $days_remaining >= 0 ? '+' . $days_remaining : $days_remaining,
            $delay_status,
            $format_amount($job['invoice_amount']),
            $format_amount($job['invoice_total_amount']),
            isset($job['invoice_vat_included']) ? ($job['invoice_vat_included'] == 1 ? 'KDV Dahil' : 'KDV Hariç') : '-',
            $withholding_label,
            !empty($job['invoice_date']) ? date('d.m.Y', strtotime($job['invoice_date'])) : '-',
            $job['note_count'] ?? 0,
            $job['file_count'] ?? 0,
            $job['created_by_name'] ?? '-',
            $job['created_at'] ?? '',
            $job['updated_at'] ?? ''
        ]);
    }
    
    exit;
}

/**
 * Müşteri listesini export eder
 * @param PDO $db
 * @param string $search Arama kelimesi
 * @param array $selected_ids Seçili müşteri IDs
 */
function export_customers($db, $search = '', $selected_ids = null) {
    $where_clauses = [];
    $params = [];
    
    // Seçili kayıtlar varsa
    if ($selected_ids && is_array($selected_ids) && count($selected_ids) > 0) {
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $where_clauses[] = "c.id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
    }
    
    // Arama filtresi
    if (!empty($search)) {
        $where_clauses[] = "(c.name LIKE ? OR c.contact_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $query_sql = "
        SELECT 
            c.id,
            c.name,
            c.contact_name,
            c.phone,
            c.email,
            c.address,
            c.created_at,
            u.name as creator_name
        FROM customers c
        LEFT JOIN users u ON c.created_by = u.id
        $where_sql
        ORDER BY c.name ASC
    ";
    
    $stmt = $db->prepare($query_sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV başlat
    export_csv_start('musteriler');
    
    // Başlıklar
    export_csv_row([
        'ID',
        'Firma Adı',
        'İlgili Kişi',
        'Telefon',
        'E-Posta',
        'Adres',
        'Oluşturulma Tarihi',
        'Oluşturan'
    ]);
    
    // Veriler
    foreach ($customers as $customer) {
        // Telefon numarasını Excel için formatla (bilimsel notasyon engelle)
        $phone = $customer['phone'] ?? '';
        if (!empty($phone)) {
            // Başına apostrof ekleyerek metin olarak işaretle
            $phone = "'" . $phone;
        }
        
        export_csv_row([
            $customer['id'],
            $customer['name'] ?? '',
            $customer['contact_name'] ?? '',
            $phone,
            $customer['email'] ?? '',
            $customer['address'] ?? '',
            $customer['created_at'] ?? '',
            $customer['creator_name'] ?? ''
        ]);
    }
    
    exit;
}

/**
 * Kullanıcı listesini export eder
 * @param PDO $db
 * @param array $selected_ids
 */
function export_users($db, $selected_ids = null) {
    $where_clauses = [];
    $params = [];
    
    // Seçili kayıtlar
    if ($selected_ids && is_array($selected_ids) && count($selected_ids) > 0) {
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $where_clauses[] = "id IN ($placeholders)";
        $params = array_merge($params, $selected_ids);
    }
    
    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $query_sql = "
        SELECT 
            id,
            name,
            email,
            role,
            is_active,
            created_at,
            last_login
        FROM users
        $where_sql
        ORDER BY name ASC
    ";
    
    $stmt = $db->prepare($query_sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rol çevirileri
    $role_tr = [
        'admin' => 'Yönetici',
        'operasyon' => 'Operasyon',
        'danisman' => 'Danışman'
    ];
    
    // CSV başlat
    export_csv_start('kullanicilar');
    
    // Başlıklar
    export_csv_row([
        'ID',
        'Ad Soyad',
        'E-Posta',
        'Rol',
        'Durum',
        'Kayıt Tarihi',
        'Son Giriş'
    ]);
    
    // Veriler
    foreach ($users as $user) {
        export_csv_row([
            $user['id'],
            $user['name'] ?? '',
            $user['email'] ?? '',
            $role_tr[$user['role']] ?? $user['role'],
            $user['is_active'] == 1 ? 'Aktif' : 'Pasif',
            $user['created_at'] ?? '',
            $user['last_login'] ?? 'Hiç giriş yapmadı'
        ]);
    }
    
    exit;
}

/**
 * Rapor verilerini export eder
 * @param PDO $db
 * @param string $report_type Rapor tipi (overview, staff, service)
 */
function export_reports($db, $report_type = 'overview') {
    export_csv_start('rapor_' . $report_type);
    
    if ($report_type === 'staff') {
        // Personel İş Yükü Raporu
        $staff_stats = $db->query("
            SELECT u.name, u.role, 
            COUNT(j.id) as total_jobs,
            COALESCE(SUM(CASE WHEN j.status = 'Tamamlandı' THEN 1 ELSE 0 END), 0) as completed_jobs,
            COALESCE(SUM(CASE WHEN j.status = 'Devam Ediyor' THEN 1 ELSE 0 END), 0) as ongoing_jobs,
            COALESCE(SUM(CASE WHEN j.status = 'Beklemede' THEN 1 ELSE 0 END), 0) as pending_jobs
            FROM users u
            LEFT JOIN jobs j ON u.id = j.assigned_user_id
            WHERE u.is_active = 1
            GROUP BY u.id, u.name, u.role
            ORDER BY total_jobs DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        export_csv_row(['Personel', 'Rol', 'Toplam İş', 'Tamamlanan', 'Devam Eden', 'Bekleyen']);
        
        foreach ($staff_stats as $stat) {
            export_csv_row([
                $stat['name'],
                $stat['role'],
                $stat['total_jobs'],
                $stat['completed_jobs'],
                $stat['ongoing_jobs'],
                $stat['pending_jobs']
            ]);
        }
        
    } elseif ($report_type === 'service') {
        // Hizmet Türü Dağılımı
        $service_stats = $db->query("
            SELECT service_type, COUNT(*) as total 
            FROM jobs 
            GROUP BY service_type 
            ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        export_csv_row(['Hizmet Türü', 'Toplam İş']);
        
        foreach ($service_stats as $stat) {
            export_csv_row([
                $stat['service_type'],
                $stat['total']
            ]);
        }
        
    } else {
        // Genel Özet
        $status_stats = $db->query("
            SELECT status, COUNT(*) as total 
            FROM jobs 
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        export_csv_row(['Durum', 'Toplam']);
        
        foreach ($status_stats as $stat) {
            export_csv_row([
                $stat['status'],
                $stat['total']
            ]);
        }
    }
    
    exit;
}

/**
 * Admin loglarını export eder
 * @param array $logs Log verileri (logger.php'den gelen)
 */
function export_logs($logs) {
    export_csv_start('admin_logs');
    
    // Başlıklar
    export_csv_row([
        'Tarih/Saat',
        'Kullanıcı',
        'İşlem',
        'Detaylar',
        'Seviye',
        'IP Adresi'
    ]);
    
    // Veriler
    foreach ($logs as $log) {
        export_csv_row([
            $log['datetime'] ?? '',
            $log['user_name'] ?? '',
            $log['action'] ?? '',
            $log['details'] ?? '',
            $log['level'] ?? 'INFO',
            $log['ip'] ?? ''
        ]);
    }
    
    exit;
}
?>
