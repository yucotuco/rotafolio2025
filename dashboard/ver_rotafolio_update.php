<?php
// ver_rotafolio_update.php
session_start();

$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/conn/connrota.php';
require_once $base_path . '/funcionesyClases/claseRotafolio.php';
require_once $base_path . '/funcionesyClases/claseRotafolioUpdate.php';

// Obtener rotafolio_id desde POST
$rotafolio_id = isset($_POST['rotafolio_id']) ? intval($_POST['rotafolio_id']) : 0;
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$accion = $_POST['accion'] ?? '';

// Verificar si es AJAX o redirección normal
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Managers
$rotaManager = new RotafolioManager($pdoRota);
$rotaUpdateManager = new RotafolioUpdateManager($pdoRota);

// Verificar usuario
$usuario_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$usuario_logueado = $usuario_id > 0;

// Verificar visitante
$visitante_id = $_COOKIE['rotafolio_visitor'] ?? null;

// Función para redirigir
function redirigir($rotafolio_id, $mensaje = '', $tipo = 'success')
{
    $params = ['id' => $rotafolio_id];
    if ($mensaje) {
        $params['mensaje'] = urlencode($mensaje);
        $params['tipo'] = $tipo;
    }
    $url = '../dashboard/ver_rotafolio.php?' . http_build_query($params);
    header("Location: $url");
    exit;
}

// Procesar según la acción
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirigir($rotafolio_id, 'Método no permitido', 'error');
    }




    // Verificar si usuario está intentando editar su propio post
    $stmt = $pdoRota->prepare("SELECT contenido FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post_contenido = $stmt->fetchColumn();

    if ($post_contenido) {
        $lines = explode("\n\n", $post_contenido, 2);
        $metadata = count($lines) >= 2 ? json_decode($lines[0], true) : [];

        // DEPURACIÓN: Mostrar información del post
        error_log("=== DEBUG POST METADATA ===");
        error_log("Post ID: $post_id");
        error_log("Usuario ID: $usuario_id");
        error_log("Visitante ID: $visitante_id");
        error_log("Metadata: " . json_encode($metadata));

        // Verificación directa
        if (isset($metadata['v'])) {
            error_log("Metadata v: " . $metadata['v']);
            error_log("Es p_ + usuario_id: " . ('p_' . $usuario_id));
            error_log("¿Coincide con usuario registrado?: " . ($metadata['v'] === 'p_' . $usuario_id ? 'SÍ' : 'NO'));
            error_log("¿Coincide con visitante?: " . ($metadata['v'] === $visitante_id ? 'SÍ' : 'NO'));
        }
    }



    // ACCIÓN: ACTUALIZAR POST
    if ($accion === 'actualizar_post' && $post_id > 0) {
        error_log("=== INICIO ACTUALIZAR POST (update.php) ===");
        error_log("Post ID: $post_id, Usuario ID: $usuario_id, Visitante ID: $visitante_id");

        // Primero verificar si es propietario del rotafolio
        $es_propietario_rotafolio = false;
        if ($usuario_logueado && $rotafolio_id > 0) {
            $stmt = $pdoRota->prepare("SELECT user_id FROM rotafolios WHERE id = ?");
            $stmt->execute([$rotafolio_id]);
            $rotafolio = $stmt->fetch(PDO::FETCH_ASSOC);
            $es_propietario_rotafolio = ($rotafolio && $rotafolio['user_id'] == $usuario_id);
            error_log("Es propietario del rotafolio: " . ($es_propietario_rotafolio ? 'Sí' : 'No'));
        }

        // Verificar permisos usando el nuevo manager
        list($permiso, $mensaje_permiso, $post_info) = $rotaUpdateManager->verificarPermisoEdicion(
            $post_id,
            $usuario_id,
            $visitante_id,  // ¡IMPORTANTE! Pasar el visitante_id
            $es_propietario_rotafolio
        );

        error_log("Resultado verificación permisos: Permiso=" . ($permiso ? 'Sí' : 'No') . ", Mensaje: $mensaje_permiso");
        error_log("Parámetros enviados: Usuario ID: $usuario_id, Visitante ID: $visitante_id, Es propietario: " . ($es_propietario_rotafolio ? 'Sí' : 'No'));

        if (!$permiso) {
            error_log("Permiso denegado: $mensaje_permiso | Usuario ID: $usuario_id | Visitante ID: $visitante_id | Es propietario: " . ($es_propietario_rotafolio ? 'Sí' : 'No'));

            if ($isAjaxRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $mensaje_permiso,
                    'redirect' => "../dashboard/ver_rotafolio.php?id={$rotafolio_id}&mensaje=" . urlencode($mensaje_permiso) . "&tipo=error"
                ]);
            } else {
                redirigir($rotafolio_id, $mensaje_permiso, 'error');
            }
            exit;
        }

        // Obtener datos del formulario
        $contenido = trim($_POST['contenido'] ?? '');
        $color = $_POST['color'] ?? '#ffffff';

        error_log("Datos recibidos: Contenido longitud=" . strlen($contenido) . ", Color=$color");

        if (empty($contenido)) {
            throw new Exception('El contenido es requerido');
        }

        // Preparar datos
        $datos = [
            'contenido' => $contenido,
            'color' => $color
        ];

        // Preparar archivos
        $archivos = [];

        if (isset($_FILES['imagen_header_edit']) && $_FILES['imagen_header_edit']['error'] === UPLOAD_ERR_OK) {
            $archivos['imagen_header'] = $_FILES['imagen_header_edit'];
            error_log("Imagen recibida: " . $_FILES['imagen_header_edit']['name'] . " (" . $_FILES['imagen_header_edit']['size'] . " bytes)");
        }

        if (isset($_FILES['archivo_adjunto_edit']) && $_FILES['archivo_adjunto_edit']['error'] === UPLOAD_ERR_OK) {
            $archivos['archivo_adjunto'] = $_FILES['archivo_adjunto_edit'];
            error_log("Archivo recibido: " . $_FILES['archivo_adjunto_edit']['name'] . " (" . $_FILES['archivo_adjunto_edit']['size'] . " bytes)");
        }

        // Determinar si es público (para manejar subida de archivos)
        $es_publico = false;
        if ($post_info && isset($post_info['rotafolio_id'])) {
            $stmt = $pdoRota->prepare("SELECT es_publico FROM rotafolios WHERE id = ?");
            $stmt->execute([$post_info['rotafolio_id']]);
            $rotafolio = $stmt->fetch(PDO::FETCH_ASSOC);
            $es_publico = $rotafolio && $rotafolio['es_publico'];
            error_log("Es público: " . ($es_publico ? 'Sí' : 'No'));
        }

        // Ejecutar actualización usando el nuevo método
        error_log("Llamando a actualizarPostCompleto...");
        $resultado = $rotaUpdateManager->actualizarPostCompleto(
            $post_id,
            $datos,
            $archivos,
            $es_publico
        );

        error_log("Resultado actualización: " . json_encode($resultado));

        if ($resultado[0]) { // success
            $success = $resultado[0];
            $message = $resultado[1];
            $post_id_actualizado = $resultado[2] ?? $post_id;
            $rotafolio_id_ret = $resultado[3] ?? $rotafolio_id;

            error_log("Actualización exitosa: $message, Rotafolio ID retornado: $rotafolio_id_ret");

            // Usar el rotafolio_id retornado o el original
            $redirect_rotafolio_id = $rotafolio_id_ret ?? $rotafolio_id;

            if ($isAjaxRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'redirect' => "../dashboard/ver_rotafolio.php?id={$redirect_rotafolio_id}&mensaje=" . urlencode($message) . "&tipo=success"
                ]);
            } else {
                redirigir($redirect_rotafolio_id, $message, 'success');
            }
        } else {
            $message = $resultado[1] ?? 'Error desconocido';
            error_log("Error en actualización: $message");
            throw new Exception($message);
        }
    }
    // ACCIÓN: OBTENER DATOS PARA EDICIÓN (para AJAX)
    elseif ($accion === 'obtener_datos_edicion' && $post_id > 0) {
        error_log("=== OBTENER DATOS PARA EDICIÓN ===");
        error_log("Post ID: $post_id, Usuario ID: $usuario_id");

        // Verificar si es propietario del rotafolio
        $es_propietario_rotafolio = false;
        if ($usuario_logueado && $rotafolio_id > 0) {
            $stmt = $pdoRota->prepare("SELECT user_id FROM rotafolios WHERE id = ?");
            $stmt->execute([$rotafolio_id]);
            $rotafolio = $stmt->fetch(PDO::FETCH_ASSOC);
            $es_propietario_rotafolio = ($rotafolio && $rotafolio['user_id'] == $usuario_id);
        }

        // Obtener datos del post para edición
        $datos_post = $rotaUpdateManager->obtenerDatosEdicion($post_id);

        if ($datos_post[0]) {
            $response = [
                'success' => true,
                'data' => $datos_post[1]
            ];
        } else {
            $response = [
                'success' => false,
                'message' => $datos_post[1]
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    // ACCIÓN: VERIFICAR PERMISOS (para AJAX)
    elseif ($accion === 'verificar_permisos' && $post_id > 0) {
        error_log("=== VERIFICAR PERMISOS ===");

        $es_propietario_rotafolio = false;
        if ($usuario_logueado && $rotafolio_id > 0) {
            $stmt = $pdoRota->prepare("SELECT user_id FROM rotafolios WHERE id = ?");
            $stmt->execute([$rotafolio_id]);
            $rotafolio = $stmt->fetch(PDO::FETCH_ASSOC);
            $es_propietario_rotafolio = ($rotafolio && $rotafolio['user_id'] == $usuario_id);
        }

        list($permiso, $mensaje_permiso, $post_info) = $rotaUpdateManager->verificarPermisoEdicion(
            $post_id,
            $usuario_id,
            $visitante_id,
            $es_propietario_rotafolio
        );

        $response = [
            'success' => $permiso,
            'message' => $mensaje_permiso,
            'puede_editar' => $permiso
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        error_log("Acción no válida: $accion");
        throw new Exception('Acción no válida o parámetros incorrectos');
    }
} catch (Exception $e) {
    error_log("Error en ver_rotafolio_update.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());

    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'redirect' => "../dashboard/ver_rotafolio.php?id={$rotafolio_id}&mensaje=" . urlencode($e->getMessage()) . "&tipo=error"
        ]);
    } else {
        redirigir($rotafolio_id, $e->getMessage(), 'error');
    }
}
