<?php
session_start();
require_once '../config/database.php';  // ← Esto es CRUCIAL, lo estabas omitiendo

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../auth/login.php"); 
    exit(); 
}

// Solo usuarios normales (aspirantes)
if ($_SESSION['role'] !== 'user') { 
    header("Location: ../dashboard/index.php"); 
    exit(); 
}

$userId = $_SESSION['user_id'];
$csrf = getCsrfToken();  // Ahora sí funciona porque ya incluimos database.php

$flash     = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Obtener datos del aspirante si existen
$stmt = $pdo->prepare("SELECT * FROM applicants WHERE user_id = ?");
$stmt->execute([$userId]);
$data = $stmt->fetch();
$hasData = (bool)$data;

$errors  = [];
$success = '';

// Añadir estado si existe en $data (para mostrarlo)
if ($hasData && !isset($data['estado'])) {
    $data['estado'] = 'No Revisado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Recoger y sanitizar ──
    $cedula      = trim($_POST['cedula_pasaporte'] ?? '');
    $nombre      = trim($_POST['nombre'] ?? '');
    $apellido    = trim($_POST['apellido'] ?? '');
    $estado_civil= $_POST['estado_civil'] ?? '';
    $genero      = $_POST['genero'] ?? '';
    $tipo_sangre = $_POST['tipo_sangre'] ?? '';
    $fecha_nac   = $_POST['fecha_nacimiento'] ?? '';
    $nacionalidad= trim($_POST['nacionalidad'] ?? '');
    $telefono    = trim($_POST['telefono'] ?? '');
    $residencia  = trim($_POST['residencia'] ?? '');
    $correo      = trim($_POST['correo'] ?? '');

    // ── Validaciones ──
    if (empty($cedula))       $errors[] = "Cédula o pasaporte es obligatorio.";
    elseif (!preg_match('/^[A-Za-z0-9\-]{4,20}$/', $cedula)) $errors[] = "Cédula/pasaporte inválido (4-20 caracteres alfanuméricos).";

    if (empty($nombre))       $errors[] = "Nombre es obligatorio.";
    elseif (!preg_match('/^[\p{L}\s\-\.]{2,100}$/u', $nombre)) $errors[] = "Nombre inválido.";

    if (empty($apellido))     $errors[] = "Apellido es obligatorio.";
    elseif (!preg_match('/^[\p{L}\s\-\.]{2,100}$/u', $apellido)) $errors[] = "Apellido inválido.";

    $generos_ok = ['Masculino','Femenino','Otro'];
    if (!in_array($genero, $generos_ok)) $errors[] = "Género es obligatorio.";

    if (empty($fecha_nac)) {
        $errors[] = "Fecha de nacimiento es obligatoria.";
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha_nac);
        $age = $dt ? (new DateTime())->diff($dt)->y : 0;
        if (!$dt || $age < 16 || $age > 100) $errors[] = "Fecha de nacimiento inválida (debe tener entre 16 y 100 años).";
    }

    if (empty($nacionalidad))  $errors[] = "Nacionalidad es obligatoria.";

// Validación de teléfono con formato específico
require_once '../config/validations.php';

$phone_country = $_POST['phone_country'] ?? 'pa';
$telefono_raw = trim($_POST['telefono'] ?? '');

if (empty($telefono_raw)) {
    $errors[] = "Teléfono es obligatorio.";
} else {
    // Limpiar solo dígitos
    $clean_phone = preg_replace('/[^\d]/', '', $telefono_raw);
    
    // Validar según país seleccionado
    $phone_validation = validatePhone($clean_phone, $phone_country);
    
    if (!$phone_validation['valid']) {
        $errors[] = $phone_validation['message'];
    } else {
        $telefono = $phone_validation['formatted']; // Guardar con formato bonito (+507 XXXX-XXXX)
    }
}

    if (empty($residencia))    $errors[] = "Residencia es obligatoria.";
    if (empty($correo))        $errors[] = "Correo electrónico es obligatorio.";
    elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errors[] = "Correo electrónico inválido.";

    $estados_civiles_ok = ['','Soltero/a','Casado/a','Divorciado/a','Viudo/a','Union libre'];
    if (!in_array($estado_civil, $estados_civiles_ok)) $estado_civil = '';

    $tipos_sangre_ok = ['','A+','A-','B+','B-','AB+','AB-','O+','O-'];
    if (!in_array($tipo_sangre, $tipos_sangre_ok)) $tipo_sangre = '';

    if (empty($errors)) {
        if ($hasData) {
            // UPDATE
            $upd = $pdo->prepare("UPDATE applicants SET cedula_pasaporte=?, nombre=?, apellido=?, estado_civil=?, genero=?, tipo_sangre=?, fecha_nacimiento=?, nacionalidad=?, telefono=?, residencia=?, correo=?, updated_at=NOW() WHERE user_id=?");
            $upd->execute([$cedula, $nombre, $apellido, $estado_civil ?: null, $genero, $tipo_sangre ?: null, $fecha_nac, $nacionalidad, $telefono, $residencia, $correo, $userId]);
        } else {
            // Verificar cédula única
            $chk = $pdo->prepare("SELECT id FROM applicants WHERE cedula_pasaporte = ?");
            $chk->execute([$cedula]);
            if ($chk->rowCount() > 0) {
                $errors[] = "La cédula/pasaporte ya está registrada en el sistema.";
            } else {
                $ins = $pdo->prepare("INSERT INTO applicants (user_id, cedula_pasaporte, nombre, apellido, estado_civil, genero, tipo_sangre, fecha_nacimiento, nacionalidad, telefono, residencia, correo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $ins->execute([$userId, $cedula, $nombre, $apellido, $estado_civil ?: null, $genero, $tipo_sangre ?: null, $fecha_nac, $nacionalidad, $telefono, $residencia, $correo]);
                $hasData = true;
                
                // Recargar datos después de insertar
                $stmt = $pdo->prepare("SELECT * FROM applicants WHERE user_id = ?");
                $stmt->execute([$userId]);
                $data = $stmt->fetch();
            }
        }

        if (empty($errors)) {
            $_SESSION['flash_message'] = $hasData ? "Información actualizada correctamente." : "Solicitud enviada correctamente.";
            $_SESSION['flash_type']    = 'success';
            header("Location: index.php");
            exit();
        }
    }

    // Repoblar $data con lo enviado para no perder los campos
    $data = compact('cedula','nombre','apellido','estado_civil','genero','tipo_sangre','fecha_nac','nacionalidad','telefono','residencia','correo');
    $data['cedula_pasaporte']  = $cedula;
    $data['fecha_nacimiento']  = $fecha_nac;
    $data['estado'] = 'No Revisado';
}

// Helpers
$v = function($k, $d='') use ($data) { 
    return e($data[$k] ?? $d); 
};
$sel = function($k, $val) use ($data) { 
    return ($data[$k] ?? '') === $val ? 'selected' : ''; 
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi Solicitud — RH System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo"><div class="logo-icon"><i class="fa-solid fa-users"></i></div><span>RH System</span></div>
    <div class="sidebar-section">Mi Cuenta</div>
    <a href="index.php" class="menu-item active"><i class="fa-solid fa-file-lines"></i> Mi Solicitud</a>
    <div class="sidebar-bottom">
        <a href="../auth/logout.php" class="menu-item"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-left"></div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="avatar avatar-blue"><?= strtoupper(mb_substr($_SESSION['username'],0,2)) ?></div>
                <?= e($_SESSION['username']) ?>
            </div>
        </div>
    </div>

    <div class="page-body" style="max-width:900px">

        <?php if ($flash): ?>
        <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>" id="flashMsg"><?= e($flash) ?></div>
        <?php endif; ?>

        <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px">
            <?= $hasData ? 'Mi Solicitud de Empleo' : 'Completar Solicitud de Empleo' ?>
        </h1>
        <p style="color:var(--text-secondary);margin-bottom:24px">
            <?= $hasData ? 'Actualiza tu información cuando sea necesario' : 'Completa todos los campos obligatorios para enviar tu solicitud' ?>
        </p>

        <?php if ($hasData && isset($data['estado'])): ?>
        <div class="alert alert-info" style="margin-bottom:20px">
            <i class="fa-solid fa-circle-info"></i>
            Estado de tu solicitud:
            <?php 
            $bc = match($data['estado']) { 
                'Considerado' => 'badge-ok', 
                'No Considerado' => 'badge-no', 
                default => 'badge-review' 
            }; 
            ?>
            <span class="badge <?= $bc ?>" style="margin-left:6px"><?= e($data['estado']) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <div><i class="fa-solid fa-circle-exclamation"></i> <strong>Por favor corrija los siguientes errores:</strong></div>
            <ul style="margin:8px 0 0 20px">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="card-dark" style="margin-bottom:20px">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
                    <i class="fa-solid fa-id-card" style="color:var(--accent-blue);margin-right:8px"></i>
                    Identificación
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cédula o Pasaporte <span class="req">*</span></label>
                        <input type="text" name="cedula_pasaporte" class="form-control"
                               placeholder="Ej: 8-123-456 o AB123456"
                               value="<?= $v('cedula_pasaporte') ?>"
                               maxlength="20" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo Electrónico <span class="req">*</span></label>
                        <input type="email" name="correo" class="form-control"
                               placeholder="correo@ejemplo.com"
                               value="<?= $v('correo') ?>"
                               maxlength="150" required>
                    </div>
                </div>
            </div>

            <div class="card-dark" style="margin-bottom:20px">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
                    <i class="fa-solid fa-user" style="color:var(--accent-blue);margin-right:8px"></i>
                    Datos Personales
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nombre <span class="req">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Tu nombre" value="<?= $v('nombre') ?>" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apellido <span class="req">*</span></label>
                        <input type="text" name="apellido" class="form-control" placeholder="Tu apellido" value="<?= $v('apellido') ?>" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Género <span class="req">*</span></label>
                        <select name="genero" class="form-control" required>
                            <option value="">Seleccionar...</option>
                            <option value="Masculino" <?= $sel('genero','Masculino') ?>>Masculino</option>
                            <option value="Femenino"  <?= $sel('genero','Femenino') ?>>Femenino</option>
                            <option value="Otro"      <?= $sel('genero','Otro') ?>>Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado Civil</label>
                        <select name="estado_civil" class="form-control">
                            <option value="">Seleccionar...</option>
                            <option value="Soltero/a"    <?= $sel('estado_civil','Soltero/a') ?>>Soltero/a</option>
                            <option value="Casado/a"     <?= $sel('estado_civil','Casado/a') ?>>Casado/a</option>
                            <option value="Divorciado/a" <?= $sel('estado_civil','Divorciado/a') ?>>Divorciado/a</option>
                            <option value="Viudo/a"      <?= $sel('estado_civil','Viudo/a') ?>>Viudo/a</option>
                            <option value="Union libre"  <?= $sel('estado_civil','Union libre') ?>>Unión libre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Nacimiento <span class="req">*</span></label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?= $v('fecha_nacimiento') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Sangre</label>
                        <select name="tipo_sangre" class="form-control">
                            <option value="">Seleccionar...</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?>
                            <option value="<?= $ts ?>" <?= $sel('tipo_sangre',$ts) ?>><?= $ts ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-dark" style="margin-bottom:24px">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
                    <i class="fa-solid fa-address-book" style="color:var(--accent-blue);margin-right:8px"></i>
                    Contacto y Ubicación
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nacionalidad <span class="req">*</span></label>
                        <input type="text" name="nacionalidad" class="form-control" placeholder="Ej: Panameña" value="<?= $v('nacionalidad') ?>" maxlength="100" required>
<div class="form-group">
    <label class="form-label">Teléfono <span class="req">*</span></label>
    <div class="phone-input-group" style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
        <select name="phone_country" id="phone_country" class="form-control" style="width: 130px;" onchange="updatePhonePlaceholder()">
            <option value="pa" <?= ($_POST['phone_country'] ?? 'pa') === 'pa' ? 'selected' : '' ?>>🇵🇦 Panamá (+507)</option>
            <option value="us" <?= ($_POST['phone_country'] ?? '') === 'us' ? 'selected' : '' ?>>🇺🇸 EE.UU. (+1)</option>
        </select>
        <div style="flex: 1; min-width: 200px;">
            <input type="tel" name="telefono" id="telefono" class="form-control" 
                   placeholder="<?= ($_POST['phone_country'] ?? 'pa') === 'pa' ? 'XXXX-XXXX' : 'XXX-XXX-XXXX' ?>" 
                   value="<?= preg_replace('/^\+507 /', '', preg_replace('/^\+1 /', '', $v('telefono'))) ?>" 
                   maxlength="20" required>
            <small class="phone-hint" style="display: block; margin-top: 4px; font-size: 11px; color: var(--text-muted);">
                <span id="phoneHint">
                    <?= ($_POST['phone_country'] ?? 'pa') === 'pa' ? '📱 Formato: 6123-4567 (Panamá - 8 dígitos)' : '📞 Formato: 555-123-4567 (EE.UU. - 10 dígitos)' ?>
                </span>
            </small>
        </div>
    </div>
</div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Residencia <span class="req">*</span></label>
                        <input type="text" name="residencia" class="form-control" placeholder="Dirección de residencia" value="<?= $v('residencia') ?>" maxlength="255" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="padding:13px 32px;font-size:14px">
                <i class="fa-solid fa-<?= $hasData ? 'floppy-disk' : 'paper-plane' ?>"></i>
                <?= $hasData ? 'Actualizar información' : 'Enviar solicitud' ?>
            </button>
        </form>
    </div>
</div>

<script>
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity='0', 3500);
// Función para actualizar el placeholder según el país seleccionado
function updatePhonePlaceholder() {
    const country = document.getElementById('phone_country').value;
    const phoneInput = document.getElementById('telefono');
    const phoneHint = document.getElementById('phoneHint');
    
    if (country === 'pa') {
        phoneInput.placeholder = 'XXXX-XXXX';
        phoneHint.innerHTML = '📱 Formato: 6123-4567 (Panamá - 8 dígitos)';
    } else {
        phoneInput.placeholder = 'XXX-XXX-XXXX';
        phoneHint.innerHTML = '📞 Formato: 555-123-4567 (EE.UU. - 10 dígitos)';
    }
}

// Formatear automáticamente mientras escribe
document.getElementById('telefono')?.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, ''); // Solo dígitos
    const country = document.getElementById('phone_country').value;
    
    if (country === 'pa') {
        // Formato para Panamá: XXXX-XXXX (8 dígitos)
        if (value.length >= 4) {
            this.value = value.slice(0, 4) + '-' + value.slice(4, 8);
        } else {
            this.value = value;
        }
        // Limitar a 8 dígitos + guión
        if (this.value.length > 9) {
            this.value = this.value.slice(0, 9);
        }
    } else {
        // Formato para EE.UU.: XXX-XXX-XXXX (10 dígitos)
        if (value.length >= 6) {
            this.value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 10);
        } else if (value.length >= 3) {
            this.value = value.slice(0, 3) + '-' + value.slice(3);
        } else {
            this.value = value;
        }
        // Limitar a 10 dígitos + 2 guiones (12 caracteres)
        if (this.value.length > 12) {
            this.value = this.value.slice(0, 12);
        }
    }
});
</script>
</body>
</html>