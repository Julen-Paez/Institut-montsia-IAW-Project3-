<?php
// Dades de connexió
$host = "localhost";
$usuari = "root";
$contrasenya = "";
$bd = "institut_montsia";

$conn = new mysqli($host, $usuari, $contrasenya, $bd);

if ($conn->connect_error) {
    die("Error: " . $conn->connect_error);
}

echo "Connexió correcta a la BD!";
?>