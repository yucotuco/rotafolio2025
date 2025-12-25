<?php
// ==================== SECCIÓN 1: INICIALIZACIÓN Y CONFIGURACIÓN ====================
// ver_rotafolio.php (Mejoras de usabilidad y diseño) - VERSIÓN ACTUALIZADA CON TODAS LAS MEJORAS
session_start();

// RUTAS Y DEPENDENCIAS
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/conn/connrota.php';
require_once $base_path . '/funcionesyClases/claseRotafolio.php';
require_once $base_path . '/funcionesyClases/claseRotafolioUpdate.php';

// CONSTANTES
if (!defined('SITIO_NOMBRE')) define('SITIO_NOMBRE', 'Rotafolio');
if (!defined('SITIO_URL')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITIO_URL', $proto . '://' . $host);
}
if (!defined('DASHBOARD_URL')) define('DASHBOARD_URL', SITIO_URL . '/dashboard/');
if (!defined('COLOR_PRINCIPAL')) define('COLOR_PRINCIPAL', '#0dcaf0');

$sitio_nombre    = SITIO_NOMBRE;
$sitio_url       = SITIO_URL;
$dashboard_url   = DASHBOARD_URL;
$color_principal = COLOR_PRINCIPAL;

// DIRECTORIOS
$directorios = [
    $base_path . '/uploads/imagenes_publicas/',
    $base_path . '/uploads/archivos_publicos/',
    $base_path . '/uploads/imagenes/',
    $base_path . '/uploads/archivos/',
    $base_path . '/uploads/fondos/',
    $base_path . '/uploads/post_headers/',
];
foreach ($directorios as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// MANEJADORES DE ERRORES
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log("Error PHP: $message en $file línea $line");
    return true;
});

// ==================== SECCIÓN 2: MANAGERS Y PARÁMETROS ====================
// MANAGERS
$rotaManager = new RotafolioManager($pdoRota);
$rotaUpdateManager = new RotafolioUpdateManager($pdoRota);

// PARAMETROS
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ==================== SECCIÓN 3: SESIÓN Y PERMISOS ====================
// SESIÓN Y PERMISOS
$usuario_logueado = false;
$usuario_id = 0;
$usuario_nombre = '';
$usuario_email = '';
$usuario_avatar = '';

if (isset($_SESSION['user_id'])) {
    $usuario_logueado = true;
    $usuario_id = (int)$_SESSION['user_id'];

    try {
        $stmt = $pdoRota->prepare("SELECT nombre, apellido, correo FROM usuario WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario_data) {
            $usuario_nombre = $usuario_data['nombre'] . ' ' . $usuario_data['apellido'];
            $usuario_email = $usuario_data['correo'];
            // Generar avatar con iniciales
            $inicial = strtoupper(substr($usuario_data['nombre'], 0, 1));
            $inicial2 = strtoupper(substr($usuario_data['apellido'], 0, 1));
            $usuario_avatar = $inicial . $inicial2;
        }
    } catch (PDOException $e) {
        error_log("Error al obtener datos del usuario: " . $e->getMessage());
    }
}

$es_propietario = false;
if ($usuario_logueado && $id > 0) {
    $es_propietario = $rotaManager->esPropietarioRotafolio($id, $usuario_id);
}

// ==================== SECCIÓN 4: CONTENIDO ROTAFOLIO ====================
// CONTENIDO ROTAFOLIO
// CONTENIDO ROTAFOLIO  Modificado
$contenido              = null;
$error_tipo             = '';
$error_mensaje          = '';
$permite_posts_publicos = false;
$color_fondo            = '#f8f9fa';
$imagen_fondo           = '';

// AÑADIR NUEVAS VARIABLES PARA CONTROL DE ACCESO
$acceso_permitido = true; // Por defecto permitido
$solo_usuarios_registrados = false; // Nueva variable para control de acceso
$modo_solo_lectura = false; // NUEVA VARIABLE PARA MODO SOLO LECTURA

try {
    if ($id > 0) {
        $stmt = $pdoRota->prepare("SELECT * FROM rotafolios WHERE id = ?");
        $stmt->execute([$id]);
        $contenido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contenido) {
            $error_tipo    = 'id_no_existe';
            $error_mensaje = "El ID #{$id} no existe en nuestro sistema.";
        } elseif (!$contenido['es_publico'] && !$es_propietario) {
            $error_tipo    = 'acceso_privado';
            $error_mensaje = "Este rotafolio es privado y solo puede ser visto por el propietario.";
            $contenido     = null;
        } else {
            $permite_posts_publicos = (bool)($contenido['permite_posts_publicos'] ?? false);
            $color_fondo  = $contenido['color_fondo'] ?? '#f8f9fa';
            $imagen_fondo = $contenido['imagen_fondo'] ?? '';

            // AÑADIR: Verificar si solo permite usuarios registrados
            $solo_usuarios_registrados = (bool)($contenido['solo_usuarios_registrados'] ?? false);

            // AÑADIR: Validar acceso según el modo (MEJORADO)
            if ($solo_usuarios_registrados && !$usuario_logueado && !$es_propietario) {
                // CAMBIO IMPORTANTE: Permitir acceso SOLO LECTURA
                $acceso_permitido = true; // Cambiado de false a true
                $modo_solo_lectura = true; // Activar modo solo lectura
                $error_tipo = 'solo_usuarios_registrados';
                $error_mensaje = "Este rotafolio es de acceso exclusivo para usuarios registrados. Puedes ver los posts, pero para interactuar necesitas registrarte o iniciar sesión.";
            }
        }
    } else {
        $error_tipo    = 'sin_parametros';
        $error_mensaje = "Debes acceder mediante un enlace válido con el ID del rotafolio.";
    }
} catch (PDOException $e) {
    $error_tipo    = 'error_bd';
    $error_mensaje = "Error del sistema. Por favor, inténtalo más tarde.";
    error_log("Error en ver_rotafolio.php: " . $e->getMessage());
}

// ==================== SECCIÓN 5: IDENTIFICACIÓN VISITANTE Y MENSAJES ====================
// IDENTIFICAR VISITANTE
$visitante_id = $_COOKIE['rotafolio_visitor'] ?? null;
if (!$visitante_id) {
    $visitante_id = 'visitor_' . uniqid() . '_' . time();
    setcookie('rotafolio_visitor', $visitante_id, time() + (86400 * 30), "/");
}

// MANEJAR MENSAJES DE REDIRECCIÓN
if (isset($_GET['mensaje']) && isset($_GET['tipo'])) {
    $mensaje_redir = urldecode($_GET['mensaje']);
    $tipo_redir = $_GET['tipo'];

    if ($tipo_redir == 'success') {
        $mensaje_exito = $mensaje_redir;
    } else {
        $error_agregar = $mensaje_redir;
    }
}

// NO-AJAX (propietario)
$mensaje_exito = $mensaje_exito ?? '';
$error_agregar = $error_agregar ?? '';

// ==================== SECCIÓN 6: MANEJO POST NO-AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contenido && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // Verificar si está en modo solo lectura
    if ($modo_solo_lectura) {
        $error_agregar = "No tienes permiso para publicar posts. Debes registrarte o iniciar sesión.";
        // No permitir continuar con el procesamiento
    } else {
        // ACTUALIZAR INFO (propietario)
        if (isset($_POST['actualizar_info']) && $es_propietario) {
            $datos_actualizar = [
                'titulo'                 => trim($_POST['titulo'] ?? ''),
                'descripcion'            => trim($_POST['descripcion'] ?? ''),
                'layout'                 => 'muro',
                'color_fondo'            => $_POST['color_fondo'] ?? '#0dcaf0',
                'es_publico'             => isset($_POST['es_publico']) ? 1 : 0,
                'permite_posts_publicos' => isset($_POST['permite_posts_publicos']) ? 1 : 0,
                // AÑADIR NUEVO CAMPO
                'solo_usuarios_registrados' => isset($_POST['solo_usuarios_registrados']) ? 1 : 0,
            ];

            if ($datos_actualizar['titulo'] === '') {
                $error_agregar = "El título es requerido";
            } else {
                if (isset($_FILES['imagen_fondo']) && $_FILES['imagen_fondo']['error'] === UPLOAD_ERR_OK) {
                    $resultado = $rotaManager->procesarSubidaArchivo($_FILES['imagen_fondo'], 'imagen', false);
                    if ($resultado[0]) {
                        $datos_actualizar['imagen_fondo'] = $resultado[1];
                    } else {
                        $error_agregar = $resultado[1];
                    }
                }
                if (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] === '1') {
                    $datos_actualizar['imagen_fondo'] = null;
                }

                if (empty($error_agregar)) {
                    $ok = $rotaManager->actualizarRotafolio($id, $datos_actualizar);
                    if ($ok) {
                        foreach ($datos_actualizar as $k => $v) $contenido[$k] = $v;
                        $color_fondo            = $contenido['color_fondo'];
                        $imagen_fondo           = $contenido['imagen_fondo'] ?? '';
                        $permite_posts_publicos = (bool)$contenido['permite_posts_publicos'];

                        header("Location: ver_rotafolio.php?id={$id}&success=1");
                        exit;
                    } else {
                        $error_agregar = "Error al actualizar la información: " . $rotaManager->getLastError();
                    }
                }
            }
        }

        // AGREGAR POST (propietario, no-AJAX)
        if (isset($_POST['agregar_post_editor']) && $es_propietario) {
            $contenido_post = trim($_POST['contenido_post_editor'] ?? '');
            $color         = $_POST['color_post_editor'] ?? '#ffffff';

            if ($contenido_post === '') {
                $error_agregar = "El contenido es requerido";
            }

            if (empty($error_agregar)) {
                $url_imagen_header = null;
                $url_archivo_adjunto = null;

                if (isset($_FILES['imagen_header']) && $_FILES['imagen_header']['error'] === UPLOAD_ERR_OK) {
                    $resultado = $rotaManager->procesarSubidaArchivo($_FILES['imagen_header'], 'imagen', false);
                    if ($resultado[0]) {
                        $url_imagen_header = $resultado[1];
                    } else {
                        $error_agregar = $resultado[1];
                    }
                }

                if (empty($error_agregar) && isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
                    $resultado = $rotaManager->procesarSubidaArchivo($_FILES['archivo_adjunto'], 'documento', false);
                    if ($resultado[0]) {
                        $url_archivo_adjunto = $resultado[1];
                    } else {
                        $error_agregar = $resultado[1];
                    }
                }

                if (empty($error_agregar)) {
                    $nombre_mostrar = $usuario_nombre ?: ($es_propietario ? 'Propietario' : 'Usuario');
                    $avatar_mostrar = $usuario_avatar ?: substr($nombre_mostrar, 0, 2);

                    $meta = json_encode([
                        'v' => 'p_' . $usuario_id,
                        'n' => $nombre_mostrar,
                        'p' => $es_propietario ? 1 : 0,
                        'u' => 1, // Usuario registrado
                        't' => time(),
                        'img_header' => $url_imagen_header,
                        'archivo_adjunto' => $url_archivo_adjunto,
                        'avatar' => $avatar_mostrar
                    ]);

                    try {
                        $contenido_completo = $meta . "\n\n" . $contenido_post;

                        // CORRECCIÓN: Solo el propietario aplica color
                        $ok = $rotaManager->crearPost(
                            $id,
                            $contenido_completo,
                            0,
                            0,
                            'medio',
                            $color, // Solo propietario tiene color
                            $url_imagen_header,
                            $url_archivo_adjunto
                        );

                        if ($ok) {
                            header("Location: ver_rotafolio.php?id={$id}&success=1");
                            exit;
                        } else {
                            $error_agregar = "Error al agregar el post: " . $rotaManager->getLastError();
                        }
                    } catch (Exception $e) {
                        $error_agregar = "Error de base de datos: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// ==================== SECCIÓN 7: OBTENER POSTS ====================
$posts    = [];
$mis_posts = [];

if ($contenido && $acceso_permitido) {  // AÑADIR CONDICIÓN
    try {
        $stmt = $pdoRota->prepare("SELECT * FROM posts WHERE rotafolio_id = ? ORDER BY fecha_creacion DESC");
        $stmt->execute([$id]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $lines = explode("\n\n", $post['contenido'], 2);
            if (count($lines) >= 2) {
                $metadata                 = json_decode($lines[0], true) ?: [];
                $post['contenido_limpio'] = $lines[1];
                $post['metadata']         = $metadata;
                $post_visitor             = $metadata['v'] ?? '';
                $post['es_mio']           = $es_propietario || ($post_visitor === $visitante_id);
                $post['es_propietario']   = (int)($metadata['p'] ?? 0) === 1;
                $post['editado']          = isset($metadata['e']);
                if ($post['es_mio'] && !$es_propietario) $mis_posts[] = $post['id'];
                $post['nombre_display']   = $metadata['n'] ?? 'Anónimo';
                $post['avatar']           = $metadata['avatar'] ?? '';

                // Si no hay avatar, crear uno basado en el nombre
                if (empty($post['avatar']) && !empty($post['nombre_display'])) {
                    $nombres = explode(' ', $post['nombre_display']);
                    $post['avatar'] = strtoupper(
                        substr($nombres[0], 0, 1) .
                            (isset($nombres[1]) ? substr($nombres[1], 0, 1) : substr($nombres[0], 1, 1))
                    );
                }

                // Determinar cómo mostrar el nombre del autor
                // Mantener el nombre original del metadata
                $post['nombre_display'] = $metadata['n'] ?? 'Anónimo';

                // Solo sobrescribir si es el usuario actual
                if (isset($metadata['v'])) {
                    if ($metadata['v'] === 'p_' . $usuario_id && $usuario_id > 0) {
                        $post['nombre_display'] = $es_propietario ? 'Tú (Propietario)' : 'Tú';
                    } elseif ($metadata['v'] === $visitante_id) {
                        $post['nombre_display'] = 'Tú';
                    }
                }
                $post['imagen_header'] = $metadata['img_header'] ?? $post['imagen_header'] ?? null;
                $post['archivo_adjunto'] = $metadata['archivo_adjunto'] ?? $post['archivo_adjunto'] ?? $post['url_archivo'] ?? null;
            } else {
                $post['contenido_limpio'] = $post['contenido'];
                $post['metadata']         = [];
                $post['es_mio']           = false;
                $post['es_propietario']   = false;
                $post['editado']          = false;
                $post['nombre_display']   = 'Anónimo';
                $post['avatar']           = '?';
                $post['imagen_header'] = $post['imagen_header'] ?? null;
                $post['archivo_adjunto'] = $post['archivo_adjunto'] ?? $post['url_archivo'] ?? null;
            }

            if (!empty($post['archivo_adjunto'])) {
                $post['archivo_adjunto'] = (strpos($post['archivo_adjunto'], 'uploads/') === 0)
                    ? '../' . $post['archivo_adjunto']
                    : $post['archivo_adjunto'];
            }

            if (!empty($post['imagen_header'])) {
                $post['imagen_header'] = (strpos($post['imagen_header'], 'uploads/') === 0)
                    ? '../' . $post['imagen_header']
                    : $post['imagen_header'];
            }
        }

        if (!empty($mis_posts)) {
            $_SESSION['posts_creados'] = array_unique(array_merge($_SESSION['posts_creados'] ?? [], $mis_posts));
        }
    } catch (PDOException $e) {
        error_log("Error al obtener posts: " . $e->getMessage());
    }
}

// ==================== SECCIÓN 8: VISTAS Y URL COMPARTIR ====================
// VISTAS
if ($contenido && $id > 0 && !$es_propietario) {
    try {
        @$pdoRota->prepare("UPDATE rotafolios SET vistas = COALESCE(vistas, 0) + 1 WHERE id = ?")->execute([$id]);
    } catch (PDOException $e) { /* ignorar */
    }
}

// URL COMPARTIR
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/dashboard/ver_rotafolio.php'), '/');
$url_compartir_absoluta = "{$protocolo}://{$host}{$base_dir}/ver_rotafolio.php?id={$id}";

// MENSAJE EXITO
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $mensaje_exito = "¡Operación completada con éxito!";
}

// ==================== SECCIÓN 9: MANEJADORES AJAX ====================
// ==================== SECCIÓN 9: MANEJADORES AJAX ====================
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && $contenido) {
    // MODIFICAR LA VALIDACIÓN PARA MODO SOLO LECTURA
    if ($solo_usuarios_registrados && !$usuario_logueado && !$es_propietario) {
        // Si es modo solo lectura, permitir algunas acciones pero no crear posts
        if (isset($_POST['agregar_post_publico']) || isset($_POST['agregar_post_editor'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Debes registrarte o iniciar sesión para publicar posts en este rotafolio.'
            ]);
            exit;
        }
        // Permitir otras acciones AJAX (como obtener datos)
    }

    // AGREGAR POST
    if ((isset($_POST['agregar_post_publico']) || isset($_POST['agregar_post_editor'])) && ($permite_posts_publicos || $es_propietario)) {
        $response    = ['success' => false, 'message' => '', 'html' => '', 'post_id' => 0];

        $es_editor   = isset($_POST['agregar_post_editor']);
        $contenido_p = trim($es_editor ? ($_POST['contenido_post_editor'] ?? '') : ($_POST['contenido'] ?? ''));
        $color       = $es_editor ? ($_POST['color_post_editor'] ?? '#ffffff') : ($_POST['color'] ?? '#ffffff');

        // Validación: contenido es requerido
        if ($contenido_p === '') {
            $response['message'] = "El contenido es requerido";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Procesar imagen de encabezado
        $url_imagen_header = null;
        $imagen_header_key = $es_editor ? 'imagen_header' : 'imagen_header_publico';

        if (isset($_FILES[$imagen_header_key]) && $_FILES[$imagen_header_key]['error'] === UPLOAD_ERR_OK) {
            $resultado = $rotaManager->procesarSubidaArchivo($_FILES[$imagen_header_key], 'imagen', !$es_propietario);
            if ($resultado[0]) {
                $url_imagen_header = $resultado[1];
            } else {
                $response['message'] = $resultado[1];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }

        // Procesar archivo adjunto
        $url_archivo_adjunto = null;
        $archivo_key = $es_editor ? 'archivo_adjunto' : 'archivo_adjunto_publico';

        if (isset($_FILES[$archivo_key]) && $_FILES[$archivo_key]['error'] === UPLOAD_ERR_OK) {
            $resultado = $rotaManager->procesarSubidaArchivo($_FILES[$archivo_key], 'documento', !$es_propietario);
            if ($resultado[0]) {
                $url_archivo_adjunto = $resultado[1];
            } else {
                $response['message'] = $resultado[1];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }

        // nombre del autor
        if ($es_propietario) {
            $nombre_autor = $usuario_nombre ?: 'Propietario';
            $avatar = $usuario_avatar ?: substr($nombre_autor, 0, 2);
        } elseif ($usuario_logueado) {
            // Usuario registrado (no propietario del rotafolio)
            $nombre_autor = $usuario_nombre ?: 'Usuario';
            $avatar = $usuario_avatar ?: substr($nombre_autor, 0, 2);
        } else {
            // Visitante (no registrado) - SOLO SI ESTÁ PERMITIDO
            if (!$permite_posts_publicos) {
                $response['message'] = "No tienes permiso para publicar posts en este rotafolio";
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }

            $nombre_autor = trim($_POST['nombre_visitante'] ?? '');
            $avatar = substr($nombre_autor, 0, 2);

            // Si no ingresó nombre, usar "Anónimo"
            if (empty($nombre_autor)) {
                $nombre_autor = 'Anónimo';
                $avatar = 'A?';
            }
        }

        $meta = json_encode([
            'v' => ($es_propietario || $usuario_logueado) ? ('p_' . $usuario_id) : $visitante_id,
            'n' => $nombre_autor,
            'p' => $es_propietario ? 1 : 0,
            'u' => $usuario_logueado ? 1 : 0, // Flag para usuario registrado
            't' => time(),
            'img_header' => $url_imagen_header,
            'archivo_adjunto' => $url_archivo_adjunto,
            'avatar' => $avatar
        ]);

        try {
            $contenido_completo = $meta . "\n\n" . $contenido_p;

            if ($es_propietario) {
                $resultado = $rotaManager->crearPost(
                    $id,
                    $contenido_completo,
                    0,
                    0,
                    'medio',
                    $color,
                    $url_imagen_header,
                    $url_archivo_adjunto
                );
            } else {
                $resultado = $rotaManager->crearPostPublico(
                    $id,
                    $contenido_completo,
                    0,
                    0,
                    $color, // CORRECCIÓN: Ahora se pasa el color para invitados
                    $url_imagen_header,
                    $url_archivo_adjunto
                );
            }

            if ($resultado) {
                $post_id = $resultado;
                // obtener y renderizar
                $stmt = $pdoRota->prepare("SELECT * FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($p) {
                    // normalizar
                    $lines = explode("\n\n", $p['contenido'], 2);
                    if (count($lines) >= 2) {
                        $m = json_decode($lines[0], true) ?: [];
                        $p['contenido_limpio'] = $lines[1];
                        $p['metadata']         = $m;
                        $p['imagen_header'] = $m['img_header'] ?? null;
                        $p['archivo_adjunto'] = $m['archivo_adjunto'] ?? null;
                        $p['avatar'] = $m['avatar'] ?? substr($m['n'] ?? 'A', 0, 2);
                    } else {
                        $p['contenido_limpio'] = '';
                        $p['metadata']         = [];
                        $p['imagen_header'] = null;
                        $p['archivo_adjunto'] = null;
                        $p['avatar'] = '?';
                    }

                    // Ajustar rutas
                    if (!empty($p['archivo_adjunto'])) {
                        $p['archivo_adjunto'] = (strpos($p['archivo_adjunto'], 'uploads/') === 0)
                            ? '../' . $p['archivo_adjunto']
                            : $p['archivo_adjunto'];
                    }
                    if (!empty($p['imagen_header'])) {
                        $p['imagen_header'] = (strpos($p['imagen_header'], 'uploads/') === 0)
                            ? '../' . $p['imagen_header']
                            : $p['imagen_header'];
                    }

                    $response['html']     = renderPostCard($p, $base_path);
                    $response['success']  = true;
                    $response['message']  = "¡Post agregado!";
                    $response['post_id']  = $post_id;
                }
            } else {
                $response['message'] = "Error al agregar el post: " . $rotaManager->getLastError();
            }
        } catch (Exception $e) {
            $response['message'] = "Error de base de datos: " . $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // ====== OBTENER DATOS DE POST PARA EDICIÓN ======
    if (isset($_POST['obtener_datos_edicion'])) {
        $response = ['success' => false, 'message' => '', 'data' => []];
        $post_id = (int)($_POST['post_id'] ?? 0);

        if ($post_id) {
            // Obtener datos del post para edición usando el nuevo manager
            $datos_post = $rotaUpdateManager->obtenerDatosEdicion($post_id);

            if ($datos_post[0]) { // success es true
                $response['success'] = true;
                $response['data'] = $datos_post[1];
            } else {
                $response['message'] = $datos_post[1]; // mensaje de error
            }
        } else {
            $response['message'] = "ID de post no válido";
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // ====== ACTUALIZAR POST (EDITAR) ======
    if (isset($_POST['actualizar_post'])) {
        $response = ['success' => false, 'message' => ''];
        $post_id = (int)($_POST['post_id'] ?? 0);
        $contenido_p = trim($_POST['contenido'] ?? '');
        $color = $_POST['color'] ?? '#ffffff';

        if (!$post_id) {
            $response['message'] = "ID de post no válido";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        if (empty($contenido_p)) {
            $response['message'] = "El contenido es requerido";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Obtener post para verificar permisos
        $stmt = $pdoRota->prepare("SELECT contenido, rotafolio_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            $response['message'] = "Post no encontrado";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $rotafolio_id_post = $post['rotafolio_id'];

        // Verificar permisos
        $permiso_concedido = false;
        $lines = explode("\n\n", $post['contenido'], 2);
        $metadata = [];

        if (count($lines) >= 2) {
            $metadata = json_decode($lines[0], true) ?: [];
        }

        if ($es_propietario) {
            // Propietario siempre tiene permiso
            $permiso_concedido = true;
        } else {
            // Verificar si el usuario es el creador del post
            if (isset($metadata['v'])) {
                if ($usuario_logueado) {
                    $permiso_concedido = ($metadata['v'] === 'p_' . $usuario_id);
                } else {
                    $permiso_concedido = ($metadata['v'] === $visitante_id);
                }
            }

            // También verificar si está en mis_posts
            if (!$permiso_concedido && in_array($post_id, $mis_posts)) {
                $permiso_concedido = true;
            }
        }

        if (!$permiso_concedido) {
            $response['message'] = "No tienes permiso para editar este post";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        try {
            // Actualizar metadata con timestamp de edición
            $metadata['e'] = time(); // Marcar como editado

            // Reconstruir contenido con metadata actualizada
            $nuevo_contenido = json_encode($metadata) . "\n\n" . $contenido_p;

            // Procesar nuevos archivos
            $url_imagen_header = $metadata['img_header'] ?? null;
            $url_archivo_adjunto = $metadata['archivo_adjunto'] ?? null;

            // Si se sube nueva imagen
            if (isset($_FILES['imagen_header_edit']) && $_FILES['imagen_header_edit']['error'] === UPLOAD_ERR_OK) {
                $resultado = $rotaManager->procesarSubidaArchivo($_FILES['imagen_header_edit'], 'imagen', !$es_propietario);
                if ($resultado[0]) {
                    $url_imagen_header = $resultado[1];
                } else {
                    $response['message'] = $resultado[1];
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
            }

            // Si se sube nuevo archivo
            if (isset($_FILES['archivo_adjunto_edit']) && $_FILES['archivo_adjunto_edit']['error'] === UPLOAD_ERR_OK) {
                $resultado = $rotaManager->procesarSubidaArchivo($_FILES['archivo_adjunto_edit'], 'documento', !$es_propietario);
                if ($resultado[0]) {
                    $url_archivo_adjunto = $resultado[1];
                } else {
                    $response['message'] = $resultado[1];
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
            }

            // Actualizar metadata con URLs de archivos
            if ($url_imagen_header) $metadata['img_header'] = $url_imagen_header;
            if ($url_archivo_adjunto) $metadata['archivo_adjunto'] = $url_archivo_adjunto;

            // Reconstruir contenido final
            $nuevo_contenido = json_encode($metadata) . "\n\n" . $contenido_p;

            // Actualizar en base de datos
            $stmt = $pdoRota->prepare("UPDATE posts SET contenido = ?, color = ?, imagen_header = ?, archivo_adjunto = ? WHERE id = ?");
            $success = $stmt->execute([
                $nuevo_contenido,
                $color,
                $url_imagen_header,
                $url_archivo_adjunto,
                $post_id
            ]);


            if ($success) {
                $response['success'] = true;
                $response['message'] = "Post actualizado correctamente";

                // Si es necesario, obtener el post actualizado para renderizar
                $stmt = $pdoRota->prepare("SELECT * FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($p) {
                    $lines = explode("\n\n", $p['contenido'], 2);
                    if (count($lines) >= 2) {
                        $m = json_decode($lines[0], true) ?: [];
                        $p['contenido_limpio'] = $lines[1];
                        $p['metadata'] = $m;
                        $p['imagen_header'] = $m['img_header'] ?? null;
                        $p['archivo_adjunto'] = $m['archivo_adjunto'] ?? null;
                        $p['avatar'] = $m['avatar'] ?? substr($m['n'] ?? 'A', 0, 2);
                    }

                    // Ajustar rutas
                    if (!empty($p['archivo_adjunto'])) {
                        $p['archivo_adjunto'] = (strpos($p['archivo_adjunto'], 'uploads/') === 0)
                            ? '../' . $p['archivo_adjunto']
                            : $p['archivo_adjunto'];
                    }
                    if (!empty($p['imagen_header'])) {
                        $p['imagen_header'] = (strpos($p['imagen_header'], 'uploads/') === 0)
                            ? '../' . $p['imagen_header']
                            : $p['imagen_header'];
                    }

                    $response['html'] = renderPostCard($p, $base_path);
                }
            } else {
                $response['message'] = "Error al actualizar el post en la base de datos";
            }
        } catch (Exception $e) {
            $response['message'] = "Error de base de datos: " . $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // ELIMINAR POST
    if (isset($_POST['eliminar_post'])) {
        $response = ['success' => false, 'message' => ''];
        $post_id = (int)($_POST['post_id'] ?? 0);

        if ($post_id) {
            // Obtener rotafolio_id para verificar permisos
            $stmt = $pdoRota->prepare("SELECT contenido, rotafolio_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($post) {
                $rotafolio_id = $post['rotafolio_id'];

                // Verificar permisos - CORREGIDO
                $permiso_concedido = false;

                if ($es_propietario) {
                    // Propietario siempre tiene permiso
                    $permiso_concedido = true;
                } else {
                    // Extraer metadata para verificar creador
                    $lines = explode("\n\n", $post['contenido'], 2);
                    $metadata = [];

                    if (count($lines) >= 2) {
                        $metadata = json_decode($lines[0], true) ?: [];
                    }

                    // Verificar si el usuario es el creador del post
                    if ($usuario_logueado && isset($metadata['v'])) {
                        // Usuario registrado: verificar si es su post
                        $permiso_concedido = ($metadata['v'] === 'p_' . $usuario_id);
                    } elseif (!$usuario_logueado && isset($metadata['v'])) {
                        // Invitado: verificar si es su post
                        $permiso_concedido = ($metadata['v'] === $visitante_id);
                    }

                    // También verificar si está en mis_posts (para invitados)
                    if (!$permiso_concedido && in_array($post_id, $mis_posts)) {
                        $permiso_concedido = true;
                    }
                }

                if ($permiso_concedido) {
                    // Usar el método eliminarPost existente
                    $success = $rotaManager->eliminarPost($post_id, $rotafolio_id);

                    if ($success) {
                        $response['success'] = true;
                        $response['message'] = "Post eliminado correctamente";

                        // Actualizar contador de mis posts
                        if (!$es_propietario && isset($_SESSION['posts_creados'])) {
                            $_SESSION['posts_creados'] = array_diff($_SESSION['posts_creados'], [$post_id]);
                        }
                    } else {
                        $response['message'] = "Error al eliminar el post: " . $rotaManager->getLastError();
                    }
                } else {
                    $response['message'] = "No tienes permiso para eliminar este post";
                }
            } else {
                $response['message'] = "Post no encontrado";
            }
        } else {
            $response['message'] = "ID de post no válido";
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }


    // ====== ELIMINAR ARCHIVO ESPECÍFICO ======
    if (isset($_POST['eliminar_archivo'])) {
        $response = ['success' => false, 'message' => ''];
        $post_id = (int)($_POST['post_id'] ?? 0);
        $tipo_archivo = $_POST['tipo_archivo'] ?? '';

        if ($post_id && in_array($tipo_archivo, ['imagen_header', 'archivo_adjunto'])) {
            // Obtener post para verificar permisos
            $stmt = $pdoRota->prepare("SELECT contenido, rotafolio_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($post) {
                $rotafolio_id = $post['rotafolio_id'];

                // Verificar permisos - CORREGIDO
                $permiso_concedido = false;

                if ($es_propietario) {
                    // Propietario siempre tiene permiso
                    $permiso_concedido = true;
                } else {
                    // Extraer metadata para verificar creador
                    $lines = explode("\n\n", $post['contenido'], 2);
                    $metadata = [];

                    if (count($lines) >= 2) {
                        $metadata = json_decode($lines[0], true) ?: [];
                    }

                    // Verificar si el usuario es el creador del post
                    if ($usuario_logueado && isset($metadata['v'])) {
                        // Usuario registrado: verificar si es su post
                        $permiso_concedido = ($metadata['v'] === 'p_' . $usuario_id);
                    } elseif (!$usuario_logueado && isset($metadata['v'])) {
                        // Invitado: verificar si es su post
                        $permiso_concedido = ($metadata['v'] === $visitante_id);
                    }

                    // También verificar si está en mis_posts (para invitados)
                    if (!$permiso_concedido && in_array($post_id, $mis_posts)) {
                        $permiso_concedido = true;
                    }
                }

                if ($permiso_concedido) {
                    // Extraer metadata para actualizar
                    $lines = explode("\n\n", $post['contenido'], 2);
                    $metadata = [];
                    $contenido_limpio = '';

                    if (count($lines) >= 2) {
                        $metadata = json_decode($lines[0], true) ?: [];
                        $contenido_limpio = $lines[1];
                    } else {
                        $contenido_limpio = $post['contenido'];
                    }

                    // Eliminar archivo de metadata
                    if ($tipo_archivo === 'imagen_header') {
                        unset($metadata['img_header']);
                        // También eliminar de la base de datos
                        $stmt = $pdoRota->prepare("UPDATE posts SET imagen_header = NULL WHERE id = ?");
                        $stmt->execute([$post_id]);
                    } else {
                        unset($metadata['archivo_adjunto']);
                        // También eliminar de la base de datos
                        $stmt = $pdoRota->prepare("UPDATE posts SET archivo_adjunto = NULL, url_archivo = NULL WHERE id = ?");
                        $stmt->execute([$post_id]);
                    }

                    // Reconstruir contenido con metadata actualizada
                    $nuevo_contenido = json_encode($metadata) . "\n\n" . $contenido_limpio;

                    // Actualizar en base de datos
                    $stmt = $pdoRota->prepare("UPDATE posts SET contenido = ? WHERE id = ?");
                    if ($stmt->execute([$nuevo_contenido, $post_id])) {
                        $response['success'] = true;
                        $response['message'] = "Archivo eliminado correctamente";
                    } else {
                        $response['message'] = "Error al actualizar el post";
                    }
                } else {
                    $response['message'] = "No tienes permiso para eliminar este archivo";
                }
            } else {
                $response['message'] = "Post no encontrado";
            }
        } else {
            $response['message'] = "Parámetros inválidos";
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // NO RECONOCIDA
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Solicitud no reconocida']);
    exit;
}
// ==================== SECCIÓN 10: FUNCIONES DE RENDERIZADO ====================
// FUNCIONES DE RENDERIZADO
function getTipoIcon($tipo)
{
    return 'card-text';
}

// Función auxiliar para limitar palabras manteniendo HTML
function limitarPalabrasHTML($html, $limite = 200)
{
    // Primero, obtener el texto plano para contar palabras
    $texto_plano = strip_tags($html);
    $palabras = explode(' ', $texto_plano);

    if (count($palabras) <= $limite) {
        return $html;
    }

    // Para simplificar, vamos a usar una versión más simple
    // que mantenga el HTML básico pero corte en el límite de palabras

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);
    $textNodes = $xpath->query('//text()');

    $contador = 0;
    foreach ($textNodes as $node) {
        $nodeText = $node->nodeValue;
        $nodeWords = preg_split('/\s+/', $nodeText, -1, PREG_SPLIT_NO_EMPTY);
        $nodeWordCount = count($nodeWords);

        if ($contador + $nodeWordCount <= $limite) {
            $contador += $nodeWordCount;
        } else {
            $wordsToKeep = $limite - $contador;
            $newText = implode(' ', array_slice($nodeWords, 0, $wordsToKeep)) . '...';
            $node->nodeValue = $newText;

            // Eliminar todos los nodos de texto restantes
            $nextNode = $node->nextSibling;
            while ($nextNode) {
                $node->parentNode->removeChild($nextNode);
                $nextNode = $node->nextSibling;
            }

            break;
        }
    }

    // Regresar el HTML limitado
    $result = $dom->saveHTML();
    return $result ?: implode(' ', array_slice($palabras, 0, $limite)) . '...';
}

// Función para obtener nombre de autor formateado (MÁS SIMPLE)
function obtenerNombreAutorFormateado($metadata, $es_propietario_actual = false, $es_mio = false, $visitante_id_actual = '')
{
    global $usuario_id;

    $nombre_raw = $metadata['n'] ?? 'Anónimo';

    // 1. Verificar si es el usuario actual
    if (isset($metadata['v'])) {
        if ($metadata['v'] === 'p_' . $usuario_id && $usuario_id > 0) {
            return $es_propietario_actual ? 'Tú (Propietario)' : 'Tú';
        }

        // Para invitados, verificar si es el mismo visitante
        if ($visitante_id_actual && $metadata['v'] === $visitante_id_actual) {
            return 'Tú';
        }
    }

    // 2. Verificar si es propietario del rotafolio
    if (isset($metadata['p']) && $metadata['p'] == 1) {
        return $es_propietario_actual ? 'Tú (Propietario)' : 'Propietario';
    }

    // 3. Devolver el nombre tal cual (ya sea nombre personalizado o "Anónimo")
    return htmlspecialchars($nombre_raw);
}

function renderPostsGrid($posts, $base_path)
{
    // Usamos la clase masonry-container definida en el CSS
    $out  = '<div class="masonry-container" id="postsRow">';
    foreach ($posts as $post) {
        $out .= '<div class="masonry-item">'; // Wrapper para evitar partición
        $out .= renderPostCard($post, $base_path);
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
}



function tiempoRelativo($timestamp)
{
    $diferencia = time() - $timestamp;

    if ($diferencia < 60) return 'Hace un momento';
    if ($diferencia < 120) return 'Hace 1 minuto';
    if ($diferencia < 3600) return 'Hace ' . floor($diferencia / 60) . ' minutos';
    if ($diferencia < 7200) return 'Hace 1 hora';
    if ($diferencia < 86400) return 'Hace ' . floor($diferencia / 3600) . ' horas';
    if ($diferencia < 172800) return 'Ayer';
    if ($diferencia < 2592000) return 'Hace ' . floor($diferencia / 86400) . ' días';
    if ($diferencia < 5184000) return 'Hace 1 mes';
    if ($diferencia < 31536000) return 'Hace ' . floor($diferencia / 2592000) . ' meses';

    return date('d/m/Y', $timestamp);
}

function getColorAvatar($nombre)
{
    // Colores consistentes para avatares
    $colores = ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#0dcaf0', '#6f42c1', '#20c997', '#fd7e14'];
    $hash = crc32($nombre);
    return $colores[abs($hash) % count($colores)];
}

function renderPostCard($post, $base_path)
{
    // Agregar estas variables globales
    global $es_propietario, $usuario_logueado, $usuario_id, $visitante_id, $modo_solo_lectura;

    $postId     = (int)$post['id'];
    $color      = $post['color'] ?? '#ffffff';
    $contenido  = $post['contenido_limpio'] ?? $post['contenido'] ?? '';
    $imagen_header = $post['imagen_header'] ?? null;
    $archivo_adjunto = $post['archivo_adjunto'] ?? null;

    // Obtener nombre formateado
    global $es_propietario, $usuario_id, $visitante_id;
    $nombre_autor = obtenerNombreAutorFormateado(
        $post['metadata'] ?? [],
        ($post['es_propietario'] ?? false) && $es_propietario,
        $post['es_mio'] ?? false,
        $visitante_id
    );
    $avatar = $post['avatar'] ?? '?';


    // 1. OBTENER Y FORMATEAR FECHA
    $timestamp = $post['metadata']['t'] ?? strtotime($post['fecha_creacion']);
    $fecha_formateada = date('d M Y, h:i A', $timestamp);
    $fecha_relativa = tiempoRelativo($timestamp);

    // Calcular palabras para ver si mostramos botón "ver más"
    $contenido_texto = strip_tags($contenido);
    $palabras = preg_split('/\s+/', $contenido_texto, -1, PREG_SPLIT_NO_EMPTY);
    $num_palabras = count($palabras);
    $contenido_limitado = $contenido;

    // Límite un poco más alto ya que ahora tenemos altura dinámica
    if ($num_palabras > 250) {
        $contenido_limitado = limitarPalabrasHTML($contenido, 250);
    }

    // Permisos - MODIFICADO PARA MODO SOLO LECTURA
    global $es_propietario, $usuario_logueado, $usuario_id, $visitante_id, $modo_solo_lectura;

    // MODIFICACIÓN PARA MODO SOLO LECTURA
    if ($modo_solo_lectura) {
        // En modo solo lectura, nadie puede editar/eliminar (excepto propietario)
        $puede_editar = false;
        $puede_eliminar = false;
    } else {
        // INICIO MODIFICACIÓN PUNTO 1
        // Usuarios registrados: pueden editar/eliminar solo sus propios posts
        // Invitados: solo pueden eliminar sus posts (no editar)
        // Propietario del rotafolio: puede editar/eliminar todos los posts

        $post_creado_por_usuario = false;
        if (isset($post['metadata']['v'])) {
            if ($usuario_logueado) {
                // Usuario registrado: verificar si el post tiene 'p_' + user_id
                // O si fue creado por el mismo visitante (cuando era invitado)
                $post_creado_por_usuario = ($post['metadata']['v'] === 'p_' . $usuario_id) ||
                    ($post['metadata']['v'] === $visitante_id);
            } else {
                // Invitado: verificar si el post tiene su visitante_id
                $post_creado_por_usuario = ($post['metadata']['v'] === $visitante_id);
            }
        }

        if ($es_propietario) {
            // Propietario del rotafolio puede hacer todo
            $puede_editar = true;
            $puede_eliminar = true;
        } elseif ($usuario_logueado) {
            // Usuario registrado (no propietario)
            // Puede editar y eliminar solo sus propios posts
            $puede_editar = $post_creado_por_usuario;
            $puede_eliminar = $post_creado_por_usuario;
        } else {
            // Usuario invitado
            $puede_editar = false; // Invitados NO pueden editar
            $puede_eliminar = $post_creado_por_usuario; // Solo eliminar sus posts
        }
        // FIN MODIFICACIÓN PUNTO 1
    }

    // Color de avatar consistente
    $color_avatar = getColorAvatar($nombre_autor);

    // --- INICIO HTML DE LA TARJETA ---
    $card  = '<div class="card" id="post-' . $postId . '" data-post-id="' . $postId . '" style="background-color:' . htmlspecialchars($color) . ';">';

    // A. IMAGEN DE ENCABEZADO - CON BOTÓN DE ELIMINAR INTEGRADO (PUNTO 2)
    if (!empty($imagen_header)) {
        $abs = $base_path . '/' . str_replace('../', '', $imagen_header);
        if (file_exists($abs)) {
            $card .= '<div class="position-relative">';
            $card .= '<img src="' . htmlspecialchars($imagen_header) . '" class="card-img-top post-header-image" alt="Imagen">';
            // Botón de eliminar integrado en la imagen
            if ($puede_editar) {
                $card .= '<button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 eliminar-archivo-btn" data-post-id="' . $postId . '" data-tipo="imagen_header" title="Eliminar imagen" style="z-index: 5;">';
                $card .= '<i class="bi bi-trash"></i>';
                $card .= '</button>';
            }
            $card .= '</div>';
        }
    }

    $card .= '<div class="card-body p-3">';

    // B. HEADER MEJORADO: Avatar + Nombre + Fecha ARRIBA
    $card .= '<div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom border-light border-opacity-50">';
    // Columna Izquierda: Avatar, Nombre y Fecha
    $card .= '<div class="d-flex align-items-center">';
    $card .= '<div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width:36px; height:36px; font-size:0.9rem; color:white; background-color:' . $color_avatar . ';">';
    $card .= htmlspecialchars($avatar);
    $card .= '</div>';
    $card .= '<div style="line-height: 1.2;">';
    $card .= '<div class="fw-bold small">' . htmlspecialchars($nombre_autor) . '</div>';
    // Fecha relativa con tooltip de fecha exacta
    $card .= '<div class="text-muted" style="font-size: 0.7rem;" title="' . $fecha_formateada . '">' . $fecha_relativa . '</div>';
    $card .= '</div>';
    $card .= '</div>';

    // Columna Derecha: Acciones (Editar/Eliminar) + Badge Editado
    $card .= '<div class="d-flex align-items-center gap-1">';
    if ($post['editado'] ?? false) {
        $card .= '<span class="badge bg-dark bg-opacity-10 text-dark me-1" style="font-size:0.6rem;" title="Editado el ' . date('d/m/Y H:i', $post['metadata']['e']) . '">Editado</span>';
    }
    if (!$modo_solo_lectura && ($puede_eliminar || $puede_editar)) {
        $card .= '<div class="dropdown">';
        $card .= '<button class="btn btn-sm btn-link text-secondary p-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>';
        $card .= '<ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">';
        if ($puede_editar) {
            $card .= '<li><a class="dropdown-item small editar-post-btn" href="#" data-post-id="' . $postId . '"><i class="bi bi-pencil me-2"></i>Editar</a></li>';
        }
        if ($puede_eliminar) {
            $card .= '<li><hr class="dropdown-divider"></li>';
            $card .= '<li><a class="dropdown-item small text-danger eliminar-post-btn" href="#" data-post-id="' . $postId . '"><i class="bi bi-trash me-2"></i>Eliminar</a></li>';
        }
        $card .= '</ul>';
        $card .= '</div>';
    }
    $card .= '</div>'; // Fin columna derecha
    $card .= '</div>'; // Fin Header

    // C. CONTENIDO (Altura dinámica automática)
    $card .= '<div class="post-content mb-3">';
    if ($num_palabras > 250) {
        $card .= '<div class="contenido-limitado">' . $contenido_limitado . '</div>';
        $card .= '<button class="btn btn-link btn-sm p-0 mt-1 text-decoration-none ver-mas-btn" data-post-id="' . $postId . '" data-completo="' . htmlspecialchars($contenido, ENT_QUOTES) . '">';
        $card .= 'Ver más...';
        $card .= '</button>';
    } else {
        $card .= $contenido;
    }
    $card .= '</div>';

    // D. ARCHIVO ADJUNTO (Si existe) - CON BOTÓN DE ELIMINAR
    if (!empty($archivo_adjunto)) {
        $abs = $base_path . '/' . str_replace('../', '', $archivo_adjunto);
        if (file_exists($abs)) {
            $filename = basename($archivo_adjunto);
            $filetype = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

            // Icono según tipo
            $icon = 'bi-file-earmark';
            if ($filetype === 'PDF') $icon = 'bi-file-pdf text-danger';
            if (in_array($filetype, ['DOC', 'DOCX'])) $icon = 'bi-file-word text-primary';
            if (in_array($filetype, ['XLS', 'XLSX'])) $icon = 'bi-file-excel text-success';

            $card .= '<div class="bg-white bg-opacity-50 rounded p-2 d-flex align-items-center border border-light">';
            $card .= '<i class="bi ' . $icon . ' fs-4 me-2"></i>';
            $card .= '<div class="flex-grow-1 text-truncate" style="font-size:0.85rem;">';
            $card .= '<div class="text-truncate fw-medium">' . htmlspecialchars($filename) . '</div>';
            $card .= '<div class="text-muted small" style="font-size:0.7rem;">' . $filetype . '</div>';
            $card .= '</div>';
            $card .= '<div class="btn-group">';
            $card .= '<a href="' . htmlspecialchars($archivo_adjunto) . '" class="btn btn-sm btn-light rounded-circle me-1" target="_blank" download><i class="bi bi-download"></i></a>';
            if ($puede_editar) {
                $card .= '<button class="btn btn-sm btn-outline-danger rounded-circle eliminar-archivo-btn" data-post-id="' . $postId . '" data-tipo="archivo_adjunto" title="Eliminar archivo"><i class="bi bi-trash"></i></button>';
            }
            $card .= '</div>';
            $card .= '</div>';
        }
    }

    $card .= '</div>'; // Cierre card-body
    $card .= '</div>'; // Cierre card

    return $card;
}
// Fin de renderPostCard

// ==================== FUNCIÓN PARA GENERAR DETALLES DEL ROTAFOLIO ====================
function generarDetallesRotafolio($contenido, $posts, $mis_posts, $permite_posts_publicos, $es_propietario)
{
    $html = '';

    // Estadísticas
    $html .= '<div class="d-flex flex-wrap gap-2 mb-3">';
    $html .= '<span class="stats-badge">';
    $html .= '<i class="bi bi-eye-fill text-primary"></i>';
    $html .= ($contenido['vistas'] ?? 0) . ' vistas';
    $html .= '</span>';

    $html .= '<span class="stats-badge">';
    $html .= '<i class="bi bi-sticky text-success"></i>';
    $html .= '<span id="totalPostsCount">' . count($posts) . '</span> posts';
    $html .= '</span>';

    if ($permite_posts_publicos && !$es_propietario) {
        $html .= '<span class="stats-badge bg-info bg-opacity-10 text-info border-info">';
        $html .= '<i class="bi bi-people-fill"></i>';
        $html .= '<span id="misPostsCount">' . count($mis_posts) . '</span> tus posts';
        $html .= '</span>';
    }

    if ($es_propietario) {
        $html .= '<span class="stats-badge bg-warning bg-opacity-10 text-warning border-warning">';
        $html .= '<i class="bi bi-star-fill"></i> Propietario';
        $html .= '</span>';

        if ($contenido['es_publico']) {
            $html .= '<span class="stats-badge bg-success bg-opacity-10 text-success border-success">';
            $html .= '<i class="bi bi-globe"></i> Público';
            $html .= '</span>';
        } else {
            $html .= '<span class="stats-badge bg-secondary bg-opacity-10 text-secondary border-secondary">';
            $html .= '<i class="bi bi-lock"></i> Privado';
            $html .= '</span>';
        }
    }
    $html .= '</div>';

    // Fecha de creación
    if (!empty($contenido['fecha_creacion'])) {
        $html .= '<div class="mb-2">';
        $html .= '<small class="text-muted">';
        $html .= '<i class="bi bi-calendar3 me-1"></i>';
        $html .= 'Creado: ' . date('d/m/Y', strtotime($contenido['fecha_creacion']));

        if (!empty($contenido['fecha_actualizacion']) && $contenido['fecha_actualizacion'] != $contenido['fecha_creacion']) {
            $html .= ' • Actualizado: ' . date('d/m/Y', strtotime($contenido['fecha_actualizacion']));
        }
        $html .= '</small>';
        $html .= '</div>';
    }

    // Información adicional (solo para propietario)
    if ($es_propietario) {
        $html .= '<div class="mt-3 pt-3 border-top border-light">';
        $html .= '<h6 class="small fw-bold mb-2">Información de administración:</h6>';
        $html .= '<div class="row g-2 small">';
        $html .= '<div class="col-6">';
        $html .= '<div class="text-muted">ID del rotafolio:</div>';
        $html .= '<div class="fw-medium">#' . ($contenido['id'] ?? 'N/A') . '</div>';
        $html .= '</div>';
        $html .= '<div class="col-6">';
        $html .= '<div class="text-muted">URL única:</div>';
        $html .= '<div class="fw-medium text-truncate" style="font-size: 0.8rem;">' .
            htmlspecialchars($contenido['url_compartir'] ?? 'N/A') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }

    return $html;
}

// ==================== SECCIÓN 11: HTML Y CSS ====================
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($contenido ? ($contenido['titulo'] . ' - ' . $sitio_nombre) : ($sitio_nombre . ' - Visualizador')); ?></title>

    <!-- Bootstrap / Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- SweetAlert2 para confirmaciones -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- TinyMCE Self-Hosted (Asegúrate de tenerlo en /tinymce/) -->
    <script src="../tinymce/tinymce.min.js"></script>

    <style>
        :root {
            --color-principal: <?php echo $color_principal; ?>;
            --color-fondo: <?php echo $color_fondo; ?>;
        }

        body {
            background-color: var(--color-fondo) !important;
            <?php if (!empty($imagen_fondo)): ?>background-image: url('../<?php echo $imagen_fondo; ?>') !important;
            background-size: cover !important;
            background-attachment: fixed !important;
            background-position: center !important;
            background-blend-mode: overlay !important;
            background-color: rgba(255, 255, 255, 0.95) !important;
            <?php endif; ?>font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Header mejorado - MÁS EFICIENTE */
        .page-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 249, 250, 0.98) 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .header-compact {
            padding: 1rem;
            border-radius: 12px;
        }

        .page-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1.8rem;
            line-height: 1.2;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title i {
            color: var(--color-principal);
            font-size: 1.5rem;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 0;
            line-height: 1.4;
            max-width: 800px;
        }

        /* Barra de usuario mejorada */
        .user-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--color-principal) 0%, #0d6efd 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.85rem;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 0.4rem 0.9rem;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.25);
            color: white;
            text-decoration: none;
        }

        /* Cards mejoradas */
        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .post-header-image {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }

        .card-body {
            flex: 1;
            padding: 1rem;
        }

        /* Estilos modificados para contenido de posts */
        .post-content {
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.5;
            flex-grow: 1;
            font-size: 0.95rem;
        }

        .post-content img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            margin: 0.5rem 0;
        }

        /* Clase para contenido largo - con scroll limitado */
        .post-contenido-largo .contenido-limitado {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 5px;
        }

        /* Scrollbar personalizada para contenido */
        .post-contenido-largo .contenido-limitado::-webkit-scrollbar {
            width: 6px;
        }

        .post-contenido-largo .contenido-limitado::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 3px;
        }

        .post-contenido-largo .contenido-limitado::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }

        /* Botón flotante */
        .floating-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-principal) 0%, #0d6efd 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .floating-btn:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 202, 240, 0.4);
        }

        /* --- ESTILO MASONRY (Tipo Padlet) --- */
        .masonry-container {
            column-count: 1;
            column-gap: 1.5rem;
        }

        /* Responsive: 2 columnas en tablets */
        @media (min-width: 768px) {
            .masonry-container {
                column-count: 2;
            }
        }

        /* Responsive: 3 columnas en escritorio normal */
        @media (min-width: 992px) {
            .masonry-container {
                column-count: 3;
            }
        }

        /* Responsive: 4 columnas en pantallas grandes */
        @media (min-width: 1400px) {
            .masonry-container {
                column-count: 4;
            }
        }

        /* El item individual (la tarjeta) */
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 1.5rem;
        }

        /* Botones de acción compactos */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            transform: translateY(-1px);
        }

        /* Badges compactos */
        .stats-badge {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            color: #495057;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-right: 0.4rem;
            margin-bottom: 0.4rem;
        }

        /* Header details compacto */
        .header-details {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            animation: fadeInSlide 0.3s ease-out;
        }

        @keyframes fadeInSlide {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Botón toggle detalles */
        .btn-toggle-details {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            background: white;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        .btn-toggle-details:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            transform: translateY(-1px);
        }

        .btn-toggle-details.active {
            background-color: #f8f9fa;
            border-color: #0dcaf0;
            color: #0dcaf0;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Alertas */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 0.9rem 1.2rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        /* Ajustes responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .page-title {
                font-size: 1.4rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .page-title i {
                font-size: 1.2rem;
            }

            .page-subtitle {
                font-size: 0.85rem;
            }

            .user-bar {
                width: 100%;
                margin-top: 0.75rem;
                padding: 0.5rem;
                justify-content: space-between;
            }

            .action-buttons {
                width: 100%;
                margin-top: 0.75rem;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
                min-width: 120px;
            }

            .floating-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }

            .masonry-container {
                column-gap: 1rem;
            }

            .masonry-item {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .page-title {
                font-size: 1.2rem;
            }

            .stats-badge {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }

            .post-header-image {
                height: 150px;
            }

            .card-body {
                padding: 0.75rem;
            }
        }

        /* Animaciones */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Scrollbar general */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--color-principal);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #0aa2c0;
        }

        /* Color options */
        .color-option {
            width: 32px;
            height: 32px;
            cursor: pointer;
            border: 3px solid transparent;
            border-radius: 6px;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .color-option:hover {
            transform: scale(1.1);
            border-color: #666;
        }

        .color-option.selected {
            border-color: #0d6efd;
            transform: scale(1.1);
            box-shadow: 0 3px 6px rgba(13, 110, 253, 0.25);
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Mensaje de modo solo lectura */
        .readonly-message {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .readonly-message h5 {
            color: #856404;
            font-weight: 600;
        }

        .readonly-message ul {
            margin-bottom: 0.5rem;
            padding-left: 1.2rem;
        }

        .readonly-message li {
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <!-- ==================== SECCIÓN 12: BARRA DE USUARIO ==================== -->
    <div class="container-fluid py-2 bg-white border-bottom shadow-sm sticky-top">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?php echo $dashboard_url; ?>" class="text-decoration-none text-primary">
                        <i class="bi bi-grid-3x3-gap fs-5"></i>
                        <span class="d-none d-md-inline ms-1 fw-medium"><?php echo htmlspecialchars($sitio_nombre); ?></span>
                    </a>
                    <?php if ($contenido): ?>
                        <span class="text-muted d-none d-md-inline">/</span>
                        <span class="text-truncate d-none d-md-inline" style="max-width: 200px;">
                            <?php echo htmlspecialchars($contenido['titulo']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="user-bar">
                    <div class="user-info">
                        <?php if ($usuario_logueado): ?>
                            <div class="user-avatar">
                                <?php echo $usuario_avatar; ?>
                            </div>
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars(explode(' ', $usuario_nombre)[0]); ?></div>
                                <small class="text-muted" style="font-size: 0.75rem;"><?php echo $es_propietario ? 'Propietario' : 'Usuario'; ?></small>
                            </div>
                        <?php else: ?>
                            <div class="user-avatar bg-secondary">?</div>
                            <div class="user-name">Visitante</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($usuario_logueado): ?>
                        <a href="../auth/cerrar.php" class="logout-btn">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-sm-inline">Salir</span>
                        </a>
                    <?php else: ?>
                        <div class="d-flex gap-1">
                            <a href="../auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-outline-primary btn-sm px-2">
                                <i class="bi bi-box-arrow-in-right"></i>
                                <span class="d-none d-sm-inline ms-1">Ingresar</span>
                            </a>
                            <a href="../auth/registro.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-primary btn-sm px-2">
                                <i class="bi bi-person-plus"></i>
                                <span class="d-none d-sm-inline ms-1">Registro</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== SECCIÓN 13: CONTENIDO PRINCIPAL ==================== -->
    <div class="container py-3 fade-in">

        <?php if ($contenido && !$error_tipo): ?>

            <!-- Header del rotafolio - EFICIENTE Y COMPACTO -->
            <div class="page-header mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div class="flex-grow-1 me-2">
                        <div class="page-title">
                            <i class="bi bi-grid-3x3-gap"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <span class="text-truncate" style="max-width: 400px;">
                                        <?php echo htmlspecialchars($contenido['titulo'] ?? 'Sin título'); ?>
                                    </span>
                                    <button class="btn btn-toggle-details" id="toggleHeaderDetails">
                                        <i class="bi bi-chevron-down" id="headerToggleIcon"></i>
                                        <span class="d-none d-md-inline">Detalles</span>
                                    </button>
                                </div>

                                <?php if (!empty($contenido['descripcion'])): ?>
                                    <div class="page-subtitle mt-1">
                                        <?php echo nl2br(htmlspecialchars($contenido['descripcion'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección expandible - INICIALMENTE OCULTA -->
                <div class="header-details mt-2" id="headerDetailsSection" style="display: none;">
                    <?php echo generarDetallesRotafolio($contenido, $posts, $mis_posts, $permite_posts_publicos, $es_propietario); ?>

                    <!-- Botones de acción -->
                    <div class="action-buttons mt-2 pt-2 border-top">
                        <?php if ($es_propietario): ?>
                            <button class="btn btn-action btn-outline-primary" data-bs-toggle="modal" data-bs-target="#compartirModal">
                                <i class="bi bi-share"></i>
                                <span>Compartir</span>
                            </button>
                            <button class="btn btn-action btn-success" data-bs-toggle="modal" data-bs-target="#editarRotafolioModal">
                                <i class="bi bi-pencil"></i>
                                <span>Editar</span>
                            </button>
                        <?php endif; ?>
                        <?php if (($permite_posts_publicos || $es_propietario) && !$modo_solo_lectura): ?>
                            <button class="btn btn-action btn-primary" data-bs-toggle="modal" data-bs-target="#agregarPostModal">
                                <i class="bi bi-plus-lg"></i>
                                <span>Nuevo Post</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mensaje de modo solo lectura -->
            <?php if ($modo_solo_lectura): ?>
                <div class="readonly-message mb-3">
                    <h5 class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-info-circle"></i>
                        Modo solo lectura
                    </h5>
                    <p class="mb-2">Este rotafolio está configurado para <strong>usuarios registrados</strong>.</p>
                    <ul>
                        <li>✅ <strong>Puedes ver</strong> todos los posts del rotafolio</li>
                        <li>❌ <strong>No puedes</strong> crear, editar ni eliminar posts</li>
                        <li>🔒 Para interactuar con el rotafolio, necesitas iniciar sesión o registrarte</li>
                    </ul>
                    <div class="d-flex gap-2 mt-2">
                        <a href="../auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                            class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión
                        </a>
                        <a href="../auth/registro.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                            class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-person-plus me-1"></i>Crear cuenta
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Alertas -->
            <?php if ($error_agregar): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_agregar; ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Grid de posts -->
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <h3 class="h4 mb-3">Aún no hay posts</h3>
                    <p class="text-muted mb-4">Sé el primero en compartir algo en este rotafolio</p>
                    <?php if (($permite_posts_publicos || $es_propietario) && !$modo_solo_lectura): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#agregarPostModal">
                            <i class="bi bi-plus-lg me-2"></i>Crear el primer post
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <h3 class="h5 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-sticky"></i>
                        Posts recientes (<span id="postsCount"><?php echo count($posts); ?></span>)
                    </h3>
                    <?php echo renderPostsGrid($posts, $base_path); ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Error -->
            <div class="empty-state">
                <?php if ($error_tipo == 'id_no_existe'): ?>
                    <div class="empty-state-icon text-primary">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3 class="h4 mb-3">Rotafolio no encontrado</h3>
                <?php elseif ($error_tipo == 'acceso_privado'): ?>
                    <div class="empty-state-icon text-primary">
                        <i class="bi bi-lock-fill"></i>
                    </div>
                    <h3 class="h4 mb-3">Acceso restringido</h3>
                <?php elseif ($error_tipo == 'solo_usuarios_registrados'): ?>
                    <!-- NUEVO CASO PARA SOLO USUARIOS REGISTRADOS - MODIFICADO -->
                    <div class="empty-state-icon text-warning">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                    <h3 class="h4 mb-3">Modo solo lectura</h3>
                    <div class="alert alert-info mb-4 text-start">
                        <h5 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Información importante</h5>
                        <p class="mb-2">Este rotafolio está configurado para <strong>usuarios registrados</strong>.</p>
                        <ul class="mb-0">
                            <li>✅ <strong>Puedes ver</strong> todos los posts del rotafolio</li>
                            <li>❌ <strong>No puedes</strong> crear, editar ni eliminar posts</li>
                            <li>🔒 Para interactuar con el rotafolio, necesitas:</li>
                        </ul>
                    </div>

                    <div class="d-flex flex-column gap-3 align-items-center">
                        <!-- Botones para registro/login -->
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="../auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
                            </a>
                            <a href="../auth/registro.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                class="btn btn-outline-primary">
                                <i class="bi bi-person-plus me-2"></i>Crear cuenta
                            </a>
                        </div>

                        <!-- Mensaje alternativo -->
                        <div class="text-muted small mt-2">
                            <i class="bi bi-envelope me-1"></i>
                            Si prefieres no registrarte, contacta al propietario del rotafolio
                        </div>
                    </div>

                    <!-- MOSTRAR LOS POSTS AUNQUE SEA MODO SOLO LECTURA -->
                    <?php if (!empty($posts) && $modo_solo_lectura): ?>
                        <div class="mt-4 pt-4 border-top">
                            <h4 class="h5 mb-3">Posts del rotafolio (<span id="postsCount"><?php echo count($posts); ?></span>)</h4>
                            <div class="alert alert-warning mb-3">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Modo solo lectura:</strong> Estás viendo estos posts como invitado. Para interactuar, inicia sesión o regístrate.
                            </div>
                            <?php echo renderPostsGrid($posts, $base_path); ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state-icon text-primary">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h3 class="h4 mb-3">Error</h3>
                <?php endif; ?>
                <?php if ($error_tipo != 'solo_usuarios_registrados'): ?>
                    <p class="text-muted mb-4"><?php echo $error_mensaje; ?></p>
                    <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary">
                        <i class="bi bi-house-door me-2"></i>Ir al Dashboard
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Botón flotante para agregar post -->
        <?php if ($contenido && ($permite_posts_publicos || $es_propietario) && !$modo_solo_lectura): ?>
            <button class="floating-btn" data-bs-toggle="modal" data-bs-target="#agregarPostModal" title="Agregar nuevo post">
                <i class="bi bi-plus-lg"></i>
            </button>
        <?php endif; ?>

        <!-- ==================== SECCIÓN 14: MODALES ==================== -->
        <?php if ($contenido && ($permite_posts_publicos || $es_propietario) && !$modo_solo_lectura): ?>
            <!-- Modal: Agregar Post -->
            <div class="modal fade" id="agregarPostModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Crear nuevo post</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data" id="formAgregarPost">
                            <?php if ($es_propietario): ?>
                                <input type="hidden" name="agregar_post_editor" value="1">
                            <?php else: ?>
                                <input type="hidden" name="agregar_post_publico" value="1">
                            <?php endif; ?>
                            <div class="modal-body">
                                <?php if (!$usuario_logueado): ?>
                                    <div class="mb-4">
                                        <label class="form-label">¿Cómo quieres que te llamemos?</label>
                                        <input type="text" class="form-control" name="nombre_visitante" maxlength="100"
                                            placeholder="Tu nombre (opcional)" value="">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Si no ingresas un nombre, aparecerás como <strong>"Anónimo"</strong>
                                        </small>
                                    </div>
                                <?php elseif ($usuario_logueado && !$es_propietario): ?>
                                    <div class="alert alert-info mb-4">
                                        <i class="bi bi-person-check me-2"></i>
                                        <strong>Publicarás como:</strong> <?php echo htmlspecialchars(explode(' ', $usuario_nombre)[0]); ?>
                                        <small class="d-block mt-1">Tu post aparecerá con tu nombre de usuario.</small>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-4">
                                    <label class="form-label">Contenido del post <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="editorContenido" name="<?php echo $es_propietario ? 'contenido_post_editor' : 'contenido'; ?>" rows="8" placeholder="Escribe tu mensaje aquí..."></textarea>
                                    <small class="text-muted">Puedes usar el editor para dar formato a tu texto</small>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Imagen de encabezado (opcional)</label>
                                        <div class="file-preview" id="headerPreviewContainer" onclick="document.getElementById('imagenHeaderInput').click()">
                                            <div id="headerPreview" class="py-4">
                                                <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                                                <p class="small mb-1">Haz clic para seleccionar imagen</p>
                                                <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 5MB</small>
                                            </div>
                                            <input type="file" class="form-control d-none"
                                                name="<?php echo $es_propietario ? 'imagen_header' : 'imagen_header_publico'; ?>"
                                                id="imagenHeaderInput"
                                                accept="image/*"
                                                capture="environment"
                                                onchange="previewHeaderImage(this)">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Archivo adjunto (opcional)</label>
                                        <div class="file-preview" id="filePreviewContainer" onclick="document.getElementById('archivoAdjuntoInput').click()">
                                            <div id="filePreview" class="py-4">
                                                <i class="bi bi-file-earmark fs-1 text-muted d-block mb-2"></i>
                                                <p class="small mb-1">Haz clic para seleccionar archivo</p>
                                                <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 5MB</small>
                                            </div>
                                            <input type="file" class="form-control d-none"
                                                name="<?php echo $es_propietario ? 'archivo_adjunto' : 'archivo_adjunto_publico'; ?>"
                                                id="archivoAdjuntoInput"
                                                accept=".pdf,.docx,.xlsx"
                                                onchange="previewFile(this)">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label mb-3">Color de fondo del post</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php
                                        $colores_post = [
                                            '#ffffff' => 'Blanco',
                                            '#e3f2fd' => 'Azul claro',
                                            '#f3e5f5' => 'Lila',
                                            '#e8f5e9' => 'Verde claro',
                                            '#fff3e0' => 'Naranja claro',
                                            '#fce4ec' => 'Rosa claro'
                                        ];
                                        foreach ($colores_post as $c => $n): ?>
                                            <div class="color-option"
                                                style="background-color:<?php echo $c; ?>"
                                                data-color="<?php echo $c; ?>"
                                                data-input="<?php echo $es_propietario ? 'color_post_editor' : 'color'; ?>"
                                                onclick="seleccionarColorPost(this, '<?php echo $es_propietario ? 'color_post_editor' : 'color'; ?>')"
                                                title="<?php echo $n; ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="<?php echo $es_propietario ? 'color_post_editor' : 'color'; ?>" id="<?php echo $es_propietario ? 'color_post_editor' : 'colorPost'; ?>" value="#ffffff">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary" type="submit" id="btnAgregarPost">Publicar post</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Modal: Editar Post -->
        <!-- Modal: Editar Post (CORREGIDO) -->
        <div class="modal fade" id="editarPostModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Post</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <!-- QUITAR EL ACTION - Se manejará con AJAX -->
                    <form method="POST" enctype="multipart/form-data" id="formEditarPost">
                        <!-- ELIMINAR ESTO: <input type="hidden" name="accion" value="actualizar_post"> -->
                        <input type="hidden" name="actualizar_post" value="1">
                        <input type="hidden" name="rotafolio_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="post_id" id="postIdEdit">

                        <div class="modal-body">
                            <div class="mb-4">
                                <label class="form-label">Contenido <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="editorContenidoEdit" name="contenido" rows="8"></textarea>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Imagen de encabezado (opcional)</label>
                                    <div class="file-preview" id="headerEditPreviewContainer" onclick="document.getElementById('imagenHeaderEditInput').click()">
                                        <div id="headerEditPreview" class="py-4">
                                            <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                                            <p class="small mb-1" id="headerEditText">Haz clic para seleccionar imagen</p>
                                            <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 5MB</small>
                                        </div>
                                        <input type="file" class="form-control d-none"
                                            name="imagen_header_edit"
                                            id="imagenHeaderEditInput"
                                            accept="image/*"
                                            capture="environment"
                                            onchange="previewHeaderImageEdit(this)">
                                    </div>
                                    <div id="currentHeaderInfo" class="mt-2 small text-muted"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Archivo adjunto (opcional)</label>
                                    <div class="file-preview" id="fileEditPreviewContainer" onclick="document.getElementById('archivoAdjuntoEditInput').click()">
                                        <div id="fileEditPreview" class="py-4">
                                            <i class="bi bi-file-earmark fs-1 text-muted d-block mb-2"></i>
                                            <p class="small mb-1" id="fileEditText">Haz clic para seleccionar archivo</p>
                                            <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 5MB</small>
                                        </div>
                                        <input type="file" class="form-control d-none"
                                            name="archivo_adjunto_edit"
                                            id="archivoAdjuntoEditInput"
                                            accept=".pdf,.docx,.xlsx"
                                            onchange="previewFileEdit(this)">
                                    </div>
                                    <div id="currentFileInfo" class="mt-2 small text-muted"></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label mb-3">Color de fondo del post</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php
                                    $colores_post = [
                                        '#ffffff' => 'Blanco',
                                        '#e3f2fd' => 'Azul claro',
                                        '#f3e5f5' => 'Lila',
                                        '#e8f5e9' => 'Verde claro',
                                        '#fff3e0' => 'Naranja claro',
                                        '#fce4ec' => 'Rosa claro'
                                    ];
                                    foreach ($colores_post as $c => $n): ?>
                                        <div class="color-option"
                                            style="background-color:<?php echo $c; ?>"
                                            data-color="<?php echo $c; ?>"
                                            data-input="colorEditado"
                                            onclick="seleccionarColorPost(this, 'colorEditado')"
                                            title="<?php echo $n; ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="color" id="colorEditado" value="#ffffff">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" type="submit" id="btnEditarPost">
                                <i class="bi bi-check-lg me-2"></i>Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($contenido && $es_propietario): ?>
            <!-- Modal: Editar Rotafolio -->
            <div class="modal fade" id="editarRotafolioModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Editar rotafolio</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data" id="formEditarRotafolio">
                            <input type="hidden" name="actualizar_info" value="1">
                            <div class="modal-body">
                                <div class="mb-4">
                                    <label class="form-label">Título <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-lg" name="titulo" id="tituloRotafolio" value="<?php echo htmlspecialchars($contenido['titulo']); ?>" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="descripcion" rows="3" placeholder="Describe brevemente tu rotafolio..."><?php echo htmlspecialchars($contenido['descripcion'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label mb-3">Color de fondo</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php
                                        $colores_fondo = [
                                            '#f8f9fa' => 'Padlet Light',
                                            '#ffffff' => 'Blanco',
                                            '#e3f2fd' => 'Azul claro',
                                            '#f3e5f5' => 'Lila',
                                            '#e8f5e9' => 'Verde claro',
                                            '#fff3e0' => 'Naranja claro',
                                            '#fce4ec' => 'Rosa claro',
                                            '#0dcaf0' => 'Celeste',
                                            '#20c997' => 'Verde',
                                            '#6f42c1' => 'Púrpura',
                                        ];
                                        $color_actual = $contenido['color_fondo'] ?? '#f8f9fa';
                                        foreach ($colores_fondo as $c => $n): ?>
                                            <div class="color-option <?php echo ($color_actual == $c) ? 'selected' : ''; ?>"
                                                style="background-color:<?php echo $c; ?>"
                                                data-color="<?php echo $c; ?>"
                                                data-input="color_fondo"
                                                onclick="seleccionarColorFondo(this, 'color_fondo')"
                                                title="<?php echo $n; ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="color_fondo" id="color_fondo" value="<?php echo $color_actual; ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Imagen de fondo (opcional)</label>
                                    <div class="file-preview mb-2" id="backgroundPreview" onclick="document.getElementById('imagen_fondo_input').click()">
                                        <?php if (!empty($imagen_fondo)): ?>
                                            <img src="../<?php echo $imagen_fondo; ?>" class="img-fluid rounded" style="max-height: 150px;">
                                        <?php else: ?>
                                            <div class="py-4">
                                                <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                                                <p class="small mb-1">Haz clic para seleccionar imagen</p>
                                                <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 5MB</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" class="form-control d-none" name="imagen_fondo" id="imagen_fondo_input" accept="image/*">
                                    <?php if (!empty($imagen_fondo)): ?>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="eliminar_imagen" id="eliminar_imagen" value="1">
                                            <label class="form-check-label text-danger" for="eliminar_imagen">
                                                <i class="bi bi-trash me-1"></i>Eliminar imagen actual
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch form-switch-lg">
                                            <input class="form-check-input" type="checkbox" name="es_publico" id="es_publico" value="1"
                                                <?php echo ($contenido['es_publico'] ?? false) ? 'checked' : ''; ?>
                                                onchange="togglePermitirPosts()">
                                            <label class="form-check-label fw-bold" for="es_publico">
                                                <i class="bi bi-globe me-1"></i>Hacer público
                                            </label>
                                            <small class="form-text text-muted d-block mt-1">
                                                Cuando está activado, el rotafolio es visible públicamente
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch form-switch-lg">
                                            <input class="form-check-input" type="checkbox" name="permite_posts_publicos" id="permite_posts_publicos"
                                                value="1" <?php echo ($contenido['permite_posts_publicos'] ?? false) ? 'checked' : ''; ?>
                                                <?php echo !($contenido['es_publico'] ?? false) ? 'disabled' : ''; ?>
                                                onchange="togglePermitirEdicion()">
                                            <label class="form-check-label fw-bold" for="permite_posts_publicos">
                                                <i class="bi bi-people-fill me-1"></i>Permitir posts públicos
                                            </label>
                                            <small class="form-text text-muted d-block mt-1">
                                                Los visitantes pueden crear posts
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- CAMPO PARA CONTROL DE ACCESO -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-12">
                                        <div class="form-check form-switch form-switch-lg">
                                            <input class="form-check-input" type="checkbox" name="solo_usuarios_registrados"
                                                id="solo_usuarios_registrados" value="1"
                                                <?php echo ($contenido['solo_usuarios_registrados'] ?? false) ? 'checked' : ''; ?>
                                                <?php echo !($contenido['es_publico'] ?? false) ? 'disabled' : ''; ?>
                                                onchange="toggleSoloUsuariosRegistrados()">
                                            <label class="form-check-label fw-bold" for="solo_usuarios_registrados">
                                                <i class="bi bi-person-lock me-1"></i>Solo usuarios registrados
                                            </label>
                                            <small class="form-text text-muted d-block mt-2">
                                                <strong>Cuando está activado:</strong> Solo los usuarios registrados pueden interactuar con los posts.<br>
                                                <strong>Los invitados pueden ver</strong> los posts en modo solo lectura.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- NOTA SOBRE LA INTERACCIÓN DE OPCIONES -->
                                <div class="alert alert-info mt-3">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Configuración de acceso:</h6>
                                    <ul class="mb-0 small">
                                        <li><strong>Hacer público:</strong> Habilita o deshabilita la visibilidad del rotafolio.</li>
                                        <li><strong>Permitir posts públicos:</strong> Los visitantes pueden crear posts (solo si es público).</li>
                                        <li><strong>Solo usuarios registrados:</strong> Los invitados solo pueden ver posts (solo lectura).</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-success" type="submit" id="btnGuardarCambios">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($contenido && $es_propietario && $contenido['es_publico']): ?>
            <!-- Modal: Compartir -->
            <div class="modal fade" id="compartirModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-share me-2"></i>
                                Compartir "<?php echo htmlspecialchars($contenido['titulo']); ?>"
                            </h5>
                            <button class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-4">
                                <label class="form-label mb-2">Enlace público</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="<?php echo $url_compartir_absoluta; ?>" id="shareLink" readonly>
                                    <button class="btn btn-primary" type="button" onclick="copiarEnlace('shareLink', this)">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Cualquiera con este enlace puede ver tu rotafolio.
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label mb-2">Compartir en redes sociales</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button class="btn btn-success w-100" onclick="compartirWhatsApp('<?php echo $url_compartir_absoluta; ?>', '<?php echo htmlspecialchars($contenido['titulo']); ?>')">
                                            <i class="bi bi-whatsapp me-2"></i>WhatsApp
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary w-100" onclick="compartirFacebook('<?php echo $url_compartir_absoluta; ?>')">
                                            <i class="bi bi-facebook me-2"></i>Facebook
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div> <!-- /container -->

    <!-- ==================== SECCIÓN 15: JAVASCRIPT ==================== -->
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Config global
        window.esPropietario = <?php echo $es_propietario ? 'true' : 'false'; ?>;
        window.permitePostsPublicos = <?php echo $permite_posts_publicos ? 'true' : 'false'; ?>;
        window.rotafolioId = <?php echo $id; ?>;
        window.misPostsCount = <?php echo count($mis_posts); ?>;
        window.usuarioLogueado = <?php echo $usuario_logueado ? 'true' : 'false'; ?>;
        window.modoSoloLectura = <?php echo $modo_solo_lectura ? 'true' : 'false'; ?>;

        // ====== TinyMCE Configuration ======
        let editorPrincipal = null;
        let editorEdicion = null;

        function inicializarEditorPrincipal() {
            if (typeof tinymce !== 'undefined' && document.getElementById('editorContenido')) {
                if (tinymce.get('editorContenido')) {
                    tinymce.remove('editorContenido');
                }

                try {
                    editorPrincipal = tinymce.init({
                        selector: '#editorContenido',
                        license_key: 'gpl',
                        height: 200,
                        menubar: false,
                        statusbar: false,
                        branding: false,
                        promotion: false,
                        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | forecolor backcolor removeformat | help | link image',
                        skin: 'oxide',
                        content_css: 'default',
                        language: 'es',
                        images_upload_handler: function(blobInfo, progress) {
                            return new Promise((resolve, reject) => {
                                if (blobInfo.blob().size <= 5120 * 5120) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        resolve(e.target.result);
                                    };
                                    reader.readAsDataURL(blobInfo.blob());
                                } else {
                                    reject({
                                        message: 'La imagen es demasiado grande. Máximo 5MB.',
                                        remove: true
                                    });
                                }
                            });
                        },
                        setup: function(editor) {
                            editor.on('init', function() {
                                editor.setContent('');
                                setTimeout(() => editor.focus(), 100);
                            });
                        }
                    });
                } catch (error) {
                    console.error('Error al inicializar TinyMCE:', error);
                }
            }
        }

        function inicializarEditorEdicion(contenido = '') {
            if (typeof tinymce !== 'undefined' && document.getElementById('editorContenidoEdit')) {
                if (tinymce.get('editorContenidoEdit')) {
                    tinymce.remove('editorContenidoEdit');
                }

                try {
                    editorEdicion = tinymce.init({
                        selector: '#editorContenidoEdit',
                        license_key: 'gpl',
                        height: 200,
                        menubar: false,
                        statusbar: false,
                        branding: false,
                        promotion: false,
                        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | forecolor backcolor removeformat | help | link image',
                        skin: 'oxide',
                        content_css: 'default',
                        language: 'es',
                        images_upload_handler: function(blobInfo, progress) {
                            return new Promise((resolve, reject) => {
                                if (blobInfo.blob().size <= 5120 * 5120) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        resolve(e.target.result);
                                    };
                                    reader.readAsDataURL(blobInfo.blob());
                                } else {
                                    reject({
                                        message: 'La imagen es demasiado grande. Máximo 5MB.',
                                        remove: true
                                    });
                                }
                            });
                        },
                        setup: function(editor) {
                            editor.on('init', function() {
                                if (contenido) {
                                    editor.setContent(contenido);
                                }
                                setTimeout(() => editor.focus(), 100);
                            });
                        }
                    });
                } catch (error) {
                    console.error('Error al inicializar editor de edición:', error);
                    const textarea = document.getElementById('editorContenidoEdit');
                    if (textarea && contenido) {
                        textarea.value = contenido;
                    }
                }
            }
        }

        // Inicializar cuando el modal se abra
        document.getElementById('agregarPostModal')?.addEventListener('shown.bs.modal', function() {
            inicializarEditorPrincipal();
        });

        // Inicializar editor de edición cuando se abra el modal
        document.getElementById('editarPostModal')?.addEventListener('shown.bs.modal', function() {
            if (window.currentPostData && window.currentPostData.contenido) {
                inicializarEditorEdicion(window.currentPostData.contenido);
            }
        });

        // ====== Funciones de preview ======
        function previewHeaderImage(input) {
            const preview = document.getElementById('headerPreview');
            const container = document.getElementById('headerPreviewContainer');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 100px;">`;
                    container.classList.add('has-file');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewFile(input) {
            const preview = document.getElementById('filePreview');
            const container = document.getElementById('filePreviewContainer');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = (file.size / 5120).toFixed(1);
                const fileName = file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name;
                const fileType = file.name.split('.').pop().toUpperCase();

                let icon = 'bi-file-earmark';
                if (fileType === 'PDF') icon = 'bi-file-earmark-pdf text-danger';
                else if (fileType === 'DOCX') icon = 'bi-file-earmark-word text-primary';
                else if (fileType === 'XLSX') icon = 'bi-file-earmark-excel text-success';

                preview.innerHTML = `
                    <div class="text-center">
                        <i class="bi ${icon} fs-1"></i>
                        <p class="small mt-2 mb-1">${fileName}</p>
                        <small class="text-muted">${fileType} • ${fileSize} KB</small>
                    </div>
                `;
                container.classList.add('has-file');
            }
        }

        // ====== FUNCIONES PARA EDICIÓN ======
        function previewHeaderImageEdit(input) {
            const preview = document.getElementById('headerEditPreview');
            const container = document.getElementById('headerEditPreviewContainer');
            const text = document.getElementById('headerEditText');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 100px;">`;
                    text.textContent = 'Imagen seleccionada';
                    container.classList.add('has-file');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewFileEdit(input) {
            const preview = document.getElementById('fileEditPreview');
            const container = document.getElementById('fileEditPreviewContainer');
            const text = document.getElementById('fileEditText');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = (file.size / 5120).toFixed(1);
                const fileName = file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name;
                const fileType = file.name.split('.').pop().toUpperCase();

                let icon = 'bi-file-earmark';
                if (fileType === 'PDF') icon = 'bi-file-earmark-pdf text-danger';
                else if (fileType === 'DOCX') icon = 'bi-file-earmark-word text-primary';
                else if (fileType === 'XLSX') icon = 'bi-file-earmark-excel text-success';

                preview.innerHTML = `
                    <div class="text-center">
                        <i class="bi ${icon} fs-1"></i>
                        <p class="small mt-2 mb-1">${fileName}</p>
                        <small class="text-muted">${fileType} • ${fileSize} KB</small>
                    </div>
                `;
                text.textContent = 'Archivo seleccionado';
                container.classList.add('has-file');
            }
        }

        async function manejarActualizacionPost(formData) {
            return new Promise((resolve, reject) => {
                fetch('ver_rotafolio_update.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la red');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            resolve({
                                success: true,
                                message: data.message,
                                redirect: data.redirect
                            });
                        } else {
                            const form = document.getElementById('formEditarPost');
                            if (form) {
                                form.submit();
                            }
                            reject(new Error('Fallback activado'));
                        }
                    })
                    .catch(error => {
                        const form = document.getElementById('formEditarPost');
                        if (form) {
                            form.submit();
                        }
                        reject(error);
                    });
            });
        }

        async function manejarEditarPost(e) {
            e.preventDefault();

            const btn = document.getElementById('btnEditarPost');
            if (!btn) {
                console.error('Botón btnEditarPost no encontrado');
                return;
            }

            const originalText = btn.innerHTML;

            let contenido = '';
            try {
                const editor = tinymce.get('editorContenidoEdit');
                if (editor && typeof editor.getContent === 'function') {
                    contenido = editor.getContent();
                } else {
                    contenido = document.getElementById('editorContenidoEdit')?.value || '';
                }
            } catch (error) {
                contenido = document.getElementById('editorContenidoEdit')?.value || '';
            }

            if (!contenido.trim()) {
                mostrarError('El contenido es requerido');
                return;
            }

            const color = document.getElementById('colorEditado').value;

            const imagenInput = document.getElementById('imagenHeaderEditInput');
            const archivoInput = document.getElementById('archivoAdjuntoEditInput');

            if (imagenInput?.files[0] && imagenInput.files[0].size > 5120 * 5120) {
                mostrarError('La imagen es demasiado grande. Máximo 5MB.');
                return false;
            }

            if (archivoInput?.files[0] && archivoInput.files[0].size > 5120 * 5120) {
                mostrarError('El archivo adjunto es demasiado grande. Máximo 5MB.');
                return false;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Guardando...';

            const form = e.target;
            const formData = new FormData(form);
            formData.set('contenido', contenido);

            try {
                const resultado = await manejarActualizacionPost(formData);

                if (resultado.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editarPostModal'));
                    if (modal) {
                        modal.hide();
                    }

                    mostrarOk(resultado.message);
                    setTimeout(() => {
                        if (resultado.redirect) {
                            window.location.href = resultado.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);

                } else {
                    mostrarError(resultado.message || 'Error al actualizar');
                }
            } catch (error) {
                console.log('Proceso continuará con redirección tradicional...');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // ====== ELIMINAR ARCHIVO ======
        async function eliminarArchivo(postId, tipoArchivo) {
            const tipoTexto = tipoArchivo === 'imagen_header' ? 'imagen de encabezado' : 'archivo adjunto';

            return Swal.fire({
                title: '¿Estás seguro?',
                text: `Esta ${tipoTexto} se eliminará permanentemente`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                backdrop: true,
                allowOutsideClick: false,
                allowEscapeKey: true
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const formData = new FormData();
                        formData.append('eliminar_archivo', '1');
                        formData.append('post_id', postId);
                        formData.append('tipo_archivo', tipoArchivo);

                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'X-Requested-Width': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Recargar la página para ver los cambios
                            window.location.reload();

                            // Mostrar mensaje de éxito mientras se recarga
                            Swal.fire({
                                title: '¡Eliminado!',
                                text: data.message || `${tipoTexto} eliminada correctamente`,
                                icon: 'success',
                                timer: 1500,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end'
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || `Error al eliminar la ${tipoTexto}`,
                                icon: 'error',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    } catch (error) {
                        console.error('Error al eliminar archivo:', error);
                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'No se pudo eliminar el archivo. Inténtalo de nuevo.',
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    }
                }
            });
        }

        // ====== Event Listeners ======
        document.addEventListener('DOMContentLoaded', function() {
            inicializarEditorPrincipal();
            inicializarBotonDetalles();

            // Event listeners para botones de acciones en posts
            document.addEventListener('click', function(e) {
                // Botón de editar (no permitir en modo solo lectura)
                if (e.target.closest('.editar-post-btn')) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (window.modoSoloLectura) {
                        mostrarError('Modo solo lectura: Debes iniciar sesión para editar posts.');
                        return;
                    }

                    const btn = e.target.closest('.editar-post-btn');
                    const postId = btn.getAttribute('data-post-id');
                    cargarDatosParaEdicion(postId, btn);
                }

                // Botón de eliminar (no permitir en modo solo lectura)
                if (e.target.closest('.eliminar-post-btn')) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (window.modoSoloLectura) {
                        mostrarError('Modo solo lectura: Debes iniciar sesión para eliminar posts.');
                        return;
                    }

                    const btn = e.target.closest('.eliminar-post-btn');
                    const postId = btn.getAttribute('data-post-id');
                    eliminarPost(postId, btn);
                }

                // Botón "Ver más"
                if (e.target.closest('.ver-mas-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    manejarVerMas(e);
                }

                // Botón eliminar archivo (no permitir en modo solo lectura)
                if (e.target.closest('.eliminar-archivo-btn')) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (window.modoSoloLectura) {
                        mostrarError('Modo solo lectura: Debes iniciar sesión para eliminar archivos.');
                        return;
                    }

                    const btn = e.target.closest('.eliminar-archivo-btn');
                    const postId = btn.getAttribute('data-post-id');
                    const tipoArchivo = btn.getAttribute('data-tipo');

                    eliminarArchivo(postId, tipoArchivo);
                }
            });

            // Formulario de agregar post
            const formAgregarPost = document.getElementById('formAgregarPost');
            if (formAgregarPost) {
                formAgregarPost.addEventListener('submit', manejarAgregarPost);
            }

            // Formulario de editar post
            const formEditarPost = document.getElementById('formEditarPost');
            if (formEditarPost) {
                formEditarPost.addEventListener('submit', manejarEditarPost);
            }
            // Formulario de editar rotafolio
            const formEditarRotafolio = document.getElementById('formEditarRotafolio');
            if (formEditarRotafolio) {
                formEditarRotafolio.addEventListener('submit', manejarEditarRotafolio);
            }

            // Preview de imagen de fondo
            const bgPreview = document.getElementById('backgroundPreview');
            const bgInput = document.getElementById('imagen_fondo_input');
            if (bgPreview && bgInput) {
                bgPreview.addEventListener('click', function() {
                    bgInput.click();
                });
                bgInput.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            bgPreview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 120px;">`;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Inicializar selección de color
            inicializarSeleccionColor();
        });

        // ====== FUNCIONES PARA EDICIÓN ======
        // ====== FUNCIONES PARA EDICIÓN ======
        async function cargarDatosParaEdicion(postId, btnElement) {
            try {
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<span class="loading-spinner"></span>';
                btnElement.disabled = true;

                const formData = new FormData();
                formData.append('obtener_datos_edicion', '1');
                formData.append('post_id', postId);

                // IMPORTANTE: Usar la misma página
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;

                if (data.success) {
                    window.currentPostData = data.data;

                    document.getElementById('postIdEdit').value = postId;
                    document.getElementById('colorEditado').value = data.data.color;

                    mostrarInfoArchivosActuales(data.data);

                    document.querySelectorAll('[data-input="colorEditado"]').forEach(el => {
                        el.classList.toggle('selected', el.getAttribute('data-color') === data.data.color);
                    });

                    const modal = new bootstrap.Modal(document.getElementById('editarPostModal'));
                    modal.show();

                } else {
                    mostrarError(data.message || 'Error al cargar datos del post');
                }
            } catch (error) {
                console.error('Error al cargar datos:', error);
                mostrarError('Error de conexión al cargar datos del post');

                if (btnElement) {
                    btnElement.innerHTML = '<i class="bi bi-pencil"></i>';
                    btnElement.disabled = false;
                }
            }
        }

        async function manejarEditarPost(e) {
            e.preventDefault();

            const btn = document.getElementById('btnEditarPost');
            if (!btn) {
                console.error('Botón btnEditarPost no encontrado');
                return;
            }

            const originalText = btn.innerHTML;

            let contenido = '';
            try {
                const editor = tinymce.get('editorContenidoEdit');
                if (editor && typeof editor.getContent === 'function') {
                    contenido = editor.getContent();
                } else {
                    contenido = document.getElementById('editorContenidoEdit')?.value || '';
                }
            } catch (error) {
                contenido = document.getElementById('editorContenidoEdit')?.value || '';
            }

            if (!contenido.trim()) {
                mostrarError('El contenido es requerido');
                return;
            }

            const color = document.getElementById('colorEditado').value;

            const imagenInput = document.getElementById('imagenHeaderEditInput');
            const archivoInput = document.getElementById('archivoAdjuntoEditInput');

            if (imagenInput?.files[0] && imagenInput.files[0].size > 5120 * 5120) {
                mostrarError('La imagen es demasiado grande. Máximo 5MB.');
                return false;
            }

            if (archivoInput?.files[0] && archivoInput.files[0].size > 5120 * 5120) {
                mostrarError('El archivo adjunto es demasiado grande. Máximo 5MB.');
                return false;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Guardando...';

            const form = e.target;
            const formData = new FormData(form);
            formData.set('contenido', contenido);

            try {
                // IMPORTANTE: Enviar a la misma página (no a update.php)
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editarPostModal'));
                    if (modal) {
                        modal.hide();
                    }

                    mostrarOk(data.message || 'Post actualizado correctamente');

                    // Recargar la página para ver los cambios
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);

                } else {
                    mostrarError(data.message || 'Error al actualizar el post');
                }
            } catch (error) {
                console.error('Error en la solicitud:', error);
                mostrarError('Error de conexión. Inténtalo de nuevo.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function mostrarInfoArchivosActuales(data) {
            const currentHeaderInfo = document.getElementById('currentHeaderInfo');
            const headerEditText = document.getElementById('headerEditText');

            if (data.imagen_header_actual) {
                currentHeaderInfo.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-truncate">
                            <strong>Imagen actual:</strong> ${data.imagen_header_nombre || data.imagen_header_actual.split('/').pop()}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="eliminarArchivo(${data.post_id}, 'imagen_header')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                headerEditText.textContent = 'Haz clic para cambiar imagen';
            } else {
                currentHeaderInfo.innerHTML = '<em>No hay imagen actual</em>';
                headerEditText.textContent = 'Haz clic para seleccionar imagen';
            }

            const currentFileInfo = document.getElementById('currentFileInfo');
            const fileEditText = document.getElementById('fileEditText');

            if (data.archivo_adjunto_actual) {
                currentFileInfo.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-truncate">
                            <strong>Archivo actual:</strong> ${data.archivo_adjunto_nombre || data.archivo_adjunto_actual.split('/').pop()}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="eliminarArchivo(${data.post_id}, 'archivo_adjunto')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                fileEditText.textContent = 'Haz clic para cambiar archivo';
            } else {
                currentFileInfo.innerHTML = '<em>No hay archivo actual</em>';
                fileEditText.textContent = 'Haz clic para seleccionar archivo';
            }
        }

        async function eliminarPost(postId, btnElement) {
            return Swal.fire({
                title: '¿Estás seguro?',
                text: 'Este post se eliminará permanentemente',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                backdrop: true,
                allowOutsideClick: false,
                allowEscapeKey: true
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const originalHtml = btnElement ? btnElement.innerHTML : '<i class="bi bi-trash"></i>';
                        if (btnElement) {
                            btnElement.innerHTML = '<span class="loading-spinner"></span>';
                            btnElement.disabled = true;
                        }

                        const formData = new FormData();
                        formData.append('eliminar_post', '1');
                        formData.append('post_id', postId);

                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (btnElement) {
                            btnElement.innerHTML = originalHtml;
                            btnElement.disabled = false;
                        }

                        if (data.success) {
                            const card = document.getElementById('post-' + postId);
                            if (card) {
                                // Animación de eliminación
                                card.style.transition = 'all 0.3s ease';
                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.9)';
                                card.style.margin = '0';
                                card.style.padding = '0';
                                card.style.maxHeight = '0';
                                card.style.overflow = 'hidden';

                                setTimeout(() => {
                                    card.remove();
                                    actualizarContadores();
                                    if (!window.esPropietario) {
                                        window.misPostsCount = Math.max(0, window.misPostsCount - 1);
                                        actualizarContadorMisPosts();
                                    }
                                }, 300);
                            }

                            // Mostrar confirmación de éxito
                            Swal.fire({
                                title: '¡Eliminado!',
                                text: data.message || 'Post eliminado correctamente',
                                icon: 'success',
                                timer: 2000,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end'
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Error al eliminar el post',
                                icon: 'error',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    } catch (error) {
                        console.error('Error al eliminar:', error);
                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'No se pudo eliminar el post. Inténtalo de nuevo.',
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    }
                }
            });
        }

        // ====== FUNCIONES PARA MANEJAR CONTENIDO LARGO ======
        function manejarVerMas(e) {
            const btn = e.target.closest('.ver-mas-btn');
            if (!btn) return;

            const postId = btn.getAttribute('data-post-id');
            const contenidoCompleto = btn.getAttribute('data-completo');
            const postCard = document.getElementById('post-' + postId);
            const contenidoDiv = postCard.querySelector('.contenido-limitado');

            if (contenidoDiv) {
                if (btn.classList.contains('expandido')) {
                    contenidoDiv.innerHTML = contenidoCompleto;
                    contenidoDiv.style.maxHeight = '250px';
                    contenidoDiv.style.overflowY = 'auto';
                    btn.innerHTML = '<i class="bi bi-chevron-down me-1"></i>Ver más...';
                    btn.classList.remove('expandido');
                } else {
                    contenidoDiv.innerHTML = contenidoCompleto;
                    contenidoDiv.style.maxHeight = 'none';
                    contenidoDiv.style.overflowY = 'visible';
                    btn.innerHTML = '<i class="bi bi-chevron-up me-1"></i>Ver menos';
                    btn.classList.add('expandido');
                }
            }
        }

        // ====== Funciones existentes ======
        async function manejarAgregarPost(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('btnAgregarPost');
            const txt = btn.innerHTML;

            let contenido = '';
            if (editorPrincipal && tinymce.get('editorContenido')) {
                contenido = tinymce.get('editorContenido').getContent();
            } else {
                contenido = document.getElementById('editorContenido')?.value || '';
            }

            if (contenido.trim() === '') {
                mostrarError('El contenido es requerido');
                return false;
            }

            const imagenHeaderInput = document.getElementById('imagenHeaderInput');
            const archivoAdjuntoInput = document.getElementById('archivoAdjuntoInput');

            if (imagenHeaderInput.files[0] && imagenHeaderInput.files[0].size > 5120 * 5120) {
                mostrarError('La imagen de encabezado es demasiado grande. Máximo 5MB');
                return false;
            }

            if (archivoAdjuntoInput.files[0] && archivoAdjuntoInput.files[0].size > 5120 * 5120) {
                mostrarError('El archivo adjunto es demasiado grande. Máximo 5MB');
                return false;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Publicando...';

            const fd = new FormData(form);
            if (esPropietario) {
                fd.set('contenido_post_editor', contenido);
            } else {
                fd.set('contenido', contenido);
            }

            try {
                const resp = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd
                });
                const data = await resp.json();
                if (data.success) {
                    agregarPostAlDOM(data.html);
                    actualizarContadores();
                    if (!window.esPropietario) {
                        window.misPostsCount++;
                        actualizarContadorMisPosts();
                    }
                    mostrarOk(data.message || '¡Post agregado!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('agregarPostModal'));
                    if (modal) modal.hide();
                    form.reset();
                    document.getElementById('headerPreview').innerHTML = `
                        <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                        <p class="small mb-1">Haz clic para seleccionar imagen</p>
                        <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 5MB</small>
                    `;
                    document.getElementById('filePreview').innerHTML = `
                        <i class="bi bi-file-earmark fs-1 text-muted d-block mb-2"></i>
                        <p class="small mb-1">Haz clic para seleccionar archivo</p>
                        <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 5MB</small>
                    `;
                    document.getElementById('headerPreviewContainer').classList.remove('has-file');
                    document.getElementById('filePreviewContainer').classList.remove('has-file');
                    if (tinymce.get('editorContenido')) {
                        tinymce.get('editorContenido').setContent('');
                    }
                    inicializarSeleccionColor();
                } else {
                    mostrarError(data.message || 'Error al agregar el post');
                }
            } catch (err) {
                console.error('Error en la solicitud:', err);
                mostrarError('Error de conexión. Inténtalo de nuevo.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = txt;
            }
        }

        async function manejarEditarRotafolio(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('btnGuardarCambios');
            const txt = btn.innerHTML;

            const titulo = document.getElementById('tituloRotafolio');
            if (!titulo || !titulo.value.trim()) {
                mostrarError('El título es requerido');
                return false;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Guardando...';

            const fd = new FormData(form);
            try {
                const resp = await fetch('', {
                    method: 'POST',
                    body: fd
                });
                if (resp.redirected) {
                    window.location.href = resp.url;
                    return;
                }
                window.location.reload();
            } catch (err) {
                console.error('Error en la solicitud:', err);
                mostrarError('Error al guardar. Inténtalo de nuevo.');
                btn.disabled = false;
                btn.innerHTML = txt;
            }
        }

        // ====== Utilidades DOM ======
        function agregarPostAlDOM(html) {
            const container = document.getElementById('postsRow');
            if (!container) {
                const emptyMsg = document.querySelector('.empty-state');
                if (emptyMsg) emptyMsg.remove();

                const newContainer = document.createElement('div');
                newContainer.className = 'masonry-container fade-in';
                newContainer.id = 'postsRow';

                const newItem = document.createElement('div');
                newItem.className = 'masonry-item';
                newItem.innerHTML = html;

                newContainer.appendChild(newItem);

                const alertArea = document.querySelector('.alert') || document.querySelector('.page-header');
                alertArea.after(newContainer);
            } else {
                const newItem = document.createElement('div');
                newItem.className = 'masonry-item fade-in';
                newItem.innerHTML = html;
                container.prepend(newItem);
            }

            actualizarContadores();
        }

        function actualizarContadores() {
            const postsCountElement = document.getElementById('postsCount');
            const totalPostsCountElement = document.getElementById('totalPostsCount');
            const count = document.querySelectorAll('#postsRow .card').length;
            if (postsCountElement) postsCountElement.textContent = count;
            if (totalPostsCountElement) totalPostsCountElement.textContent = count;
        }

        function actualizarContadorMisPosts() {
            const misPostsCountElement = document.getElementById('misPostsCount');
            if (misPostsCountElement) misPostsCountElement.textContent = window.misPostsCount;
        }

        // ====== UI helpers ======
        function inicializarSeleccionColor() {
            const colorInputId = window.esPropietario ? 'color_post_editor' : 'colorPost';
            const hiddenInput = document.getElementById(colorInputId);
            if (hiddenInput && hiddenInput.value) {
                const color = hiddenInput.value;
                document.querySelectorAll(`[data-input="${colorInputId}"]`).forEach(el => {
                    el.classList.toggle('selected', el.getAttribute('data-color') === color);
                });
            }

            const colorEditadoInput = document.getElementById('colorEditado');
            if (colorEditadoInput && colorEditadoInput.value) {
                const color = colorEditadoInput.value;
                document.querySelectorAll('[data-input="colorEditado"]').forEach(el => {
                    el.classList.toggle('selected', el.getAttribute('data-color') === color);
                });
            }
        }

        function seleccionarColorPost(el, inputId) {
            const color = el.getAttribute('data-color');
            const hidden = document.getElementById(inputId);
            if (hidden) hidden.value = color;

            document.querySelectorAll(`[data-input="${inputId}"]`).forEach(item => {
                item.classList.remove('selected');
            });

            el.classList.add('selected');
        }

        function seleccionarColorFondo(el, inputId) {
            const color = el.getAttribute('data-color');
            const hidden = document.getElementById(inputId);
            if (hidden) hidden.value = color;

            document.querySelectorAll(`[data-input="${inputId}"]`).forEach(item => {
                item.classList.remove('selected');
            });

            el.classList.add('selected');
        }

        function togglePermitirPosts() {
            const esPublico = document.getElementById('es_publico');
            const permitePosts = document.getElementById('permite_posts_publicos');
            const soloUsuariosRegistrados = document.getElementById('solo_usuarios_registrados');

            if (esPublico && permitePosts && soloUsuariosRegistrados) {
                const habilitado = esPublico.checked;

                permitePosts.disabled = !habilitado;
                soloUsuariosRegistrados.disabled = !habilitado;

                if (!habilitado) {
                    permitePosts.checked = false;
                    soloUsuariosRegistrados.checked = false;
                }
            }
        }

        function toggleSoloUsuariosRegistrados() {
            const soloUsuariosRegistrados = document.getElementById('solo_usuarios_registrados');
            const permitePosts = document.getElementById('permite_posts_publicos');

            if (soloUsuariosRegistrados && permitePosts) {
                // Si se activa "solo usuarios registrados", desactivar "permite posts públicos"
                if (soloUsuariosRegistrados.checked) {
                    permitePosts.checked = false;
                }
            }
        }

        function togglePermitirEdicion() {
            const permitePosts = document.getElementById('permite_posts_publicos');
            const soloUsuariosRegistrados = document.getElementById('solo_usuarios_registrados');

            if (permitePosts && soloUsuariosRegistrados) {
                // Si se activa "permite posts públicos", desactivar "solo usuarios registrados"
                if (permitePosts.checked) {
                    soloUsuariosRegistrados.checked = false;
                }
            }

            const nuevoEstado = permitePosts.checked ? 'activada' : 'desactivada';
            mostrarOk(`La publicación por visitantes ha sido ${nuevoEstado}`);
        }

        function copiarEnlace(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);

            const originalText = btn.innerHTML;

            navigator.clipboard.writeText(input.value).then(() => {
                btn.innerHTML = '<i class="bi bi-check"></i>';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                mostrarOk('Enlace copiado al portapapeles');

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-primary');
                }, 2000);
            }).catch(() => {
                document.execCommand('copy');
                mostrarOk('Enlace copiado');
            });
        }

        function compartirWhatsApp(url, titulo) {
            const text = encodeURIComponent(titulo + ' ' + url);
            window.open('https://wa.me/?text=' + text, '_blank');
        }

        function compartirFacebook(url) {
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url), '_blank');
        }

        function mostrarError(msg) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: msg,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }

        function mostrarOk(msg) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: msg,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }

        function cerrarModalEdicion() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editarPostModal'));
            if (modal) {
                modal.hide();

                if (tinymce.get('editorContenidoEdit')) {
                    tinymce.get('editorContenidoEdit').setContent('');
                    tinymce.remove('editorContenidoEdit');
                }

                const form = document.getElementById('formEditarPost');
                if (form) {
                    form.reset();
                }

                document.getElementById('headerEditPreview').innerHTML = `
                    <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                    <p class="small mb-1" id="headerEditText">Haz clic para seleccionar imagen</p>
                    <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 5MB</small>
                `;
                document.getElementById('fileEditPreview').innerHTML = `
                    <i class="bi bi-file-earmark fs-1 text-muted d-block mb-2"></i>
                    <p class="small mb-1" id="fileEditText">Haz clic para seleccionar archivo</p>
                    <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 5MB</small>
                `;
            }
        }

        document.getElementById('editarPostModal')?.addEventListener('hidden.bs.modal', function() {
            cerrarModalEdicion();
        });

        // ====== FUNCIONALIDAD PARA EL BOTÓN "DETALLES" ======
        function inicializarBotonDetalles() {
            // Botón detalles
            const toggleBtn = document.getElementById('toggleHeaderDetails');
            const detailsSection = document.getElementById('headerDetailsSection');
            const toggleIcon = document.getElementById('headerToggleIcon');

            if (toggleBtn && detailsSection) {
                // Configurar el estado inicial
                let detallesVisible = false;

                toggleBtn.addEventListener('click', function() {
                    detallesVisible = !detallesVisible;

                    if (detallesVisible) {
                        // Mostrar detalles
                        detailsSection.style.display = 'block';
                        toggleIcon.className = 'bi bi-chevron-up';
                        toggleBtn.setAttribute('title', 'Ocultar detalles');
                        toggleBtn.classList.add('active');

                        // Animación suave
                        detailsSection.style.opacity = '0';
                        detailsSection.style.transform = 'translateY(-8px)';

                        setTimeout(() => {
                            detailsSection.style.transition = 'all 0.3s ease';
                            detailsSection.style.opacity = '1';
                            detailsSection.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        // Ocultar detalles
                        detailsSection.style.transition = 'all 0.3s ease';
                        detailsSection.style.opacity = '0';
                        detailsSection.style.transform = 'translateY(-8px)';
                        toggleIcon.className = 'bi bi-chevron-down';
                        toggleBtn.setAttribute('title', 'Mostrar detalles');
                        toggleBtn.classList.remove('active');

                        // Ocultar después de la animación
                        setTimeout(() => {
                            detailsSection.style.display = 'none';
                        }, 300);
                    }
                });

                // Configurar tooltip
                toggleBtn.setAttribute('title', 'Mostrar detalles');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            inicializarBotonDetalles();
        });
    </script>
</body>

</html>