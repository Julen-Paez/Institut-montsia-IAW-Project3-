<?php
/**
 * index.php
 * Punt d'entrada principal. Redirigeix al login o al dashboard.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

session_start();

if (isset($_SESSION['usuari'])) {
    header('Location: ' . ($_SESSION['rol'] === 'professor' ? 'professor/dashboard.php' : 'alumne/dashboard.php'));
} else {
    header('Location: login.php');
}
exit;
?>
