<?php
/**
 * professor/alumnes.php
 * Gestió completa (CRUD) dels alumnes del centre.
 * Permet llistar, crear, editar i eliminar alumnes.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

require_once '../includes/auth.php';
requireGestio();
require_once '../config/connexio.php';

$conn    = getConnexio();
$accio   = $_GET['accio']  ?? 'llistar';
$id      = (int)($_GET['id'] ?? 0);
$missatge = '';
$tipusMissatge = 'success';

// ══════════════════════════════════════════
//  PROCESSAR FORMULARIS (POST)
// ══════════════════════════════════════════

// Bloquejar accions no permeses per rol
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accioForm = $_POST['accio_form'] ?? '';
    if ($accioForm === 'nou')    denyIfCannot('crear');
    if ($accioForm === 'editar') denyIfCannot('editar');
}
if ($accio === 'eliminar') denyIfCannot('eliminar');
if ($accio === 'nou' && $_SERVER['REQUEST_METHOD'] !== 'POST') denyIfCannot('crear');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = trim($_POST['nom']      ?? '');
    $cognom1  = trim($_POST['cognom1']  ?? '');
    $cognom2  = trim($_POST['cognom2']  ?? '');
    $correu   = trim($_POST['correu']   ?? '');
    $grup     = trim($_POST['grupClasse'] ?? '');

    // Validació bàsica
    if (empty($nom) || empty($cognom1) || empty($correu) || empty($grup)) {
        $missatge = 'Omple tots els camps obligatoris.';
        $tipusMissatge = 'error';
        $accio = $_POST['accio_form'] ?? 'nou';
    } else {
        if ($_POST['accio_form'] === 'nou') {
            // ── CREATE ──
            $stmt = $conn->prepare("INSERT INTO Alumnes (nom, cognom1, cognom2, correu, grupClasse) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nom, $cognom1, $cognom2, $correu, $grup);
            if ($stmt->execute()) {
                $nouId = $conn->insert_id;
                // Crear usuari de login per a l'alumne (password = correu)
                $username = strtolower($nom . '.' . $cognom1);
                $hash     = password_hash($correu, PASSWORD_BCRYPT);
                $nomComplet = $nom . ' ' . $cognom1;
                $stmtU = $conn->prepare("INSERT IGNORE INTO Usuaris (username, password, rol, nom, idAlumne) VALUES (?, ?, 'alumne', ?, ?)");
                $stmtU->bind_param("sssi", $username, $hash, $nomComplet, $nouId);
                $stmtU->execute();
                $stmtU->close();
                $missatge = "Alumne creat correctament. Login: $username / $correu";
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt->close();
            $accio = 'llistar';

        } elseif ($_POST['accio_form'] === 'editar') {
            // ── UPDATE ──
            $idEdit = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE Alumnes SET nom=?, cognom1=?, cognom2=?, correu=?, grupClasse=? WHERE id=?");
            $stmt->bind_param("sssssi", $nom, $cognom1, $cognom2, $correu, $grup, $idEdit);
            if ($stmt->execute()) {
                $missatge = 'Alumne actualitzat correctament.';
            } else {
                $missatge = 'Error: ' . $conn->error;
                $tipusMissatge = 'error';
            }
            $stmt->close();
            $accio = 'llistar';
        }
    }
}

// ── DELETE ──
if ($accio === 'eliminar' && $id > 0) {
    // Comprovar si té assignacions o incidències actives
    $actives = $conn->query("SELECT COUNT(*) FROM Assignacions WHERE idAlumne=$id AND dataFinal IS NULL")->fetch_row()[0];
    if ($actives > 0) {
        $missatge = 'No es pot eliminar: té assignacions actives.';
        $tipusMissatge = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM Alumnes WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $missatge = 'Alumne eliminat correctament.';
    }
    $accio = 'llistar';
}

// ══════════════════════════════════════════
//  CARREGAR DADES PER ALS FORMULARIS
// ══════════════════════════════════════════

$alumneEdit = null;
if ($accio === 'editar' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Alumnes WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $alumneEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$alumneEdit) { $accio = 'llistar'; }
}

// ── Dispositius de l'alumne (per a editar) ──
$dispositiusAlumne = null;
if ($accio === 'editar' && $id > 0) {
    $dispositiusAlumne = $conn->query("
        SELECT a.id AS idAssig, m.id AS idMat, tm.tipus, tm.model,
               m.idInventari, a.dataInici, a.dataFinal,
               e.estat, i.id AS idInc
        FROM Assignacions a
        JOIN Material m       ON a.idMaterial = m.id
        JOIN TipusMaterial tm ON m.idTipus = tm.id
        LEFT JOIN Incidencies i ON i.idDispositiu = m.id AND i.dataTancada IS NULL
        LEFT JOIN Estats e      ON i.idEstat = e.id
        WHERE a.idAlumne = $id
        ORDER BY a.dataFinal IS NULL DESC, a.dataInici DESC
    ");
}

// ── Llistat principal ──
$cerca   = trim($_GET['cerca'] ?? '');
$filtreGrup = trim($_GET['grup'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types  = '';

if ($cerca) {
    $cercaQ = "%$cerca%";
    $where .= " AND (a.nom LIKE ? OR a.cognom1 LIKE ? OR a.cognom2 LIKE ? OR a.correu LIKE ?)";
    array_push($params, $cercaQ, $cercaQ, $cercaQ, $cercaQ);
    $types .= 'ssss';
}
if ($filtreGrup) {
    $where .= " AND a.grupClasse = ?";
    $params[] = $filtreGrup;
    $types   .= 's';
}

$query = "
    SELECT a.*,
        COUNT(DISTINCT CASE WHEN ass.dataFinal IS NULL THEN ass.id END) AS dispositius_actius,
        COUNT(DISTINCT CASE WHEN i.dataTancada IS NULL THEN i.id END) AS incidencies_obertes
    FROM Alumnes a
    LEFT JOIN Assignacions ass ON ass.idAlumne = a.id
    LEFT JOIN Incidencies  i   ON i.idAlumne   = a.id
    $where
    GROUP BY a.id
    ORDER BY a.grupClasse, a.cognom1, a.nom
";

$stmt = $conn->prepare($query);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$alumnes = $stmt->get_result();
$stmt->close();

// Grups disponibles per al filtre
$grups = $conn->query("SELECT DISTINCT grupClasse FROM Alumnes ORDER BY grupClasse");

$conn->close();

$pageTitle = 'Gestió Alumnes · Professor';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>👤 Gestió d'Alumnes</h2>
        <p>Crear, editar i eliminar alumnes del sistema</p>
    </div>
    <?php if (potCrear()): ?><a href="alumnes.php?accio=nou" class="btn btn-success">➕ Nou alumne</a><?php endif; ?>
</div>

<?php if ($missatge): ?>
    <div class="alert alert-<?= $tipusMissatge ?>"><?= htmlspecialchars($missatge) ?></div>
<?php endif; ?>

<?php if ($accio === 'nou' || $accio === 'editar'): ?>
<!-- ══════════════ FORMULARI ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $accio === 'nou' ? '➕ Nou alumne' : '✏️ Editar alumne' ?></span>
        <a href="alumnes.php" class="btn btn-sm btn-secondary">← Tornar</a>
    </div>
    <form method="POST" action="alumnes.php">
        <input type="hidden" name="accio_form" value="<?= $accio ?>">
        <?php if ($accio === 'editar'): ?>
            <input type="hidden" name="id" value="<?= $alumneEdit['id'] ?>">
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="form-group">
                <label>Nom <span style="color:var(--danger)">*</span></label>
                <input type="text" name="nom" value="<?= htmlspecialchars($alumneEdit['nom'] ?? $_POST['nom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Primer cognom <span style="color:var(--danger)">*</span></label>
                <input type="text" name="cognom1" value="<?= htmlspecialchars($alumneEdit['cognom1'] ?? $_POST['cognom1'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Segon cognom</label>
                <input type="text" name="cognom2" value="<?= htmlspecialchars($alumneEdit['cognom2'] ?? $_POST['cognom2'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Correu electrònic <span style="color:var(--danger)">*</span></label>
                <input type="email" name="correu" value="<?= htmlspecialchars($alumneEdit['correu'] ?? $_POST['correu'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Grup classe <span style="color:var(--danger)">*</span></label>
                <input type="text" name="grupClasse" maxlength="10" value="<?= htmlspecialchars($alumneEdit['grupClasse'] ?? $_POST['grupClasse'] ?? '') ?>" placeholder="Ex: ASIX1A" required>
            </div>
        </div>

        <div style="display:flex; gap:1rem; margin-top:0.5rem;">
            <button type="submit" class="btn btn-primary">
                <?= $accio === 'nou' ? '➕ Crear alumne' : '💾 Guardar canvis' ?>
            </button>
            <a href="alumnes.php" class="btn btn-secondary">Cancel·lar</a>
        </div>
    </form>
</div>

<?php if ($accio === 'editar' && $dispositiusAlumne && $dispositiusAlumne->num_rows > 0): ?>
<!-- Dispositius de l'alumne -->
<div class="card">
    <div class="card-header">
        <span class="card-title">💻 Dispositius assignats</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Tipus</th><th>Model</th><th>Inventari</th><th>Inici</th><th>Fi</th><th>Incidència</th></tr>
            </thead>
            <tbody>
            <?php while ($dev = $dispositiusAlumne->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($dev['tipus']) ?></td>
                    <td><?= htmlspecialchars($dev['model']) ?></td>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($dev['idInventari'] ?? '—') ?></span></td>
                    <td><?= $dev['dataInici'] ?></td>
                    <td><?= $dev['dataFinal'] ?? '<span class="badge badge-ok">Actiu</span>' ?></td>
                    <td>
                        <?php if ($dev['idInc']): ?>
                            <span class="badge badge-danger">⚠️ <?= htmlspecialchars($dev['estat'] ?? 'Oberta') ?></span>
                        <?php else: ?>
                            <span class="badge badge-ok">Cap</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════════════ LLISTAT ══════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Llistat d'alumnes</span>
    </div>
    <!-- Cerca i filtre -->
    <form method="GET" action="alumnes.php" style="display:flex; gap:1rem; margin-bottom:1.2rem; flex-wrap:wrap;">
        <input type="text" name="cerca" value="<?= htmlspecialchars($cerca) ?>" placeholder="🔍 Cerca per nom o correu..." style="flex:1; min-width:200px; padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif;">
        <select name="grup" style="padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'DM Sans',sans-serif;">
            <option value="">Tots els grups</option>
            <?php while ($g = $grups->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($g['grupClasse']) ?>" <?= $filtreGrup === $g['grupClasse'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['grupClasse']) ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="alumnes.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nom complet</th>
                    <th>Correu</th>
                    <th>Grup</th>
                    <th>Dispositius</th>
                    <th>Incidències</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($alumnes->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">Cap alumne trobat.</td></tr>
            <?php else: ?>
            <?php while ($a = $alumnes->fetch_assoc()): ?>
                <tr>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= $a['id'] ?></span></td>
                    <td><strong><?= htmlspecialchars($a['cognom1'] . ' ' . ($a['cognom2'] ?? '') . ', ' . $a['nom']) ?></strong></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($a['correu']) ?></td>
                    <td><span class="badge badge-ok"><?= htmlspecialchars($a['grupClasse']) ?></span></td>
                    <td><?= $a['dispositius_actius'] > 0 ? '<span class="badge badge-ok">'.$a['dispositius_actius'].'</span>' : '—' ?></td>
                    <td><?= $a['incidencies_obertes'] > 0 ? '<span class="badge badge-danger">'.$a['incidencies_obertes'].'</span>' : '—' ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <?php if (potEditar()): ?>
                            <a href="alumnes.php?accio=editar&id=<?= $a['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                            <?php endif; ?>
                            <?php if (potEliminar()): ?>
                            <a href="alumnes.php?accio=eliminar&id=<?= $a['id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Segur que vols eliminar aquest alumne?')">🗑️</a>
                            <?php endif; ?>
                            <?php if (!potEditar() && !potEliminar()): ?>
                            <span style="font-size:12px;color:var(--text-muted)">Només lectura</span>
                            <?php endif; ?>
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
