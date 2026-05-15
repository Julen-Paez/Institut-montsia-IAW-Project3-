<?php
/**
 * header.php
 * Capçalera comuna. Mostra navbar amb rol i permisos de l'usuari actiu.
 *
 * @author Institut Montsià - ASIX
 * @version 2.0
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$depth = substr_count($_SERVER['PHP_SELF'], '/') - 2;
$base  = str_repeat('../', $depth);

$rolColors = [
    'admin'     => '#9b59b6',
    'professor' => '#e8a020',
    'editor'    => '#2980b9',
    'alumne'    => '#27ae60',
];
$rolActual = $_SESSION['rol'] ?? '';
$rolColor  = $rolColors[$rolActual] ?? '#999';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Institut Montsià' ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        code, .stat-number { font-family: 'Courier New', Courier, monospace; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <span class="nav-logo">IM</span>
        <span class="nav-title">Institut Montsià</span>
    </div>

    <?php if (isset($_SESSION['usuari'])): ?>
    <div class="nav-info">
        <!-- Navegació per rols de gestió -->
        <?php if (in_array($rolActual, ['admin', 'professor', 'editor'])): ?>
        <div class="nav-links">
            <a href="/professor/dashboard.php">Inici</a>
            <a href="/professor/alumnes.php">Alumnes</a>
            <a href="/professor/material.php">Material</a>
            <a href="/professor/assignacions.php">Assignacions</a>
            <a href="/professor/incidencies.php">Incidències</a>
        </div>
        <?php endif; ?>

        <span class="nav-user"><?= htmlspecialchars($_SESSION['nom']) ?></span>

        <!-- Badge de rol amb color distintiu -->
        <span class="nav-badge" style="background:<?= $rolColor ?>;color:white;">
            <?= getNomRol() ?>
        </span>

        <a href="/logout.php" class="btn-logout">Sortir</a>
    </div>
    <?php endif; ?>
</nav>

<!-- Barra de permisos (visible pels rols de gestió) -->
<?php if (isset($_SESSION['usuari']) && in_array($rolActual, ['admin', 'professor', 'editor'])): ?>
<div class="permisos-bar">
    <span>Permisos actius:</span>
    <span class="perm <?= potCrear()    ? 'perm-ok' : 'perm-no' ?>"><?= potCrear()    ? '✅' : '❌' ?> Crear</span>
    <span class="perm <?= potEditar()   ? 'perm-ok' : 'perm-no' ?>"><?= potEditar()   ? '✅' : '❌' ?> Editar</span>
    <span class="perm <?= potEliminar() ? 'perm-ok' : 'perm-no' ?>"><?= potEliminar() ? '✅' : '❌' ?> Eliminar</span>
    <span class="perm perm-ok">✅ Llegir</span>
</div>
<?php endif; ?>

<main class="main-content">
