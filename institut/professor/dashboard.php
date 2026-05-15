<?php
/**
 * professor/dashboard.php
 * Panell principal del professorat.
 * Mostra estadístiques generals del sistema.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

require_once '../includes/auth.php';
requireGestio();

require_once '../config/connexio.php';
$conn = getConnexio();

// ── Estadístiques generals ──
$totalAlumnes   = $conn->query("SELECT COUNT(*) FROM Alumnes")->fetch_row()[0];
$totalMaterial  = $conn->query("SELECT COUNT(*) FROM Material")->fetch_row()[0];
$totalAssignats = $conn->query("SELECT COUNT(*) FROM Assignacions WHERE dataFinal IS NULL")->fetch_row()[0];
$totalIncidencies = $conn->query("SELECT COUNT(*) FROM Incidencies WHERE dataTancada IS NULL")->fetch_row()[0];

// ── Incidències obertes recents ──
$incRecents = $conn->query("
    SELECT i.id, i.informacio, i.dataOberta,
           CONCAT(a.nom, ' ', a.cognom1) AS alumne,
           e.estat
    FROM Incidencies i
    LEFT JOIN Alumnes a ON i.idAlumne = a.id
    LEFT JOIN Estats  e ON i.idEstat  = e.id
    WHERE i.dataTancada IS NULL
    ORDER BY i.dataOberta DESC
    LIMIT 5
");

$conn->close();

$pageTitle = 'Dashboard · Professor';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Benvingut, <?= htmlspecialchars($_SESSION['nom']) ?></h2>
        <p>Panell de control · <?= date('d/m/Y') ?></p>
    </div>
</div>

<!-- Estadístiques -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-number"><?= $totalAlumnes ?></span>
        <span class="stat-label">👤 Alumnes registrats</span>
    </div>
    <div class="stat-card accent">
        <span class="stat-number"><?= $totalMaterial ?></span>
        <span class="stat-label">💻 Dispositius totals</span>
    </div>
    <div class="stat-card success">
        <span class="stat-number"><?= $totalAssignats ?></span>
        <span class="stat-label">✅ Assignacions actives</span>
    </div>
    <div class="stat-card danger">
        <span class="stat-number"><?= $totalIncidencies ?></span>
        <span class="stat-label">⚠️ Incidències obertes</span>
    </div>
</div>

<!-- Accions ràpides -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Accions ràpides</span>
    </div>
    <div style="display:flex; gap:1rem; flex-wrap:wrap;">
        <a href="alumnes.php" class="btn btn-primary">👤 Gestionar alumnes</a>
        <a href="alumnes.php?accio=nou" class="btn btn-success">➕ Nou alumne</a>
        <a href="material.php" class="btn btn-primary">💻 Gestionar material</a>
        <a href="material.php?accio=nou" class="btn btn-success">➕ Nou material</a>
        <a href="incidencies.php" class="btn btn-warning">⚠️ Veure incidències</a>
        <a href="assignacions.php" class="btn btn-primary">📋 Assignacions</a>
    </div>
</div>

<!-- Incidències recents -->
<div class="card">
    <div class="card-header">
        <span class="card-title">⚠️ Incidències obertes recents</span>
        <a href="incidencies.php" class="btn btn-sm btn-secondary">Veure totes</a>
    </div>
    <?php if ($incRecents->num_rows === 0): ?>
        <p style="color:var(--text-muted); font-size:14px;">Cap incidència oberta. ✅</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Alumne</th>
                    <th>Informació</th>
                    <th>Data</th>
                    <th>Estat</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($inc = $incRecents->fetch_assoc()): ?>
                <tr>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;">#<?= $inc['id'] ?></span></td>
                    <td><?= htmlspecialchars($inc['alumne'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(substr($inc['informacio'], 0, 60)) ?>...</td>
                    <td><?= $inc['dataOberta'] ?></td>
                    <td><span class="badge badge-warning"><?= htmlspecialchars($inc['estat'] ?? 'Sense estat') ?></span></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
