<?php
/**
 * Müşteriler için Not Sistemi ve Kullanıcı Takibi
 */
require_once 'config/db.php';

echo "<h2>Müşteri Sistemi Veritabanı Güncellemesi</h2>";
echo "<p>Müşteri notları ve kullanıcı takibi ekleniyor...</p><hr>";

try {
    // 1. customers tablosuna kullanıcı takip alanları ekle
    echo "<h4>1. customers tablosu güncelleniyor...</h4>";
    
    $columns = $db->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('created_by', $columns)) {
        $db->exec("ALTER TABLE customers ADD COLUMN created_by INT NULL");
        echo "✓ created_by alanı eklendi<br>";
    } else {
        echo "✓ created_by alanı zaten mevcut<br>";
    }
    
    if (!in_array('created_at', $columns)) {
        $db->exec("ALTER TABLE customers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✓ created_at alanı eklendi<br>";
    } else {
        echo "✓ created_at alanı zaten mevcut<br>";
    }
    
    if (!in_array('updated_by', $columns)) {
        $db->exec("ALTER TABLE customers ADD COLUMN updated_by INT NULL");
        echo "✓ updated_by alanı eklendi<br>";
    } else {
        echo "✓ updated_by alanı zaten mevcut<br>";
    }
    
    if (!in_array('updated_at', $columns)) {
        $db->exec("ALTER TABLE customers ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP");
        echo "✓ updated_at alanı eklendi<br>";
    } else {
        echo "✓ updated_at alanı zaten mevcut<br>";
    }
    
    // 2. customer_notes tablosu oluştur
    echo "<br><h4>2. customer_notes tablosu oluşturuluyor...</h4>";
    
    $table_exists = $db->query("SHOW TABLES LIKE 'customer_notes'")->fetch();
    
    if (!$table_exists) {
        $db->exec("
            CREATE TABLE customer_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                user_id INT NOT NULL,
                note TEXT NOT NULL,
                note_type ENUM('general', 'agreement', 'important', 'meeting') DEFAULT 'general',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ customer_notes tablosu oluşturuldu<br>";
    } else {
        echo "✓ customer_notes tablosu zaten mevcut<br>";
    }
    
    // 3. Eski kayıtları güncelle
    echo "<br><h4>3. Eski müşteri kayıtları güncelleniyor...</h4>";
    
    $admin = $db->query("SELECT id, name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Varsayılan kullanıcı: <strong>{$admin['name']}</strong><br>";
        
        $stmt = $db->prepare("UPDATE customers SET created_by = ? WHERE created_by IS NULL");
        $stmt->execute([$admin['id']]);
        echo "✓ {$stmt->rowCount()} müşteri kaydı güncellendi<br>";
    }
    
    echo "<hr>";
    echo "<p style='color: green;'><strong>✓ Güncelleme Tamamlandı!</strong></p>";
    echo "<p><strong>Eklenen Özellikler:</strong></p>";
    echo "<ul>";
    echo "<li>✓ Müşteri notları sistemi (customer_notes tablosu)</li>";
    echo "<li>✓ customers.created_by - Müşteriyi ekleyen kullanıcı</li>";
    echo "<li>✓ customers.created_at - Eklenme zamanı</li>";
    echo "<li>✓ customers.updated_by - Son güncelleyen kullanıcı</li>";
    echo "<li>✓ customers.updated_at - Son güncelleme zamanı</li>";
    echo "<li>✓ Not tipleri: Genel, Anlaşma, Önemli, Toplantı</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<p style='color: red;'><strong>ÖNEMLİ:</strong> Bu dosyayı (update_customer_system.php) şimdi silin!</p>";
    echo "<p><a href='musteriler.php' class='btn btn-success'>Müşteriler Sayfasına Git</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>HATA: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #14b8a6;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    margin-top: 20px;
}
</style>
