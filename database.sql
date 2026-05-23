
-- RH System - Base de Datos

CREATE DATABASE IF NOT EXISTS rh_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rh_system;

-- Tabla de usuarios (aspirantes y personal RH)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'rh') NOT NULL DEFAULT 'user',
    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME DEFAULT NOW(),
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB;

-- Tabla de aspirantes (formulario de solicitud)
CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    cedula_pasaporte VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    estado_civil ENUM('Soltero/a','Casado/a','Divorciado/a','Viudo/a','Union libre') DEFAULT NULL,
    genero ENUM('Masculino','Femenino','Otro') NOT NULL,
    tipo_sangre ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
    fecha_nacimiento DATE NOT NULL,
    nacionalidad VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    residencia VARCHAR(255) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    estado ENUM('No Revisado','Considerado','No Considerado') DEFAULT 'No Revisado',
    created_at DATETIME DEFAULT NOW(),
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de log de actividad
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Usuario RH por defecto (contraseña: RHSystem@2024!)
-- password_hash generado con PASSWORD_BCRYPT
INSERT INTO users (username, password, role) VALUES 
('rh_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rh')
ON DUPLICATE KEY UPDATE id=id;

-- NOTA: La contraseña por defecto del usuario rh_admin es: password
-- Cambiar inmediatamente en producción usando:
-- UPDATE users SET password = '$hash_nueva_contrasena' WHERE username = 'rh_admin';
