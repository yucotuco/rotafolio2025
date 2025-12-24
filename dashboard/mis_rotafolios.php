<?php
// dashboard/mis_rotafolios.php
ob_start();
session_start();

require_once "../conn/connrota.php";
require_once "../funcionesyClases/validarSesion.php";
require_once "../funcionesyClases/claseRotafolio.php";

$rotaManager = new RotafolioManager($pdoRota);

redirigirSiSesionInvalida($pdoRota);

$usuario_id   = obtenerUsuarioID();
$usuario_data = obtenerUsuarioDatos();
$usuario_nombre = $usuario_data['nombre'] . ' ' . $usuario_data['apellido'];
$usuario_plan   = $usuario_data['plan'];

$rotafolios       = $rotaManager->obtenerRotafoliosUsuario($usuario_id, 100, 0);
$total_rotafolios = count($rotafolios);

// Plan
$limites_plan = [
    'free' => ['max_rotafolios' => 5, 'max_posts' => 100, 'max_espacio_mb' => 100],
    'pro'  => ['max_rotafolios' => 20, 'max_posts' => 1000, 'max_espacio_mb' => 5000],
    'team' => ['max_rotafolios' => 100, 'max_posts' => 5000, 'max_espacio_mb' => 20000]
];
$plan_actual    = $limites_plan[$usuario_plan] ?? $limites_plan['free'];
$max_rotafolios = $plan_actual['max_rotafolios'];
$puede_crear    = ($total_rotafolios < $max_rotafolios);

// Acciones
$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
    } else {
        // Crear
        if (isset($_POST['crear_rotafolio'])) {
            $titulo      = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $layout      = $_POST['layout'] ?? 'muro';
            $color_fondo = $_POST['color_fondo'] ?? '#0dcaf0';
            if (empty($titulo)) {
                $error = "El título es requerido";
            } elseif (!$puede_crear) {
                $error = "Has alcanzado el límite de rotafolios de tu plan";
            } else {
                $resultado = $rotaManager->crearRotafolio($usuario_id, $titulo, $descripcion, $layout, $color_fondo);
                if ($resultado) {
                    $mensaje = "¡Rotafolio '{$titulo}' creado exitosamente!";
                    header("Location: mis_rotafolios.php?mensaje=" . urlencode($mensaje));
                    exit();
                } else {
                    $error = 'Error al crear rotafolio';
                }
            }
        }

        // Eliminar
        if (isset($_POST['eliminar_rotafolio'])) {
            $rotafolio_id = intval($_POST['rotafolio_id'] ?? 0);
            if ($rotafolio_id > 0) {
                $resultado = $rotaManager->eliminarRotafolio($rotafolio_id, $usuario_id);
                if ($resultado) {
                    $mensaje = 'Rotafolio eliminado exitosamente';
                    header("Location: mis_rotafolios.php?mensaje=" . urlencode($mensaje));
                    exit();
                } else {
                    $error = 'Error al eliminar rotafolio';
                }
            }
        }

        // Duplicar
        if (isset($_POST['duplicar_rotafolio'])) {
            $rotafolio_id = intval($_POST['rotafolio_id'] ?? 0);
            if ($rotafolio_id > 0) {
                if (!$puede_crear) {
                    $error = "Has alcanzado el límite de rotafolios de tu plan";
                } else {
                    if (!$rotaManager->esPropietarioRotafolio($rotafolio_id, $usuario_id)) {
                        $error = "No tienes permiso para duplicar este rotafolio";
                    } else {
                        $resultado = $rotaManager->duplicarRotafolio($rotafolio_id, $usuario_id);
                        if ($resultado) {
                            $mensaje = 'Rotafolio duplicado exitosamente';
                            header("Location: mis_rotafolios.php?mensaje=" . urlencode($mensaje));
                            exit();
                        } else {
                            $error = 'Error al duplicar rotafolio';
                        }
                    }
                }
            }
        }
    }
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Mensajes por GET
if (isset($_GET['mensaje'])) $mensaje = htmlspecialchars(urldecode($_GET['mensaje']));
if (isset($_GET['error']))   $error   = htmlspecialchars(urldecode($_GET['error']));

// Conteos compactos
$ids             = array_column($rotafolios, 'id');
$mapaCounts      = $rotaManager->contarPostsPorRotafolios($ids);
$total_posts     = array_sum($mapaCounts);
$rotafolios_publicos = array_reduce($rotafolios, fn($c, $r) => $c + ((int)($r['es_publico'] ?? 0)), 0);

$titulo = "Mis Rotafolios";
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mantengo tu línea gráfica compacta */
        :root {
            --rota-primary: #0dcaf0;
            --rota-primary-dark: #0aa2c0;
            --rota-gradient: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1030;
            padding: 0;
            background: white;
            border-right: 1px solid #e9ecef;
            width: 260px;
        }

        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            min-height: 100vh;
        }

        @media (max-width:992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        .rotafolio-card {
            background: white;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
            transition: all .3s cubic-bezier(.4, 0, .2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .rotafolio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, .15);
        }

        .rotafolio-thumb {
            height: 180px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .rotafolio-thumb .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            transition: transform .5s ease;
        }

        .rotafolio-card:hover .rotafolio-thumb .background-image {
            transform: scale(1.05);
        }

        .rotafolio-thumb .color-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .rotafolio-thumb .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0, 0, 0, .1) 0%, rgba(0, 0, 0, .3) 100%);
            z-index: 1;
        }

        .layout-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, .95);
            padding: .4rem .8rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            z-index: 2;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, .8);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
        }

        .status-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: .4rem .8rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            z-index: 2;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, .8);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
        }

        .status-public {
            background: rgba(40, 167, 69, .95);
            color: white;
        }

        .status-private {
            background: rgba(108, 117, 125, .95);
            color: white;
        }

        .thumbnail-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            color: rgba(255, 255, 255, .9);
            z-index: 2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, .3);
        }

        .rotafolio-content {
            padding: 1.25rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .rotafolio-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: .5rem;
            color: #212529;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rotafolio-description {
            font-size: .875rem;
            color: #6c757d;
            margin-bottom: 1rem;
            line-height: 1.4;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .rotafolio-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .8rem;
            color: #6c757d;
            margin-bottom: 1rem;
            padding-top: .75rem;
            border-top: 1px solid #e9ecef;
        }

        .rotafolio-actions {
            display: flex;
            gap: .5rem;
            margin-top: auto;
        }

        .btn-action {
            flex: 1;
            padding: .5rem;
            font-size: .85rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .25rem;
            transition: all .2s;
        }

        .btn-action-sm {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-rota {
            background: var(--rota-gradient);
            border: none;
            color: #fff;
            padding: .75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all .3s;
        }

        .btn-rota:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 202, 240, .3);
        }

        .rotafolios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width:768px) {
            .rotafolios-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1rem;
            }

            .rotafolio-thumb {
                height: 160px;
            }
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
            height: 100%;
            border: 1px solid #e9ecef;
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .stats-icon-primary {
            background: rgba(13, 202, 240, .1);
            color: var(--rota-primary);
        }

        .stats-icon-success {
            background: rgba(40, 167, 69, .1);
            color: #28a745;
        }

        .stats-icon-warning {
            background: rgba(255, 193, 7, .1);
            color: #ffc107;
        }

        .nav-link {
            color: #495057;
            padding: .75rem 1.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(13, 202, 240, .1);
            color: #0dcaf0;
        }

        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
    </style>
</head>

<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header p-4 border-bottom">
            <a href="index.php" class="logo text-decoration-none"><i class="bi bi-grid-3x3-gap-fill text-primary"></i><span class="fw-bold text-primary ms-2">Rotafolio</span></a>
        </div>
        <div class="sidebar-content py-3">
            <ul class="nav flex-column">
                <li class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
                <li class="nav-item"><a href="mis_rotafolios.php" class="nav-link active"><i class="bi bi-grid-3x3-gap"></i><span>Mis Rotafolios</span><?php if ($total_rotafolios > 0): ?><span class="badge bg-primary rounded-pill ms-auto"><?= $total_rotafolios ?></span><?php endif; ?></a></li>
                <li class="nav-item"><a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#crearRotafolioModal"><i class="bi bi-plus-circle"></i><span>Nuevo Rotafolio</span></a></li>
                <li class="nav-item"><a href="../auth/cerrar.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </div>
        <div class="sidebar-footer p-4 border-top">
            <div class="d-flex align-items-center">
                <div class="user-avatar me-3"><?= strtoupper(substr($usuario_nombre, 0, 2)) ?></div>
                <div class="flex-grow-1">
                    <div class="fw-bold text-truncate"><?= htmlspecialchars($usuario_nombre) ?></div>
                    <div class="text-muted small">Plan <?= ucfirst($usuario_plan) ?></div>
                </div>
            </div>
        </div>
    </nav>

    <button class="btn btn-outline-primary d-lg-none position-fixed top-2 start-2" id="sidebarToggle"><i class="bi bi-list"></i></button>

    <main class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Mis Rotafolios</h1>
                <p class="text-muted mb-0">Administra todos tus rotafolios</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-rota" data-bs-toggle="modal" data-bs-target="#crearRotafolioModal"><i class="bi bi-plus-lg me-2"></i>Nuevo Rotafolio</button>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas compactas -->
        <div class="row mb-4 g-3">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon stats-icon-primary me-3"><i class="bi bi-grid-3x3 fs-4"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $total_rotafolios ?></h3>
                            <p class="text-muted mb-0">Rotafolios</p>
                            <small class="text-muted"><?= $max_rotafolios - $total_rotafolios ?> disponibles</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon stats-icon-success me-3"><i class="bi bi-sticky fs-4"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $total_posts ?></h3>
                            <p class="text-muted mb-0">Posts totales</p>
                            <small class="text-muted">En todos los rotafolios</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon stats-icon-warning me-3"><i class="bi bi-globe fs-4"></i></div>
                        <div>
                            <h3 class="fw-bold mb-0"><?= $rotafolios_publicos ?></h3>
                            <p class="text-muted mb-0">Públicos</p>
                            <small class="text-muted"><?= $total_rotafolios - $rotafolios_publicos ?> privados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Todos tus rotafolios</h5>
                    <div class="d-flex gap-2">
                        <span class="text-muted small"><?= $total_rotafolios ?> de <?= $max_rotafolios ?> disponibles</span>
                        <?php if ($total_rotafolios > 0): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-funnel"></i> Filtrar</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="?filtro=todos">Todos</a></li>
                                    <li><a class="dropdown-item" href="?filtro=publicos">Solo públicos</a></li>
                                    <li><a class="dropdown-item" href="?filtro=privados">Solo privados</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="?orden=reciente">Más recientes</a></li>
                                    <li><a class="dropdown-item" href="?orden=antiguo">Más antiguos</a></li>
                                    <li><a class="dropdown-item" href="?orden=nombre">Por nombre</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <?php if (empty($rotafolios)): ?>
                    <div class="text-center py-5">
                        <div class="empty-state-icon"><i class="bi bi-grid-3x3-gap"></i></div>
                        <h4 class="mb-3">Aún no tienes rotafolios</h4>
                        <p class="text-muted mb-4">Crea tu primer rotafolio para comenzar a organizar tus ideas</p>
                        <button class="btn btn-rota" data-bs-toggle="modal" data-bs-target="#crearRotafolioModal"><i class="bi bi-plus-lg me-2"></i>Crear primer rotafolio</button>
                    </div>
                <?php else: ?>
                    <div class="rotafolios-grid">
                        <?php foreach ($rotafolios as $rotafolio):
                            $layout_icons = ['muro' => 'grid-3x3', 'rejilla' => 'grid', 'columna' => 'layout-sidebar-inset-reverse', 'canvas' => 'easel', 'timeline' => 'clock', 'mapa' => 'diagram-3'];
                            $layout_icon  = $layout_icons[$rotafolio['layout']] ?? 'grid-3x3';
                            $total_posts_rotafolio = $mapaCounts[$rotafolio['id']] ?? 0;
                            // URL de ver por id (puedes cambiar a token si deseas)
                            $url_ver_completa = SITIO_URL . '/dashboard/ver_rotafolio.php?id=' . $rotafolio['id'];
                        ?>
                            <div class="rotafolio-card">
                                <div class="rotafolio-thumb">
                                    <?php if (!empty($rotafolio['imagen_fondo'])): ?>
                                        <div class="background-image" style="background-image: url('<?= $rotafolio['imagen_fondo'] ?>')"></div>
                                    <?php else: ?>
                                        <div class="color-background" style="background: <?= $rotafolio['color_fondo'] ?? '#0dcaf0' ?>"></div>
                                        <i class="bi bi-grid-3x3-gap thumbnail-icon"></i>
                                    <?php endif; ?>
                                    <div class="overlay"></div>
                                    <span class="layout-badge"><i class="bi bi-<?= $layout_icon ?> me-1"></i><?= ucfirst($rotafolio['layout'] ?? 'muro') ?></span>
                                    <span class="status-badge <?= $rotafolio['es_publico'] ? 'status-public' : 'status-private' ?>">
                                        <i class="bi bi-<?= $rotafolio['es_publico'] ? 'globe' : 'lock' ?> me-1"></i>
                                        <?= $rotafolio['es_publico'] ? 'Público' : 'Privado' ?>
                                    </span>
                                </div>
                                <div class="rotafolio-content">
                                    <h5 class="rotafolio-title"><?= htmlspecialchars($rotafolio['titulo'] ?? 'Sin título') ?></h5>
                                    <?php if (!empty($rotafolio['descripcion'])): ?>
                                        <p class="rotafolio-description"><?= htmlspecialchars($rotafolio['descripcion']) ?></p>
                                    <?php endif; ?>
                                    <div class="rotafolio-meta">
                                        <span><i class="bi bi-sticky me-1"></i><?= $total_posts_rotafolio ?> posts</span>
                                        <span><i class="bi bi-calendar me-1"></i><?= date('d/m/Y', strtotime($rotafolio['fecha_creacion'])) ?></span>
                                    </div>
                                    <div class="rotafolio-actions">
                                        <a href="ver_rotafolio.php?id=<?= $rotafolio['id'] ?>" class="btn btn-success btn-action" title="Ver/Editar rotafolio">
                                            <i class="bi bi-pencil"></i><span class="d-none d-sm-inline">Ver/Editar</span>
                                        </a>

                                        <?php if ($rotafolio['es_publico']): ?>
                                            <button class="btn btn-primary btn-action" data-bs-toggle="modal" data-bs-target="#compartirModal<?= $rotafolio['id'] ?>" title="Compartir">
                                                <i class="bi bi-share"></i><span class="d-none d-sm-inline">Compartir</span>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-action" onclick="alert('Este rotafolio es privado. Hazlo público para compartirlo.')" title="Privado">
                                                <i class="bi bi-lock"></i><span class="d-none d-sm-inline">Privado</span>
                                            </button>
                                        <?php endif; ?>

                                        <div class="dropdown dropdown-actions">
                                            <button class="btn btn-outline-secondary btn-action-sm" type="button" data-bs-toggle="dropdown" title="Más opciones">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <form method="POST" class="d-inline w-100">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="rotafolio_id" value="<?= $rotafolio['id'] ?>">
                                                        <button type="submit" name="duplicar_rotafolio" class="dropdown-item <?= !$puede_crear ? 'disabled' : '' ?>" onclick="return confirm('¿Duplicar este rotafolio?');">
                                                            <i class="bi bi-copy me-2"></i>Duplicar
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <form method="POST" class="d-inline w-100" onsubmit="return confirm('¿Estás seguro de eliminar este rotafolio? Esta acción no se puede deshacer.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="rotafolio_id" value="<?= $rotafolio['id'] ?>">
                                                        <button type="submit" name="eliminar_rotafolio" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash me-2"></i>Eliminar
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Compartir por rotafolio -->
                            <div class="modal fade" id="compartirModal<?= $rotafolio['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-share me-2"></i>Compartir "<?= htmlspecialchars($rotafolio['titulo']) ?>"</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-4">
                                                <label class="form-label">Enlace público</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" value="<?= $url_ver_completa ?>" readonly id="enlace<?= $rotafolio['id'] ?>">
                                                    <button class="btn btn-outline-primary" type="button" onclick="copiarEnlace('enlace<?= $rotafolio['id'] ?>', this)"><i class="bi bi-clipboard"></i></button>
                                                </div>
                                                <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Cualquiera con este enlace puede ver tu rotafolio.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Compartir en:</label>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <button class="btn btn-outline-success w-100" onclick="compartirWhatsApp('<?= $url_ver_completa ?>', '<?= htmlspecialchars($rotafolio['titulo']) ?>')">
                                                            <i class="bi bi-whatsapp me-2"></i>WhatsApp
                                                        </button>
                                                    </div>
                                                    <div class="col-6">
                                                        <button class="btn btn-outline-primary w-100" onclick="compartirFacebook('<?= $url_ver_completa ?>')">
                                                            <i class="bi bi-facebook me-2"></i>Facebook
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($rotafolios)): ?>
                <div class="card-footer bg-white border-0 py-3">
                    <div class="text-center"><small class="text-muted">Mostrando <?= count($rotafolios) ?> rotafolio<?= count($rotafolios) != 1 ? 's' : '' ?></small></div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal: Crear Rotafolio -->
    <div class="modal fade" id="crearRotafolioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear nuevo Rotafolio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!$puede_crear): ?>
                            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
                                Has alcanzado el límite de <?= $max_rotafolios ?> rotafolios en tu plan actual.
                                <a href="planes.php" class="alert-link fw-bold">Actualizar plan</a> para crear más.
                            </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="titulo" class="form-label">Título *</label>
                                    <input type="text" class="form-control form-control-lg" id="titulo" name="titulo" placeholder="Ej: Mi Proyecto Final" required <?= !$puede_crear ? 'disabled' : '' ?>>
                                </div>
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción (opcional)</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Describe brevemente tu rotafolio..." <?= !$puede_crear ? 'disabled' : '' ?>></textarea>
                                    <div class="form-text">Puedes cambiarla después.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Color de fondo</label>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <?php
                                        $colores = ['#0dcaf0' => 'Celeste', '#20c997' => 'Verde', '#6f42c1' => 'Púrpura', '#fd7e14' => 'Naranja', '#e83e8c' => 'Rosa', '#343a40' => 'Oscuro'];
                                        foreach ($colores as $color => $nombre):
                                        ?>
                                            <div class="color-option <?= $color == '#0dcaf0' ? 'active' : '' ?>" style="background-color: <?= $color ?>;"
                                                data-color="<?= $color ?>" onclick="selectColor(this, '<?= $color ?>')" title="<?= $nombre ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="color_fondo" id="color_fondo" value="#0dcaf0">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label mb-3">Selecciona un layout</label>
                            <div class="row g-3">
                                <?php
                                $layouts = [
                                    'muro'    => ['icon' => 'grid-3x3', 'name' => 'Muro', 'desc' => 'Posts libres como Pinterest'],
                                    'rejilla' => ['icon' => 'grid', 'name' => 'Rejilla', 'desc' => 'Organizado en filas y columnas'],
                                    'columna' => ['icon' => 'layout-sidebar-inset-reverse', 'name' => 'Columna', 'desc' => 'Contenido en una columna vertical'],
                                    'canvas'  => ['icon' => 'easel', 'name' => 'Canvas', 'desc' => 'Espacio libre para creatividad'],
                                    'timeline' => ['icon' => 'clock', 'name' => 'Timeline', 'desc' => 'Línea de tiempo cronológica'],
                                    'mapa'    => ['icon' => 'diagram-3', 'name' => 'Mapa', 'desc' => 'Mapa conceptual']
                                ];
                                foreach ($layouts as $key => $lay):
                                ?>
                                    <div class="col-md-4 col-sm-6">
                                        <input type="radio" class="d-none" name="layout" id="layout-<?= $key ?>" value="<?= $key ?>" <?= $key == 'muro' ? 'checked' : '' ?> <?= !$puede_crear ? 'disabled' : '' ?>>
                                        <label class="layout-option-card <?= $key == 'muro' ? 'active' : '' ?>" for="layout-<?= $key ?>">
                                            <div class="layout-icon"><i class="bi bi-<?= $lay['icon'] ?>"></i></div>
                                            <h6 class="fw-bold mb-2"><?= $lay['name'] ?></h6>
                                            <p class="small text-muted mb-0"><?= $lay['desc'] ?></p>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><i class="bi bi-info-circle me-2"></i>Plan actual: <span class="fw-bold"><?= ucfirst($usuario_plan) ?></span></div>
                                <div><small class="text-muted"><?= $total_rotafolios ?>/<?= $max_rotafolios ?> rotafolios</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_rotafolio" class="btn btn-primary" <?= !$puede_crear ? 'disabled' : '' ?>><i class="bi bi-plus-circle me-2"></i>Crear Rotafolio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectColor(element, color) {
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('color_fondo').value = color;
        }

        function copiarEnlace(inputId, button) {
            const input = document.getElementById(inputId);
            const text = input.value;
            const original = button.innerHTML;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => feedbackOK(button, original))
                    .catch(() => fallbackCopy(text, button, original));
            } else {
                fallbackCopy(text, button, original);
            }
        }

        function fallbackCopy(text, button, original) {
            const temp = document.createElement('textarea');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
            } catch (e) {}
            temp.remove();
            feedbackOK(button, original);
        }

        function feedbackOK(button, original) {
            button.innerHTML = '<i class="bi bi-check"></i>';
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
            setTimeout(() => {
                button.innerHTML = original;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-primary');
            }, 2000);
        }

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        document.getElementById('crearRotafolioModal')?.addEventListener('shown.bs.modal', function() {
            document.getElementById('titulo').focus();
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>