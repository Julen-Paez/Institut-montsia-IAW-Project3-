<?php
/**
 * login.php
 * Pàgina d'inici de sessió de l'aplicació.
 * Gestiona l'autenticació d'usuaris amb verificació de contrasenya encriptada.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

session_start();

// Si ja hi ha sessió activa, redirigeix al dashboard corresponent
if (isset($_SESSION['usuari'])) {
    header('Location: ' . ($_SESSION['rol'] === 'professor' ? 'professor/dashboard.php' : 'alumne/dashboard.php'));
    exit;
}

require_once 'config/connexio.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Omple tots els camps.';
    } else {
        $conn = getConnexio();
        $stmt = $conn->prepare("SELECT id, username, password, rol, nom, idAlumne FROM Usuaris WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuari = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($usuari && password_verify($password, $usuari['password'])) {
            // Sessió vàlida: guardem dades mínimes
            $_SESSION['usuari']   = $usuari['id'];
            $_SESSION['nom']      = $usuari['nom'];
            $_SESSION['rol']      = $usuari['rol'];
            $_SESSION['idAlumne'] = $usuari['idAlumne'];

            session_regenerate_id(true); // Prevenir session fixation

            if (in_array($usuari['rol'], ['admin', 'professor', 'editor'])) {
                header('Location: professor/dashboard.php');
            } else {
                header('Location: alumne/dashboard.php');
            }
            exit;
        } else {
            $error = 'Usuari o contrasenya incorrectes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accés · Institut Montsià</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">

        <div class="login-logo">
            <div class="logo-badge">IM</div>
            <h1>Institut Montsià</h1>
            <p>Gestió de Material Informàtic</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <div class="form-group">
                <label for="username">Usuari</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    placeholder="nom.cognom"
                    autocomplete="username"
                    required
                >
            </div>
            <div class="form-group">
                <label for="password">Contrasenya</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
                Iniciar sessió
            </button>
        </form>

        <p style="text-align:center; margin-top:1.5rem; font-size:12px; color:var(--text-muted);">
            CFGS ASIX · Curs 2025–2026
        </p>
    </div>
</div>
</body>
</html>
