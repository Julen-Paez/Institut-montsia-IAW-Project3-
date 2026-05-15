<?php
/**
 * test.php - Diagnòstic de connexió Docker
 * Esborra aquest arxiu després d'usar-lo.
 */

$hosts = ['db', 'localhost', '127.0.0.1', 'institut_db'];
echo "<h2>Test de connexió Docker</h2>";

foreach ($hosts as $host) {
    $conn = @new mysqli($host, 'root', 'root', 'institut_montsia');
    if (!$conn->connect_error) {
        echo "<p style='color:green'>✅ Funciona amb host: <strong>$host</strong></p>";
        
        // Comprovar usuaris
        $res = $conn->query("SELECT username, rol FROM Usuaris");
        if ($res && $res->num_rows > 0) {
            echo "<p>Usuaris trobats:</p><ul>";
            while ($u = $res->fetch_assoc()) {
                echo "<li>{$u['username']} ({$u['rol']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:orange'>⚠️ Taula Usuaris buida o no existeix</p>";
        }
        $conn->close();
    } else {
        echo "<p style='color:red'>❌ Falla amb host <strong>$host</strong>: " . $conn->connect_error . "</p>";
    }
}
?>
