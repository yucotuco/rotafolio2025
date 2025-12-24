<?php
// funcionesyClases/validarSesion.php

function validarSesion()
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Verificar que los datos de sesión sean consistentes
    if (!isset($_SESSION['user_data']) || $_SESSION['user_data']['id'] != $_SESSION['user_id']) {
        return false;
    }

    // Opcional: verificar tiempo de sesión
    if (isset($_SESSION['user_data']['fecha_login'])) {
        $tiempoSesion = 8 * 60 * 60; // 8 horas
        $tiempoTranscurrido = time() - strtotime($_SESSION['user_data']['fecha_login']);

        if ($tiempoTranscurrido > $tiempoSesion) {
            session_destroy();
            return false;
        }
    }

    // Verificar que el usuario existe en la base de datos
    global $pdoRota;
    try {
        $sql = "SELECT COUNT(*) as total FROM usuario WHERE id = ? AND activo = '1' AND correo_verificado = 'si'";
        $stmt = $pdoRota->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();

        if ($result['total'] == 0) {
            session_destroy();
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error validando sesión: " . $e->getMessage());
        return false;
    }

    return true;
}

function redirigirSiSesionInvalida($pdo = null)
{
    if (!validarSesion()) {
        // Registrar intento de acceso no autorizado
        if ($pdo && isset($_SERVER['REMOTE_ADDR'])) {
            try {
                $sql = "INSERT INTO rota_logs (usuario_id, accion, descripcion, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                    'acceso_no_autorizado',
                    'Intento de acceso sin sesión válida',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                // Ignorar errores de log
            }
        }

        header("Location: ../auth/login.php?error=sesion_expirada");
        exit();
    }
}

function obtenerUsuarioID()
{
    return $_SESSION['user_id'] ?? null;
}

function obtenerUsuarioNombre()
{
    return $_SESSION['user_data']['nombre'] ?? 'Usuario';
}

function obtenerUsuarioPlan()
{
    return $_SESSION['user_data']['plan'] ?? 'free';
}

function obtenerUsuarioDatos()
{
    return $_SESSION['user_data'] ?? null;
}

function actualizarSesionUsuario($pdo, $usuario_id)
{
    try {
        $sql = "SELECT * FROM usuario WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch();

        if ($user) {
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
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error actualizando sesión: " . $e->getMessage());
    }
    return false;
}
