<?php
/**
 * logout.php
 * Tanca la sessió de l'usuari actual i redirigeix al login.
 *
 * @author Institut Montsià - ASIX
 * @version 1.0
 */

session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit;
?>
