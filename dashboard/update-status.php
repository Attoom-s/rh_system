<?php
session_start();
require_once '../config/database.php';

// 1. Sesión
if (!isset($_SESSION['user_id'])) { http_response_code(401); die("No autorizado"); }

// 2. Solo RH puede cambiar estados
if ($_SESSION['role'] !== 'rh') { http_response_code(403); die("Acceso denegado"); }

// 3. Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die("Método no permitido"); }

// 4. CSRF
verifyCsrf();

// 5. Validar parámetros
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$estado = $_POST['estado'] ?? '';
$redir  = $_POST['redirect'] ?? 'index.php';

// Sanear redirect
if (!in_array($redir, ['index.php', 'solicitudes.php'])) $redir = 'index.php';

if (!$id || $id <= 0) { die("ID inválido"); }
if (!in_array($estado, ['Considerado','No Considerado','No Revisado'])) { die("Estado inválido"); }

// 6. Verificar que el registro existe
$stmt = $pdo->prepare("SELECT id, estado, nombre, apellido FROM applicants WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch();
if (!$applicant) { die("Solicitud no encontrada"); }

$estadoAnterior = $applicant['estado'];

// 7. Actualizar
$upd = $pdo->prepare("UPDATE applicants SET estado = ?, updated_at = NOW() WHERE id = ?");
$upd->execute([$estado, $id]);

// 8. Log de actividad
try {
    $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, created_at) VALUES (?, 'update_status', ?, ?, NOW())");
    $nombre = $applicant['nombre'] . ' ' . $applicant['apellido'];
    $log->execute([$_SESSION['user_id'], "Cambió solicitud de '$nombre' de '$estadoAnterior' a '$estado'", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
} catch (PDOException $e) {
    error_log("Log error: " . $e->getMessage());
}

$_SESSION['flash_message'] = "Estado actualizado a '$estado' correctamente.";
$_SESSION['flash_type']    = 'success';

header("Location: $redir");
exit();
?>
