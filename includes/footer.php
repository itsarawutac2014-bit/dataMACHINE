        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container-fluid px-3 px-xl-4 text-center text-md-between d-flex flex-column flex-md-row justify-content-between align-items-center">
            <span class="text-muted fs-8">
                &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> | ระบบติดตามและรายงานประสิทธิภาพการเดินงาน BHS
            </span>
            <span class="text-muted fs-8 mt-1 mt-md-0">
                <i class="fa-solid fa-server text-success me-1"></i> XAMPP PHP 8.x + MySQL PDO | Prepared Statements 100%
            </span>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS (Local with CDN Fallback) -->
    <script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>if (typeof bootstrap === 'undefined') document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"><\/script>');</script>
    
    <!-- Chart.js (Local with CDN Fallback) -->
    <script src="<?= BASE_URL ?>/assets/js/chart.umd.min.js"></script>
    <script>if (typeof Chart === 'undefined') document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>');</script>

    <!-- html2canvas (Local with CDN Fallback) -->
    <script src="<?= BASE_URL ?>/assets/js/html2canvas.min.js"></script>
    <script>if (typeof html2canvas === 'undefined') document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"><\/script>');</script>

    <!-- Custom App JS -->
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
