<?php
/**
 * auth.php
 * Sistema de control d'accés amb 4 rols: admin, professor, editor, alumne.
 *
 * MATRIU DE PERMISOS:
 * ┌──────────┬────────┬───────────┬────────┬────────┐
 * │ Acció    │ Admin  │ Professor │ Editor │ Alumne │
 * ├──────────┼────────┼───────────┼────────┼────────┤
 * │ Crear    │  ✅   │    ✅     │   ❌   │   ❌  │
 * │ Llegir   │  ✅   │    ✅     │   ✅   │   ✅  │
 * │ Editar   │  ✅   │    ✅     │   ✅   │   ❌  │
 * │ Eliminar │  ✅   │    ✅     │   ❌   │   ❌  │
 * └──────────┴────────┴───────────┴────────┴────────┘
 *
 * @author Institut Montsià - ASIX
 * @version 2.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROLS_GESTIO',   ['admin', 'professor', 'editor']);
define('ROLS_CREAR',    ['admin', 'professor']);
define('ROLS_EDITAR',   ['admin', 'professor', 'editor']);
define('ROLS_ELIMINAR', ['admin', 'professor']);

/**
 * Verifica sessió activa. Si no, redirigeix al login.
 * @return void
 */
function requireLogin(): void {
    if (!isset($_SESSION['usuari'])) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
}

/**
 * Permet accés només als rols de gestió (admin, professor, editor).
 * Els alumnes es redirigeixen al seu dashboard.
 * @return void
 */
function requireGestio(): void {
    requireLogin();
    if (!in_array($_SESSION['rol'], ROLS_GESTIO)) {
        header('Location: ' . getBaseUrl() . 'alumne/dashboard.php');
        exit;
    }
}

/**
 * Verifica un rol específic exacte.
 * @param string $rol Rol requerit
 * @return void
 */
function requireRol(string $rol): void {
    requireLogin();
    if ($_SESSION['rol'] !== $rol) {
        accesNegat();
    }
}

/**
 * Comprova si l'usuari pot crear registres.
 * @return bool
 */
function potCrear(): bool {
    return in_array($_SESSION['rol'] ?? '', ROLS_CREAR);
}

/**
 * Comprova si l'usuari pot editar registres.
 * @return bool
 */
function potEditar(): bool {
    return in_array($_SESSION['rol'] ?? '', ROLS_EDITAR);
}

/**
 * Comprova si l'usuari pot eliminar registres.
 * @return bool
 */
function potEliminar(): bool {
    return in_array($_SESSION['rol'] ?? '', ROLS_ELIMINAR);
}

/**
 * Atura l'execució si l'usuari no té permís per a l'acció indicada.
 * @param string $accio 'crear' | 'editar' | 'eliminar'
 * @return void
 */
function denyIfCannot(string $accio): void {
    $allowed = match($accio) {
        'crear'    => potCrear(),
        'editar'   => potEditar(),
        'eliminar' => potEliminar(),
        default    => false,
    };
    if (!$allowed) {
        accesNegat("No tens permís per $accio registres.");
    }
}

/**
 * Mostra pàgina d'error 403 i atura l'execució.
 * @param string $missatge Missatge opcional
 * @return void
 */
function accesNegat(string $missatge = 'No tens permisos per accedir a aquesta pàgina.'): void {
    http_response_code(403);
    $base = getBaseUrl();
    die('<!DOCTYPE html><html lang="ca"><head><meta charset="UTF-8">
    <title>Accés denegat</title>
    <link rel="stylesheet" href="' . $base . 'assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    </head><body>
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f6f9;">
    <div style="background:white;padding:3rem;border-radius:12px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.1);max-width:420px;">
    <div style="font-size:56px;margin-bottom:1rem;">🔒</div>
    <h2 style="color:#c0392b;margin-bottom:0.5rem;font-family:DM Sans,sans-serif;">Accés denegat</h2>
    <p style="color:#6b7e95;margin-bottom:1.5rem;font-family:DM Sans,sans-serif;">' . htmlspecialchars($missatge) . '</p>
    <a href="' . $base . 'login.php" style="background:#1a3a5c;color:white;padding:10px 24px;border-radius:8px;text-decoration:none;font-family:DM Sans,sans-serif;font-size:14px;">Tornar al login</a>
    </div></div></body></html>');
}

/**
 * Retorna etiqueta llegible del rol de la sessió actual.
 * @return string
 */
function getNomRol(): string {
    return match($_SESSION['rol'] ?? '') {
        'admin'     => '⚙️ Admin',
        'professor' => '👨‍🏫 Professor',
        'editor'    => '✏️ Editor',
        'alumne'    => '🎓 Alumne',
        default     => 'Desconegut',
    };
}

/**
 * Calcula la URL base relativa segons la profunditat de l'arxiu actual.
 * @return string
 */
function getBaseUrl(): string {
    $depth = substr_count($_SERVER['PHP_SELF'], '/') - 2;
    return str_repeat('../', $depth);
}
?>
