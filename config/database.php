<?php
// ============================================================
// Configuración de base de datos
// ============================================================

// Temporal: mostrar errores para depurar
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Configuración - CAMBIA SEGÚN TU ENTORNO
$host = 'localhost';
$db   = 'rh_system';
$user = 'sa';
$pass = '12345UTP';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die("Error de conexión con la base de datos: " . $e->getMessage());
}

// Función helper para sanitizar output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Función para verificar CSRF
function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die("Token de seguridad inválido. Recargue la página.");
    }
}

// Generar CSRF token si no existe
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>