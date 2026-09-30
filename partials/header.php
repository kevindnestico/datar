<?php
require_once __DIR__ . '/../lib/bcra.php';

$paginas = [
    'index.php'      => 'Panel',
    'cambio.php'     => 'Tipo de cambio',
    'monetario.php'  => 'Dinero y crédito',
    'tasas.php'      => 'Tasas',
    'precios.php'    => 'Inflación e índices',
    'explorador.php' => 'Explorador',
];
$actual = basename($_SERVER['SCRIPT_NAME']);
$titulo = $titulo ?? 'DatAR';
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · DatAR</title>
    <meta name="description" content="Datos económicos de Argentina a partir de la API pública del BCRA.">
    <link rel="stylesheet" href="assets/app.css">
    <script>
        // Aplica el tema guardado antes de pintar para evitar parpadeo.
        try { const t = localStorage.getItem('datar-tema'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php">Dat<span>AR</span></a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="nav" aria-label="Abrir menú">☰</button>
        <nav id="nav" class="nav">
            <?php foreach ($paginas as $archivo => $nombre): ?>
                <a href="<?= $archivo ?>" <?= $archivo === $actual ? 'aria-current="page"' : '' ?>><?= h($nombre) ?></a>
            <?php endforeach; ?>
        </nav>
        <button class="theme-toggle" type="button" aria-label="Cambiar tema claro/oscuro" title="Cambiar tema">◐</button>
    </div>
</header>
<main class="container">
