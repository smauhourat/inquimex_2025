<?php
/**
 * Inicializador de sesión para el formulario de contacto.
 * El frontend llama a este endpoint al cargar la página para obtener
 * el token CSRF antes de que el usuario envíe el formulario.
 */
header('Content-Type: application/json; charset=utf-8');

session_start();

$_SESSION['form_token'] = bin2hex(random_bytes(32));
$_SESSION['form_time']  = time();

echo json_encode(['csrf_token' => $_SESSION['form_token']]);
