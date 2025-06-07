<?php
// Configuración de la base de datos
$host = 'proinvestecdb.ccrygqa4i1kp.us-east-1.rds.amazonaws.com';
$dbname = 'proinvestec';
$username = 'postgres';
$password = 'SmfBsc00148*';
$port = '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
