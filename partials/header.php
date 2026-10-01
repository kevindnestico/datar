<?php
require_once __DIR__ . '/../lib/bcra.php';

$paginas = [
    'bcra' => [
        'index.php'      => 'Panel',
        'cambio.php'     => 'Tipo de cambio',
        'monetario.php'  => 'Dinero y crédito',
        'tasas.php'      => 'Tasas',
        'precios.php'    => 'Inflación e índices',
        'explorador.php' => 'Explorador',
    ],
    'indec' => [
        'actividad.php'          => 'Actividad',
        'precios-salarios.php'   => 'Precios y salarios',
        'trabajo-comercio.php'   => 'Trabajo y comercio',
    ],
];
$fuente = $fuente ?? 'bcra';
$actual = basename($_SERVER['SCRIPT_NAME']);
$titulo = $titulo ?? 'DatAR';
?>
<!doctype html>
<html lang="es-AR"<?= $fuente === 'indec' ? ' class="indec"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · DatAR</title>
    <meta name="description" content="Datos económicos de Argentina a partir de la API pública del BCRA.">
    <link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
    <script>
        // Aplica el tema guardado antes de pintar para evitar parpadeo.
        try { const t = localStorage.getItem('datar-tema'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= enlace('index.php') ?>">Dat<span>AR</span></a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="nav" aria-label="Abrir menú">☰</button>
        <button class="theme-toggle" type="button" aria-label="Cambiar tema claro/oscuro" title="Cambiar tema">◐</button>
    </div>
    <nav id="nav" class="nav" aria-label="Secciones">
        <div class="nav-inner">
            <?php foreach ($paginas as $grupo => $links): ?>
                <div class="nav-grupo <?= $grupo ?>">
                    <span class="fuente fuente-<?= $grupo ?>" title="Datos del <?= strtoupper($grupo) ?>"><?= strtoupper($grupo) ?></span>
                    <?php foreach ($links as $archivo => $nombre): ?>
                        <a href="<?= enlace($archivo) ?>" <?= $archivo === $actual ? 'aria-current="page"' : '' ?>><?= h($nombre) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main class="container">
