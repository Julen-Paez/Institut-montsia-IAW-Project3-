<?php
/**
 * alumne/dashboard.php
 * Panell principal de l'alumnat.
 * Mostra els dispositius assignats i les seves incidències.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

require_once '../includes/auth.php';
requireRol('alumne');

require_once '../config/connexio.php';
$conn = getConnexio();

$idAlumne = $_SESSION['idAlumne'];

// Dades de l'alumne
$stmtAlumne = $conn->prepare("SELECT * FROM Alumnes WHERE id = ?");
$stmtAlumne->bind_param("i", $idAlumne);
$stmtAlumne->execute();
$alumne = $stmtAlumne->get_result()->fetch_assoc();
$stmtAlumne->close();

// Dispositius assignats (actius)
$stmtDev = $conn->prepare("
    SELECT m.id, tm.tipus, tm.model, m.numSerie, m.idInventari,
           u.nom AS aula, a.dataInici,
           (SELECT COUNT(*) FROM Incidencies i
            WHERE i.idDispositiu = m.id AND i.dataTancada IS NULL) AS incidencies
    FROM Assignacions a
    JOIN Material m       ON a.idMaterial = m.id
    JOIN TipusMaterial tm ON m.idTipus = tm.id
    LEFT JOIN Ubicacions u ON m.idUbicacio = u.id
    WHERE a.idAlumne = ? AND a.dataFinal IS NULL
");
$stmtDev->bind_param("i", $idAlumne);
$stmtDev->execute();
$dispositius = $stmtDev->get_result();
$stmtDev->close();

// Incidències de l'alumne
$stmtInc = $conn->prepare("
    SELECT i.*, e.estat, tm.tipus, tm.model
    FROM Incidencies i
    LEFT JOIN Estats e   ON i.idEstat = e.id
    LEFT JOIN Material m ON i.idDispositiu = m.id
    LEFT JOIN TipusMaterial tm ON m.idTipus = tm.id
    WHERE i.idAlumne = ?
    ORDER BY i.dataOberta DESC
");
$stmtInc->bind_param("i", $idAlumne);
$stmtInc->execute();
$incidencies = $stmtInc->get_result();
$stmtInc->close();

$conn->close();

$pageTitle = 'El meu panell · ' . ($alumne['nom'] ?? '');
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Hola, <?= htmlspecialchars($alumne['nom'] . ' ' . $alumne['cognom1']) ?></h2>
        <p>Grup: <strong><?= htmlspecialchars($alumne['grupClasse']) ?></strong> · <?= date('d/m/Y') ?></p>
    </div>
</div>

<!-- Dispositius assignats -->
<div class="card">
    <div class="card-header">
        <span class="card-title">💻 Els meus dispositius</span>
    </div>
    <?php if ($dispositius->num_rows === 0): ?>
        <p style="color:var(--text-muted); font-size:14px;">No tens cap dispositiu assignat.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Tipus</th>
                    <th>Model</th>
                    <th>Núm. inventari</th>
                    <th>Núm. sèrie</th>
                    <th>Aula</th>
                    <th>Des de</th>
                    <th>Incidències</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($dev = $dispositius->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($dev['tipus']) ?></td>
                    <td><?= htmlspecialchars($dev['model']) ?></td>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($dev['idInventari'] ?? '—') ?></span></td>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($dev['numSerie'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($dev['aula'] ?? '—') ?></td>
                    <td><?= $dev['dataInici'] ?></td>
                    <td>
                        <?php if ($dev['incidencies'] > 0): ?>
                            <span class="badge badge-danger"><?= $dev['incidencies'] ?> oberta/es</span>
                        <?php else: ?>
                            <span class="badge badge-ok">Cap</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Incidències -->
<div class="card">
    <div class="card-header">
        <span class="card-title">⚠️ Les meves incidències</span>
    </div>
    <?php if ($incidencies->num_rows === 0): ?>
        <p style="color:var(--text-muted); font-size:14px;">Cap incidència registrada. ✅</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Dispositiu</th>
                    <th>Descripció</th>
                    <th>Oberta</th>
                    <th>Tancada</th>
                    <th>Estat</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($inc = $incidencies->fetch_assoc()): ?>
                <tr>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;">#<?= $inc['id'] ?></span></td>
                    <td><?= htmlspecialchars(($inc['tipus'] ?? '') . ' ' . ($inc['model'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars(substr($inc['informacio'], 0, 80)) ?>...</td>
                    <td><?= $inc['dataOberta'] ?></td>
                    <td><?= $inc['dataTancada'] ?? '<span style="color:var(--text-muted)">Oberta</span>' ?></td>
                    <td>
                        <span class="badge <?= $inc['dataTancada'] ? 'badge-ok' : 'badge-warning' ?>">
                            <?= htmlspecialchars($inc['estat'] ?? 'Pendent') ?>
                        </span>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
