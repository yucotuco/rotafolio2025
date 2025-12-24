<?php
// funcionesyClases/claseDashboard.php

class DashboardManager
{
    private $pdo;
    private $usuario_id;

    public function __construct($pdo, $usuario_id)
    {
        $this->pdo = $pdo;
        $this->usuario_id = $usuario_id;
    }

    /**
     * Obtener información del plan del usuario
     */
    public function obtenerPlanUsuario()
    {
        try {
            $sql = "SELECT plan FROM usuario WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->usuario_id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            // Definir límites según el plan
            $limites = [
                'free' => [
                    'max_rotafolios' => 3,
                    'max_espacio_mb' => 100,
                    'nombre' => 'Free',
                    'color' => '#6c757d'
                ],
                'basic' => [
                    'max_rotafolios' => 10,
                    'max_espacio_mb' => 250,
                    'nombre' => 'Basic',
                    'color' => '#0dcaf0'
                ],
                'pro' => [
                    'max_rotafolios' => 20,
                    'max_espacio_mb' => 500,
                    'nombre' => 'Pro',
                    'color' => '#6f42c1'
                ],
                'premium' => [
                    'max_rotafolios' => 50,
                    'max_espacio_mb' => 1024,
                    'nombre' => 'Premium',
                    'color' => '#fd7e14'
                ]
            ];

            $plan_tipo = $plan['plan'] ?? 'free';
            return $limites[$plan_tipo] ?? $limites['free'];
        } catch (PDOException $e) {
            error_log("Error al obtener plan: " . $e->getMessage());
            return $limites['free'];
        }
    }

    /**
     * Obtener rotafolios del usuario
     */
    public function obtenerRotafolios($limit = 10, $offset = 0)
    {
        try {
            $sql = "SELECT r.*, 
                    COUNT(p.id) as total_posts,
                    MAX(p.fecha_creacion) as ultima_actividad
                    FROM rotafolios r 
                    LEFT JOIN posts p ON r.id = p.rotafolio_id
                    WHERE r.usuario_id = ? 
                    AND r.estado = 'activo'
                    GROUP BY r.id
                    ORDER BY r.fecha_actualizacion DESC
                    LIMIT ? OFFSET ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->usuario_id, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener rotafolios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de rotafolios del usuario
     */
    public function contarRotafolios()
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM rotafolios 
                    WHERE usuario_id = ? AND estado = 'activo'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->usuario_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error al contar rotafolios: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function obtenerEstadisticas($periodo = 'mes')
    {
        try {
            $estadisticas = [];

            // Obtener espacio usado
            $sql_espacio = "SELECT SUM(tamanio_mb) as total_espacio 
                           FROM archivos 
                           WHERE usuario_id = ?";
            $stmt = $this->pdo->prepare($sql_espacio);
            $stmt->execute([$this->usuario_id]);
            $espacio = $stmt->fetch(PDO::FETCH_ASSOC);
            $estadisticas['espacio_usado_mb'] = $espacio['total_espacio'] ?? 0;

            // Posts creados este mes
            $sql_posts = "SELECT COUNT(*) as total 
                         FROM posts 
                         WHERE usuario_id = ? 
                         AND MONTH(fecha_creacion) = MONTH(CURRENT_DATE())
                         AND YEAR(fecha_creacion) = YEAR(CURRENT_DATE())";
            $stmt = $this->pdo->prepare($sql_posts);
            $stmt->execute([$this->usuario_id]);
            $posts = $stmt->fetch(PDO::FETCH_ASSOC);
            $estadisticas['posts_creados'] = $posts['total'] ?? 0;

            // Rotafolios creados este mes
            $sql_rotafolios = "SELECT COUNT(*) as total 
                              FROM rotafolios 
                              WHERE usuario_id = ? 
                              AND MONTH(fecha_creacion) = MONTH(CURRENT_DATE())
                              AND YEAR(fecha_creacion) = YEAR(CURRENT_DATE())";
            $stmt = $this->pdo->prepare($sql_rotafolios);
            $stmt->execute([$this->usuario_id]);
            $rotafolios = $stmt->fetch(PDO::FETCH_ASSOC);
            $estadisticas['rotafolios_creados'] = $rotafolios['total'] ?? 0;

            return $estadisticas;
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas: " . $e->getMessage());
            return [
                'espacio_usado_mb' => 0,
                'posts_creados' => 0,
                'rotafolios_creados' => 0
            ];
        }
    }

    /**
     * Obtener actividad reciente
     */
    public function obtenerActividadReciente($limit = 5)
    {
        try {
            $sql = "SELECT r.id, r.titulo, r.descripcion, r.layout, r.url_unica,
                    COUNT(p.id) as total_posts,
                    MAX(p.fecha_creacion) as ultima_actividad
                    FROM rotafolios r 
                    LEFT JOIN posts p ON r.id = p.rotafolio_id
                    WHERE r.usuario_id = ? 
                    AND r.estado = 'activo'
                    GROUP BY r.id
                    ORDER BY r.fecha_actualizacion DESC
                    LIMIT ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->usuario_id, $limit]);
            $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear fecha de última actividad
            foreach ($actividad as &$item) {
                if ($item['ultima_actividad']) {
                    $item['ultima_actividad'] = $this->formatearFecha($item['ultima_actividad']);
                } else {
                    $item['ultima_actividad'] = 'Nunca';
                }
            }

            return $actividad;
        } catch (PDOException $e) {
            error_log("Error al obtener actividad: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crear nuevo rotafolio
     */
    public function crearRotafolio($titulo, $descripcion = '', $layout = 'muro', $color_fondo = '#0dcaf0')
    {
        try {
            // Verificar límite de rotafolios
            $plan = $this->obtenerPlanUsuario();
            $total_rotafolios = $this->contarRotafolios();

            if ($total_rotafolios >= $plan['max_rotafolios']) {
                return [
                    'success' => false,
                    'error' => 'Has alcanzado el límite de rotafolios de tu plan'
                ];
            }

            // Generar URL única
            $url_unica = $this->generarUrlUnica();

            // Insertar rotafolio
            $sql = "INSERT INTO rotafolios 
                   (usuario_id, titulo, descripcion, layout, color_fondo, url_unica, fecha_creacion, fecha_actualizacion) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->usuario_id,
                $titulo,
                $descripcion,
                $layout,
                $color_fondo,
                $url_unica
            ]);

            $rotafolio_id = $this->pdo->lastInsertId();

            return [
                'success' => true,
                'id' => $rotafolio_id,
                'url' => $url_unica,
                'mensaje' => 'Rotafolio creado exitosamente'
            ];
        } catch (PDOException $e) {
            error_log("Error al crear rotafolio: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear el rotafolio. Intenta nuevamente.'
            ];
        }
    }

    /**
     * Duplicar rotafolio
     */
    public function duplicarRotafolio($rotafolio_id)
    {
        try {
            // Obtener rotafolio original
            $sql = "SELECT * FROM rotafolios 
                   WHERE id = ? AND usuario_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$rotafolio_id, $this->usuario_id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                return [
                    'success' => false,
                    'error' => 'Rotafolio no encontrado'
                ];
            }

            // Verificar límite
            $plan = $this->obtenerPlanUsuario();
            $total_rotafolios = $this->contarRotafolios();

            if ($total_rotafolios >= $plan['max_rotafolios']) {
                return [
                    'success' => false,
                    'error' => 'Has alcanzado el límite de rotafolios de tu plan'
                ];
            }

            // Crear nuevo título
            $nuevo_titulo = $original['titulo'] . ' (Copia)';

            // Generar nueva URL única
            $url_unica = $this->generarUrlUnica();

            // Insertar copia
            $sql = "INSERT INTO rotafolios 
                   (usuario_id, titulo, descripcion, layout, color_fondo, url_unica, fecha_creacion, fecha_actualizacion) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->usuario_id,
                $nuevo_titulo,
                $original['descripcion'],
                $original['layout'],
                $original['color_fondo'],
                $url_unica
            ]);

            $nuevo_id = $this->pdo->lastInsertId();

            // Duplicar posts si existen
            $this->duplicarPosts($rotafolio_id, $nuevo_id);

            return [
                'success' => true,
                'id' => $nuevo_id,
                'url' => $url_unica,
                'mensaje' => 'Rotafolio duplicado exitosamente'
            ];
        } catch (PDOException $e) {
            error_log("Error al duplicar rotafolio: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al duplicar el rotafolio'
            ];
        }
    }

    /**
     * Eliminar rotafolio
     */
    public function eliminarRotafolio($rotafolio_id)
    {
        try {
            // Verificar que el rotafolio pertenezca al usuario
            $sql = "SELECT id FROM rotafolios 
                   WHERE id = ? AND usuario_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$rotafolio_id, $this->usuario_id]);
            $rotafolio = $stmt->fetch();

            if (!$rotafolio) {
                return [
                    'success' => false,
                    'error' => 'Rotafolio no encontrado o no tienes permisos'
                ];
            }

            // Eliminar posts relacionados
            $sql_posts = "DELETE FROM posts WHERE rotafolio_id = ?";
            $stmt_posts = $this->pdo->prepare($sql_posts);
            $stmt_posts->execute([$rotafolio_id]);

            // Eliminar rotafolio
            $sql_rotafolio = "DELETE FROM rotafolios WHERE id = ?";
            $stmt_rotafolio = $this->pdo->prepare($sql_rotafolio);
            $stmt_rotafolio->execute([$rotafolio_id]);

            return [
                'success' => true,
                'mensaje' => 'Rotafolio eliminado exitosamente'
            ];
        } catch (PDOException $e) {
            error_log("Error al eliminar rotafolio: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al eliminar el rotafolio'
            ];
        }
    }

    /**
     * Registrar actividad del usuario
     */
    public function registrarActividad($accion, $descripcion)
    {
        try {
            $sql = "INSERT INTO logs_actividad 
                   (usuario_id, accion, descripcion, ip_address, user_agent, fecha) 
                   VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->usuario_id,
                $accion,
                $descripcion,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Error al registrar actividad: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Métodos auxiliares
     */

    private function generarUrlUnica()
    {
        return substr(md5(uniqid(rand(), true)), 0, 12);
    }

    private function formatearFecha($fecha)
    {
        $ahora = new DateTime();
        $fecha_obj = new DateTime($fecha);
        $diferencia = $ahora->diff($fecha_obj);

        if ($diferencia->y > 0) {
            return $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '') . ' atrás';
        } elseif ($diferencia->m > 0) {
            return $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '') . ' atrás';
        } elseif ($diferencia->d > 0) {
            return $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '') . ' atrás';
        } elseif ($diferencia->h > 0) {
            return $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '') . ' atrás';
        } elseif ($diferencia->i > 0) {
            return $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '') . ' atrás';
        } else {
            return 'Hace unos momentos';
        }
    }

    private function duplicarPosts($rotafolio_original_id, $rotafolio_nuevo_id)
    {
        try {
            $sql = "SELECT * FROM posts WHERE rotafolio_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$rotafolio_original_id]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($posts as $post) {
                $sql_insert = "INSERT INTO posts 
                              (rotafolio_id, usuario_id, titulo, contenido, tipo, posicion_x, posicion_y, 
                              ancho, alto, color_fondo, color_texto, fecha_creacion, fecha_actualizacion) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

                $stmt_insert = $this->pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    $rotafolio_nuevo_id,
                    $this->usuario_id,
                    $post['titulo'] . ' (Copia)',
                    $post['contenido'],
                    $post['tipo'],
                    $post['posicion_x'],
                    $post['posicion_y'],
                    $post['ancho'],
                    $post['alto'],
                    $post['color_fondo'],
                    $post['color_texto']
                ]);
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error al duplicar posts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener espacio disponible
     */
    public function obtenerEspacioDisponible()
    {
        $plan = $this->obtenerPlanUsuario();
        $estadisticas = $this->obtenerEstadisticas();

        return [
            'usado' => $estadisticas['espacio_usado_mb'],
            'disponible' => $plan['max_espacio_mb'] - $estadisticas['espacio_usado_mb'],
            'total' => $plan['max_espacio_mb'],
            'porcentaje' => ($estadisticas['espacio_usado_mb'] / $plan['max_espacio_mb']) * 100
        ];
    }

    /**
     * Verificar si puede crear más rotafolios
     */
    public function puedeCrearRotafolio()
    {
        $plan = $this->obtenerPlanUsuario();
        $total = $this->contarRotafolios();

        return $total < $plan['max_rotafolios'];
    }
}
