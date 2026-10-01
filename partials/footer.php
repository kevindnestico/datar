</main>
<footer class="footer">
    <div class="container">
        <?php if (($fuente ?? 'bcra') === 'ambas'): ?>
        Fuentes: <a href="https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp" rel="noopener">API pública del BCRA</a> e <a href="https://www.indec.gob.ar" rel="noopener">INDEC</a> vía la <a href="https://datos.gob.ar/series" rel="noopener">API de Series de Tiempo de datos.gob.ar</a>.
        <?php elseif (($fuente ?? 'bcra') === 'indec'): ?>
        Fuente: <a href="https://www.indec.gob.ar" rel="noopener">Instituto Nacional de Estadística y Censos (INDEC)</a>, vía la <a href="https://datos.gob.ar/series" rel="noopener">API de Series de Tiempo de datos.gob.ar</a>.
        <?php else: ?>
        Fuente: <a href="https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp" rel="noopener">API pública del Banco Central de la República Argentina</a>.
        <?php endif; ?>
        Los datos se actualizan cada pocas horas<?= es_estatico() ? ' (última actualización: ' . date('d/m/Y H:i') . ' hora de Argentina)' : '' ?>. Sitio sin afiliación con el BCRA ni el INDEC.
    </div>
</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<?php if (es_estatico()): ?>
<script>window.DATAR_STATIC = true;</script>
<?php endif; ?>
<script src="<?= asset('assets/app.js') ?>"></script>
<?php if (!empty($script)): ?>
<script>
<?= $script ?>
</script>
<?php endif; ?>
</body>
</html>
