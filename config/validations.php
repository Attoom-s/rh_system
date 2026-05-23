<?php
// Validaciones compartidas para todo el sistema

/**
 * Validar número de teléfono según país
 * @param string $phone Número de teléfono (solo dígitos)
 * @param string $country Código de país ('pa' o 'us')
 * @return array ['valid' => bool, 'message' => string, 'formatted' => string]
 */
function validatePhone($phone, $country = 'pa') {
    // Limpiar: solo mantener dígitos
    $clean = preg_replace('/[^\d]/', '', $phone);
    
    // Panamá: 8 dígitos (XXXX-XXXX)
    if ($country === 'pa') {
        // Eliminar código 507 si está presente
        if (substr($clean, 0, 3) === '507') {
            $clean = substr($clean, 3);
        }
        
        // Validar exactamente 8 dígitos
        if (preg_match('/^\d{8}$/', $clean)) {
            $formatted = '+507 ' . substr($clean, 0, 4) . '-' . substr($clean, 4, 4);
            return ['valid' => true, 'message' => '', 'formatted' => $formatted];
        } else {
            $current_len = strlen($clean);
            return ['valid' => false, 'message' => "Número inválido para Panamá. Debe tener 8 dígitos (tiene $current_len). Ejemplo: 6123-4567", 'formatted' => ''];
        }
    }
    
    // Estados Unidos: 10 dígitos (XXX-XXX-XXXX)
    if ($country === 'us') {
        // Eliminar código 1 si está presente
        if (substr($clean, 0, 1) === '1') {
            $clean = substr($clean, 1);
        }
        
        // Validar exactamente 10 dígitos
        if (preg_match('/^\d{10}$/', $clean)) {
            $formatted = '+1 ' . substr($clean, 0, 3) . '-' . substr($clean, 3, 3) . '-' . substr($clean, 6, 4);
            return ['valid' => true, 'message' => '', 'formatted' => $formatted];
        } else {
            $current_len = strlen($clean);
            return ['valid' => false, 'message' => "Número inválido para EE.UU. Debe tener 10 dígitos (tiene $current_len). Ejemplo: 555-123-4567", 'formatted' => ''];
        }
    }
    
    return ['valid' => false, 'message' => 'País no soportado', 'formatted' => ''];
}

/**
 * Validar teléfono desde input del usuario (con formato)
 */
function validatePhoneFromInput($raw_phone, $country) {
    // Extraer solo dígitos
    $digits = preg_replace('/[^\d]/', '', $raw_phone);
    
    // Para USA: si el usuario incluye el 1 inicial, lo manejamos
    if ($country === 'us' && strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
        $digits = substr($digits, 1);
    }
    
    // Para Panamá: si el usuario incluye 507, lo manejamos
    if ($country === 'pa' && strlen($digits) === 11 && substr($digits, 0, 3) === '507') {
        $digits = substr($digits, 3);
    }
    
    return validatePhone($digits, $country);
}
?>