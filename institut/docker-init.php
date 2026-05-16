<?php
/**
 * docker-init.php
 * S'executa automàticament en arrencar el contenidor.
 * Genera hashes bcrypt correctes per a tots els usuaris.
 * No accessible des del navegador (ruta protegida).
 */

$conn = new mysqli('db', 'root', 'root', 'institut_montsia');
if ($conn->connect_error) {
    echo "Error: " . $conn->connect_error . "\n";
    exit(1);
}

$hash = password_hash('admin1234', PASSWORD_BCRYPT);

$usuaris = ['admin', 'professor', 'editor', 'joan.garcia'];
foreach ($usuaris as $u) {
    $stmt = $conn->prepare("UPDATE Usuaris SET password=? WHERE username=?");
    $stmt->bind_param("ss", $hash, $u);
    $stmt->execute();
    echo "✅ Contrasenya actualitzada: $u\n";
    $stmt->close();
}

$conn->close();
echo "🔑 Totes les contrasenyes actualitzades correctament.\n";
?>
