<?php
// funcionesyClases/claseRotafolio.php

class RotafolioManager
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
    const MAX_IMAGE_SIZE = 5048576; // 5MB
    const MAX_DOCUMENT_SIZE = 5048576; // 5MB

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
        error_log("RotafolioManager Error: " . $message);
        return false;
    }

    // Crear rotafolio
    public function crearRotafolio($usuario_id, $titulo, $descripcion = '', $layout = 'muro', $color_fondo = '#0dcaf0')
    {
        try {
            $url_compartir = md5(uniqid() . time());
            $stmt = $this->pdo->prepare(
                "INSERT INTO rotafolios
                (user_id, titulo, descripcion, layout, color_fondo, es_publico, permite_posts_publicos, url_compartir, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, NOW())"
            );
            if ($stmt->execute([$usuario_id, $titulo, $descripcion, $layout, $color_fondo, $url_compartir])) {
                return $this->pdo->lastInsertId();
            }
            return $this->setError("Error al ejecutar INSERT en crearRotafolio: " . implode(", ", $stmt->errorInfo()));
        } catch (PDOException $e) {
            return $this->setError("Error PDO en crearRotafolio: " . $e->getMessage());
        }
    }

    // Obtener rotafolios del usuario
    public function obtenerRotafoliosUsuario($usuario_id, $limit = 100, $offset = 0)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM rotafolios WHERE user_id = ? ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $usuario_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $this->setError("Error al obtener rotafolios: " . $e->getMessage());
        }
    }

    // Obtener rotafolio por ID
    public function obtenerRotafolioPorId($rotafolio_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM rotafolios WHERE id = ?");
            $stmt->execute([$rotafolio_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $this->setError("Error al obtener rotafolio: " . $e->getMessage());
        }
    }

    // Verificar propiedad
    public function esPropietarioRotafolio($rotafolio_id, $usuario_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rotafolios WHERE id = ? AND user_id = ?");
            $stmt->execute([$rotafolio_id, $usuario_id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return $this->setError("Error al verificar propiedad: " . $e->getMessage());
        }
    }

    // Actualizar rotafolio (whitelist)
    public function actualizarRotafolio($rotafolio_id, $datos)
    {
        try {
            $permitidos = ['titulo', 'descripcion', 'layout', 'color_fondo', 'imagen_fondo', 'es_publico', 'permite_posts_publicos', 'url_compartir'];
            $sets = [];
            $valores = [];
            foreach ($datos as $campo => $valor) {
                if (!in_array($campo, $permitidos, true)) continue;
                if ($valor === null) {
                    $sets[] = "$campo = NULL";
                } else {
                    $sets[] = "$campo = ?";
                    $valores[] = $valor;
                }
            }
            if (!$sets) return false;
            $valores[] = $rotafolio_id;
            $sql = "UPDATE rotafolios SET " . implode(', ', $sets) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($valores);
        } catch (PDOException $e) {
            return $this->setError("Error al actualizar rotafolio: " . $e->getMessage());
        }
    }

    // Crear post - AHORA UNIFICADO
    public function crearPost($rotafolio_id, $contenido, $posicion_x, $posicion_y, $tamanno = 'medio', $color = '#ffffff', $url_imagen_header = null, $url_archivo_adjunto = null)
    {
        try {
            $sql = "INSERT INTO posts (rotafolio_id, tipo, contenido, posicion_x, posicion_y, tamanno, color, url_archivo, imagen_header, archivo_adjunto, fecha_creacion)
                    VALUES (?, 'texto', ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $rotafolio_id,
                $contenido,
                $posicion_x,
                $posicion_y,
                $tamanno,
                $color,
                $url_archivo_adjunto, // archivo adjunto va en url_archivo
                $url_imagen_header,   // imagen header en nuevo campo
                $url_archivo_adjunto  // duplicado en archivo_adjunto para compatibilidad
            ]);
            if (!$ok) {
                return $this->setError("Error al ejecutar INSERT en crearPost: " . implode(", ", $stmt->errorInfo()));
            }
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            return $this->setError("Error PDO en crearPost: " . $e->getMessage());
        }
    }

    // Crear post público - ACTUALIZADO PARA NUEVA ESTRUCTURA (CORREGIDO PARA ACEPTAR COLOR)
    public function crearPostPublico($rotafolio_id, $contenido, $posicion_x, $posicion_y, $color = '#ffffff', $url_imagen_header = null, $url_archivo_adjunto = null)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT es_publico, permite_posts_publicos FROM rotafolios WHERE id = ?");
            $stmt->execute([$rotafolio_id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debe ser público y permitir posts públicos
            if (!$resultado || (int)$resultado['es_publico'] !== 1 || (int)$resultado['permite_posts_publicos'] !== 1) {
                return $this->setError("Este rotafolio no permite posts públicos.");
            }

            // PASAR EL COLOR AL MÉTODO crearPost (CORRECCIÓN APLICADA)
            return $this->crearPost($rotafolio_id, $contenido, $posicion_x, $posicion_y, 'medio', $color, $url_imagen_header, $url_archivo_adjunto);
        } catch (PDOException $e) {
            return $this->setError("Error al crear post público: " . $e->getMessage());
        }
    }

    // Obtener posts por rotafolio - ACTUALIZADO
    public function obtenerPostsRotafolio($rotafolio_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE rotafolio_id = ? ORDER BY fecha_creacion DESC");
            $stmt->execute([$rotafolio_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $this->setError("Error al obtener posts: " . $e->getMessage());
        }
    }



    // Eliminar post (nuevo método mejorado)
    public function eliminarPost($post_id, $rotafolio_id)
    {
        try {
            // Primero obtener información del post para eliminar archivos
            $stmt = $this->pdo->prepare("SELECT imagen_header, archivo_adjunto, url_archivo FROM posts WHERE id = ? AND rotafolio_id = ?");
            $stmt->execute([$post_id, $rotafolio_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($post) {
                // Eliminar archivos físicos si existen
                $this->eliminarArchivosPost($post_id);

                // Eliminar el registro de la base de datos
                $stmt = $this->pdo->prepare("DELETE FROM posts WHERE id = ? AND rotafolio_id = ?");
                return $stmt->execute([$post_id, $rotafolio_id]);
            }
            return false;
        } catch (PDOException $e) {
            return $this->setError("Error al eliminar post: " . $e->getMessage());
        }
    }

    // Eliminar rotafolio (CASCADE borra posts)
    public function eliminarRotafolio($rotafolio_id, $usuario_id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM rotafolios WHERE id = ? AND user_id = ?");
            return $stmt->execute([$rotafolio_id, $usuario_id]);
        } catch (PDOException $e) {
            return $this->setError("Error al eliminar rotafolio: " . $e->getMessage());
        }
    }

    // Duplicar rotafolio con transacción
    public function duplicarRotafolio($rotafolio_id, $usuario_id)
    {
        try {
            $this->pdo->beginTransaction();
            $rotafolio = $this->obtenerRotafolioPorId($rotafolio_id);
            if (!$rotafolio) {
                $this->pdo->rollBack();
                return false;
            }
            $nuevo_titulo = "Copia de " . $rotafolio['titulo'];
            $nuevo_id = $this->crearRotafolio(
                $usuario_id,
                $nuevo_titulo,
                $rotafolio['descripcion'],
                $rotafolio['layout'],
                $rotafolio['color_fondo']
            );
            if (!$nuevo_id) {
                $this->pdo->rollBack();
                return false;
            }
            $posts = $this->obtenerPostsRotafolio($rotafolio_id);
            foreach ($posts as $post) {
                $ok = $this->crearPost(
                    $nuevo_id,
                    $post['contenido'],
                    $post['posicion_x'],
                    $post['posicion_y'],
                    $post['tamanno'],
                    $post['color'],
                    $post['imagen_header'],
                    $post['archivo_adjunto'] ?? $post['url_archivo']
                );
                if (!$ok) {
                    $this->pdo->rollBack();
                    return false;
                }
            }
            $this->pdo->commit();
            return $nuevo_id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return $this->setError("Error al duplicar rotafolio: " . $e->getMessage());
        }
    }

    // Contar posts por múltiples rotafolios
    public function contarPostsPorRotafolios(array $rotafolioIds): array
    {
        if (empty($rotafolioIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($rotafolioIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT rotafolio_id, COUNT(*) AS cnt
                 FROM posts
                 WHERE rotafolio_id IN ($placeholders)
                 GROUP BY rotafolio_id"
            );
            $stmt->execute($rotafolioIds);
            $res = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $res[(int)$row['rotafolio_id']] = (int)$row['cnt'];
            }
            return $res;
        } catch (PDOException $e) {
            $this->setError("Error al contar posts: " . $e->getMessage());
            return [];
        }
    }

    // ====== MÉTODO AUXILIAR MEJORADO: Procesar subida de archivos ======
    public function procesarSubidaArchivo($file, $tipo = 'imagen', $es_publico = false)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [false, "Error en la subida del archivo"];
        }

        // Validar tipo de archivo
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
            return [false, "Archivo demasiado grande. Máximo: " . ($max_size / 1024 / 1024) . "MB"];
        }

        // Generar nombre único seguro
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

    // ====== MÉTODO MEJORADO: Extraer metadatos de post ======
    public function extraerMetadataPost($post_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT contenido, color, imagen_header, archivo_adjunto, url_archivo FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) return null;

            $lines = explode("\n\n", $post['contenido'], 2);

            if (count($lines) >= 2) {
                $metadata = json_decode($lines[0], true) ?: [];
                $contenido_html = $lines[1];
                return [
                    'metadata' => $metadata,
                    'contenido' => $contenido_html,
                    'color' => $post['color'] ?? '#ffffff',
                    'imagen_header' => $post['imagen_header'] ?? null,
                    'archivo_adjunto' => $post['archivo_adjunto'] ?? $post['url_archivo'] ?? null
                ];
            } else {
                // Si no hay metadatos, devolver todo como contenido
                return [
                    'metadata' => [],
                    'contenido' => $post['contenido'],
                    'color' => $post['color'] ?? '#ffffff',
                    'imagen_header' => $post['imagen_header'] ?? null,
                    'archivo_adjunto' => $post['archivo_adjunto'] ?? $post['url_archivo'] ?? null
                ];
            }
        } catch (PDOException $e) {
            error_log("Error al extraer metadata: " . $e->getMessage());
            return null;
        }
    }

    // Obtener post por ID con información completa
    public function obtenerPostPorId($post_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($post) {
                // Extraer contenido limpio
                $lines = explode("\n\n", $post['contenido'], 2);
                if (count($lines) >= 2) {
                    $metadata = json_decode($lines[0], true) ?: [];
                    $post['contenido_limpio'] = $lines[1];
                    $post['metadata'] = $metadata;
                } else {
                    $post['contenido_limpio'] = $post['contenido'];
                    $post['metadata'] = [];
                }

                // Asegurar que los campos de archivos existan
                $post['imagen_header'] = $post['imagen_header'] ?? null;
                $post['archivo_adjunto'] = $post['archivo_adjunto'] ?? null;
                $post['url_archivo'] = $post['url_archivo'] ?? null;
            }

            return $post;
        } catch (PDOException $e) {
            $this->setError("Error al obtener post: " . $e->getMessage());
            return false;
        }
    }

    // ====== MÉTODO MEJORADO: Verificar permisos para editar un post ======
    public function verificarPermisoEdicionPost($post_id, $usuario_id, $visitante_id = null, $es_propietario_rotafolio = false)
    {
        try {
            // Primero obtener el post
            $stmt = $this->pdo->prepare("SELECT p.*, r.user_id as rotafolio_user_id FROM posts p 
                                       LEFT JOIN rotafolios r ON p.rotafolio_id = r.id 
                                       WHERE p.id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return [false, "Post no encontrado"];
            }

            // Si es propietario del rotafolio, puede editar cualquier post
            if ($es_propietario_rotafolio && $post['rotafolio_user_id'] == $usuario_id) {
                return [true, "Permiso concedido (propietario del rotafolio)"];
            }

            // Extraer metadata del contenido
            $lines = explode("\n\n", $post['contenido'], 2);
            $metadata = [];

            if (count($lines) >= 2) {
                $metadata = json_decode($lines[0], true) ?: [];
            }

            // Verificar si el post fue creado por este usuario
            if (isset($metadata['v'])) {
                // Si es un usuario registrado que creó el post
                if ($usuario_id > 0 && $metadata['v'] === 'p_' . $usuario_id) {
                    return [true, "Permiso concedido (creador del post)"];
                }

                // Si es un visitante que creó el post
                if ($visitante_id && $metadata['v'] === $visitante_id) {
                    return [true, "Permiso concedido (creador del post - visitante)"];
                }
            }

            return [false, "No tienes permiso para editar este post"];
        } catch (PDOException $e) {
            return [false, "Error al verificar permisos: " . $e->getMessage()];
        }
    }

    // ====== MÉTODO MEJORADO: Actualizar post con contenido simple ======
    public function actualizarPostSimple($post_id, $datos, $archivos = [], $es_publico = false)
    {
        try {
            error_log("DEBUG actualizarPostSimple: Iniciando actualización del post ID: " . $post_id);

            // 1. Verificar que el post existe
            $stmt = $this->pdo->prepare("SELECT contenido, imagen_header, archivo_adjunto, url_archivo, rotafolio_id FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post_actual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post_actual) {
                error_log("DEBUG actualizarPostSimple: Post no encontrado ID: " . $post_id);
                return [false, "Post no encontrado"];
            }

            error_log("DEBUG actualizarPostSimple: Post encontrado, procesando...");

            // 2. Procesar archivos subidos
            $archivos_procesados = [];

            // Procesar imagen de encabezado
            if (isset($archivos['imagen_header']) && $archivos['imagen_header']['error'] === UPLOAD_ERR_OK) {
                error_log("DEBUG actualizarPostSimple: Procesando imagen de encabezado");
                $resultado = $this->procesarSubidaArchivo($archivos['imagen_header'], 'imagen', $es_publico);
                if ($resultado[0]) {
                    $archivos_procesados['imagen_header'] = $resultado[1];
                    // Eliminar imagen anterior si existe
                    if (!empty($post_actual['imagen_header'])) {
                        $this->eliminarArchivoFisico($post_actual['imagen_header']);
                        error_log("DEBUG actualizarPostSimple: Imagen anterior eliminada: " . $post_actual['imagen_header']);
                    }
                    error_log("DEBUG actualizarPostSimple: Imagen procesada: " . $resultado[1]);
                } else {
                    error_log("DEBUG actualizarPostSimple: Error al procesar imagen: " . $resultado[1]);
                    return [false, $resultado[1]];
                }
            } else {
                error_log("DEBUG actualizarPostSimple: No hay imagen nueva o error en upload");
            }

            // Procesar archivo adjunto
            if (isset($archivos['archivo_adjunto']) && $archivos['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
                error_log("DEBUG actualizarPostSimple: Procesando archivo adjunto");
                $resultado = $this->procesarSubidaArchivo($archivos['archivo_adjunto'], 'documento', $es_publico);
                if ($resultado[0]) {
                    $archivos_procesados['archivo_adjunto'] = $resultado[1];
                    $archivos_procesados['url_archivo'] = $resultado[1];
                    // Eliminar archivo anterior si existe
                    $archivo_anterior = $post_actual['archivo_adjunto'] ?? $post_actual['url_archivo'];
                    if (!empty($archivo_anterior)) {
                        $this->eliminarArchivoFisico($archivo_anterior);
                        error_log("DEBUG actualizarPostSimple: Archivo anterior eliminado: " . $archivo_anterior);
                    }
                    error_log("DEBUG actualizarPostSimple: Archivo procesado: " . $resultado[1]);
                } else {
                    error_log("DEBUG actualizarPostSimple: Error al procesar archivo: " . $resultado[1]);
                    return [false, $resultado[1]];
                }
            } else {
                error_log("DEBUG actualizarPostSimple: No hay archivo adjunto nuevo o error en upload");
            }

            // 3. Extraer y actualizar metadata
            $contenido_actual = $post_actual['contenido'];
            $lines = explode("\n\n", $contenido_actual, 2);
            $metadata = [];
            $contenido_html = '';

            if (count($lines) >= 2) {
                $metadata = json_decode($lines[0], true) ?: [];
                $contenido_html = $lines[1];
                error_log("DEBUG actualizarPostSimple: Metadata extraída: " . json_encode($metadata));
            } else {
                $contenido_html = $contenido_actual;
                error_log("DEBUG actualizarPostSimple: No se encontró metadata, usando contenido completo");
            }

            // Actualizar metadata
            $metadata['e'] = time(); // Marcar como editado
            error_log("DEBUG actualizarPostSimple: Timestamp de edición agregado: " . $metadata['e']);

            // Mantener información original si existe
            if (!isset($metadata['t'])) {
                $metadata['t'] = time();
                error_log("DEBUG actualizarPostSimple: Timestamp original establecido: " . $metadata['t']);
            }

            // Actualizar información de archivos en metadata
            if (isset($archivos_procesados['imagen_header'])) {
                $metadata['img_header'] = $archivos_procesados['imagen_header'];
                error_log("DEBUG actualizarPostSimple: Metadata img_header actualizada: " . $archivos_procesados['imagen_header']);
            } elseif (isset($metadata['img_header'])) {
                // Mantener la imagen existente en metadata
                $archivos_procesados['imagen_header'] = $metadata['img_header'];
                error_log("DEBUG actualizarPostSimple: Manteniendo imagen existente en metadata");
            }

            if (isset($archivos_procesados['archivo_adjunto'])) {
                $metadata['archivo_adjunto'] = $archivos_procesados['archivo_adjunto'];
                error_log("DEBUG actualizarPostSimple: Metadata archivo_adjunto actualizada: " . $archivos_procesados['archivo_adjunto']);
            } elseif (isset($metadata['archivo_adjunto'])) {
                // Mantener el archivo existente en metadata
                $archivos_procesados['archivo_adjunto'] = $metadata['archivo_adjunto'];
                error_log("DEBUG actualizarPostSimple: Manteniendo archivo existente en metadata");
            }

            // 4. Construir nuevo contenido
            $nuevo_contenido = json_encode($metadata) . "\n\n" . ($datos['contenido'] ?? $contenido_html);
            error_log("DEBUG actualizarPostSimple: Nuevo contenido construido, primeros 100 chars: " . substr($nuevo_contenido, 0, 100));

            // 5. Preparar datos para actualización
            $datos_actualizar = [
                'contenido' => $nuevo_contenido
            ];

            if (isset($datos['color'])) {
                $datos_actualizar['color'] = $datos['color'];
                error_log("DEBUG actualizarPostSimple: Color a actualizar: " . $datos['color']);
            }

            if (isset($archivos_procesados['imagen_header'])) {
                $datos_actualizar['imagen_header'] = $archivos_procesados['imagen_header'];
                error_log("DEBUG actualizarPostSimple: Imagen header a actualizar: " . $archivos_procesados['imagen_header']);
            }

            if (isset($archivos_procesados['archivo_adjunto'])) {
                $datos_actualizar['archivo_adjunto'] = $archivos_procesados['archivo_adjunto'];
                $datos_actualizar['url_archivo'] = $archivos_procesados['archivo_adjunto'];
                error_log("DEBUG actualizarPostSimple: Archivo adjunto a actualizar: " . $archivos_procesados['archivo_adjunto']);
            }

            // 6. Ejecutar actualización
            $permitidos = ['contenido', 'color', 'imagen_header', 'archivo_adjunto', 'url_archivo'];
            $sets = [];
            $valores = [];

            foreach ($datos_actualizar as $campo => $valor) {
                if (!in_array($campo, $permitidos, true)) continue;
                $sets[] = "$campo = ?";
                $valores[] = $valor;
                error_log("DEBUG actualizarPostSimple: Campo a actualizar: $campo = $valor");
            }

            if (!$sets) {
                error_log("DEBUG actualizarPostSimple: No hay datos válidos para actualizar");
                return [false, "No hay datos válidos para actualizar"];
            }

            $valores[] = $post_id;
            $sql = "UPDATE posts SET " . implode(', ', $sets) . " WHERE id = ?";
            error_log("DEBUG actualizarPostSimple: SQL: " . $sql);
            error_log("DEBUG actualizarPostSimple: Valores: " . json_encode($valores));

            $stmt = $this->pdo->prepare($sql);

            if ($stmt->execute($valores)) {
                $rows = $stmt->rowCount();
                error_log("DEBUG actualizarPostSimple: Actualización exitosa. Filas afectadas: " . $rows);
                return [true, "Post actualizado correctamente", $post_id];
            } else {
                $error_info = $stmt->errorInfo();
                error_log("DEBUG actualizarPostSimple: Error en ejecución SQL: " . json_encode($error_info));
                return [false, "Error al actualizar: " . ($error_info[2] ?? 'desconocido')];
            }
        } catch (PDOException $e) {
            error_log("DEBUG actualizarPostSimple: Excepción PDO: " . $e->getMessage());
            return [false, "Error en la base de datos: " . $e->getMessage()];
        }
    }

    // ====== MÉTODO AUXILIAR: Eliminar archivo físico ======
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

    // Obtener datos de post para formulario de edición
    public function obtenerDatosParaEdicion($post_id, $usuario_id, $visitante_id = null, $es_propietario_rotafolio = false)
    {
        try {
            // Obtener post
            $post = $this->obtenerPostPorId($post_id);
            if (!$post) {
                return [false, "Post no encontrado"];
            }

            // Verificar permisos
            list($permiso, $mensaje) = $this->verificarPermisoEdicionPost($post_id, $usuario_id, $visitante_id, $es_propietario_rotafolio);
            if (!$permiso) {
                return [false, $mensaje];
            }

            // Extraer contenido limpio
            $contenido_limpio = $post['contenido_limpio'] ?? $post['contenido'];

            // Preparar datos para el formulario
            $datos_formulario = [
                'post_id' => $post_id,
                'contenido' => $contenido_limpio,
                'color' => $post['color'] ?? '#ffffff',
                'nombre_display' => $post['metadata']['n'] ?? 'Anónimo'
            ];

            // Información de archivos adjuntos
            if (!empty($post['imagen_header'])) {
                $datos_formulario['imagen_header_actual'] = $post['imagen_header'];
                $datos_formulario['imagen_header_nombre'] = basename($post['imagen_header']);
            }

            $archivo = !empty($post['archivo_adjunto']) ? $post['archivo_adjunto'] : $post['url_archivo'];
            if (!empty($archivo)) {
                $datos_formulario['archivo_adjunto_actual'] = $archivo;
                $datos_formulario['archivo_adjunto_nombre'] = basename($archivo);
            }

            return [true, $datos_formulario];
        } catch (PDOException $e) {
            return [false, "Error al obtener datos para edición: " . $e->getMessage()];
        }
    }

    // ====== MÉTODO MEJORADO: Eliminar archivos asociados a un post ======
    public function eliminarArchivosPost($post_id)
    {
        try {
            $post = $this->obtenerPostPorId($post_id);
            if (!$post) {
                return false;
            }

            $base_path = dirname(dirname(__FILE__));
            $archivos_eliminar = [];

            // Agregar archivos de la base de datos
            if (!empty($post['imagen_header'])) {
                $archivos_eliminar[] = $post['imagen_header'];
            }
            if (!empty($post['archivo_adjunto'])) {
                $archivos_eliminar[] = $post['archivo_adjunto'];
            }
            if (!empty($post['url_archivo'])) {
                $archivos_eliminar[] = $post['url_archivo'];
            }

            // Agregar archivos de metadata
            $metadata = $post['metadata'] ?? [];
            if (!empty($metadata['img_header'])) {
                $archivos_eliminar[] = $metadata['img_header'];
            }
            if (!empty($metadata['archivo_adjunto'])) {
                $archivos_eliminar[] = $metadata['archivo_adjunto'];
            }

            // Eliminar archivos únicos
            $archivos_eliminar = array_unique(array_filter($archivos_eliminar));

            foreach ($archivos_eliminar as $archivo) {
                if ($archivo) {
                    $ruta_completa = $base_path . '/' . $archivo;
                    if (file_exists($ruta_completa)) {
                        @unlink($ruta_completa);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error al eliminar archivos del post: " . $e->getMessage());
            return false;
        }
    }

    // Incrementar vistas de un rotafolio
    public function incrementarVistas($rotafolio_id)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE rotafolios SET vistas = COALESCE(vistas, 0) + 1 WHERE id = ?");
            return $stmt->execute([$rotafolio_id]);
        } catch (PDOException $e) {
            error_log("Error al incrementar vistas: " . $e->getMessage());
            return false;
        }
    }

    // Obtener estadísticas de posts por usuario en un rotafolio
    public function obtenerEstadisticasPosts($rotafolio_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_posts,
                    COUNT(DISTINCT CASE WHEN contenido LIKE '%\"e\":%' THEN id END) as posts_editados,
                    COUNT(CASE WHEN imagen_header IS NOT NULL THEN 1 END) as posts_con_imagen,
                    COUNT(CASE WHEN archivo_adjunto IS NOT NULL OR url_archivo IS NOT NULL THEN 1 END) as posts_con_archivo
                FROM posts 
                WHERE rotafolio_id = ?
            ");
            $stmt->execute([$rotafolio_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas: " . $e->getMessage());
            return null;
        }
    }

    // Verificar si un post pertenece a un rotafolio
    public function postPerteneceARotafolio($post_id, $rotafolio_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts WHERE id = ? AND rotafolio_id = ?");
            $stmt->execute([$post_id, $rotafolio_id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar pertenencia: " . $e->getMessage());
            return false;
        }
    }

    // Obtener posts creados por un visitante específico
    public function obtenerPostsPorVisitante($rotafolio_id, $visitante_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.* 
                FROM posts p 
                WHERE p.rotafolio_id = ? 
                AND p.contenido LIKE CONCAT('%\"v\":\"', ?, '\"%')
                ORDER BY p.fecha_creacion DESC
            ");
            $stmt->execute([$rotafolio_id, $visitante_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener posts por visitante: " . $e->getMessage());
            return [];
        }
    }

    // Eliminar posts antiguos de visitantes (limpieza automática)
    public function limpiarPostsAntiguos($dias = 30)
    {
        try {
            $fecha_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));

            // Primero obtener posts a eliminar para borrar sus archivos
            $stmt = $this->pdo->prepare("
                SELECT id FROM posts 
                WHERE contenido LIKE '%\"p\":0%' 
                AND contenido LIKE '%\"v\":\"visitor_%\"' 
                AND fecha_creacion < ?
            ");
            $stmt->execute([$fecha_limite]);
            $posts_a_eliminar = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Eliminar archivos de cada post
            foreach ($posts_a_eliminar as $post_id) {
                $this->eliminarArchivosPost($post_id);
            }

            // Eliminar los posts de la base de datos
            $stmt = $this->pdo->prepare("
                DELETE FROM posts 
                WHERE contenido LIKE '%\"p\":0%' 
                AND contenido LIKE '%\"v\":\"visitor_%\"' 
                AND fecha_creacion < ?
            ");
            $eliminados = $stmt->execute([$fecha_limite]);

            return $eliminados ? count($posts_a_eliminar) : 0;
        } catch (PDOException $e) {
            error_log("Error al limpiar posts antiguos: " . $e->getMessage());
            return 0;
        }
    }

    // ====== NUEVO MÉTODO: Obtener información básica de post para edición rápida ======
    public function obtenerInfoBasicaPost($post_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.contenido, p.color, p.imagen_header, p.archivo_adjunto, p.url_archivo, 
                       p.rotafolio_id, r.es_publico
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
            $lines = explode("\n\n", $post['contenido'], 2);
            $contenido_limpio = '';

            if (count($lines) >= 2) {
                $contenido_limpio = $lines[1];
            } else {
                $contenido_limpio = $post['contenido'];
            }

            return [true, [
                'id' => $post['id'],
                'contenido' => $contenido_limpio,
                'color' => $post['color'] ?? '#ffffff',
                'imagen_header' => $post['imagen_header'],
                'archivo_adjunto' => $post['archivo_adjunto'] ?? $post['url_archivo'],
                'rotafolio_id' => $post['rotafolio_id'],
                'es_publico' => (bool)$post['es_publico']
            ]];
        } catch (PDOException $e) {
            return [false, "Error al obtener información del post: " . $e->getMessage()];
        }
    }

    // ====== NUEVO MÉTODO: Verificar si usuario puede eliminar post ======
    public function puedeEliminarPost($post_id, $usuario_id, $visitante_id = null)
    {
        try {
            // Obtener información del post y su rotafolio
            $stmt = $this->pdo->prepare("
                SELECT p.*, r.user_id as propietario_rotafolio, r.es_publico
                FROM posts p
                LEFT JOIN rotafolios r ON p.rotafolio_id = r.id
                WHERE p.id = ?
            ");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return [false, "Post no encontrado"];
            }

            // Si es propietario del rotafolio, puede eliminar cualquier post
            if ($post['propietario_rotafolio'] == $usuario_id) {
                return [true, "Permiso concedido (propietario del rotafolio)"];
            }

            // Extraer metadata
            $lines = explode("\n\n", $post['contenido'], 2);
            $metadata = [];

            if (count($lines) >= 2) {
                $metadata = json_decode($lines[0], true) ?: [];
            }

            // Verificar si es el creador del post
            if (isset($metadata['v'])) {
                // Usuario registrado
                if ($usuario_id > 0 && $metadata['v'] === 'p_' . $usuario_id) {
                    return [true, "Permiso concedido (creador del post)"];
                }

                // Visitante
                if ($visitante_id && $metadata['v'] === $visitante_id) {
                    return [true, "Permiso concedido (creador del post - visitante)"];
                }
            }

            return [false, "No tienes permiso para eliminar este post"];
        } catch (PDOException $e) {
            return [false, "Error al verificar permisos: " . $e->getMessage()];
        }
    }

    // ====== NUEVO MÉTODO: Reemplazar archivo de post ======
    public function reemplazarArchivoPost($post_id, $tipo_archivo, $nuevo_archivo, $es_publico = false)
    {
        try {
            // Obtener archivo actual
            $stmt = $this->pdo->prepare("SELECT imagen_header, archivo_adjunto, url_archivo FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                return [false, "Post no encontrado"];
            }

            // Determinar qué archivo reemplazar
            $campo_bd = '';
            $archivo_actual = '';

            if ($tipo_archivo === 'imagen') {
                $campo_bd = 'imagen_header';
                $archivo_actual = $post['imagen_header'];
            } else {
                $campo_bd = 'archivo_adjunto';
                $archivo_actual = $post['archivo_adjunto'] ?? $post['url_archivo'];
            }

            // Procesar nuevo archivo
            $resultado = $this->procesarSubidaArchivo($nuevo_archivo, $tipo_archivo, $es_publico);
            if (!$resultado[0]) {
                return [false, $resultado[1]];
            }

            $nueva_ruta = $resultado[1];

            // Actualizar en base de datos
            if ($tipo_archivo === 'imagen') {
                $sql = "UPDATE posts SET imagen_header = ? WHERE id = ?";
            } else {
                $sql = "UPDATE posts SET archivo_adjunto = ?, url_archivo = ? WHERE id = ?";
            }

            $stmt = $this->pdo->prepare($sql);

            if ($tipo_archivo === 'imagen') {
                $stmt->execute([$nueva_ruta, $post_id]);
            } else {
                $stmt->execute([$nueva_ruta, $nueva_ruta, $post_id]);
            }

            // Eliminar archivo anterior si existe
            if (!empty($archivo_actual)) {
                $this->eliminarArchivoFisico($archivo_actual);
            }

            return [true, "Archivo actualizado correctamente", $nueva_ruta];
        } catch (PDOException $e) {
            return [false, "Error al reemplazar archivo: " . $e->getMessage()];
        }
    }
}
