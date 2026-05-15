<?php
/**
 * professor/assignacions.php
 * Gestió del lloguer i assignació de portàtils i material a l'alumnat.
 * Permet crear assignacions, modificar-les i retornar el material (eliminar).
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
if ($accio === 'retornar') denyIfCannot('editar');
if ($accio === 'nou' && $_SERVER['REQUEST_METHOD'] !== 'POST') denyIfCannot('crear');

//  PROCESSAR POST
// ══════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idMaterial = (int)($_POST['idMaterial'] ?? 0);
    $idAlumne   = (int)($_POST['idAlumne']   ?? 0);
    $dataInici  = trim($_POST['dataInici']   ?? '') ?: date('Y-m-d');
    $dataFinal  = trim($_POST['dataFinal']   ?? '') ?: null;

    if (!$idMaterial || !$idAlumne) {
        $missatge = 'Cal seleccionar un alumne i un dispositiu.';
        $tipusMissatge = 'error';
        $accio = $_POST['accio_form'];
    } else {
        if ($_POST['accio_form'] === 'nou') {
            // Comprovar que el material no està assignat
            $check = $conn->query("SELECT id FROM Assignacions WHERE idMaterial=$idMaterial AND dataFinal IS NULL");
            if ($check->num_rows > 0) {
                $missatge = 'Aquest dispositiu ja té una assignació activa.';
                $tipusMissatge = 'error';
                $accio = 'nou';
            } else {
                $stmt = $conn->prepare("INSERT INTO Assignacions (idMaterial, idAlumne, dataInici, dataFinal) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $idMaterial, $idAlumne, $dataInici, $dataFinal);
                if ($stmt->execute()) {
                    $missatge = 'Assignació creada correctament.';
                } else {
                    $missatge = 'Error: ' . $conn->error;
                    $tipusMissatge = 'error';
                }
                $stmt->close();
                $accio = 'llistar';
            }

        } elseif ($_POST['accio_form'] === 'editar') {
            $idEdit = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE Assignacions SET idMaterial=?, idAlumne=?, dataInici=?, dataFinal=? WHERE id=?");
            $stmt->bind_param("iissi", $idMaterial, $idAlumne, $dataInici, $dataFinal, $idEdit);
            if ($stmt->execute()) {
                $missatge = 'Assignació actualitzada correctament.';
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt->close();
            $accio = 'llistar';
        }
    }
}

// ── RETORNAR (tancar assignació posant dataFinal = avui) ──
if ($accio === 'retornar' && $id > 0) {
    $avui = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE Assignacions SET dataFinal=? WHERE id=?");
    $stmt->bind_param("si", $avui, $id);
    $stmt->execute();
    $stmt->close();
    $missatge = 'Material retornat correctament.';
    $accio = 'llistar';
}

// ── ELIMINAR ──
if ($accio === 'eliminar' && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM Assignacions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $missatge = 'Assignació eliminada.';
    $accio = 'llistar';
}

// ══════════════════════════════════════════
//  CARREGAR DADES
// ══════════════════════════════════════════

// Material lliure (sense assignació activa) per al formulari nou
$materialLliure = $conn->query("
    SELECT m.id, tm.tipus, tm.model, m.idInventari, u.nom AS aula
    FROM Material m
    JOIN TipusMaterial tm ON m.idTipus = tm.id
    LEFT JOIN Ubicacions u ON m.idUbicacio = u.id
    WHERE m.id NOT IN (
        SELECT idMaterial FROM Assignacions WHERE dataFinal IS NULL
    )
    ORDER BY tm.tipus, tm.model
");

// Tot el material (per editar)
$totMaterial = $conn->query("
    SELECT m.id, tm.tipus, tm.model, m.idInventari
    FROM Material m
    JOIN TipusMaterial tm ON m.idTipus = tm.id
    ORDER BY tm.tipus, tm.model
");

$alumnes = $conn->query("SELECT id, nom, cognom1, grupClasse FROM Alumnes ORDER BY grupClasse, cognom1");

$assignEdit = null;
if ($accio === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Assignacions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $assignEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$assignEdit) { $accio = 'llistar'; }
}

// Filtre llistat
$filtreActiu = $_GET['actiu'] ?? '';
$filtreAlumne = (int)($_GET['idAlumne'] ?? 0);

$where  = "WHERE 1=1";
if ($filtreActiu === '1') { $where .= " AND a.dataFinal IS NULL"; }
if ($filtreActiu === '0') { $where .= " AND a.dataFinal IS NOT NULL"; }
if ($filtreAlumne)        { $where .= " AND a.idAlumne = $filtreAlumne"; }

$assignacions = $conn->query("
    SELECT a.*,
           CONCAT(al.nom, ' ', al.cognom1) AS alumne,
           al.grupClasse,
           tm.tipus, tm.model, m.idInventari,
           u.nom AS aula
    FROM Assignacions a
    JOIN Alumnes al        ON a.idAlumne   = al.id
    JOIN Material m        ON a.idMaterial = m.id
    JOIN TipusMaterial tm  ON m.idTipus    = tm.id
    LEFT JOIN Ubicacions u ON m.idUbicacio = u.id
    $where
    ORDER BY a.dataFinal IS NULL DESC, a.dataInici DESC
");

// Stats ràpides
$totalActives  = $conn->query("SELECT COUNT(*) FROM Assignacions WHERE dataFinal IS NULL")->fetch_row()[0];
$totalTancades = $conn->query("SELECT COUNT(*) FROM Assignacions WHERE dataFinal IS NOT NULL")->fetch_row()[0];
$totalLliure   = $conn->query("
    SELECT COUNT(*) FROM Material WHERE id NOT IN
    (SELECT idMaterial FROM Assignacions WHERE dataFinal IS NULL)
")->fetch_row()[0];

$conn->close();

$pageTitle = 'Assignacions · Professor';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>📋 Assignacions i Lloguer</h2>
        <p>Gestió del préstec de portàtils i material a l'alumnat</p>
    </div>
    <a href="assignacions.php?accio=nou" class="btn btn-success">➕ Nova assignació</a>
</div>

<?php if ($missatge): ?>
    <div class="alert alert-<?= $tipusMissatge ?>"><?= htmlspecialchars($missatge) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:1.5rem;">
    <div class="stat-card success">
        <span class="stat-number"><?= $totalActives ?></span>
        <span class="stat-label">✅ Assignacions actives</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?= $totalTancades ?></span>
        <span class="stat-label">📦 Retornats</span>
    </div>
    <div class="stat-card accent">
        <span class="stat-number"><?= $totalLliure ?></span>
        <span class="stat-label">💻 Dispositius lliures</span>
    </div>
</div>

<?php if ($accio === 'nou' || $accio === 'editar'): ?>
<!-- ══════════════ FORMULARI ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $accio === 'nou' ? '➕ Nova assignació' : '✏️ Editar assignació' ?></span>
        <a href="assignacions.php" class="btn btn-sm btn-secondary">← Tornar</a>
    </div>
    <form method="POST" action="assignacions.php">
        <input type="hidden" name="accio_form" value="<?= $accio ?>">
        <?php if ($accio === 'editar'): ?>
            <input type="hidden" name="id" value="<?= $assignEdit['id'] ?>">
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label>Alumne <span style="color:var(--danger)">*</span></label>
                <select name="idAlumne" required>
                    <option value="">-- Selecciona alumne --</option>
                    <?php while ($a = $alumnes->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>"
                            <?= ($assignEdit['idAlumne'] ?? 0) == $a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['cognom1'] . ', ' . $a['nom'] . ' (' . $a['grupClasse'] . ')') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Dispositiu <span style="color:var(--danger)">*</span></label>
                <select name="idMaterial" required>
                    <option value="">-- Selecciona dispositiu --</option>
                    <?php
                    $srcMat = ($accio === 'nou') ? $materialLliure : $totMaterial;
                    while ($m = $srcMat->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"
                            <?= ($assignEdit['idMaterial'] ?? 0) == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['tipus'] . ' ' . $m['model']) ?>
                            <?= $m['idInventari'] ? '· ' . $m['idInventari'] : '' ?>
                            <?php if (isset($m['aula'])): ?>(<?= htmlspecialchars($m['aula']) ?>)<?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if ($accio === 'nou'): ?>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">Només es mostren els dispositius lliures</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Data d'inici <span style="color:var(--danger)">*</span></label>
                <input type="date" name="dataInici"
                    value="<?= htmlspecialchars($assignEdit['dataInici'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>Data de retorn <span style="font-size:11px;color:var(--text-muted)">(deixa buit si segueix activa)</span></label>
                <input type="date" name="dataFinal"
                    value="<?= htmlspecialchars($assignEdit['dataFinal'] ?? '') ?>">
            </div>
        </div>

        <div style="display:flex; gap:1rem; margin-top:0.5rem;">
            <button type="submit" class="btn btn-primary">
                <?= $accio === 'nou' ? '➕ Crear assignació' : '💾 Guardar canvis' ?>
            </button>
            <a href="assignacions.php" class="btn btn-secondary">Cancel·lar</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ══════════════ LLISTAT ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Totes les assignacions</span>
    </div>

    <!-- Filtres -->
    <div style="display:flex; gap:8px; margin-bottom:1.2rem; flex-wrap:wrap; align-items:center;">
        <a href="assignacions.php" class="btn btn-sm <?= $filtreActiu === '' ? 'btn-primary' : 'btn-secondary' ?>">Totes</a>
        <a href="assignacions.php?actiu=1" class="btn btn-sm <?= $filtreActiu === '1' ? 'btn-primary' : 'btn-secondary' ?>">✅ Actives</a>
        <a href="assignacions.php?actiu=0" class="btn btn-sm <?= $filtreActiu === '0' ? 'btn-primary' : 'btn-secondary' ?>">📦 Retornades</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Alumne</th>
                    <th>Grup</th>
                    <th>Dispositiu</th>
                    <th>Inventari</th>
                    <th>Aula</th>
                    <th>Data inici</th>
                    <th>Data retorn</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($assignacions->num_rows === 0): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:2rem;">Cap assignació trobada.</td></tr>
            <?php else: ?>
            <?php while ($ass = $assignacions->fetch_assoc()): ?>
                <tr>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= $ass['id'] ?></span></td>
                    <td><strong><?= htmlspecialchars($ass['alumne']) ?></strong></td>
                    <td><span class="badge badge-ok"><?= htmlspecialchars($ass['grupClasse']) ?></span></td>
                    <td><?= htmlspecialchars($ass['tipus'] . ' ' . $ass['model']) ?></td>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($ass['idInventari'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($ass['aula'] ?? '—') ?></td>
                    <td><?= $ass['dataInici'] ?></td>
                    <td><?= $ass['dataFinal'] ?? '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <td>
                        <?php if (!$ass['dataFinal']): ?>
                            <span class="badge badge-ok">✅ Activa</span>
                        <?php else: ?>
                            <span class="badge badge-warning">📦 Retornada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <a href="assignacions.php?accio=editar&id=<?= $ass['id'] ?>" class="btn btn-sm btn-warning">✏️</a>
                            <?php if (!$ass['dataFinal']): ?>
                            <a href="assignacions.php?accio=retornar&id=<?= $ass['id'] ?>"
                               class="btn btn-sm btn-success"
                               onclick="return confirm('Marcar com a retornat?')">📦</a>
                            <?php endif; ?>
                            <a href="assignacions.php?accio=eliminar&id=<?= $ass['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Eliminar aquesta assignació?')">🗑️</a>
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
