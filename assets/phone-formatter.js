/**
 * Telefon Numarası Otomatik Formatlama
 * Türk telefon numarası formatı: 0555 123 4567
 */

function formatPhoneNumber(input) {
    // Sadece rakamları al
    let value = input.value.replace(/\D/g, '');
    
    // Boş ise return
    if (value.length === 0) {
        input.value = '';
        return;
    }
    
    // Maksimum 11 rakam (0 ile başlayan Türk numarası)
    if (value.length > 11) {
        value = value.substring(0, 11);
    }
    
    // Formatla: 0XXX XXX XX XX
    let formatted = '';
    
    if (value.length > 0) {
        formatted = value.substring(0, 4); // İlk 4 rakam (0555)
    }
    if (value.length > 4) {
        formatted += ' ' + value.substring(4, 7); // Sonraki 3 rakam (123)
    }
    if (value.length > 7) {
        formatted += ' ' + value.substring(7, 9); // Sonraki 2 rakam (45)
    }
    if (value.length > 9) {
        formatted += ' ' + value.substring(9, 11); // Son 2 rakam (67)
    }
    
    input.value = formatted;
}

// Telefon inputlarına otomatik format uygula
function initPhoneFormatters() {
    const phoneInputs = document.querySelectorAll('input[name="phone"], .phone-input');
    
    phoneInputs.forEach(input => {
        // Input değiştiğinde formatla
        input.addEventListener('input', function(e) {
            formatPhoneNumber(e.target);
        });
        
        // Paste olayında formatla
        input.addEventListener('paste', function(e) {
            setTimeout(() => {
                formatPhoneNumber(e.target);
            }, 10);
        });
        
        // Yüklendiğinde mevcut değeri formatla
        if (input.value) {
            formatPhoneNumber(input);
        }
        
        // Placeholder güncelle
        if (!input.placeholder || input.placeholder === 'Telefon') {
            input.placeholder = '0555 123 4567';
        }
    });
}

// Sayfa yüklendiğinde çalıştır
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPhoneFormatters);
} else {
    initPhoneFormatters();
}
