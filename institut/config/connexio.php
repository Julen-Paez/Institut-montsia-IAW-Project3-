<?php
/**
 * connexio.php
 * Gestiona la connexió amb la base de dades MySQL/MariaDB.
 * Configurat per funcionar tant en Docker com en XAMPP local.
 *
 * @author Institut Montsià - ASIX
 * @version 2.0
 */

// En Docker el host és el nom del servei 'db', no 'localhost'
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'institut_montsia');

/**
 * Crea i retorna una connexió mysqli a la base de dades.
 * En cas d'error, atura l'execució i mostra el missatge.
 *
 * @return mysqli Objecte de connexió actiu
 */
function getConnexio(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de connexió: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
