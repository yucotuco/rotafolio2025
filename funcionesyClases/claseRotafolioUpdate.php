<?php
// funcionesyClases/claseRotafolioUpdate.php

class RotafolioUpdateManager
{
    private $pdo;
    private $error_message;

    // Constantes para tipos de archivos
    const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    const MAX_IMAGE_SIZE = 1048576; // 5MB
    const MAX_DOCUMENT_SIZE = 1048576; // 5MB

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->error_message = '';
    }

    public function getLastError()
    {
        return $this->error_message;
    }

    private function setError($message)
    {
        $this->error_message = $message;
        error_log("RotafolioUpdateManager Error: " . $message);
        return false;
    }

    // ====== VERIFICAR PERMISOS DE EDICIÓN ======

    public function verificarPermisoEdicion($post_id, $usuario_id, $visitante_id, $es_propietario_rotafolio)
    {
        // 1. Obtener información del post
        $stmt = $this->pdo->prepare("SELECT contenido, rotafolio_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            return [false, "El post no existe", null];
        }

        // 2. Extraer metadata
        $lines = explode("\n\n", $post['contenido'], 2);
        $metadata = count($lines) >= 2 ? json_decode($lines[0], true) : [];

        // 3. Propietario del rotafolio siempre puede editar
        if ($es_propietario_rotafolio) {
            return [true, "Permiso concedido", [
                'post' => $post,
                'metadata' => $metadata,
                'rotafolio_id' => $post['rotafolio_id']
            ]];
        }

        // 4. Usuario registrado - puede editar sus propios posts
        if ($usuario_id > 0) {
            // Verificar si el post fue creado por este usuario
            if (isset($metadata['v'])) {
                // Verificar si fue creado como usuario registrado
                if ($metadata['v'] === 'p_' . $usuario_id) {
                    return [true, "Permiso concedido (creador del post - usuario registrado)", [
                        'post' => $post,
                        'metadata' => $metadata,
                        'rotafolio_id' => $post['rotafolio_id']
                    ]];
                }

                // Verificar si fue creado como visitante y luego el usuario se registró
                if ($visitante_id && $metadata['v'] === $visitante_id) {
                    return [true, "Permiso concedido (creador del post - ex visitante)", [
                        'post' => $post,
                        'metadata' => $metadata,
                        'rotafolio_id' => $post['rotafolio_id']
                    ]];
                }
            }

            return [false, "No tienes permiso para editar este post", null];
        }

        // 5. Usuarios invitados NO PUEDEN EDITAR posts
        return [false, "Los usuarios invitados no pueden editar posts", null];
    }

    // ====== OBTENER DATOS DEL POST PARA EDICIÓN ======
    public function obtenerDatosEdicion($post_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, r.es_publico 
                FROM posts p
                LEFT JOIN rotafolios r ON p.rotafolio_id = r.id
                WHERE p.id = ?
            ");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return [false, "Post no encontrado"];
            }

            // Extraer contenido limpio
            $datos_extraidos = $this->extraerMetadata($post['contenido']);

            // Preparar datos para formulario
            $datos_formulario = [
                'post_id' => $post_id,
                'contenido' => $datos_extraidos['contenido_limpio'],
                'color' => $post['color'] ?? '#ffffff',
                'nombre_display' => $datos_extraidos['metadata']['n'] ?? 'Anónimo',
                'es_publico' => (bool)$post['es_publico']
            ];

            // Información de archivos actuales
            if (!empty($post['imagen_header'])) {
                $datos_formulario['imagen_header_actual'] = $post['imagen_header'];
                $datos_formulario['imagen_header_nombre'] = basename($post['imagen_header']);
            }

            $archivo_actual = !empty($post['archivo_adjunto']) ? $post['archivo_adjunto'] : $post['url_archivo'];
            if (!empty($archivo_actual)) {
                $datos_formulario['archivo_adjunto_actual'] = $archivo_actual;
                $datos_formulario['archivo_adjunto_nombre'] = basename($archivo_actual);
            }

            return [true, $datos_formulario];
        } catch (PDOException $e) {
            return [false, "Error al obtener datos: " . $e->getMessage()];
        }
    }

    // ====== ACTUALIZAR POST COMPLETO ======
    public function actualizarPostCompleto($post_id, $datos, $archivos = [], $es_publico = false)
    {
        try {
            error_log("INICIO actualizarPostCompleto - Post ID: $post_id");
            error_log("Datos recibidos: " . json_encode($datos));
            error_log("Archivos recibidos: " . (isset($archivos['imagen_header']) ? 'Sí imagen' : 'No imagen') . ", " . (isset($archivos['archivo_adjunto']) ? 'Sí archivo' : 'No archivo'));

            // 1. Obtener post actual
            $stmt = $this->pdo->prepare("SELECT contenido, color, imagen_header, archivo_adjunto, url_archivo, rotafolio_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post_actual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post_actual) {
                error_log("ERROR: Post no encontrado");
                return [false, "Post no encontrado"];
            }

            error_log("Post actual obtenido, rotafolio_id: " . $post_actual['rotafolio_id']);

            // 2. Extraer metadata actual
            $datos_actuales = $this->extraerMetadata($post_actual['contenido']);
            $metadata = $datos_actuales['metadata'];
            error_log("Metadata actual extraída: " . json_encode($metadata));

            // 3. Procesar archivos subidos
            $archivos_procesados = [];

            // Procesar imagen de encabezado
            if (isset($archivos['imagen_header']) && $archivos['imagen_header']['error'] === UPLOAD_ERR_OK) {
                error_log("Procesando imagen de encabezado...");
                $resultado = $this->procesarSubidaArchivo($archivos['imagen_header'], 'imagen', $es_publico);
                if ($resultado[0]) {
                    $archivos_procesados['imagen_header'] = $resultado[1];
                    // Eliminar imagen anterior si existe
                    if (!empty($post_actual['imagen_header'])) {
                        $this->eliminarArchivoFisico($post_actual['imagen_header']);
                    }
                    error_log("Imagen procesada: " . $resultado[1]);
                } else {
                    error_log("Error procesando imagen: " . $resultado[1]);
                    return [false, $resultado[1]];
                }
            }

            // Procesar archivo adjunto
            if (isset($archivos['archivo_adjunto']) && $archivos['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
                error_log("Procesando archivo adjunto...");
                $resultado = $this->procesarSubidaArchivo($archivos['archivo_adjunto'], 'documento', $es_publico);
                if ($resultado[0]) {
                    $archivos_procesados['archivo_adjunto'] = $resultado[1];
                    $archivos_procesados['url_archivo'] = $resultado[1];
                    // Eliminar archivo anterior si existe
                    $archivo_anterior = $post_actual['archivo_adjunto'] ?? $post_actual['url_archivo'];
                    if (!empty($archivo_anterior)) {
                        $this->eliminarArchivoFisico($archivo_anterior);
                    }
                    error_log("Archivo procesado: " . $resultado[1]);
                } else {
                    error_log("Error procesando archivo: " . $resultado[1]);
                    return [false, $resultado[1]];
                }
            }

            // 4. Actualizar metadata
            $metadata['e'] = time(); // Marcar como editado

            // Actualizar información de archivos en metadata
            if (isset($archivos_procesados['imagen_header'])) {
                $metadata['img_header'] = $archivos_procesados['imagen_header'];
            } elseif (isset($metadata['img_header'])) {
                // Mantener imagen existente
                $archivos_procesados['imagen_header'] = $metadata['img_header'];
            }

            if (isset($archivos_procesados['archivo_adjunto'])) {
                $metadata['archivo_adjunto'] = $archivos_procesados['archivo_adjunto'];
            } elseif (isset($metadata['archivo_adjunto'])) {
                // Mantener archivo existente
                $archivos_procesados['archivo_adjunto'] = $metadata['archivo_adjunto'];
            }

            // 5. Construir nuevo contenido
            $contenido_texto = trim($datos['contenido'] ?? '');
            if (empty($contenido_texto)) {
                error_log("ERROR: Contenido vacío");
                return [false, "El contenido es requerido"];
            }

            $nuevo_contenido = json_encode($metadata) . "\n\n" . $contenido_texto;
            error_log("Nuevo contenido construido, primeros 200 chars: " . substr($nuevo_contenido, 0, 200));

            // 6. Preparar datos para actualización
            $datos_actualizar = [
                'contenido' => $nuevo_contenido,
                'color' => $datos['color'] ?? $post_actual['color']
            ];

            // Agregar archivos procesados
            if (isset($archivos_procesados['imagen_header'])) {
                $datos_actualizar['imagen_header'] = $archivos_procesados['imagen_header'];
            }

            if (isset($archivos_procesados['archivo_adjunto'])) {
                $datos_actualizar['archivo_adjunto'] = $archivos_procesados['archivo_adjunto'];
                $datos_actualizar['url_archivo'] = $archivos_procesados['archivo_adjunto'];
            }

            // 7. Ejecutar actualización en la base de datos
            $permitidos = ['contenido', 'color', 'imagen_header', 'archivo_adjunto', 'url_archivo'];
            $sets = [];
            $valores = [];

            foreach ($datos_actualizar as $campo => $valor) {
                if (!in_array($campo, $permitidos)) continue;
                $sets[] = "$campo = ?";
                $valores[] = $valor;
                error_log("Campo a actualizar: $campo = " . (is_string($valor) ? substr($valor, 0, 50) : $valor));
            }

            if (empty($sets)) {
                error_log("ERROR: No hay datos válidos para actualizar");
                return [false, "No hay datos válidos para actualizar"];
            }

            $valores[] = $post_id;
            $sql = "UPDATE posts SET " . implode(', ', $sets) . " WHERE id = ?";

            error_log("SQL a ejecutar: $sql");
            error_log("Valores a ejecutar: " . json_encode($valores));

            $stmt = $this->pdo->prepare($sql);

            if ($stmt->execute($valores)) {
                $filas_afectadas = $stmt->rowCount();
                error_log("¡Actualización exitosa! Filas afectadas: $filas_afectadas");

                // Retornar datos para redirección
                return [
                    true,
                    "Post actualizado correctamente",
                    $post_id,
                    $post_actual['rotafolio_id'] // Agregar rotafolio_id para redirección
                ];
            } else {
                $error_info = $stmt->errorInfo();
                error_log("Error en ejecución SQL: " . json_encode($error_info));
                return [false, "Error al actualizar en la base de datos"];
            }
        } catch (PDOException $e) {
            error_log("Excepción en actualizarPostCompleto: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            return [false, "Error en la base de datos: " . $e->getMessage()];
        }
    }

    // ====== ACTUALIZAR POST CON REDIRECCIÓN ======
    public function actualizarPostYRedirigir($post_id, $datos, $archivos = [], $es_publico = false)
    {
        try {
            error_log("=== INICIO actualizarPostYRedirigir ===");

            // Obtener información del post para saber el rotafolio_id
            $stmt = $this->pdo->prepare("SELECT rotafolio_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post_info) {
                return [false, "Post no encontrado"];
            }

            $rotafolio_id = $post_info['rotafolio_id'];
            error_log("Rotafolio ID: $rotafolio_id");

            // Ejecutar actualización
            $resultado = $this->actualizarPostCompleto($post_id, $datos, $archivos, $es_publico);

            if ($resultado[0]) { // success
                error_log("Actualización exitosa, preparando redirección...");

                return [
                    'success' => true,
                    'message' => $resultado[1],
                    'post_id' => $resultado[2],
                    'redirect_url' => "../dashboard/ver_rotafolio.php?id=" . $rotafolio_id . "&mensaje=" . urlencode($resultado[1]) . "&tipo=success"
                ];
            } else {
                error_log("Error en actualización: " . $resultado[1]);
                return [
                    'success' => false,
                    'message' => $resultado[1],
                    'redirect_url' => "../dashboard/ver_rotafolio.php?id=" . $rotafolio_id . "&mensaje=" . urlencode($resultado[1]) . "&tipo=error"
                ];
            }
        } catch (Exception $e) {
            error_log("Error en actualizarPostYRedirigir: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error del sistema: " . $e->getMessage()
            ];
        }
    }

    // ====== FUNCIONES AUXILIARES ======

    private function extraerMetadata($contenido)
    {
        $lines = explode("\n\n", $contenido, 2);

        if (count($lines) >= 2) {
            $metadata = json_decode($lines[0], true) ?: [];
            $contenido_limpio = $lines[1];
        } else {
            $metadata = [];
            $contenido_limpio = $contenido;
        }

        return [
            'metadata' => $metadata,
            'contenido_limpio' => $contenido_limpio
        ];
    }

    private function procesarSubidaArchivo($file, $tipo = 'imagen', $es_publico = false)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [false, "Error en la subida del archivo"];
        }

        // Validar tipo
        if ($tipo === 'imagen') {
            $tipos_permitidos = self::IMAGE_MIME_TYPES;
            $max_size = self::MAX_IMAGE_SIZE;
            $subdir = $es_publico ? 'imagenes_publicas' : 'imagenes';
        } else {
            $tipos_permitidos = self::DOCUMENT_MIME_TYPES;
            $max_size = self::MAX_DOCUMENT_SIZE;
            $subdir = $es_publico ? 'archivos_publicos' : 'archivos';
        }

        if (!in_array($file['type'], $tipos_permitidos, true)) {
            return [false, "Tipo de archivo no permitido"];
        }

        if ($file['size'] > $max_size) {
            return [false, "Archivo demasiado grande. Máximo: " . ($max_size / 5120 / 5120) . "MB"];
        }

        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nombre_unico = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

        // Directorio de destino
        $base_path = dirname(dirname(__FILE__));
        $directorio_destino = $base_path . "/uploads/{$subdir}/";

        if (!file_exists($directorio_destino)) {
            @mkdir($directorio_destino, 0777, true);
        }

        $ruta_completa = $directorio_destino . $nombre_unico;

        if (move_uploaded_file($file['tmp_name'], $ruta_completa)) {
            $ruta_relativa = "uploads/{$subdir}/{$nombre_unico}";
            return [true, $ruta_relativa];
        } else {
            return [false, "Error al mover el archivo"];
        }
    }

    private function eliminarArchivoFisico($ruta_relativa)
    {
        if (!$ruta_relativa) return false;

        $base_path = dirname(dirname(__FILE__));
        $ruta_completa = $base_path . '/' . $ruta_relativa;

        if (file_exists($ruta_completa)) {
            return @unlink($ruta_completa);
        }
        return false;
    }

    // ====== OBTENER POST ACTUALIZADO PARA RENDERIZAR ======
    public function obtenerPostActualizado($post_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return [false, "Post no encontrado"];
            }

            // Procesar para renderizado
            $datos_extraidos = $this->extraerMetadata($post['contenido']);

            $post['contenido_limpio'] = $datos_extraidos['contenido_limpio'];
            $post['metadata'] = $datos_extraidos['metadata'];

            // Asegurar campos de archivos
            $post['imagen_header'] = $post['imagen_header'] ?? null;
            $post['archivo_adjunto'] = $post['archivo_adjunto'] ?? $post['url_archivo'] ?? null;

            return [true, $post];
        } catch (PDOException $e) {
            return [false, "Error al obtener post actualizado: " . $e->getMessage()];
        }
    }

    // ====== VALIDAR DATOS DE EDICIÓN ======
    public function validarDatosEdicion($datos)
    {
        $errores = [];

        // Validar contenido
        if (empty(trim($datos['contenido'] ?? ''))) {
            $errores[] = "El contenido es requerido";
        }

        // Validar color
        $color = $datos['color'] ?? '#ffffff';
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            $errores[] = "El color no es válido";
        }

        return empty($errores) ? [true, []] : [false, implode(", ", $errores)];
    }
}
