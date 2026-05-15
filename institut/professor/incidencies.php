<?php
/**
 * professor/incidencies.php
 * Gestió completa (CRUD) de les incidències del centre.
 * Permet crear, veure, editar i tancar incidències.
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

// ══════════════════════════════════════════
// Bloquejar accions no permeses per rol
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accioForm = $_POST['accio_form'] ?? '';
    if ($accioForm === 'nou')    denyIfCannot('crear');
    if ($accioForm === 'editar') denyIfCannot('editar');
}
if ($accio === 'eliminar') denyIfCannot('eliminar');
if ($accio === 'tancar')   denyIfCannot('editar');
if ($accio === 'nou' && $_SERVER['REQUEST_METHOD'] !== 'POST') denyIfCannot('crear');

//  PROCESSAR POST
// ══════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $informacio   = trim($_POST['informacio']   ?? '');
    $idAlumne     = (int)($_POST['idAlumne']    ?? 0) ?: null;
    $idDispositiu = (int)($_POST['idDispositiu'] ?? 0) ?: null;
    $idEstat      = (int)($_POST['idEstat']     ?? 0) ?: null;
    $dataOberta   = trim($_POST['dataOberta']   ?? '') ?: date('Y-m-d');
    $dataTancada  = trim($_POST['dataTancada']  ?? '') ?: null;

    if (empty($informacio)) {
        $missatge = 'La descripció de la incidència és obligatòria.';
        $tipusMissatge = 'error';
        $accio = $_POST['accio_form'];
    } else {
        if ($_POST['accio_form'] === 'nou') {
            // ── CREATE ──
            $stmt = $conn->prepare("INSERT INTO Incidencies
                (informacio, dataOberta, dataTancada, idAlumne, idDispositiu, idEstat)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiii", $informacio, $dataOberta, $dataTancada,
                $idAlumne, $idDispositiu, $idEstat);
            if ($stmt->execute()) {
                $missatge = 'Incidència creada correctament.';
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt->close();

        } elseif ($_POST['accio_form'] === 'editar') {
            // ── UPDATE ──
            $idEdit = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE Incidencies SET
                informacio=?, dataOberta=?, dataTancada=?,
                idAlumne=?, idDispositiu=?, idEstat=?
                WHERE id=?");
            $stmt->bind_param("sssiiit", $informacio, $dataOberta, $dataTancada,
                $idAlumne, $idDispositiu, $idEstat, $idEdit);
            // fix bind
            $stmt->close();
            $stmt2 = $conn->prepare("UPDATE Incidencies SET
                informacio=?, dataOberta=?, dataTancada=?,
                idAlumne=?, idDispositiu=?, idEstat=?
                WHERE id=?");
            $stmt2->bind_param("sssiii i", $informacio, $dataOberta, $dataTancada,
                $idAlumne, $idDispositiu, $idEstat, $idEdit);
            if ($stmt2->execute()) {
                $missatge = 'Incidència actualitzada correctament.';
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt2->close();
        }
        $accio = 'llistar';
    }
}

// ── DELETE ──
if ($accio === 'eliminar' && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM Incidencies WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $missatge = 'Incidència eliminada.';
    $accio = 'llistar';
}

// ── TANCAR ràpid ──
if ($accio === 'tancar' && $id > 0) {
    $avui = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE Incidencies SET dataTancada=? WHERE id=?");
    $stmt->bind_param("si", $avui, $id);
    $stmt->execute();
    $stmt->close();
    $missatge = 'Incidència tancada.';
    $accio = 'llistar';
}

// ══════════════════════════════════════════
//  CARREGAR DADES
// ══════════════════════════════════════════

$estats    = $conn->query("SELECT * FROM Estats ORDER BY estat");
$alumnes   = $conn->query("SELECT id, nom, cognom1 FROM Alumnes ORDER BY cognom1, nom");
$materials = $conn->query("
    SELECT m.id, tm.tipus, tm.model, m.idInventari
    FROM Material m
    JOIN TipusMaterial tm ON m.idTipus = tm.id
    ORDER BY tm.tipus, tm.model
");

$incEdit = null;
if ($accio === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Incidencies WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $incEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$incEdit) { $accio = 'llistar'; }
}

// Filtres
$filtreEstat = $_GET['estat'] ?? '';  // 'oberta' | 'tancada' | ''

$where = "WHERE 1=1";
if ($filtreEstat === 'oberta')  { $where .= " AND i.dataTancada IS NULL"; }
if ($filtreEstat === 'tancada') { $where .= " AND i.dataTancada IS NOT NULL"; }

$incidencies = $conn->query("
    SELECT i.*,
           CONCAT(al.nom, ' ', al.cognom1) AS alumne,
           CONCAT(tm.tipus, ' ', tm.model) AS dispositiu,
           m.idInventari,
           e.estat
    FROM Incidencies i
    LEFT JOIN Alumnes al        ON i.idAlumne    = al.id
    LEFT JOIN Material m        ON i.idDispositiu = m.id
    LEFT JOIN TipusMaterial tm  ON m.idTipus      = tm.id
    LEFT JOIN Estats e          ON i.idEstat      = e.id
    $where
    ORDER BY i.dataTancada IS NULL DESC, i.dataOberta DESC
");

$conn->close();

$pageTitle = 'Gestió Incidències · Professor';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>⚠️ Gestió d'Incidències</h2>
        <p>Control de totes les incidències del material</p>
    </div>
    <a href="incidencies.php?accio=nou" class="btn btn-success">➕ Nova incidència</a>
</div>

<?php if ($missatge): ?>
    <div class="alert alert-<?= $tipusMissatge ?>"><?= htmlspecialchars($missatge) ?></div>
<?php endif; ?>

<?php if ($accio === 'nou' || $accio === 'editar'): ?>
<!-- ══════════════ FORMULARI ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $accio === 'nou' ? '➕ Nova incidència' : '✏️ Editar incidència' ?></span>
        <a href="incidencies.php" class="btn btn-sm btn-secondary">← Tornar</a>
    </div>
    <form method="POST" action="incidencies.php">
        <input type="hidden" name="accio_form" value="<?= $accio ?>">
        <?php if ($accio === 'editar'): ?>
            <input type="hidden" name="id" value="<?= $incEdit['id'] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Descripció de la incidència <span style="color:var(--danger)">*</span></label>
            <textarea name="informacio" rows="4" style="resize:vertical;" required><?= htmlspecialchars($incEdit['informacio'] ?? '') ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label>Alumne afectat</label>
                <select name="idAlumne">
                    <option value="">-- Cap / Sense alumne --</option>
                    <?php while ($a = $alumnes->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>" <?= ($incEdit['idAlumne'] ?? 0) == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['cognom1'] . ', ' . $a['nom']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Dispositiu afectat</label>
                <select name="idDispositiu">
                    <option value="">-- Cap / Sense dispositiu --</option>
                    <?php while ($m = $materials->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>" <?= ($incEdit['idDispositiu'] ?? 0) == $m['id'] ? 'selected' : '' ?>>
                            #<?= $m['id'] ?> · <?= htmlspecialchars($m['tipus'] . ' ' . $m['model']) ?>
                            <?= $m['idInventari'] ? '(' . $m['idInventari'] . ')' : '' ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Estat</label>
                <select name="idEstat">
                    <option value="">-- Sense estat --</option>
                    <?php while ($e = $estats->fetch_assoc()): ?>
                        <option value="<?= $e['id'] ?>" <?= ($incEdit['idEstat'] ?? 0) == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['estat']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Data obertura</label>
                <input type="date" name="dataOberta" value="<?= htmlspecialchars($incEdit['dataOberta'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>Data tancament <span style="font-size:11px;color:var(--text-muted)">(deixa buit si està oberta)</span></label>
                <input type="date" name="dataTancada" value="<?= htmlspecialchars($incEdit['dataTancada'] ?? '') ?>">
            </div>
        </div>

        <div style="display:flex; gap:1rem; margin-top:0.5rem;">
            <button type="submit" class="btn btn-primary">
                <?= $accio === 'nou' ? '➕ Crear incidència' : '💾 Guardar canvis' ?>
            </button>
            <a href="incidencies.php" class="btn btn-secondary">Cancel·lar</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ══════════════ LLISTAT ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Totes les incidències</span>
    </div>
    <!-- Filtre ràpid -->
    <div style="display:flex; gap:8px; margin-bottom:1.2rem;">
        <a href="incidencies.php" class="btn btn-sm <?= $filtreEstat === '' ? 'btn-primary' : 'btn-secondary' ?>">Totes</a>
        <a href="incidencies.php?estat=oberta" class="btn btn-sm <?= $filtreEstat === 'oberta' ? 'btn-primary' : 'btn-secondary' ?>">⚠️ Obertes</a>
        <a href="incidencies.php?estat=tancada" class="btn btn-sm <?= $filtreEstat === 'tancada' ? 'btn-primary' : 'btn-secondary' ?>">✅ Tancades</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descripció</th>
                    <th>Alumne</th>
                    <th>Dispositiu</th>
                    <th>Oberta</th>
                    <th>Tancada</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($incidencies->num_rows === 0): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">Cap incidència trobada.</td></tr>
            <?php else: ?>
            <?php while ($inc = $incidencies->fetch_assoc()): ?>
                <tr>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;">#<?= $inc['id'] ?></span></td>
                    <td style="max-width:220px; font-size:13px;"><?= htmlspecialchars(substr($inc['informacio'], 0, 70)) ?>...</td>
                    <td style="font-size:13px;"><?= htmlspecialchars($inc['alumne'] ?? '—') ?></td>
                    <td style="font-size:12px; font-family:'DM Mono',monospace;"><?= htmlspecialchars($inc['dispositiu'] ?? '—') ?></td>
                    <td><?= $inc['dataOberta'] ?? '—' ?></td>
                    <td><?= $inc['dataTancada'] ?? '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <td>
                        <span class="badge <?= $inc['dataTancada'] ? 'badge-ok' : 'badge-danger' ?>">
                            <?= $inc['dataTancada'] ? '✅ Tancada' : ('⚠️ ' . htmlspecialchars($inc['estat'] ?? 'Oberta')) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <a href="incidencies.php?accio=editar&id=<?= $inc['id'] ?>" class="btn btn-sm btn-warning">✏️</a>
                            <?php if (!$inc['dataTancada']): ?>
                            <a href="incidencies.php?accio=tancar&id=<?= $inc['id'] ?>"
                               class="btn btn-sm btn-success"
                               onclick="return confirm('Tancar aquesta incidència?')">✅</a>
                            <?php endif; ?>
                            <a href="incidencies.php?accio=eliminar&id=<?= $inc['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Eliminar aquesta incidència?')">🗑️</a>
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
