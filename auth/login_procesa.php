<?php
// auth/login_procesa.php
session_start();
require_once "../conn/connrota.php";

// Activar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que venga del formulario
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    error_log("DEBUG: No es método POST");
    header("Location: login.php?error=acceso_invalido");
    exit();
}

// Verificar que se enviaron los campos necesarios
if (!isset($_POST['usuario']) || !isset($_POST['clave'])) {
    error_log("DEBUG: Faltan campos usuario o clave");
    header("Location: login.php?error=campos_vacios");
    exit();
}

// Obtener datos del formulario
$usuario_input = trim($_POST['usuario']);
$clave_input = trim($_POST['clave']);

// Validar campos
if (empty($usuario_input) || empty($clave_input)) {
    error_log("DEBUG: Campos vacíos");
    header("Location: login.php?error=campos_vacios");
    exit();
}

error_log("DEBUG: Procesando login para: " . $usuario_input);

// Buscar usuario por usuario o correo
$sql = "SELECT * FROM usuario WHERE (usuario = ? OR correo = ?)";
error_log("DEBUG: SQL: " . $sql);
$stmt = $pdoRota->prepare($sql);
$stmt->execute([$usuario_input, $usuario_input]);
$user = $stmt->fetch();

if (!$user) {
    // Usuario no encontrado
    error_log("DEBUG: Usuario no encontrado: " . $usuario_input);
    header("Location: login.php?error=usuario_no_encontrado");
    exit();
}

error_log("DEBUG: Usuario encontrado - ID: " . $user['id'] . ", Usuario: " . $user['usuario']);

// Verificar contraseña
error_log("DEBUG: Verificando contraseña...");
error_log("DEBUG: Clave ingresada: " . $clave_input);
error_log("DEBUG: Hash en BD: " . substr($user['clave'], 0, 30) . "...");

if (!password_verify($clave_input, $user['clave'])) {
    // Contraseña incorrecta
    error_log("DEBUG: Contraseña INCORRECTA para usuario: " . $user['usuario']);
    header("Location: login.php?error=contrasena_incorrecta");
    exit();
}

error_log("DEBUG: Contraseña VERIFICADA correctamente");

// Verificar si la cuenta está activa
if ($user['activo'] != '1') {
    error_log("DEBUG: Cuenta no activa - activo = " . $user['activo']);
    $estado = $user['activo'];
    if ($estado == '0') {
        header("Location: login.php?error=cuenta_pendiente");
    } elseif ($estado == '2') {
        header("Location: login.php?error=cuenta_suspendida");
    } elseif ($estado == '3') {
        header("Location: login.php?error=cuenta_bloqueada");
    } else {
        header("Location: login.php?error=cuenta_inactiva");
    }
    exit();
}

error_log("DEBUG: Cuenta activa");

// Crear sesión
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_data'] = [
    'id' => $user['id'],
    'usuario' => $user['usuario'],
    'nombre' => $user['nombre'],
    'apellido' => $user['apellido'],
    'correo' => $user['correo'],
    'nivel' => $user['nivel'],
    'plan' => $user['plan'],
    'correo_verificado' => $user['correo_verificado'],
    'activo' => $user['activo'],
    'fecha_login' => date('Y-m-d H:i:s')
];

error_log("DEBUG: Sesión creada - user_id: " . $_SESSION['user_id']);

// Registrar actividad de login
try {
    $sql_log = "INSERT INTO rota_logs (usuario_id, accion, descripcion, ip_address, user_agent, fecha_registro) 
                VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt_log = $pdoRota->prepare($sql_log);
    $stmt_log->execute([
        $user['id'],
        'login',
        'Inicio de sesión exitoso',
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    error_log("DEBUG: Log registrado");
} catch (Exception $e) {
    error_log("Error al registrar log de acceso: " . $e->getMessage());
}

// Actualizar última conexión
try {
    $sql_update = "UPDATE usuario SET fecha = NOW() WHERE id = ?";
    $stmt_update = $pdoRota->prepare($sql_update);
    $stmt_update->execute([$user['id']]);
    error_log("DEBUG: Última conexión actualizada");
} catch (Exception $e) {
    error_log("Error al actualizar última conexión: " . $e->getMessage());
}

// Redirigir según estado de verificación
if ($user['correo_verificado'] == 'si') {
    // Correo verificado, ir al dashboard
    error_log("DEBUG: Correo verificado, redirigiendo a dashboard");
    header("Location: ../dashboard/");
    exit();
} else {
    // Correo no verificado, ir a verificación
    error_log("DEBUG: Correo NO verificado, redirigiendo a verificación");

    // Guardar datos temporales para verificación
    $_SESSION['temp_user_id'] = $user['id'];
    $_SESSION['temp_user_data'] = [
        'id' => $user['id'],
        'usuario' => $user['usuario'],
        'nombre' => $user['nombre'],
        'correo' => $user['correo']
    ];

    header("Location: verificar_correo.php?nuevo_login=1");
    exit();
}
