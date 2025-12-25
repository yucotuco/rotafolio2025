<?php
// funcionesyClases/validarSesion.php

/**
 * Validar si la sesión del usuario es válida
 * @return bool True si la sesión es válida, false si no
 */
function validarSesion()
{
    // Verificar que exista la sesión
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Verificar que los datos de sesión sean consistentes
    if (!isset($_SESSION['user_data']) || !is_array($_SESSION['user_data'])) {
        return false;
    }

    // Verificar que el ID en user_data coincida con user_id
    if ($_SESSION['user_data']['id'] != $_SESSION['user_id']) {
        return false;
    }

    // Verificar tiempo de sesión (opcional - 8 horas)
    if (isset($_SESSION['user_data']['fecha_login'])) {
        $tiempoSesion = 8 * 60 * 60; // 8 horas en segundos
        $tiempoTranscurrido = time() - strtotime($_SESSION['user_data']['fecha_login']);

        if ($tiempoTranscurrido > $tiempoSesion) {
            // Sesión expirada
            session_destroy();
            return false;
        }

        // Renovar sesión si falta menos de 1 hora para expirar
        if ($tiempoTranscurrido > ($tiempoSesion - 3600)) {
            $_SESSION['user_data']['fecha_login'] = date('Y-m-d H:i:s');
        }
    }

    return true;
}

/**
 * Redirigir al login si la sesión no es válida
 * @param PDO|null $pdo Conexión a la base de datos (opcional para logs)
 */
function redirigirSiSesionInvalida($pdo = null)
{
    if (!validarSesion()) {
        // Registrar intento de acceso no autorizado (si hay conexión a BD)
        if ($pdo && isset($_SERVER['REMOTE_ADDR'])) {
            try {
                $sql = "INSERT INTO logs_acceso (usuario_id, accion, descripcion, ip_address, user_agent, fecha) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                    'acceso_no_autorizado',
                    'Intento de acceso sin sesión válida',
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                // Ignorar errores de log (la tabla puede no existir)
                error_log("Error al registrar acceso no autorizado: " . $e->getMessage());
            }
        }

        // Limpiar sesión
        session_unset();
        session_destroy();

        // Redirigir al login
        header("Location: ../auth/login.php?error=sesion_expirada");
        exit();
    }
}

/**
 * Verificar si el usuario tiene un rol específico
 * @param string $rolRequerido Rol requerido (admin, editor, user)
 * @return bool True si tiene el rol o superior
 */
function verificarRol($rolRequerido)
{
    if (!isset($_SESSION['user_data']['nivel'])) {
        return false;
    }

    $roles = [
        'admin' => 1,
        'editor' => 2,
        'user' => 3
    ];

    $rolUsuario = $_SESSION['user_data']['nivel'];
    $rolRequeridoNivel = $roles[$rolRequerido] ?? 999;

    return ($rolUsuario <= $rolRequeridoNivel);
}

/**
 * Obtener ID del usuario desde la sesión
 * @return int|null ID del usuario o null si no hay sesión
 */
function obtenerUsuarioID()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtener nombre completo del usuario
 * @return string Nombre del usuario o 'Usuario' por defecto
 */
function obtenerUsuarioNombre()
{
    if (isset($_SESSION['user_data']['nombre']) && isset($_SESSION['user_data']['apellido'])) {
        return $_SESSION['user_data']['nombre'] . ' ' . $_SESSION['user_data']['apellido'];
    }
    return 'Usuario';
}

/**
 * Obtener plan del usuario
 * @return string Plan del usuario o 'free' por defecto
 */
function obtenerUsuarioPlan()
{
    return $_SESSION['user_data']['plan'] ?? 'free';
}

/**
 * Obtener todos los datos del usuario desde la sesión
 * @return array|null Datos del usuario o null si no hay sesión
 */
function obtenerUsuarioDatos()
{
    return $_SESSION['user_data'] ?? null;
}

/**
 * Actualizar datos del usuario en la sesión desde la base de datos
 * @param PDO $pdo Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @return bool True si se actualizó correctamente
 */
function actualizarSesionUsuario($pdo, $usuario_id)
{
    try {
        $sql = "SELECT id, usuario, nombre, apellido, correo, nivel, plan, correo_verificado, activo 
                FROM usuario 
                WHERE id = ? AND activo = '1'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
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
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error al actualizar sesión: " . $e->getMessage());
    }
    return false;
}

/**
 * Verificar si el correo del usuario está verificado
 * @return bool True si el correo está verificado
 */
function verificarCorreoVerificado()
{
    return (isset($_SESSION['user_data']['correo_verificado']) &&
        $_SESSION['user_data']['correo_verificado'] == 'si');
}

/**
 * Verificar si la cuenta del usuario está activa
 * @return bool True si la cuenta está activa
 */
function verificarCuentaActiva()
{
    return (isset($_SESSION['user_data']['activo']) &&
        $_SESSION['user_data']['activo'] == '1');
}

/**
 * Crear sesión para un usuario
 * @param array $usuario Datos del usuario
 * @return bool True si se creó la sesión correctamente
 */
function crearSesionUsuario($usuario)
{
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_data'] = [
        'id' => $usuario['id'],
        'usuario' => $usuario['usuario'],
        'nombre' => $usuario['nombre'],
        'apellido' => $usuario['apellido'],
        'correo' => $usuario['correo'],
        'nivel' => $usuario['nivel'] ?? 3,
        'plan' => $usuario['plan'] ?? 'free',
        'correo_verificado' => $usuario['correo_verificado'] ?? 'no',
        'activo' => $usuario['activo'] ?? '1',
        'fecha_login' => date('Y-m-d H:i:s')
    ];
    return true;
}

/**
 * Cerrar sesión del usuario
 * @param PDO|null $pdo Conexión a la base de datos (opcional para logs)
 */
function cerrarSesion($pdo = null)
{
    // Registrar cierre de sesión (si hay conexión a BD)
    if ($pdo && isset($_SESSION['user_id'])) {
        try {
            $sql = "INSERT INTO logs_acceso (usuario_id, accion, descripcion, ip_address, user_agent, fecha) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_SESSION['user_id'],
                'logout',
                'Usuario cerró sesión',
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Ignorar errores de log
            error_log("Error al registrar cierre de sesión: " . $e->getMessage());
        }
    }

    // Limpiar todas las variables de sesión
    $_SESSION = array();

    // Eliminar cookie de sesión
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

    // Destruir sesión
    session_destroy();
}

/**
 * Verificar si el usuario tiene acceso a un recurso específico
 * @param string $recurso Nombre del recurso
 * @return bool True si tiene acceso
 */
function verificarAccesoRecurso($recurso)
{
    if (!isset($_SESSION['user_data']['nivel'])) {
        return false;
    }

    // Definir permisos por rol
    $permisos = [
        'admin' => ['dashboard', 'usuarios', 'configuracion', 'reportes', 'backup'],
        'editor' => ['dashboard', 'contenido', 'publicaciones', 'estadisticas'],
        'user' => ['dashboard', 'mis_rotafolios', 'perfil', 'configuracion_personal']
    ];

    $nivel = $_SESSION['user_data']['nivel'];
    $rol = '';

    // Determinar rol basado en nivel
    if ($nivel == 1) $rol = 'admin';
    elseif ($nivel == 2) $rol = 'editor';
    else $rol = 'user';

    return in_array($recurso, $permisos[$rol]);
}

/**
 * Generar token CSRF
 * @return string Token CSRF
 */
function generarTokenCSRF()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 * @param string $token Token a validar
 * @return bool True si el token es válido
 */
function validarTokenCSRF($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Obtener avatar del usuario (iniciales)
 * @return string Iniciales del usuario
 */
function obtenerAvatarUsuario()
{
    if (isset($_SESSION['user_data']['nombre']) && isset($_SESSION['user_data']['apellido'])) {
        return strtoupper(substr($_SESSION['user_data']['nombre'], 0, 1) .
            substr($_SESSION['user_data']['apellido'], 0, 1));
    }
    return 'US';
}

/**
 * Verificar si el usuario necesita verificar su correo
 * @return bool True si necesita verificar correo
 */
function necesitaVerificarCorreo()
{
    return (isset($_SESSION['user_data']['correo_verificado']) &&
        $_SESSION['user_data']['correo_verificado'] != 'si');
}

/**
 * Redirigir a verificación de correo si es necesario
 */
function redirigirSiCorreoNoVerificado()
{
    if (necesitaVerificarCorreo()) {
        header("Location: ../auth/verificar_correo.php");
        exit();
    }
}

/**
 * Obtener límites del plan del usuario
 * @param PDO $pdo Conexión a la base de datos
 * @return array Límites del plan
 */
function obtenerLimitesPlan($pdo)
{
    $plan = obtenerUsuarioPlan();
    $limites = [];

    // Definir límites por plan
    switch ($plan) {
        case 'premium':
            $limites = [
                'max_rotafolios' => 50,
                'max_espacio_mb' => 5120,
                'max_posts' => 1000,
                'puede_crear_plantillas' => true,
                'puede_colaborar' => true,
                'soporte_prioritario' => true
            ];
            break;
        case 'pro':
            $limites = [
                'max_rotafolios' => 20,
                'max_espacio_mb' => 500,
                'max_posts' => 500,
                'puede_crear_plantillas' => true,
                'puede_colaborar' => true,
                'soporte_prioritario' => false
            ];
            break;
        case 'basic':
            $limites = [
                'max_rotafolios' => 10,
                'max_espacio_mb' => 250,
                'max_posts' => 250,
                'puede_crear_plantillas' => false,
                'puede_colaborar' => false,
                'soporte_prioritario' => false
            ];
            break;
        default: // free
            $limites = [
                'max_rotafolios' => 3,
                'max_espacio_mb' => 100,
                'max_posts' => 100,
                'puede_crear_plantillas' => false,
                'puede_colaborar' => false,
                'soporte_prioritario' => false
            ];
    }

    return $limites;
}
