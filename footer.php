</div> <footer class="bg-dark text-white text-center py-3 mt-auto">
    <div class="container">
        <p class="mb-0">&copy; <?php echo date("Y"); ?> Ohael Bibel-App</p>
        <p class="mb-0">
            <a href="impressum.php" class="text-white-50">Impressum & Datenschutz</a>
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Globale JS Variable für eingeloggten User (falls benötigt)
    const currentUserIdFromPHP = <?php echo $_SESSION['user_id'] ?? 0; ?>;
</script>
<script src="app.js?v=<?php echo time(); // Cache-Busting für app.js ?>"></script>

<?php if(basename($_SERVER['PHP_SELF']) == 'status.php' && $loggedInUserId > 0 && !empty($chartData)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('colorUsageChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($chartLabels ?? []); ?>, // PHP Variablen müssen hier verfügbar sein
                    datasets: [{
                        label: 'Anzahl Markierungen',
                        data: <?php echo json_encode($chartData ?? []); ?>,
                        backgroundColor: <?php echo json_encode($chartBackgroundColors ?? []); ?>,
                        hoverOffset: 8,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, boxWidth: 12 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                        const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                        label += context.parsed + ' (' + percentage + '%)';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
<?php endif; ?>


</body>
</html>