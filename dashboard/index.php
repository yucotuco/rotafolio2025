<?php
// dashboard/index.php
ob_start();
session_start();

require_once "../conn/connrota.php";
require_once "../funcionesyClases/validarSesion.php";
require_once "../funcionesyClases/claseRotafolio.php";

$rotaManager = new RotafolioManager($pdoRota);

// Validar sesión
redirigirSiSesionInvalida($pdoRota);

// Datos de sesión
$usuario_id   = obtenerUsuarioID();
$usuario_data = obtenerUsuarioDatos();
$usuario_nombre = $usuario_data['nombre'] . ' ' . $usuario_data['apellido'];
$usuario_plan   = $usuario_data['plan'];
$usuario_email  = $usuario_data['correo'];

// Rotafolios
$rotafolios        = $rotaManager->obtenerRotafoliosUsuario($usuario_id, 100, 0);
$total_rotafolios  = count($rotafolios);

// Contar posts en bloque (evita N+1)
$ids           = array_column($rotafolios, 'id');
$mapaCounts    = $rotaManager->contarPostsPorRotafolios($ids);
$total_posts   = array_sum($mapaCounts);

// Recientes
$rotafolios_recientes = array_slice($rotafolios, 0, 5);

// Espacio (demo)
$espacio_usado_mb      = $total_rotafolios * 1; // demo
$espacio_disponible_mb = $usuario_data['espacio_disponible_mb'] ?? MAX_ESPACIO_MB_FREE;
$porcentaje_espacio    = $espacio_disponible_mb > 0 ? ($espacio_usado_mb / $espacio_disponible_mb) * 100 : 0;

// Límites por plan (centralizado)
$limites_plan = [
    'free' => ['max_rotafolios' => 5,   'max_posts' => 100,  'max_espacio_mb' => 100,   'caracteristicas' => ['Rotafolios básicos', 'Posts de texto/imagen', 'Compartir público']],
    'pro'  => ['max_rotafolios' => 20,  'max_posts' => 1000, 'max_espacio_mb' => 5000,  'caracteristicas' => ['Todo en Free', 'Videos y archivos', 'Equipos', 'Exportar PDF']],
    'team' => ['max_rotafolios' => 100, 'max_posts' => 5000, 'max_espacio_mb' => 20000, 'caracteristicas' => ['Todo en Pro', 'Colaboración en tiempo real', 'Análisis avanzado']]
];
$plan_actual = $limites_plan[$usuario_plan] ?? $limites_plan['free'];
$puede_crear = ($total_rotafolios < $plan_actual['max_rotafolios']);

// Post: Crear rápido
$mensaje = '';
$error   = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_rapido'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
    } else {
        $titulo = trim($_POST['titulo_rapido'] ?? '');
        if (empty($titulo)) {
            $error = "El título es requerido";
        } elseif (!$puede_crear) {
            $error = "Has alcanzado el límite de " . $plan_actual['max_rotafolios'] . " rotafolios en tu plan actual";
        } else {
            $resultado = $rotaManager->crearRotafolio($usuario_id, $titulo, '', 'muro', '#0dcaf0');
            if ($resultado) {
                $mensaje = "¡Rotafolio '{$titulo}' creado exitosamente!";
                header("Location: mis_rotafolios.php?mensaje=" . urlencode($mensaje));
                exit();
            } else {
                $error = 'Error al crear rotafolio';
            }
        }
    }
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$titulo = "Dashboard - Rotafolio";
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
        /* (Mantengo tu línea gráfica del dashboard que compartiste) */
        :root {
            --rota-primary: #0dcaf0;
            --rota-primary-dark: #0aa2c0;
            --rota-gradient: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
            --rota-success: #20c997;
            --rota-warning: #fd7e14;
            --rota-danger: #e83e8c;
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

        @media (max-width: 992px) {
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

        .stat-card {
            background: white;
            border: none;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon-primary {
            background: rgba(13, 202, 240, 0.1);
            color: var(--rota-primary);
        }

        .stat-icon-success {
            background: rgba(32, 201, 151, 0.1);
            color: var(--rota-success);
        }

        .stat-icon-warning {
            background: rgba(253, 126, 20, 0.1);
            color: var(--rota-warning);
        }

        .stat-icon-danger {
            background: rgba(232, 62, 140, 0.1);
            color: var(--rota-danger);
        }

        .progress-rota {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }

        .progress-rota .progress-bar {
            border-radius: 4px;
            background: var(--rota-gradient);
        }

        .btn-rota {
            background: var(--rota-gradient);
            border: none;
            color: white;
            padding: .75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all .3s;
        }

        .btn-rota:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 202, 240, 0.3);
        }

        .btn-rota-outline {
            background: transparent;
            border: 2px solid var(--rota-primary);
            color: var(--rota-primary);
            padding: .75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all .3s;
        }

        .btn-rota-outline:hover {
            background: var(--rota-primary);
            color: #fff;
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
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
            background-color: rgba(13, 202, 240, 0.1);
            color: #0dcaf0;
        }

        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .modal-crear-rapido .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-crear-rapido .modal-header {
            background: var(--rota-gradient);
            color: white;
            border: none;
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header p-4 border-bottom">
            <a href="index.php" class="logo text-decoration-none d-flex align-items-center">
                <i class="bi bi-grid-3x3-gap-fill text-primary fs-4"></i>
                <span class="fw-bold text-primary ms-2 fs-5">Rotafolio</span>
            </a>
        </div>
        <div class="sidebar-content py-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="index.php" class="nav-link active"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
                </li>
                <li class="nav-item">
                    <a href="mis_rotafolios.php" class="nav-link"><i class="bi bi-grid-3x3-gap"></i><span>Mis Rotafolios</span><?php if ($total_rotafolios > 0): ?><span class="badge bg-primary rounded-pill ms-auto"><?= $total_rotafolios ?></span><?php endif; ?></a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#crearRapidoModal"><i class="bi bi-plus-circle"></i><span>Crear Rápido</span></a>
                </li>
                <li class="nav-item"><a href="ajustes.php" class="nav-link"><i class="bi bi-gear"></i><span>Ajustes</span></a></li>
                <li class="nav-item mt-3"><a href="../auth/cerrar.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </div>
        <div class="sidebar-footer p-4 border-top">
            <div class="d-flex align-items-center">
                <div class="user-avatar me-3"><?= strtoupper(substr($usuario_nombre, 0, 2)) ?></div>
                <div class="flex-grow-1">
                    <div class="fw-bold text-truncate"><?= htmlspecialchars($usuario_nombre) ?></div>
                    <div class="d-flex align-items-center">
                        <span class="badge plan-<?= $usuario_plan ?> me-2"><?= ucfirst($usuario_plan) ?></span>
                        <small class="text-muted"><?= $total_rotafolios ?>/<?= $plan_actual['max_rotafolios'] ?></small>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <button class="btn btn-outline-primary d-lg-none position-fixed top-2 start-2 z-3" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- MAIN -->
    <main class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Bienvenido, <?= htmlspecialchars(explode(' ', $usuario_nombre)[0]) ?>!</h1>
                <p class="text-muted mb-0">Resumen de tu actividad en Rotafolio</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-rota" data-bs-toggle="modal" data-bs-target="#crearRapidoModal"><i class="bi bi-plus-lg me-2"></i>Crear Rotafolio</button>
                <a href="mis_rotafolios.php" class="btn btn-rota-outline"><i class="bi bi-grid-3x3-gap me-2"></i>Ver Todos</a>
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

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-primary"><i class="bi bi-grid-3x3"></i></div>
                    <h3 class="fw-bold mb-2"><?= $total_rotafolios ?></h3>
                    <p class="text-muted mb-0">Rotafolios</p>
                    <div class="progress-rota mt-3">
                        <div class="progress-bar" style="width: <?= min(100, ($total_rotafolios / $plan_actual['max_rotafolios']) * 100) ?>%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block"><?= $total_rotafolios ?> de <?= $plan_actual['max_rotafolios'] ?> disponibles</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-success"><i class="bi bi-sticky"></i></div>
                    <h3 class="fw-bold mb-2"><?= $total_posts ?></h3>
                    <p class="text-muted mb-0">Posts totales</p>
                    <div class="progress-rota mt-3">
                        <div class="progress-bar bg-success" style="width: <?= min(100, ($total_posts / $plan_actual['max_posts']) * 100) ?>%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block"><?= $total_posts ?> de <?= $plan_actual['max_posts'] ?> disponibles</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-warning"><i class="bi bi-hdd"></i></div>
                    <h3 class="fw-bold mb-2"><?= $espacio_usado_mb ?> MB</h3>
                    <p class="text-muted mb-0">Espacio usado</p>
                    <div class="progress-rota mt-3">
                        <div class="progress-bar bg-warning" style="width: <?= min(100, $porcentaje_espacio) ?>%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block"><?= $espacio_usado_mb ?> MB de <?= $espacio_disponible_mb ?> MB</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-danger"><i class="bi bi-globe"></i></div>
                    <?php $rotafolios_publicos = array_reduce($rotafolios, fn($c, $r) => $c + ((int)($r['es_publico'] ?? 0)), 0); ?>
                    <h3 class="fw-bold mb-2"><?= $rotafolios_publicos ?></h3>
                    <p class="text-muted mb-0">Públicos</p>
                    <div class="progress-rota mt-3">
                        <div class="progress-bar" style="width: <?= $total_rotafolios > 0 ? ($rotafolios_publicos / $total_rotafolios) * 100 : 0 ?>%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block"><?= $rotafolios_publicos ?> de <?= $total_rotafolios ?> son públicos</small>
                </div>
            </div>
        </div>

        <!-- Recientes -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">Rotafolios recientes</h5>
                            <a href="mis_rotafolios.php" class="btn btn-sm btn-outline-primary">Ver todos <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rotafolios_recientes)): ?>
                            <div class="text-center py-5">
                                <div class="empty-state-icon mb-3"><i class="bi bi-grid-3x3-gap fs-1 text-muted"></i></div>
                                <h5 class="mb-3">Aún no tienes rotafolios</h5>
                                <p class="text-muted mb-4">Comienza creando tu primer rotafolio</p>
                                <button class="btn btn-rota" data-bs-toggle="modal" data-bs-target="#crearRapidoModal"><i class="bi bi-plus-lg me-2"></i>Crear primer rotafolio</button>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($rotafolios_recientes as $rotafolio):
                                    $total_posts_rotafolio = $mapaCounts[$rotafolio['id']] ?? 0;
                                    $dias = floor((time() - strtotime($rotafolio['fecha_creacion'])) / (60 * 60 * 24));
                                    $hace = $dias == 0 ? 'Hoy' : ($dias == 1 ? 'Ayer' : "Hace {$dias} días");
                                ?>
                                    <a href="ver_rotafolio.php?id=<?= $rotafolio['id'] ?>" class="list-group-item list-group-item-action border-0 py-3 px-0">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                                    style="width:48px;height:48px;background:<?= $rotafolio['color_fondo'] ?? '#0dcaf0' ?>;">
                                                    <i class="bi bi-grid-3x3 text-white"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($rotafolio['titulo']) ?></h6>
                                                    <span class="badge <?= $rotafolio['es_publico'] ? 'bg-success' : 'bg-secondary' ?>"><?= $rotafolio['es_publico'] ? 'Público' : 'Privado' ?></span>
                                                </div>
                                                <p class="text-muted mb-1 small">
                                                    <i class="bi bi-sticky me-1"></i><?= $total_posts_rotafolio ?> posts
                                                    <span class="mx-2">•</span>
                                                    <i class="bi bi-calendar me-1"></i><?= $hace ?>
                                                </p>
                                                <small class="text-muted"><i class="bi bi-palette me-1"></i><?= ucfirst($rotafolio['layout'] ?? 'muro') ?></small>
                                            </div>
                                            <div class="flex-shrink-0 ms-3"><i class="bi bi-chevron-right text-muted"></i></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Plan y acciones -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">Tu Plan: <?= ucfirst($usuario_plan) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Rotafolios</span><span class="fw-bold"><?= $total_rotafolios ?>/<?= $plan_actual['max_rotafolios'] ?></span>
                            </div>
                            <div class="progress-rota mb-3">
                                <div class="progress-bar" style="width: <?= min(100, ($total_rotafolios / $plan_actual['max_rotafolios']) * 100) ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Posts</span><span class="fw-bold"><?= $total_posts ?>/<?= $plan_actual['max_posts'] ?></span>
                            </div>
                            <div class="progress-rota mb-3">
                                <div class="progress-bar bg-success" style="width: <?= min(100, ($total_posts / $plan_actual['max_posts']) * 100) ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Espacio</span><span class="fw-bold"><?= $espacio_usado_mb ?>/<?= $espacio_disponible_mb ?> MB</span>
                            </div>
                            <div class="progress-rota">
                                <div class="progress-bar bg-warning" style="width: <?= min(100, $porcentaje_espacio) ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-bold mb-3">Características:</h6>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($plan_actual['caracteristicas'] as $c): ?>
                                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= $c ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php if ($usuario_plan == 'free'): ?>
                            <a href="planes.php" class="btn btn-rota w-100"><i class="bi bi-rocket-takeoff me-2"></i>Actualizar Plan</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">Acciones rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-create-card mb-3" data-bs-toggle="modal" data-bs-target="#crearRapidoModal">
                            <i class="bi bi-plus-circle display-6 text-primary mb-3"></i>
                            <h6 class="fw-bold mb-2">Crear Rotafolio Rápido</h6>
                            <p class="text-muted small mb-0">Solo título, lo demás lo ajustas después</p>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="mis_rotafolios.php" class="btn btn-outline-primary"><i class="bi bi-grid-3x3-gap me-2"></i>Gestionar Rotafolios</a>
                            <a href="#" class="btn btn-outline-success"><i class="bi bi-upload me-2"></i>Importar Archivos</a>
                            <a href="ajustes.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-2"></i>Ajustes de Cuenta</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-lightbulb text-warning me-2"></i>Consejos Rápidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex"><i class="bi bi-palette text-primary fs-4"></i>
                                    <div class="ms-3">
                                        <h6 class="fw-bold">Personaliza colores</h6>
                                        <p class="text-muted small mb-0">Usa colores que reflejen tu marca o proyecto</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex"><i class="bi bi-share text-success fs-4"></i>
                                    <div class="ms-3">
                                        <h6 class="fw-bold">Comparte fácilmente</h6>
                                        <p class="text-muted small mb-0">Haz tus rotafolios públicos para compartir con otros</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex"><i class="bi bi-layers text-danger fs-4"></i>
                                    <div class="ms-3">
                                        <h6 class="fw-bold">Usa diferentes layouts</h6>
                                        <p class="text-muted small mb-0">Experimenta con muros, líneas de tiempo o mapas</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal: Crear rápido -->
    <div class="modal fade modal-crear-rapido" id="crearRapidoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Rotafolio Rápido</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!$puede_crear): ?>
                            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
                                Has alcanzado el límite de <?= $plan_actual['max_rotafolios'] ?> rotafolios en tu plan <?= ucfirst($usuario_plan) ?>.
                                <a href="planes.php" class="alert-link fw-bold">Actualizar plan</a> para crear más.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Puedes personalizar el layout y colores después de crear.</div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="titulo_rapido" class="form-label">Título del Rotafolio *</label>
                            <input type="text" class="form-control form-control-lg" id="titulo_rapido" name="titulo_rapido"
                                placeholder="Ej: Mi proyecto de marketing" required <?= !$puede_crear ? 'disabled' : '' ?>>
                            <div class="form-text">El título puede cambiarse después</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plan actual: <span class="badge plan-<?= $usuario_plan ?>"><?= ucfirst($usuario_plan) ?></span></label>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Rotafolios creados:</small>
                                <small class="fw-bold"><?= $total_rotafolios ?>/<?= $plan_actual['max_rotafolios'] ?></small>
                            </div>
                            <div class="progress-rota mt-2">
                                <div class="progress-bar" style="width: <?= min(100, ($total_rotafolios / $plan_actual['max_rotafolios']) * 100) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear_rapido" class="btn btn-primary" <?= !$puede_crear ? 'disabled' : '' ?>><i class="bi bi-lightning me-2"></i>Crear Ahora</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        document.getElementById('crearRapidoModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('titulo_rapido').focus();
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>