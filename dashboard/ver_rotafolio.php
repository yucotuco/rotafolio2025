<?php
// ver_rotafolio.php (Mejoras de usabilidad y diseño) - VERSIÓN ACTUALIZADA CON TODAS LAS MEJORAS
session_start();

// RUTAS Y DEPENDENCIAS
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/conn/connrota.php';
require_once $base_path . '/funcionesyClases/claseRotafolio.php';
require_once $base_path . '/funcionesyClases/claseRotafolioUpdate.php'; // NUEVO: Clase de actualización

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

// MANAGERS
$rotaManager = new RotafolioManager($pdoRota);
$rotaUpdateManager = new RotafolioUpdateManager($pdoRota);

// PARAMETROS
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// SESIÓN Y PERMISOS
$usuario_logueado = false;
$usuario_id = 0;
$usuario_nombre = '';
$usuario_email = '';

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
        }
    } catch (PDOException $e) {
        error_log("Error al obtener datos del usuario: " . $e->getMessage());
    }
}

$es_propietario = false;
if ($usuario_logueado && $id > 0) {
    $es_propietario = $rotaManager->esPropietarioRotafolio($id, $usuario_id);
}

// CONTENIDO ROTAFOLIO
$contenido              = null;
$error_tipo             = '';
$error_mensaje          = '';
$permite_posts_publicos = false;
$color_fondo            = '#f8f9fa';
$imagen_fondo           = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contenido && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // ACTUALIZAR INFO (propietario)
    if (isset($_POST['actualizar_info']) && $es_propietario) {
        $datos_actualizar = [
            'titulo'                 => trim($_POST['titulo'] ?? ''),
            'descripcion'            => trim($_POST['descripcion'] ?? ''),
            'layout'                 => 'muro',
            'color_fondo'            => $_POST['color_fondo'] ?? '#0dcaf0',
            'es_publico'             => isset($_POST['es_publico']) ? 1 : 0,
            'permite_posts_publicos' => isset($_POST['permite_posts_publicos']) ? 1 : 0,
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
                $meta = json_encode([
                    'v' => 'p_' . $usuario_id,
                    'n' => $usuario_nombre ?: 'Propietario',
                    'p' => 1,
                    't' => time(),
                    'img_header' => $url_imagen_header,
                    'archivo_adjunto' => $url_archivo_adjunto
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

// OBTENER POSTS
$posts    = [];
$mis_posts = [];

if ($contenido) {
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
                if ($post['es_propietario'] && $es_propietario)       $post['nombre_display'] = 'Tú (Propietario)';
                elseif ($post['es_mio'] && !$es_propietario)          $post['nombre_display'] = 'Tú';

                $post['imagen_header'] = $metadata['img_header'] ?? $post['imagen_header'] ?? null;
                $post['archivo_adjunto'] = $metadata['archivo_adjunto'] ?? $post['archivo_adjunto'] ?? $post['url_archivo'] ?? null;
            } else {
                $post['contenido_limpio'] = $post['contenido'];
                $post['metadata']         = [];
                $post['es_mio']           = false;
                $post['es_propietario']   = false;
                $post['editado']          = false;
                $post['nombre_display']   = 'Anónimo';
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

// ================== AJAX HANDLERS ==================
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && $contenido) {
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

        // nombre visitante
        if ($es_propietario) {
            $nombre_visitante = $usuario_nombre ?: 'Propietario';
        } else {
            $nombre_visitante = trim($_POST['nombre_visitante'] ?? 'Anónimo');
        }

        $meta = json_encode([
            'v' => $es_propietario ? ('p_' . $usuario_id) : $visitante_id,
            'n' => $nombre_visitante,
            'p' => $es_propietario ? 1 : 0,
            't' => time(),
            'img_header' => $url_imagen_header,
            'archivo_adjunto' => $url_archivo_adjunto
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
                    } else {
                        $p['contenido_limpio'] = '';
                        $p['metadata']         = [];
                        $p['imagen_header'] = null;
                        $p['archivo_adjunto'] = null;
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

    // ELIMINAR POST
    if (isset($_POST['eliminar_post'])) {
        $response = ['success' => false, 'message' => ''];
        $post_id = (int)($_POST['post_id'] ?? 0);

        if ($post_id) {
            // Obtener rotafolio_id para verificar permisos
            $stmt = $pdoRota->prepare("SELECT rotafolio_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($post) {
                $rotafolio_id = $post['rotafolio_id'];

                // Verificar permisos manualmente
                if ($es_propietario || in_array($post_id, $mis_posts)) {
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

    // NO RECONOCIDA
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Solicitud no reconocida']);
    exit;
}

// ================== RENDER HELPERS ==================
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
function renderPostCard($post, $base_path)
{
    $postId     = (int)$post['id'];
    $color      = $post['color'] ?? '#ffffff';
    $contenido  = $post['contenido_limpio'] ?? $post['contenido'] ?? '';
    $imagen_header = $post['imagen_header'] ?? null;
    $archivo_adjunto = $post['archivo_adjunto'] ?? null;
    $nombre_autor = $post['nombre_display'] ?? 'Anónimo';

    // 1. OBTENER Y FORMATEAR FECHA
    // Usamos el timestamp de la metadata ('t') o la fecha de creación de la BD
    $timestamp = $post['metadata']['t'] ?? strtotime($post['fecha_creacion']);
    $fecha_formateada = date('d M Y, h:i A', $timestamp); // Ej: 23 Dic 2025, 09:30 PM

    // Calcular palabras para ver si mostramos botón "ver más"
    $contenido_texto = strip_tags($contenido);
    $palabras = preg_split('/\s+/', $contenido_texto, -1, PREG_SPLIT_NO_EMPTY);
    $num_palabras = count($palabras);
    $contenido_limitado = $contenido;

    // Límite un poco más alto ya que ahora tenemos altura dinámica
    if ($num_palabras > 250) {
        $contenido_limitado = limitarPalabrasHTML($contenido, 250);
    }

    // Permisos
    global $es_propietario, $usuario_logueado, $usuario_id;
    $puede_editar = ($es_propietario || ($usuario_logueado && isset($post['metadata']['v']) && $post['metadata']['v'] === 'p_' . $usuario_id));
    $puede_eliminar = $es_propietario || $post['es_mio'];

    // --- INICIO HTML DE LA TARJETA ---
    // Nota: Quitamos 'h-100' para permitir altura dinámica
    $card  = '<div class="card" id="post-' . $postId . '" data-post-id="' . $postId . '" style="background-color:' . htmlspecialchars($color) . ';">';

    // A. IMAGEN DE ENCABEZADO (Sin cambios)
    if (!empty($imagen_header)) {
        $abs = $base_path . '/' . str_replace('../', '', $imagen_header);
        if (file_exists($abs)) {
            $card .= '<img src="' . htmlspecialchars($imagen_header) . '" class="card-img-top post-header-image" alt="Imagen">';
        }
    }

    $card .= '<div class="card-body p-3">';

    // B. HEADER MEJORADO: Autor + Fecha ARRIBA
    $card .= '<div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom border-light border-opacity-50">';
    // Columna Izquierda: Icono, Nombre y Fecha
    $card .= '<div class="d-flex align-items-center">';
    $card .= '<div class="rounded-circle bg-dark bg-opacity-10 d-flex align-items-center justify-content-center me-2" style="width:32px; height:32px; font-size:0.8rem;">';
    $card .= strtoupper(substr($nombre_autor, 0, 1));
    $card .= '</div>';
    $card .= '<div style="line-height: 1.2;">';
    $card .= '<div class="fw-bold small">' . htmlspecialchars($nombre_autor) . '</div>';
    // AQUÍ ESTÁ LA FECHA ARRIBA
    $card .= '<div class="text-muted" style="font-size: 0.7rem;">' . $fecha_formateada . '</div>';
    $card .= '</div>';
    $card .= '</div>';

    // Columna Derecha: Acciones (Editar/Eliminar) + Badge Editado
    $card .= '<div class="d-flex align-items-center gap-1">';
    if ($post['editado'] ?? false) {
        $card .= '<span class="badge bg-dark bg-opacity-10 text-dark me-1" style="font-size:0.6rem;">Editado</span>';
    }
    if ($puede_eliminar || $puede_editar) {
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
    // Quitamos clases de altura fija o overflow
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

    // D. ARCHIVO ADJUNTO (Si existe)
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
            $card .= '<a href="' . htmlspecialchars($archivo_adjunto) . '" class="btn btn-sm btn-light rounded-circle" target="_blank" download><i class="bi bi-download"></i></a>';
            $card .= '</div>';
        }
    }

    $card .= '</div>'; // Cierre card-body
    // Nota: NO agregamos card-footer. La fecha ya está arriba.

    $card .= '</div>'; // Cierre card
    return $card;
}

// VISTAS
if ($contenido && $id > 0 && !$es_propietario) {
    try {
        @$pdoRota->prepare("UPDATE rotafolios SET vistas = COALESCE(vistas, 0) + 1 WHERE id = ?")->execute([$id]);
    } catch (PDOException $e) { /* ignorar */
    }
}

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

        /* Header mejorado */
        .page-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 249, 250, 0.95) 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .page-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 2.2rem;
            line-height: 1.2;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        /* Barra de usuario mejorada */
        .user-bar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--color-principal) 0%, #0d6efd 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
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
            min-height: 300px;
            /* Altura mínima, no fija */
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .post-header-image {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }

        .post-header-placeholder {
            height: 180px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .card-body {
            flex: 1;
            padding: 1.25rem;
        }

        /* Estilos modificados para contenido de posts */
        .post-content {
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.6;
            flex-grow: 1;
        }

        .post-content img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            margin: 0.5rem 0;
        }

        .post-content p {
            margin-bottom: 0.75rem;
        }

        .post-content ul,
        .post-content ol {
            padding-left: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .post-content h1,
        .post-content h2,
        .post-content h3,
        .post-content h4,
        .post-content h5,
        .post-content h6 {
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        /* Clase para contenido largo - con scroll limitado */
        .post-contenido-largo .contenido-limitado {
            max-height: 300px;
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

        .post-contenido-largo .contenido-limitado::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        /* Para posts con poco contenido - altura automática */
        .post-contenido-normal {
            max-height: none !important;
            overflow-y: visible !important;
        }

        /* Estilos para el botón "Ver más" */
        .ver-mas-btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
        }

        .ver-mas-btn:hover {
            transform: translateY(-1px);
        }

        /* Eliminar la fecha del footer */
        .card-footer {
            display: none !important;
        }

        /* Mejorar la visualización de tablas dentro del contenido */
        .post-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.9rem;
        }

        .post-content table th,
        .post-content table td {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            text-align: left;
        }

        .post-content table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        /* Mejorar bloques de código */
        .post-content pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            overflow-x: auto;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .post-content code {
            background-color: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        /* Botones de acción */
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        /* Badges mejorados */
        .badge {
            padding: 0.35em 0.65em;
            font-weight: 500;
            border-radius: 6px;
        }

        /* Modal mejorado */
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--color-principal) 0%, #0d6efd 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* Formularios mejorados */
        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--color-principal);
            box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        /* Editor TinyMCE */
        .tox-tinymce {
            border-radius: 8px !important;
            border: 1px solid #dee2e6 !important;
            margin-bottom: 1rem;
        }

        /* Grid responsivo */
        .row {
            margin-bottom: 2rem;
        }

        .col {
            margin-bottom: 1.5rem;
        }

        /* Botón flotante */
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-principal) 0%, #0d6efd 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 20px rgba(13, 202, 240, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(13, 202, 240, 0.5);
        }

        /* Ajustes responsive para contenido */
        @media (max-width: 768px) {
            .post-contenido-largo .contenido-limitado {
                max-height: 250px;
            }

            .post-content {
                font-size: 0.95rem;
            }

            .page-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .user-bar {
                padding: 0.5rem 1rem;
                flex-direction: column;
                gap: 0.75rem;
                border-radius: 12px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .logout-btn {
                width: 100%;
                justify-content: center;
            }

            .floating-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
            }

            .post-header-image,
            .post-header-placeholder {
                height: 150px;
            }

            /* MODIFICADO: Ajustar altura de contenido en móviles */
            .card-content {
                max-height: 250px;
            }
        }

        @media (max-width: 576px) {
            .post-contenido-largo .contenido-limitado {
                max-height: 200px;
            }

            .post-content {
                font-size: 0.9rem;
                line-height: 1.5;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            /* MODIFICADO: Ajustar botones en móviles */
            .btn-group-sm .btn {
                padding: 0.2rem 0.4rem;
                font-size: 0.8rem;
            }
        }

        /* Animaciones */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Scrollbar personalizada */
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

        /* Color options mejorados */
        .color-option {
            width: 36px;
            height: 36px;
            cursor: pointer;
            border: 3px solid transparent;
            border-radius: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .color-option:hover {
            transform: scale(1.15);
            border-color: #666;
        }

        .color-option.selected {
            border-color: #0d6efd;
            transform: scale(1.15);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }

        /* Alertas mejoradas */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        /* Contadores */
        .stats-badge {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: #495057;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        /* File preview */
        .file-preview {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .file-preview:hover {
            border-color: var(--color-principal);
            background: rgba(13, 202, 240, 0.05);
        }

        .file-preview.has-file {
            border-style: solid;
            background: white;
        }

        /* Info de archivos actuales */
        .current-file-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .current-file-info img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            margin-top: 5px;
        }

        /* Spinner para carga */
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

        /* Para posts con poco contenido, altura automática */
        .card-content:not(:has(> :nth-child(10))) {
            max-height: none;
            overflow-y: visible;
        }

        /* Mejorar scroll en móviles */
        @media (max-width: 768px) {
            .file-preview {
                min-height: 120px;
                padding: 1.5rem !important;
            }
        }

        /* Para tablas dentro del contenido */
        .card-content table {
            max-width: 100%;
            overflow-x: auto;
            display: block;
        }
    </style>

    <style>
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
            /* Evita que la tarjeta se parta entre columnas */
            margin-bottom: 1.5rem;
            /* Espacio vertical entre tarjetas */
        }

        /* --- AJUSTES DE LA TARJETA --- */
        .card {
            /* Quitamos height: 100% para que se ajuste al contenido */
            height: auto !important;
            min-height: 0 !important;
            /* Resetear min-height */
            display: flex;
            flex-direction: column;
            border: none;
            border-radius: 16px;
            /* Bordes más redondeados estilo app */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
            z-index: 2;
            /* Que se sobreponga ligeramente al pasar el mouse */
        }

        /* Ajuste fino para el header de la tarjeta */
        .card-header-custom {
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <!-- Barra de usuario - FIJADA EN LA PARTE SUPERIOR -->
    <div class="container-fluid py-2 bg-white border-bottom shadow-sm sticky-top">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="<?php echo $dashboard_url; ?>" class="text-decoration-none">
                        <h1 class="h4 mb-0 text-primary">
                            <i class="bi bi-grid-3x3-gap me-2"></i>
                            <?php echo htmlspecialchars($sitio_nombre); ?>
                        </h1>
                    </a>
                </div>

                <div class="user-bar d-flex align-items-center gap-3">
                    <div class="user-info">
                        <?php if ($usuario_logueado): ?>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr(explode(' ', $usuario_nombre)[0], 0, 1) . substr(explode(' ', $usuario_nombre)[1] ?? '', 0, 1)); ?>
                            </div>
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars(explode(' ', $usuario_nombre)[0]); ?></div>
                                <small class="text-muted"><?php echo $es_propietario ? 'Propietario' : 'Usuario'; ?></small>
                            </div>
                        <?php else: ?>
                            <div class="user-avatar bg-secondary">?</div>
                            <div class="user-name">Visitante</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($usuario_logueado): ?>
                        <a href="../auth/cerrar.php" class="logout-btn">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-md-inline">Cerrar sesión</span>
                        </a>
                    <?php else: ?>
                        <div class="d-flex gap-2">
                            <a href="../auth/login.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Ingresar
                            </a>
                            <a href="../auth/registro.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-person-plus me-1"></i>Registrarse
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4 fade-in">

        <?php if ($contenido && !$error_tipo): ?>

            <!-- Header del rotafolio - REORGANIZADO -->
            <!-- Header del rotafolio - MEJORADO CON EXPANSIÓN -->
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h1 class="page-title mb-0">
                                    <i class="bi bi-grid-3x3-gap text-primary me-2"></i>
                                    <?php echo htmlspecialchars($contenido['titulo'] ?? 'Sin título'); ?>
                                </h1>

                                <?php if (!empty($contenido['descripcion'])): ?>
                                    <p class="page-subtitle mb-0 mt-1"><?php echo nl2br(htmlspecialchars($contenido['descripcion'])); ?></p>
                                <?php endif; ?>
                            </div>

                            <button class="btn btn-sm btn-outline-secondary ms-3" id="toggleHeaderDetails">
                                <i class="bi bi-chevron-down" id="headerToggleIcon"></i>
                                <span class="d-none d-sm-inline">Detalles</span>
                            </button>
                        </div>

                        <!-- Sección expandible -->
                        <div class="header-details" id="headerDetailsSection" style="display: none;">
                            <!-- Estadísticas -->
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="stats-badge">
                                    <i class="bi bi-eye-fill text-primary"></i>
                                    <?php echo $contenido['vistas'] ?? 0; ?> vistas
                                </span>
                                <span class="stats-badge">
                                    <i class="bi bi-sticky text-success"></i>
                                    <span id="totalPostsCount"><?php echo count($posts); ?></span> posts
                                </span>
                                <?php if ($permite_posts_publicos && !$es_propietario): ?>
                                    <span class="stats-badge bg-info bg-opacity-10 text-info border-info">
                                        <i class="bi bi-people-fill"></i>
                                        <span id="misPostsCount"><?php echo count($mis_posts); ?></span> tus posts
                                    </span>
                                <?php endif; ?>
                                <?php if ($es_propietario): ?>
                                    <span class="stats-badge bg-warning bg-opacity-10 text-warning border-warning">
                                        <i class="bi bi-star-fill"></i> Propietario
                                    </span>
                                    <?php if ($contenido['es_publico']): ?>
                                        <span class="stats-badge bg-success bg-opacity-10 text-success border-success">
                                            <i class="bi bi-globe"></i> Público
                                        </span>
                                    <?php else: ?>
                                        <span class="stats-badge bg-secondary bg-opacity-10 text-secondary border-secondary">
                                            <i class="bi bi-lock"></i> Privado
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Fecha de creación -->
                            <?php if (!empty($contenido['fecha_creacion'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Creado: <?php echo date('d/m/Y', strtotime($contenido['fecha_creacion'])); ?>
                                        <?php if (!empty($contenido['fecha_actualizacion']) && $contenido['fecha_actualizacion'] != $contenido['fecha_creacion']): ?>
                                            • Actualizado: <?php echo date('d/m/Y', strtotime($contenido['fecha_actualizacion'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Botones de acción - Mejorados -->
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if ($es_propietario): ?>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#compartirModal" title="Compartir">
                                <i class="bi bi-share"></i>
                                <span class="d-none d-md-inline ms-1">Compartir</span>
                            </button>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editarRotafolioModal" title="Editar rotafolio">
                                <i class="bi bi-pencil"></i>
                                <span class="d-none d-md-inline ms-1">Editar</span>
                            </button>
                        <?php endif; ?>
                        <?php if ($permite_posts_publicos || $es_propietario): ?>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#agregarPostModal" title="Nuevo post">
                                <i class="bi bi-plus-lg"></i>
                                <span class="d-none d-md-inline ms-1">Nuevo Post</span>
                            </button>
                        <?php endif; ?>

                        <!-- Botón para mostrar/ocultar todos los posts -->
                        <?php if (!empty($posts)): ?>
                            <button class="btn btn-sm btn-outline-secondary" id="toggleAllPosts" title="Mostrar/ocultar todos los posts">
                                <i class="bi bi-layout-text-sidebar-reverse"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Barra de progreso para posts -->
                <?php if (!empty($posts)): ?>
                    <div class="progress mt-3" style="height: 4px; display: none;" id="postsProgressBar">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                <?php endif; ?>
            </div>


            <!-- Alertas -->
            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success alert-dismissible fade show">

                    <i class="bi bi-check-circle-fill me-2"></i><?php echo $mensaje_exito; ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
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
                    <?php if ($permite_posts_publicos || $es_propietario): ?>
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#agregarPostModal">
                            <i class="bi bi-plus-lg me-2"></i>Crear el primer post
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <h3 class="h5 mb-3">Posts recientes (<span id="postsCount"><?php echo count($posts); ?></span>)</h3>
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
                <?php else: ?>
                    <div class="empty-state-icon text-primary">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h3 class="h4 mb-3">Error</h3>
                <?php endif; ?>
                <p class="text-muted mb-4"><?php echo $error_mensaje; ?></p>
                <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary">
                    <i class="bi bi-house-door me-2"></i>Ir al Dashboard
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón flotante para agregar post -->
        <?php if ($contenido && ($permite_posts_publicos || $es_propietario)): ?>
            <button class="floating-btn" data-bs-toggle="modal" data-bs-target="#agregarPostModal" title="Agregar nuevo post">
                <i class="bi bi-plus-lg"></i>
            </button>
        <?php endif; ?>

        <!-- Modales -->
        <?php if ($contenido && ($permite_posts_publicos || $es_propietario)): ?>
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
                                <?php if (!$es_propietario && !$usuario_logueado): ?>
                                    <div class="mb-4">
                                        <label class="form-label">¿Cómo quieres que te llamemos?</label>
                                        <input type="text" class="form-control" name="nombre_visitante" maxlength="100" placeholder="Tu nombre (opcional)">
                                        <small class="text-muted">Si no ingresas un nombre, aparecerás como "Anónimo"</small>
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
                                                <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 1MB</small>
                                            </div>
                                            <!-- MODIFICADO: Agregado capture="environment" para móviles -->
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
                                                <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 1MB</small>
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

        <!-- ====== MODAL: Editar Post - NUEVA IMPLEMENTACIÓN ====== -->
        <div class="modal fade" id="editarPostModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Post</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="formEditarPost"
                        action="ver_rotafolio_update.php">
                        <!-- Campos ocultos necesarios -->
                        <input type="hidden" name="accion" value="actualizar_post">
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
                                            <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 1MB</small>
                                        </div>
                                        <!-- MODIFICADO: Agregado capture="environment" para móviles -->
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
                                            <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 1MB</small>
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
                                            <input class="form-check-input" type="checkbox" name="es_publico" id="es_publico" value="1" <?php echo $contenido['es_publico'] ? 'checked' : ''; ?> onchange="togglePermitirPosts()">
                                            <label class="form-check-label fw-bold" for="es_publico">
                                                <i class="bi bi-globe me-1"></i>Hacer público
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch form-switch-lg">
                                            <input class="form-check-input" type="checkbox" name="permite_posts_publicos" id="permite_posts_publicos" value="1" <?php echo $contenido['permite_posts_publicos'] ? 'checked' : ''; ?> <?php echo !$contenido['es_publico'] ? 'disabled' : ''; ?> onchange="togglePermitirEdicion()">
                                            <label class="form-check-label fw-bold" for="permite_posts_publicos">
                                                <i class="bi bi-people-fill me-1"></i>Permitir posts públicos
                                            </label>
                                        </div>
                                    </div>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Config global
        window.esPropietario = <?php echo $es_propietario ? 'true' : 'false'; ?>;
        window.permitePostsPublicos = <?php echo $permite_posts_publicos ? 'true' : 'false'; ?>;
        window.rotafolioId = <?php echo $id; ?>;
        window.misPostsCount = <?php echo count($mis_posts); ?>;
        window.usuarioLogueado = <?php echo $usuario_logueado ? 'true' : 'false'; ?>;

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
                        height: 250,
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
                                if (blobInfo.blob().size <= 1024 * 1024) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        resolve(e.target.result);
                                    };
                                    reader.readAsDataURL(blobInfo.blob());
                                } else {
                                    reject({
                                        message: 'La imagen es demasiado grande. Máximo 1MB.',
                                        remove: true
                                    });
                                }
                            });
                        },
                        setup: function(editor) {
                            editor.on('init', function() {
                                console.log('Editor principal inicializado');
                                editor.setContent('');
                                setTimeout(() => editor.focus(), 100);
                            });
                        }
                    });
                } catch (error) {
                    console.error('Error al inicializar TinyMCE:', error);
                    mostrarErrorFallback();
                }
            } else {
                console.log('TinyMCE no disponible, usando textarea normal');
                mostrarErrorFallback();
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
                        height: 250,
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
                                if (blobInfo.blob().size <= 1024 * 1024) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        resolve(e.target.result);
                                    };
                                    reader.readAsDataURL(blobInfo.blob());
                                } else {
                                    reject({
                                        message: 'La imagen es demasiado grande. Máximo 1MB.',
                                        remove: true
                                    });
                                }
                            });
                        },
                        setup: function(editor) {
                            editor.on('init', function() {
                                console.log('Editor de edición inicializado');
                                if (contenido) {
                                    editor.setContent(contenido);
                                }
                                setTimeout(() => editor.focus(), 100);
                            });
                        }
                    });
                } catch (error) {
                    console.error('Error al inicializar editor de edición:', error);
                    // Fallback: usar textarea normal
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
            // El contenido se carga dinámicamente antes de abrir el modal
            if (window.currentPostData && window.currentPostData.contenido) {
                inicializarEditorEdicion(window.currentPostData.contenido);
            }
        });

        // Limpiar cuando se cierren los modales
        document.getElementById('agregarPostModal')?.addEventListener('hidden.bs.modal', function() {
            if (tinymce.get('editorContenido')) {
                tinymce.get('editorContenido').setContent('');
            }
        });

        document.getElementById('editarPostModal')?.addEventListener('hidden.bs.modal', function() {
            if (tinymce.get('editorContenidoEdit')) {
                tinymce.remove('editorContenidoEdit');
                editorEdicion = null;
            }
            // Limpiar datos
            window.currentPostData = null;
        });

        // ====== Funciones de preview ======
        function previewHeaderImage(input) {
            const preview = document.getElementById('headerPreview');
            const container = document.getElementById('headerPreviewContainer');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 120px;">`;
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
                const fileSize = (file.size / 1024).toFixed(1);
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
                    preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 120px;">`;
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
                const fileSize = (file.size / 1024).toFixed(1);
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

        // ====== FUNCIÓN MEJORADA PARA MANEJAR EDICIÓN DE POST ======
        function manejarActualizacionPost(formData) {
            return new Promise((resolve, reject) => {
                // Intentar con AJAX primero
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
                            // Fallback: enviar el formulario de forma tradicional
                            console.log('Fallback activado - enviando formulario tradicional');
                            const form = document.getElementById('formEditarPost');
                            if (form) {
                                form.submit();
                            }
                            reject(new Error('Fallback activado'));
                        }
                    })
                    .catch(error => {
                        console.log('Fallback activado por error:', error);
                        // Enviar formulario tradicional
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

            console.log('=== DEBUG: manejarEditarPost iniciado ===');

            const btn = document.getElementById('btnEditarPost');
            if (!btn) {
                console.error('Botón btnEditarPost no encontrado');
                return;
            }

            const originalText = btn.innerHTML;

            // Obtener contenido
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

            console.log('Contenido obtenido:', contenido.substring(0, 50) + '...');

            if (!contenido.trim()) {
                mostrarError('El contenido es requerido');
                return;
            }

            // Obtener color seleccionado
            const color = document.getElementById('colorEditado').value;
            console.log('Color seleccionado:', color);

            // Validar tamaño de archivos
            const imagenInput = document.getElementById('imagenHeaderEditInput');
            const archivoInput = document.getElementById('archivoAdjuntoEditInput');

            if (imagenInput?.files[0] && imagenInput.files[0].size > 1024 * 1024) {
                mostrarError('La imagen es demasiado grande. Máximo 1MB.');
                return false;
            }

            if (archivoInput?.files[0] && archivoInput.files[0].size > 1024 * 1024) {
                mostrarError('El archivo adjunto es demasiado grande. Máximo 1MB.');
                return false;
            }

            // Estado de carga
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Guardando...';

            // Crear FormData
            const form = e.target;
            const formData = new FormData(form);

            // Asegurar que el contenido esté actualizado
            formData.set('contenido', contenido);

            console.log('FormData creado, enviando a ver_rotafolio_update.php...');

            try {
                const resultado = await manejarActualizacionPost(formData);

                if (resultado.success) {
                    // Cerrar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editarPostModal'));
                    if (modal) {
                        modal.hide();
                    }

                    // Mostrar mensaje y redirigir
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
                // El fallback ya maneja la redirección
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // ====== Event Listeners ======
        document.addEventListener('DOMContentLoaded', function() {
            inicializarEditorPrincipal();

            // Event listeners para botones de acciones en posts
            document.addEventListener('click', function(e) {
                // Botón de editar
                if (e.target.closest('.editar-post-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const btn = e.target.closest('.editar-post-btn');
                    const postId = btn.getAttribute('data-post-id');
                    console.log('Editar post clickeado:', postId);
                    cargarDatosParaEdicion(postId, btn);
                }

                // Botón de eliminar
                if (e.target.closest('.eliminar-post-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const btn = e.target.closest('.eliminar-post-btn');
                    const postId = btn.getAttribute('data-post-id');
                    console.log('Eliminar post clickeado:', postId);
                    eliminarPost(postId, btn);
                }

                // Botón "Ver más"
                if (e.target.closest('.ver-mas-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    manejarVerMas(e);
                }
            });

            // Formulario de agregar post
            const formAgregarPost = document.getElementById('formAgregarPost');
            if (formAgregarPost) {
                formAgregarPost.addEventListener('submit', manejarAgregarPost);
            }

            // Formulario de editar post - NUEVA IMPLEMENTACIÓN
            const formEditarPost = document.getElementById('formEditarPost');
            if (formEditarPost) {
                console.log('Configurando listener para formulario de edición');
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
                            bgPreview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 150px;">`;
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Inicializar selección de color
            inicializarSeleccionColor();

            // Configurar inputs para móviles
            configurarInputsParaMovil();

            // Ajustar alturas de cards después de cargar
            setTimeout(ajustarAlturasCards, 500);
        });

        // ====== FUNCIONES PARA EDICIÓN ======
        async function cargarDatosParaEdicion(postId, btnElement) {
            try {
                console.log('Cargando datos para edición del post:', postId);

                // Mostrar estado de carga
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<span class="loading-spinner"></span>';
                btnElement.disabled = true;

                // Enviar solicitud AJAX
                const formData = new FormData();
                formData.append('obtener_datos_edicion', '1');
                formData.append('post_id', postId);

                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();
                console.log('Datos recibidos:', data);

                // Restaurar botón
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;

                if (data.success) {
                    // Guardar datos globalmente
                    window.currentPostData = data.data;

                    // Configurar modal
                    document.getElementById('postIdEdit').value = postId;
                    document.getElementById('colorEditado').value = data.data.color;

                    // Mostrar información de archivos actuales
                    mostrarInfoArchivosActuales(data.data);

                    // Seleccionar color visualmente
                    document.querySelectorAll('[data-input="colorEditado"]').forEach(el => {
                        el.classList.toggle('selected', el.getAttribute('data-color') === data.data.color);
                    });

                    // Mostrar modal
                    const modal = new bootstrap.Modal(document.getElementById('editarPostModal'));
                    modal.show();

                } else {
                    mostrarError(data.message || 'Error al cargar datos del post');
                }
            } catch (error) {
                console.error('Error al cargar datos:', error);
                mostrarError('Error de conexión al cargar datos del post');

                // Restaurar botón
                if (btnElement) {
                    btnElement.innerHTML = '<i class="bi bi-pencil"></i>';
                    btnElement.disabled = false;
                }
            }
        }

        function mostrarInfoArchivosActuales(data) {
            // Mostrar información de imagen actual
            const currentHeaderInfo = document.getElementById('currentHeaderInfo');
            const headerEditText = document.getElementById('headerEditText');

            if (data.imagen_header_actual) {
                currentHeaderInfo.innerHTML = `
                    <strong>Imagen actual:</strong> ${data.imagen_header_nombre || data.imagen_header_actual.split('/').pop()}
                    <br><small>Se reemplazará si seleccionas una nueva</small>
                `;
                headerEditText.textContent = 'Haz clic para cambiar imagen';
            } else {
                currentHeaderInfo.innerHTML = '<em>No hay imagen actual</em>';
                headerEditText.textContent = 'Haz clic para seleccionar imagen';
            }

            // Mostrar información de archivo actual
            const currentFileInfo = document.getElementById('currentFileInfo');
            const fileEditText = document.getElementById('fileEditText');

            if (data.archivo_adjunto_actual) {
                currentFileInfo.innerHTML = `
                    <strong>Archivo actual:</strong> ${data.archivo_adjunto_nombre || data.archivo_adjunto_actual.split('/').pop()}
                    <br><small>Se reemplazará si seleccionas uno nuevo</small>
                `;
                fileEditText.textContent = 'Haz clic para cambiar archivo';
            } else {
                currentFileInfo.innerHTML = '<em>No hay archivo actual</em>';
                fileEditText.textContent = 'Haz clic para seleccionar archivo';
            }
        }

        async function eliminarPost(postId, btnElement) {
            if (!confirm('¿Estás seguro de eliminar este post? Esta acción no se puede deshacer.')) {
                return;
            }

            try {
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<span class="loading-spinner"></span>';
                btnElement.disabled = true;

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

                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;

                if (data.success) {
                    // Eliminar tarjeta con animación
                    const card = document.getElementById('post-' + postId);
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();
                            actualizarContadores();
                            if (!window.esPropietario) {
                                window.misPostsCount = Math.max(0, window.misPostsCount - 1);
                                actualizarContadorMisPosts();
                            }
                        }, 300);
                    }
                    mostrarOk(data.message || 'Post eliminado');
                } else {
                    mostrarError(data.message || 'Error al eliminar');
                }
            } catch (error) {
                console.error('Error al eliminar:', error);
                mostrarError('Error de conexión al eliminar');
            }
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
                    // Volver al estado limitado
                    contenidoDiv.innerHTML = contenidoCompleto;
                    contenidoDiv.style.maxHeight = '300px';
                    contenidoDiv.style.overflowY = 'auto';
                    btn.innerHTML = '<i class="bi bi-chevron-down me-1"></i>Ver menos';
                    btn.classList.remove('expandido');
                } else {
                    // Mostrar contenido completo sin límite
                    contenidoDiv.innerHTML = contenidoCompleto;
                    contenidoDiv.style.maxHeight = 'none';
                    contenidoDiv.style.overflowY = 'visible';
                    btn.innerHTML = '<i class="bi bi-chevron-up me-1"></i>Ver más';
                    btn.classList.add('expandido');
                }

                // Reajustar altura de la card
                ajustarAlturaCard(postId);
            }
        }

        // ====== Funciones existentes ======
        async function manejarAgregarPost(e) {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('btnAgregarPost');
            const txt = btn.innerHTML;

            // Obtener contenido del editor
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

            // Validar tamanno de archivos
            const imagenHeaderInput = document.getElementById('imagenHeaderInput');
            const archivoAdjuntoInput = document.getElementById('archivoAdjuntoInput');

            if (imagenHeaderInput.files[0] && imagenHeaderInput.files[0].size > 1024 * 1024) {
                mostrarError('La imagen de encabezado es demasiado grande. Máximo 1MB');
                return false;
            }

            if (archivoAdjuntoInput.files[0] && archivoAdjuntoInput.files[0].size > 1024 * 1024) {
                mostrarError('El archivo adjunto es demasiado grande. Máximo 1MB');
                return false;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Publicando...';

            const fd = new FormData(form);
            // Agregar contenido al FormData
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
                    // Resetear previews
                    document.getElementById('headerPreview').innerHTML = `
                        <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                        <p class="small mb-1">Haz clic para seleccionar imagen</p>
                        <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 1MB</small>
                    `;
                    document.getElementById('filePreview').innerHTML = `
                        <i class="bi bi-file-earmark fs-1 text-muted d-block mb-2"></i>
                        <p class="small mb-1">Haz clic para seleccionar archivo</p>
                        <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 1MB</small>
                    `;
                    document.getElementById('headerPreviewContainer').classList.remove('has-file');
                    document.getElementById('filePreviewContainer').classList.remove('has-file');
                    // Resetear editor
                    if (tinymce.get('editorContenido')) {
                        tinymce.get('editorContenido').setContent('');
                    }
                    // Resetear selección de color
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
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

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
                // Si no existe el contenedor (era el primer post), crearlo
                const emptyMsg = document.querySelector('.empty-state');
                if (emptyMsg) emptyMsg.remove();

                // Crear contenedor Masonry
                const newContainer = document.createElement('div');
                newContainer.className = 'masonry-container fade-in';
                newContainer.id = 'postsRow';

                // Crear item masonry
                const newItem = document.createElement('div');
                newItem.className = 'masonry-item';
                newItem.innerHTML = html;

                newContainer.appendChild(newItem);

                // Insertar después del header
                const alertArea = document.querySelector('.alert') || document.querySelector('.page-header');
                alertArea.after(newContainer);
            } else {
                // Crear item masonry
                const newItem = document.createElement('div');
                newItem.className = 'masonry-item fade-in';
                newItem.innerHTML = html;
                container.prepend(newItem);
            }

            // Actualizar contadores... (tu código existente)
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

        // ====== FUNCIONES PARA AJUSTAR ALTURA ======
        function ajustarAlturasCards() {
            document.querySelectorAll('.card').forEach(card => {
                ajustarAlturaCardPorElemento(card);
            });
        }

        function ajustarAlturaCardPorElemento(card) {
            const contenidoDiv = card.querySelector('.post-content');
            if (contenidoDiv) {
                const texto = contenidoDiv.textContent || contenidoDiv.innerText || '';
                const palabras = texto.trim().split(/\s+/).filter(word => word.length > 0);

                // Altura mínima basada en contenido
                if (palabras.length < 50) {
                    card.style.minHeight = '250px';
                } else if (palabras.length < 100) {
                    card.style.minHeight = '300px';
                } else if (palabras.length < 200) {
                    card.style.minHeight = '350px';
                } else {
                    card.style.minHeight = '400px';
                }
            }
        }

        function ajustarAlturaCard(postId) {
            const card = document.getElementById('post-' + postId);
            if (card) {
                ajustarAlturaCardPorElemento(card);
            }
        }

        // ====== UI helpers ======
        function inicializarSeleccionColor() {
            // Para modal agregar post
            const colorInputId = window.esPropietario ? 'color_post_editor' : 'colorPost';
            const hiddenInput = document.getElementById(colorInputId);
            if (hiddenInput && hiddenInput.value) {
                const color = hiddenInput.value;
                document.querySelectorAll(`[data-input="${colorInputId}"]`).forEach(el => {
                    el.classList.toggle('selected', el.getAttribute('data-color') === color);
                });
            }

            // Para modal editar post
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

            // Remover selección anterior
            document.querySelectorAll(`[data-input="${inputId}"]`).forEach(item => {
                item.classList.remove('selected');
            });

            // Agregar selección actual
            el.classList.add('selected');
        }

        function seleccionarColorFondo(el, inputId) {
            const color = el.getAttribute('data-color');
            const hidden = document.getElementById(inputId);
            if (hidden) hidden.value = color;

            // Remover selección anterior
            document.querySelectorAll(`[data-input="${inputId}"]`).forEach(item => {
                item.classList.remove('selected');
            });

            // Agregar selección actual
            el.classList.add('selected');
        }

        function togglePermitirPosts() {
            const esPublico = document.getElementById('es_publico');
            const permitePosts = document.getElementById('permite_posts_publicos');
            if (esPublico && permitePosts) {
                permitePosts.disabled = !esPublico.checked;
                if (!esPublico.checked) {
                    permitePosts.checked = false;
                }
            }
        }

        function togglePermitirEdicion() {
            const permitePosts = document.getElementById('permite_posts_publicos');
            if (permitePosts) {
                const nuevoEstado = permitePosts.checked ? 'activada' : 'desactivada';
                mostrarOk(`La publicación por visitantes ha sido ${nuevoEstado}`);
            }
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
            const el = document.createElement('div');
            el.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            el.style.cssText = 'top: 20px; right: 20px; z-index: 2000; max-width: 400px;';
            el.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div class="flex-grow-1">
                        <strong>Error</strong>
                        <div class="small">${msg}</div>
                    </div>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.appendChild(el);
            setTimeout(() => {
                if (el.parentNode) {
                    el.remove();
                }
            }, 5000);
        }

        function mostrarOk(msg) {
            const el = document.createElement('div');
            el.className = 'alert alert-success alert-dismissible fade show position-fixed';
            el.style.cssText = 'top: 20px; right: 20px; z-index: 2000; max-width: 400px;';
            el.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div class="flex-grow-1">
                        <strong>¡Éxito!</strong>
                        <div class="small">${msg}</div>
                    </div>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.appendChild(el);
            setTimeout(() => {
                if (el.parentNode) {
                    el.remove();
                }
            }, 3000);
        }

        // Función para cerrar modal de edición
        function cerrarModalEdicion() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editarPostModal'));
            if (modal) {
                modal.hide();
                console.log('Modal de edición cerrado');

                // Limpiar el editor
                if (tinymce.get('editorContenidoEdit')) {
                    tinymce.get('editorContenidoEdit').setContent('');
                    tinymce.remove('editorContenidoEdit');
                }

                // Resetear formulario
                const form = document.getElementById('formEditarPost');
                if (form) {
                    form.reset();
                }

                // Resetear previews
                document.getElementById('headerEditPreview').innerHTML = `
                    <i class="bi bi-image fs-1 text-muted d-block mb-2"></i>
                    <p class="small mb-1" id="headerEditText">Haz clic para seleccionar imagen</p>
                    <small class="text-muted d-block">JPEG, PNG, GIF o WebP • Máx. 1MB</small>
                `;
                document.getElementById('fileEditPreview').innerHTML = `
                    <i class="bi bi-file-earmark fs-1 text-muted d-block mb-2"></i>
                    <p class="small mb-1" id="fileEditText">Haz clic para seleccionar archivo</p>
                    <small class="text-muted d-block">PDF, DOCX o XLSX • Máx. 1MB</small>
                `;
            }
        }

        // Agregar listener para cuando el modal se oculta
        document.getElementById('editarPostModal')?.addEventListener('hidden.bs.modal', function() {
            cerrarModalEdicion();
        });

        // ====== FUNCIONES AGREGADAS PARA MANEJO MÓVIL Y MEJORAS ======

        // Detectar si es dispositivo móvil
        function esDispositivoMovil() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        // Configurar inputs de archivo según dispositivo
        function configurarInputsParaMovil() {
            if (!esDispositivoMovil()) return;

            // Permitir captura desde cámara en móviles
            const imagenInputs = document.querySelectorAll('input[type="file"][accept="image/*"]');
            imagenInputs.forEach(input => {
                if (!input.hasAttribute('capture')) {
                    input.setAttribute('capture', 'environment');
                }
            });

            // Mejorar experiencia táctil
            document.querySelectorAll('.file-preview').forEach(el => {
                el.style.minHeight = '120px';
                el.style.padding = '1.5rem';
            });
        }

        // Llamar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            configurarInputsParaMovil();

            // También cuando se abran modales
            const modales = ['agregarPostModal', 'editarPostModal', 'editarRotafolioModal'];
            modales.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('shown.bs.modal', configurarInputsParaMovil);
                }
            });

            // Detectar cambios en orientación de pantalla
            window.addEventListener('orientationchange', function() {
                setTimeout(configurarInputsParaMovil, 100);
                setTimeout(ajustarAlturasCards, 200);
            });

            // Detectar cambios en tamaño de ventana
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    configurarInputsParaMovil();
                    setTimeout(ajustarAlturasCards, 100);
                }
            });
        });

        // Función mejorada para contar palabras en contenido HTML
        function contarPalabrasEnHTML(html) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const text = tempDiv.textContent || tempDiv.innerText || '';
            const palabras = text.trim().split(/\s+/).filter(word => word.length > 0);
            return palabras.length;
        }

        // Verificar y ajustar contenido largo al cargar
        function ajustarContenidoLargo() {
            document.querySelectorAll('.post-content').forEach(contenidoDiv => {
                const html = contenidoDiv.innerHTML;
                const numPalabras = contarPalabrasEnHTML(html);

                if (numPalabras > 200 && !contenidoDiv.querySelector('.contenido-limitado')) {
                    // Si ya tiene el botón "Ver más", no hacer nada
                    return;
                }
            });
        }

        // Ejecutar ajuste de contenido después de cargar posts
        setTimeout(ajustarContenidoLargo, 500);

        // Mejorar experiencia de formularios en móviles
        if (esDispositivoMovil()) {
            // Prevenir zoom en inputs
            document.addEventListener('touchstart', function(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                    document.body.style.zoom = "100%";
                }
            }, {
                passive: true
            });

            // Mejorar scroll en modales
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('shown.bs.modal', function() {
                    const modalBody = this.querySelector('.modal-body');
                    if (modalBody) {
                        modalBody.style.WebkitOverflowScrolling = 'touch';
                    }
                });
            });
        }
    </script>
</body>

</html>