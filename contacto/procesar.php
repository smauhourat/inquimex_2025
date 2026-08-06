<?php
/**
 * Procesador de Formulario de Contacto - Inquimex
 * - Configuración local protegida (config/config_app.php, bloqueada por .htaccess)
 * - Anti-Spam: CSRF token + Honeypot + Trampa de tiempo
 * - Envío por SMTP vía PHPMailer (librería vendorizada, sin dependencia de Composer)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

// ==============================================================================
// 1. FUNCIONES DE VALIDACIÓN Y SEGURIDAD
// ==============================================================================

function validarNombre($str) {
    return preg_match('/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s\'\-\.]+$/u', trim($str));
}

function validarTextoGeneral($str) {
    // Empresa / Industria / Producto de interés: letras, números y puntuación común
    return preg_match('/^[a-zA-Z0-9ñÑáéíóúÁÉÍÓÚüÜ\s\.,\-\'&()\/]+$/u', trim($str));
}

function detectarPatronesMaliciosos($str) {
    $str_decoded = urldecode($str);
    $analisis = strtolower($str . ' ' . $str_decoded);
    $patrones = [
        '/(\;|\||`|\$|\(|\))(\s)*(ls|cat|wget|curl|ping|uname|whoami|netcat|rm|mv|cp)\b/i',
        '/<script/i', '/javascript:/i', '/vbscript:/i',
        '/onload=/i', '/onerror=/i', '/onclick=/i',
        '/<iframe/i', '/<object/i', '/<embed/i',
        '/base64_decode/i', '/data:text\/html/i',
        '/union\s+select/i', '/select\s+.*\s+from/i',
        '/insert\s+into/i', '/update\s+.*\s+set/i',
        '/or\s+1=1/i',
        '/<\?php/i', '/eval\(/i', '/system\(/i',
        '/\.\.\//', '/etc\/passwd/i'
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $str) || preg_match($patron, $str_decoded)) {
            return true;
        }
    }
    return false;
}

function limpiarSalida($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ==============================================================================
// 2. CARGA DE CONFIGURACIÓN (RUTA LOCAL)
// ==============================================================================

define('ACCESO_SEGURO', true);

$ruta_config = __DIR__ . '/config/config_app.php';

if (!file_exists($ruta_config)) {
    echo json_encode(['success' => false, 'message' => 'Error 500: Falta configuración.']);
    exit;
}

$conf = require $ruta_config;

require __DIR__ . '/vendor/phpmailer/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // ==========================================================================
    // 3. FASE DE VERIFICACIÓN (Pre-Procesamiento)
    // ==========================================================================

    // A. CSRF Token
    if (empty($_POST['csrf_token']) || empty($_SESSION['form_token']) || !hash_equals($_SESSION['form_token'], $_POST['csrf_token'])) {
        throw new Exception("Error de seguridad: Token inválido. Recargá la página.");
    }

    // B. Honeypot
    $honey_field = $conf['seguridad']['honey_pot_field'] ?? 'website_check';
    if (!empty($_POST[$honey_field])) {
        echo json_encode(['success' => true, 'message' => $conf['textos']['exito']]);
        exit;
    }

    // C. Trampa de Tiempo
    $tiempo_minimo = $conf['seguridad']['tiempo_minimo'] ?? 3;
    $tiempo_transcurrido = time() - ($_SESSION['form_time'] ?? 0);
    if ($tiempo_transcurrido < $tiempo_minimo) {
        throw new Exception("Envío bloqueado: demasiado rápido (posible bot).");
    }

    // ==========================================================================
    // 4. VALIDACIÓN ESTRICTA DE CAMPOS
    // ==========================================================================

    // --- NOMBRE Y APELLIDO ---
    $nombre = trim($_POST['nombre'] ?? '');
    if ($conf['campos']['nombre']['requerido'] && $nombre === '') throw new Exception("El nombre es obligatorio.");
    if ($nombre !== '' && !validarNombre($nombre)) throw new Exception("El Nombre contiene caracteres no válidos.");

    // --- EMPRESA ---
    $empresa = trim($_POST['empresa'] ?? '');
    if ($conf['campos']['empresa']['requerido'] && $empresa === '') throw new Exception("La empresa es obligatoria.");
    if ($empresa !== '' && !validarTextoGeneral($empresa)) throw new Exception("La Empresa contiene caracteres no válidos.");

    // --- INDUSTRIA ---
    $industria = trim($_POST['industria'] ?? '');
    if ($conf['campos']['industria']['requerido'] && $industria === '') throw new Exception("La industria es obligatoria.");
    if ($industria !== '' && !validarTextoGeneral($industria)) throw new Exception("La Industria contiene caracteres no válidos.");

    // --- EMAIL ---
    $email = trim($_POST['email'] ?? '');
    if ($email === '') throw new Exception("El email es obligatorio.");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Formato de email incorrecto.");
    if (preg_match("/[\r\n]/", $email)) throw new Exception("Intento de inyección en Email.");

    // --- PRODUCTO DE INTERÉS ---
    $producto_interes = trim($_POST['producto_interes'] ?? '');
    // if ($conf['campos']['producto_interes']['requerido'] && $producto_interes === '') throw new Exception("El producto de interés es obligatorio.");
    if ($producto_interes !== '' && !validarTextoGeneral($producto_interes)) throw new Exception("El Producto de Interés contiene caracteres no válidos.");

    // --- CONSUMO MENSUAL ESPERADO ---
    $consumo = trim($_POST['consumo'] ?? '');
    if ($conf['campos']['consumo']['requerido'] && $consumo === '') throw new Exception("El consumo mensual esperado es obligatorio.");
    if ($consumo !== '' && !in_array($consumo, $conf['opciones_consumo'], true)) throw new Exception("Opción de consumo mensual no válida.");

    // --- CONSULTA ---
    $consulta = trim($_POST['consulta'] ?? '');
    if ($conf['campos']['consulta']['requerido'] && $consulta === '') throw new Exception("La consulta es obligatoria.");
    if (mb_strlen($consulta) > 5000) throw new Exception("La consulta es demasiado extensa.");
    if (detectarPatronesMaliciosos($consulta)) throw new Exception("La consulta contiene caracteres no permitidos.");

    // ==========================================================================
    // 5. CONSTRUCCIÓN DEL CORREO
    // ==========================================================================

    $campos_email = [
        'nombre'           => $nombre,
        'empresa'          => $empresa,
        'industria'        => $industria,
        'email'            => $email,
        'producto_interes' => $producto_interes,
        'consumo'          => $consumo,
    ];

    $mensaje_html = "<div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>";
    $mensaje_html .= "<h2 style='color: #0235d2; border-bottom: 2px solid #0235d2; padding-bottom: 10px;'>Nueva consulta desde la web</h2>";
    $mensaje_html .= "<table style='width: 100%; border-collapse: collapse;'>";

    foreach ($campos_email as $key => $valor) {
        if ($valor === '') continue;
        $label = $conf['campos'][$key]['label'] ?? ucfirst($key);
        $valor_limpio = limpiarSalida($valor);
        $mensaje_html .= "<tr>";
        $mensaje_html .= "<td style='padding: 8px; font-weight: bold; width: 200px; background: #f9f9f9;'>" . $label . ":</td>";
        $mensaje_html .= "<td style='padding: 8px;'>" . $valor_limpio . "</td>";
        $mensaje_html .= "</tr>";
    }

    if ($consulta !== '') {
        $valor_limpio = nl2br(limpiarSalida($consulta));
        $mensaje_html .= "<tr>";
        $mensaje_html .= "<td style='padding: 8px; font-weight: bold; width: 200px; background: #f9f9f9;'>Consulta:</td>";
        $mensaje_html .= "<td style='padding: 8px;'>" . $valor_limpio . "</td>";
        $mensaje_html .= "</tr>";
    }

    $mensaje_html .= "</table>";
    $mensaje_html .= "<div style='margin-top: 20px; font-size: 0.8em; color: #777;'>IP: " . $_SERVER['REMOTE_ADDR'] . "</div>";
    $mensaje_html .= "</div>";

    // ==========================================================================
    // 6. ENVÍO SMTP
    // ==========================================================================

    $mail = new PHPMailer(true);

    $mail->SMTPOptions = [
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ];

    $mail->isSMTP();
    $mail->Host       = $conf['smtp']['host'];
    $mail->SMTPAuth   = $conf['smtp']['auth'];
    $mail->Username   = $conf['smtp']['username'];
    $mail->Password   = $conf['smtp']['password'];
    $mail->SMTPSecure = $conf['smtp']['secure'];
    $mail->Port       = $conf['smtp']['port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($conf['smtp']['username'], $conf['smtp']['from_name']);
    $mail->addAddress($conf['smtp']['recipient']);
    $mail->addReplyTo($email, limpiarSalida($nombre !== '' ? $nombre : 'Usuario'));

    $mail->isHTML(true);
    $mail->Subject = 'Consulta Web Inquimex - ' . limpiarSalida($empresa);
    $mail->Body    = $mensaje_html;
    $mail->AltBody = strip_tags(str_replace(['<br>', '</tr>'], ["\n", "\n"], $mensaje_html));

    $mail->send();

    $response['success'] = true;
    $response['message'] = $conf['textos']['exito'];

} catch (Exception $e) {
    $msg = $e->getMessage();
    if (!empty($conf['smtp']['debug']) && $conf['smtp']['debug'] > 0) {
        $response['message'] = "DEBUG: " . $msg . " | " . ($mail->ErrorInfo ?? '');
    } else {
        if (strpos($msg, 'SMTP') !== false || strpos($msg, 'connect') !== false) {
            $response['message'] = $conf['textos']['error_gral'];
        } else {
            $response['message'] = $msg;
        }
    }
}

echo json_encode($response);
