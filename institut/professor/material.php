<?php
/**
 * professor/material.php
 * Gestió completa (CRUD) del material informàtic del centre.
 * Permet llistar, crear, editar i eliminar material.
 * Inclou vista per aula i per tipus.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

require_once '../includes/auth.php';
requireGestio();
require_once '../config/connexio.php';

$conn   = getConnexio();
$accio  = $_GET['accio'] ?? 'llistar';
$id     = (int)($_GET['id'] ?? 0);
$missatge = '';
$tipusMissatge = 'success';

// Bloquejar accions no permeses per rol
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accioForm = $_POST['accio_form'] ?? '';
    if ($accioForm === 'nou')    denyIfCannot('crear');
    if ($accioForm === 'editar') denyIfCannot('editar');
}
if ($accio === 'eliminar') denyIfCannot('eliminar');
if ($accio === 'nou' && $_SERVER['REQUEST_METHOD'] !== 'POST') denyIfCannot('crear');

// ══════════════════════════════════════════
//  PROCESSAR POST
// ══════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idTipus    = (int)$_POST['idTipus'];
    $idUbicacio = (int)$_POST['idUbicacio'];
    $idInventari     = trim($_POST['idInventari']     ?? '') ?: null;
    $etiquetaDepInf  = trim($_POST['etiquetaDepInf']  ?? '') ?: null;
    $numSerie        = trim($_POST['numSerie']         ?? '') ?: null;
    $macEthernet     = trim($_POST['macEthernet']      ?? '') ?: null;
    $macWifi         = trim($_POST['macWifi']           ?? '') ?: null;
    $SACE            = trim($_POST['SACE']              ?? '') ?: null;
    $dataAdquisicio  = trim($_POST['dataAdquisicio']   ?? '') ?: null;

    if (!$idTipus || !$idUbicacio) {
        $missatge = 'El tipus i la ubicació són obligatoris.';
        $tipusMissatge = 'error';
        $accio = $_POST['accio_form'];
    } else {
        if ($_POST['accio_form'] === 'nou') {
            // ── CREATE ──
            $stmt = $conn->prepare("INSERT INTO Material
                (idTipus, idInventari, etiquetaDepInf, numSerie, macEthernet, macWifi, SACE, dataAdquisicio, idUbicacio)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss i", $idTipus, $idInventari, $etiquetaDepInf, $numSerie,
                $macEthernet, $macWifi, $SACE, $dataAdquisicio, $idUbicacio);
            if ($stmt->execute()) {
                $missatge = 'Material creat correctament.';
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt->close();

        } elseif ($_POST['accio_form'] === 'editar') {
            $idEdit = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE Material SET
                idTipus=?, idInventari=?, etiquetaDepInf=?, numSerie=?,
                macEthernet=?, macWifi=?, SACE=?, dataAdquisicio=?, idUbicacio=?
                WHERE id=?");
            $stmt->bind_param("isssssssi i", $idTipus, $idInventari, $etiquetaDepInf, $numSerie,
                $macEthernet, $macWifi, $SACE, $dataAdquisicio, $idUbicacio, $idEdit);
            if ($stmt->execute()) {
                $missatge = 'Material actualitzat correctament.';
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt->close();
        }
        $accio = 'llistar';
    }
}

// ── DELETE ──
if ($accio === 'eliminar' && $id > 0) {
    $actives = $conn->query("SELECT COUNT(*) FROM Assignacions WHERE idMaterial=$id AND dataFinal IS NULL")->fetch_row()[0];
    if ($actives > 0) {
        $missatge = 'No es pot eliminar: dispositiu amb assignació activa.';
        $tipusMissatge = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM Material WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $missatge = 'Material eliminat correctament.';
    }
    $accio = 'llistar';
}

// ══════════════════════════════════════════
//  CARREGAR DADES
// ══════════════════════════════════════════

$tipus     = $conn->query("SELECT * FROM TipusMaterial ORDER BY tipus");
$ubicacions = $conn->query("SELECT * FROM Ubicacions ORDER BY nom");

$materialEdit = null;
if ($accio === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Material WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $materialEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$materialEdit) { $accio = 'llistar'; }
}

// Filtres llistat
$filtreTipus = (int)($_GET['idTipus'] ?? 0);
$filtreUbic  = (int)($_GET['idUbicacio'] ?? 0);
$filtreAssig = $_GET['assignat'] ?? '';

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($filtreTipus) {
    $where .= " AND m.idTipus = ?"; $params[] = $filtreTipus; $types .= 'i';
}
if ($filtreUbic) {
    $where .= " AND m.idUbicacio = ?"; $params[] = $filtreUbic; $types .= 'i';
}
if ($filtreAssig === '1') {
    $where .= " AND activa.idMaterial IS NOT NULL";
} elseif ($filtreAssig === '0') {
    $where .= " AND activa.idMaterial IS NULL";
}

$stmt = $conn->prepare("
    SELECT m.*, tm.tipus, tm.model, tm.origen,
           u.nom AS aula,
           CONCAT(al.nom, ' ', al.cognom1) AS alumne_assignat,
           activa.dataInici AS data_assignacio,
           COUNT(DISTINCT CASE WHEN i.dataTancada IS NULL THEN i.id END) AS incidencies
    FROM Material m
    JOIN TipusMaterial tm ON m.idTipus = tm.id
    LEFT JOIN Ubicacions u ON m.idUbicacio = u.id
    LEFT JOIN Assignacions activa ON activa.idMaterial = m.id AND activa.dataFinal IS NULL
    LEFT JOIN Alumnes al ON activa.idAlumne = al.id
    LEFT JOIN Incidencies i ON i.idDispositiu = m.id
    $where
    GROUP BY m.id
    ORDER BY tm.tipus, tm.model, u.nom
");
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$materials = $stmt->get_result();
$stmt->close();

// Resum per aula i tipus
$resums = $conn->query("
    SELECT u.nom AS aula, tm.tipus, COUNT(*) AS total
    FROM Material m
    JOIN TipusMaterial tm ON m.idTipus = tm.id
    LEFT JOIN Ubicacions u ON m.idUbicacio = u.id
    GROUP BY u.nom, tm.tipus
    ORDER BY u.nom, tm.tipus
");

$conn->close();

$pageTitle = 'Gestió Material · Professor';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>💻 Gestió de Material</h2>
        <p>Inventari de dispositius del centre</p>
    </div>
    <a href="material.php?accio=nou" class="btn btn-success">➕ Nou material</a>
</div>

<?php if ($missatge): ?>
    <div class="alert alert-<?= $tipusMissatge ?>"><?= htmlspecialchars($missatge) ?></div>
<?php endif; ?>

<?php if ($accio === 'nou' || $accio === 'editar'): ?>
<!-- ══════════════ FORMULARI ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $accio === 'nou' ? '➕ Nou material' : '✏️ Editar material' ?></span>
        <a href="material.php" class="btn btn-sm btn-secondary">← Tornar</a>
    </div>
    <form method="POST" action="material.php">
        <input type="hidden" name="accio_form" value="<?= $accio ?>">
        <?php if ($accio === 'editar'): ?>
            <input type="hidden" name="id" value="<?= $materialEdit['id'] ?>">
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label>Tipus de material <span style="color:var(--danger)">*</span></label>
                <select name="idTipus" required>
                    <option value="">-- Selecciona --</option>
                    <?php while ($t = $tipus->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" <?= ($materialEdit['idTipus'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['tipus'] . ' - ' . $t['model']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ubicació (aula) <span style="color:var(--danger)">*</span></label>
                <select name="idUbicacio" required>
                    <option value="">-- Selecciona --</option>
                    <?php while ($u = $ubicacions->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>" <?= ($materialEdit['idUbicacio'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nom']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Núm. inventari</label>
                <input type="text" name="idInventari" maxlength="10" value="<?= htmlspecialchars($materialEdit['idInventari'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Etiqueta Dep. Inf.</label>
                <input type="text" name="etiquetaDepInf" value="<?= htmlspecialchars($materialEdit['etiquetaDepInf'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Número de sèrie</label>
                <input type="text" name="numSerie" value="<?= htmlspecialchars($materialEdit['numSerie'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>MAC Ethernet</label>
                <input type="text" name="macEthernet" placeholder="AA:BB:CC:DD:EE:FF" value="<?= htmlspecialchars($materialEdit['macEthernet'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>MAC WiFi</label>
                <input type="text" name="macWifi" placeholder="AA:BB:CC:DD:EE:FF" value="<?= htmlspecialchars($materialEdit['macWifi'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>SACE</label>
                <input type="text" name="SACE" value="<?= htmlspecialchars($materialEdit['SACE'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Data adquisició</label>
                <input type="date" name="dataAdquisicio" value="<?= htmlspecialchars($materialEdit['dataAdquisicio'] ?? '') ?>">
            </div>
        </div>

        <div style="display:flex; gap:1rem; margin-top:0.5rem;">
            <button type="submit" class="btn btn-primary">
                <?= $accio === 'nou' ? '➕ Crear material' : '💾 Guardar canvis' ?>
            </button>
            <a href="material.php" class="btn btn-secondary">Cancel·lar</a>
        </div>
    </form>
</div>

<?php else: ?>

<!-- ══════════════ RESUM PER AULA ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📊 Dispositius per aula i tipus</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Aula</th><th>Tipus</th><th>Total</th></tr></thead>
            <tbody>
            <?php while ($r = $resums->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['aula'] ?? 'Sense aula') ?></td>
                    <td><?= htmlspecialchars($r['tipus']) ?></td>
                    <td><span style="font-family:'DM Mono',monospace;font-weight:700;"><?= $r['total'] ?></span></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════ LLISTAT ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Inventari complet</span>
    </div>
    <!-- Filtres -->
    <form method="GET" action="material.php" style="display:flex; gap:1rem; margin-bottom:1.2rem; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:4px;">TIPUS</label>
            <select name="idTipus" style="padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif;">
                <option value="">Tots els tipus</option>
                <?php
                $tipus->data_seek(0);
                while ($t = $tipus->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>" <?= $filtreTipus == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['tipus']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:4px;">AULA</label>
            <select name="idUbicacio" style="padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif;">
                <option value="">Totes les aules</option>
                <?php
                $ubicacions->data_seek(0);
                while ($u = $ubicacions->fetch_assoc()): ?>
                    <option value="<?= $u['id'] ?>" <?= $filtreUbic == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nom']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:4px;">ESTAT</label>
            <select name="assignat" style="padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif;">
                <option value="">Tots</option>
                <option value="1" <?= $filtreAssig === '1' ? 'selected' : '' ?>>Assignats</option>
                <option value="0" <?= $filtreAssig === '0' ? 'selected' : '' ?>>Lliures</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="material.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Tipus</th><th>Model</th><th>Inventari</th>
                    <th>Aula</th><th>Assignat a</th><th>Incidències</th><th>Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($materials->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">Cap material trobat.</td></tr>
            <?php else: ?>
            <?php while ($m = $materials->fetch_assoc()): ?>
                <tr>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= $m['id'] ?></span></td>
                    <td><?= htmlspecialchars($m['tipus']) ?></td>
                    <td><?= htmlspecialchars($m['model']) ?></td>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($m['idInventari'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($m['aula'] ?? '—') ?></td>
                    <td>
                        <?php if ($m['alumne_assignat']): ?>
                            <span class="badge badge-warning">👤 <?= htmlspecialchars($m['alumne_assignat']) ?></span>
                        <?php else: ?>
                            <span class="badge badge-ok">Lliure</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $m['incidencies'] > 0 ? '<span class="badge badge-danger">'.$m['incidencies'].'</span>' : '—' ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="material.php?accio=editar&id=<?= $m['id'] ?>" class="btn btn-sm btn-warning">✏️</a>
                            <a href="material.php?accio=eliminar&id=<?= $m['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Segur que vols eliminar aquest material?')">🗑️</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
