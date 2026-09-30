</main>
<footer class="footer">
    <div class="container">
        Fuente: <a href="https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp" rel="noopener">API pública del Banco Central de la República Argentina</a>.
        Los datos se actualizan cada pocas horas. Sitio sin afiliación con el BCRA.
    </div>
</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="assets/app.js"></script>
<?php if (!empty($script)): ?>
<script>
<?= $script ?>
</script>
<?php endif; ?>
</body>
</html>
