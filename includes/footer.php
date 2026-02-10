</div> <!-- container kapanış -->

<!-- Modern Footer -->
<footer class="mt-5 py-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: rgba(255,255,255,0.8);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                <h6 class="mb-2" style="color: #22c55e; font-weight: 800;">
                    <i class="bi bi-lightning-charge-fill me-2"></i>WINERGY TECHNOLOGIES
                </h6>
                <p class="mb-0 small" style="color: rgba(255,255,255,0.6);">
                    Enerji Verimliliği ve Tasarrufu Mühendislik Sistemleri
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="d-flex justify-content-center justify-content-md-end align-items-center gap-3">
                    <a href="tel:03123956828" class="text-white text-decoration-none">
                        <i class="bi bi-telephone-fill me-1"></i> 0312 395 68 28
                    </a>
                    <span style="color: rgba(255,255,255,0.3);">|</span>
                    <a href="https://winergytechnologies.com" target="_blank" class="text-white text-decoration-none">
                        <i class="bi bi-globe me-1"></i> winergytechnologies.com
                    </a>
                </div>
                <p class="mb-0 mt-2 small" style="color: rgba(255,255,255,0.5);">
                    &copy; <?php echo date('Y'); ?> Tüm Hakları Saklıdır
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Smooth animations
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

</body>
</html>