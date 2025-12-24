<?php
ob_start();
session_start();

require_once "../conn/connrota.php";
require_once "../funcionesyClases/validarSesion.php";

// Registrar cierre de sesión
if (isset($_SESSION['user_id'])) {
    try {
        $sql = "INSERT INTO rota_logs (usuario_id, accion, descripcion, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdoRota->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            'logout',
            'Usuario cerró sesión',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        // Ignorar error si la tabla no existe
    }
}

// Destruir todas las variables de sesión.
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();
// Cerrar sesión usando la nueva función
cerrarSesion();

// Redirigir al login con mensaje
header("Location: login.php?logout=exito");
exit();
