<?php
// Protección contra acceso directo (aunque el .htaccess ya lo protege)
defined('ACCESO_SEGURO') or die('Acceso denegado');

return [
    // 1. Configuración SMTP
    // Completar 'host' y 'password' con los datos reales que provee el hosting
    // (panel de cPanel / Webmail de la cuenta web@inquimex.com.ar).
    'smtp' => [
        // 'host'       => 'mail.nobra.com.ar', // Obtener del panel de hosting DonWeb/Dattaweb
        // 'auth'       => true,
        // 'username'   => 'web@nobra.com.ar',
        // 'password'   => 'asdasdasdasdas@asdasd',  // Completar con la clave r        
        // 'secure'     => 'ssl',
        // 'port'       => 465,		
        'host'       => 'smtp.gmail.com', // TODO: confirmar con el hosting
        'auth'       => true,
        'username'   => 'web.inquimex@gmail.com',
        'password'   => 'zheb ndob unyg fttr', // TODO: completar con la clave real
        'secure'     => 'tls',
        'port'       => 587,
        'debug'      => 0, // 0 para producción, 2 para desarrollo
        'from_name'  => 'Inquimex · Formulario Web',
        'recipient'  => 'web@inquimex.com.ar'
        //'recipient'  => 'santiago.mauhourat@gmail.com'
    ],


    // 2. Google reCAPTCHA (desactivado por defecto)
    'recaptcha' => [
        'activo'     => false,
        'site_key'   => '',
        'secret_key' => ''
    ],

    // 3. Activación y etiquetas de los campos del formulario
    'campos' => [
        'nombre'           => ['activo' => true, 'requerido' => true,  'label' => 'Nombre y Apellido'],
        'empresa'          => ['activo' => true, 'requerido' => true,  'label' => 'Empresa'],
        'industria'        => ['activo' => true, 'requerido' => true,  'label' => 'Industria'],
        'producto_interes' => ['activo' => true, 'requerido' => false, 'label' => 'Producto de Interés'],
        'consumo'          => ['activo' => true, 'requerido' => true,  'label' => 'Consumo mensual esperado'],
        'consulta'         => ['activo' => true, 'requerido' => true,  'label' => 'Consulta'],
    ],

    // Opciones válidas para el campo "consumo" (se validan también server-side)
    'opciones_consumo' => [
        'Menos de 1000 kg o litros',
        'Entre 1000 y 10000 kg o litros',
        'Más de 10000 kg o litros',
    ],

    // 4. Seguridad Interna (Honeypot + TimeTrap)
    'seguridad' => [
        'tiempo_minimo'   => 3,
        'honey_pot_field' => 'website_check'
    ],

    // 5. Textos de respuesta
    'textos' => [
        'exito'      => '¡Mensaje enviado! Te responderemos a la brevedad.',
        'error_gral' => 'Ocurrió un error al enviar. Por favor intentá nuevamente en unos minutos.'
    ]
];
