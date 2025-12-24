<?php
// index.php - Página principal
ob_start();
session_start();
require_once "conn/connrota.php";
/* require_once "funcionesyClases/claseRotafolio.php"; */

$titulo = "Rotafolio - Crea, Comparte y Colabora";
$esHomepage = true;

// Redirigir si ya está logueado
if (isset($_SESSION['rotaforlioyuco2025'])) {
    header("Location: dashboard/");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <script src="https://www.google.com/recaptcha/enterprise.js?render=6Le_LjAsAAAAAMML_S7Ofubq2k4nxle2eAQjyHDv"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> | NovaExperto</title>

    <!-- Meta tags para SEO -->
    <meta name="description" content="Crea tableros visuales interactivos. Organiza ideas, comparte proyectos y colabora en tiempo real con Rotafolio.">
    <meta name="keywords" content="rotafolio, padlet, tableros visuales, colaboración, organización, proyectos">
    <meta property="og:title" content="Rotafolio - Tu espacio creativo">
    <meta property="og:description" content="Crea, comparte y colabora en tableros visuales.">
    <meta property="og:image" content="assets/images/og-image.jpg">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@700;800&display=swap" rel="stylesheet">

    <!-- AOS Animations -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="css/rota-estilos.css?v=1.0">

    <style>
        :root {
            --rota-primary: #0dcaf0;
            --rota-primary-dark: #0aa2c0;
            --rota-secondary: #6f42c1;
            --rota-success: #198754;
            --rota-gradient: linear-gradient(135deg, #0dcaf0 0%, #20c997 100%);
            --rota-gradient-dark: linear-gradient(135deg, #0aa2c0 0%, #1aa179 100%);
            --rota-shadow: 0 10px 30px rgba(13, 202, 240, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
            background: #f8fafc;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        .display-1,
        .display-2,
        .display-3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }

        /* ===== HERO SECTION ===== */
        .hero-section {
            background: var(--rota-gradient);
            min-height: 100vh;
            color: white;
            position: relative;
            overflow: hidden;
            padding-top: 80px;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        @media (min-width: 768px) {
            .hero-title {
                font-size: 4rem;
            }
        }

        @media (min-width: 992px) {
            .hero-title {
                font-size: 4.5rem;
            }
        }

        /* ===== NAVBAR ===== */
        .rota-navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(13, 202, 240, 0.1);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }

        .rota-navbar.scrolled {
            padding: 0.7rem 0;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--rota-primary) !important;
        }

        .navbar-brand i {
            font-size: 2rem;
            vertical-align: middle;
        }

        .nav-link {
            font-weight: 500;
            color: #495057 !important;
            padding: 0.5rem 1rem !important;
            border-radius: 50px;
            transition: all 0.3s ease;
            margin: 0 0.2rem;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(13, 202, 240, 0.1);
            color: var(--rota-primary) !important;
        }

        /* ===== BUTTONS ===== */
        .btn-rota {
            background: var(--rota-gradient);
            border: none;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--rota-shadow);
            position: relative;
            overflow: hidden;
        }

        .btn-rota::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-rota:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(13, 202, 240, 0.3);
            color: white;
        }

        .btn-rota:hover::after {
            left: 100%;
        }

        .btn-rota-outline {
            border: 2px solid var(--rota-primary);
            color: var(--rota-primary);
            background: transparent;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-rota-outline:hover {
            background: var(--rota-primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: var(--rota-shadow);
        }

        /* ===== CARDS ===== */
        .rota-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }

        .rota-card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .rota-card-hover:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(13, 202, 240, 0.15) !important;
        }

        /* ===== FEATURES ===== */
        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--rota-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            box-shadow: var(--rota-shadow);
        }

        .feature-icon-secondary {
            background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
        }

        .feature-icon-success {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
        }

        /* ===== PRICING ===== */
        .pricing-card {
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
        }

        .pricing-card:hover {
            border-color: var(--rota-primary);
            box-shadow: var(--rota-shadow);
        }

        .pricing-card.popular {
            border-color: var(--rota-primary);
            box-shadow: var(--rota-shadow);
            transform: scale(1.05);
        }

        .popular-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--rota-gradient);
            color: white;
            padding: 0.3rem 1.5rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(13, 202, 240, 0.3);
        }

        /* ===== LAYOUT PREVIEW ===== */
        .layout-preview {
            height: 180px;
            background: linear-gradient(45deg, #f8f9fa 25%, #e9ecef 25%, #e9ecef 50%, #f8f9fa 50%, #f8f9fa 75%, #e9ecef 75%);
            background-size: 20px 20px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 2px dashed #dee2e6;
        }

        .layout-preview.active {
            border-color: var(--rota-primary);
            background: rgba(13, 202, 240, 0.05);
        }

        /* ===== FOOTER ===== */
        .rota-footer {
            background: #1a1d29;
            color: #adb5bd;
        }

        .footer-links a {
            color: #adb5bd;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--rota-primary);
        }

        /* ===== ANIMATIONS ===== */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* ===== UTILITIES ===== */
        .text-gradient {
            background: var(--rota-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
        }

        .section-padding {
            padding: 5rem 0;
        }

        @media (max-width: 768px) {
            .section-padding {
                padding: 3rem 0;
            }

            .hero-title {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg rota-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Rotafolio
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">Características</a></li>
                    <li class="nav-item"><a class="nav-link" href="#layouts">Layouts</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Precios</a></li>
                    <li class="nav-item"><a class="nav-link" href="#use-cases">Usos</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonios</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="auth/login.php" class="btn btn-rota-outline">Iniciar Sesión</a>
                    <a href="auth/registro.php" class="btn btn-rota">Crear Cuenta</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container py-5">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6" data-aos="fade-right" data-aos-delay="100">
                    <div class="hero-content">
                        <h1 class="hero-title">
                            Da vida a tus ideas en
                            <span class="text-gradient">tableros visuales</span>
                        </h1>
                        <p class="lead mb-4" style="font-size: 1.2rem;">
                            Crea, organiza y comparte contenido de forma visual. Perfecto para equipos,
                            educación o proyectos personales. ¡Todo en un solo lugar!
                        </p>
                        <div class="d-flex flex-wrap gap-3 mb-4">
                            <a href="auth/registro.php" class="btn btn-rota btn-lg">
                                <i class="bi bi-lightning-fill me-2"></i>Comenzar Gratis
                            </a>
                            <a href="#features" class="btn btn-rota-outline btn-lg">
                                <i class="bi bi-play-circle me-2"></i>Ver Demo
                            </a>
                        </div>
                        <div class="d-flex flex-wrap gap-3 text-white opacity-75">
                            <small><i class="bi bi-check-circle-fill me-1"></i> 3 rotafolios gratis</small>
                            <small><i class="bi bi-check-circle-fill me-1"></i> Sin tarjeta necesaria</small>
                            <small><i class="bi bi-check-circle-fill me-1"></i> Setup en 30 segundos</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <div class="position-relative floating">
                        <div class="rota-card p-3 bg-white shadow-lg" style="border-radius: 25px;">
                            <!-- Mockup del editor -->
                            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                                <div class="d-flex gap-2">
                                    <div class="rounded-circle bg-primary" style="width: 35px; height: 35px;"></div>
                                    <div class="rounded-circle bg-success" style="width: 35px; height: 35px;"></div>
                                    <div class="rounded-circle bg-warning" style="width: 35px; height: 35px;"></div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Añadir</button>
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-share"></i> Compartir</button>
                                </div>
                            </div>

                            <!-- Preview interactivo del editor -->
                            <div class="position-relative" style="height: 400px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 15px; overflow: hidden;">
                                <!-- Posts de ejemplo -->
                                <div class="position-absolute bg-white border border-primary rounded-3 p-3 shadow-sm"
                                    style="width: 160px; height: 120px; top: 40px; left: 50px; cursor: move;">
                                    <i class="bi bi-stickies-fill text-primary fs-4"></i>
                                    <div class="mt-2">
                                        <small class="text-muted d-block">Nota rápida</small>
                                        <small class="text-primary fw-bold">¡Bienvenido!</small>
                                    </div>
                                </div>

                                <div class="position-absolute bg-info text-white rounded-3 p-3 shadow-sm"
                                    style="width: 180px; height: 140px; top: 80px; left: 250px; cursor: move;">
                                    <i class="bi bi-image fs-4"></i>
                                    <div class="mt-2">
                                        <small class="text-white-75 d-block">Imagen</small>
                                        <small class="fw-bold">Fotos del proyecto</small>
                                    </div>
                                </div>

                                <div class="position-absolute bg-warning text-white rounded-3 p-3 shadow-sm"
                                    style="width: 200px; height: 100px; top: 180px; left: 100px; cursor: move;">
                                    <i class="bi bi-link-45deg fs-4"></i>
                                    <div class="mt-2">
                                        <small class="text-white-75 d-block">Enlace</small>
                                        <small class="fw-bold">Documentación importante</small>
                                    </div>
                                </div>

                                <div class="position-absolute bg-success text-white rounded-3 p-3 shadow-sm"
                                    style="width: 150px; height: 150px; top: 220px; left: 320px; cursor: move;">
                                    <i class="bi bi-file-earmark-text fs-4"></i>
                                    <div class="mt-2">
                                        <small class="text-white-75 d-block">Documento</small>
                                        <small class="fw-bold">Plan de trabajo</small>
                                    </div>
                                </div>

                                <!-- Grid overlay (solo visible en hover) -->
                                <div class="position-absolute w-100 h-100" style="background-image: linear-gradient(rgba(13, 202, 240, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(13, 202, 240, 0.05) 1px, transparent 1px); background-size: 20px 20px; pointer-events: none;"></div>
                            </div>

                            <!-- Barra de herramientas inferior -->
                            <div class="d-flex justify-content-center gap-3 mt-3">
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-text-left"></i></button>
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-image"></i></button>
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-link"></i></button>
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-camera-video"></i></button>
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-palette"></i></button>
                            </div>
                        </div>

                        <!-- Elementos decorativos -->
                        <div class="position-absolute top-0 start-0 translate-middle" style="width: 100px; height: 100px; background: rgba(13, 202, 240, 0.1); border-radius: 50%; z-index: -1;"></div>
                        <div class="position-absolute bottom-0 end-0 translate-middle" style="width: 150px; height: 150px; background: rgba(32, 201, 151, 0.1); border-radius: 50%; z-index: -1;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="section-padding bg-white">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill mb-3">POTENTE Y SIMPLE</span>
                <h2 class="display-5 fw-bold mb-3">Todo lo que necesitas para <span class="text-gradient">crear y colaborar</span></h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">Diseñado para equipos modernos, educadores y creadores individuales.</p>
            </div>

            <div class="row g-4">
                <?php
                $features = [
                    ['icon' => 'grid-3x3-gap-fill', 'class' => '', 'title' => 'Layouts Flexibles', 'desc' => '6 layouts diferentes para cada tipo de proyecto'],
                    ['icon' => 'people-fill', 'class' => 'feature-icon-secondary', 'title' => 'Colaboración en Vivo', 'desc' => 'Trabaja simultáneamente con tu equipo'],
                    ['icon' => 'cloud-arrow-up-fill', 'class' => '', 'title' => 'Subida de Archivos', 'desc' => 'Soporta imágenes, PDFs, videos, audio y más'],
                    ['icon' => 'palette-fill', 'class' => 'feature-icon-success', 'title' => 'Personalización Total', 'desc' => 'Colores, fondos, tipografía y más'],
                    ['icon' => 'shield-lock-fill', 'class' => '', 'title' => 'Privacidad Granular', 'desc' => 'Control total sobre quién ve y edita'],
                    ['icon' => 'phone-fill', 'title' => 'Totalmente Responsive', 'desc' => 'Funciona perfecto en móvil, tablet y desktop'],
                ];

                foreach ($features as $index => $feature):
                ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                        <div class="rota-card p-4 h-100 rota-card-hover">
                            <div class="feature-icon <?= $feature['class'] ?? '' ?>">
                                <i class="bi bi-<?= $feature['icon'] ?>"></i>
                            </div>
                            <h4 class="fw-bold mb-3"><?= $feature['title'] ?></h4>
                            <p class="text-muted mb-0"><?= $feature['desc'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Layouts Section -->
    <section id="layouts" class="section-padding bg-light">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill mb-3">FLEXIBILIDAD</span>
                <h2 class="display-5 fw-bold mb-3">Elige el <span class="text-gradient">layout perfecto</span> para cada proyecto</h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">Diferentes formas de organizar y visualizar tus ideas.</p>
            </div>

            <div class="row g-4">
                <?php
                $layouts = [
                    ['icon' => 'grid-3x3-gap', 'name' => 'Muro Libre', 'desc' => 'Arrastra y coloca elementos donde quieras. Perfecto para lluvia de ideas.'],
                    ['icon' => 'grid-fill', 'name' => 'Rejilla', 'desc' => 'Organización ordenada en cuadrícula. Ideal para galerías.'],
                    ['icon' => 'columns-gap', 'name' => 'Columnas', 'desc' => 'Divide en columnas para flujos de trabajo (To-Do, Doing, Done).'],
                    ['icon' => 'easel-fill', 'name' => 'Lienzo', 'desc' => 'Espacio infinito para mapas mentales y diagramas complejos.'],
                    ['icon' => 'clock-fill', 'name' => 'Línea de Tiempo', 'desc' => 'Organiza eventos y hitos cronológicamente.'],
                    ['icon' => 'map-fill', 'name' => 'Mapa', 'desc' => 'Ubica contenido geográficamente. Perfecto para proyectos con ubicación.'],
                ];

                foreach ($layouts as $index => $layout):
                ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                        <div class="rota-card p-4 h-100 rota-card-hover">
                            <div class="layout-preview">
                                <i class="bi bi-<?= $layout['icon'] ?> fs-1 text-primary"></i>
                            </div>
                            <h5 class="fw-bold mb-2"><?= $layout['name'] ?></h5>
                            <p class="text-muted small mb-0"><?= $layout['desc'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Use Cases Section -->
    <section id="use-cases" class="section-padding bg-white">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill mb-3">CASOS DE USO</span>
                <h2 class="display-5 fw-bold mb-3">Perfecto para <span class="text-gradient">muchos propósitos</span></h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">Descubre cómo diferentes profesionales usan Rotafolio.</p>
            </div>

            <div class="row g-4">
                <?php
                $useCases = [
                    ['icon' => 'briefcase-fill', 'title' => 'Equipos de Trabajo', 'desc' => 'Gestión de proyectos, planning, reuniones y documentación colaborativa.'],
                    ['icon' => 'mortarboard-fill', 'title' => 'Educación', 'desc' => 'Aulas virtuales, portafolios estudiantiles, trabajos grupales y recursos educativos.'],
                    ['icon' => 'brush-fill', 'title' => 'Diseñadores', 'desc' => 'Moodboards, inspiración, presentaciones de proyectos y feedback visual.'],
                    ['icon' => 'kanban-fill', 'title' => 'Gestión de Proyectos', 'desc' => 'Seguimiento de tareas, roadmap, sprints y retrospectivas.'],
                    ['icon' => 'person-badge-fill', 'title' => 'Recursos Humanos', 'desc' => 'Onboarding, formación, evaluación y cultura organizacional.'],
                    ['icon' => 'house-door-fill', 'title' => 'Uso Personal', 'desc' => 'Planificación de viajes, organización familiar, hobbies y metas personales.'],
                ];

                foreach ($useCases as $index => $case):
                ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                        <div class="rota-card p-4 h-100 rota-card-hover border-start border-4 border-primary">
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                                    <i class="bi bi-<?= $case['icon'] ?> fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1"><?= $case['title'] ?></h5>
                                    <p class="text-muted small mb-0"><?= $case['desc'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="section-padding bg-light">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill mb-3">PRECIOS SIMPLES</span>
                <h2 class="display-5 fw-bold mb-3">Planes <span class="text-gradient">para todos</span></h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">Comienza gratis, actualiza cuando lo necesites. 3 rotafolios gratis para siempre.</p>
            </div>

            <div class="row justify-content-center g-4">
                <?php
                $planes = [
                    [
                        'name' => 'Gratis',
                        'tagline' => 'Para comenzar',
                        'price_annual' => '0',
                        'price_monthly' => '0',
                        'features' => ['3 Rotafolios', '100MB de espacio', 'Layouts básicos', 'Soporte por email', 'Hasta 1 colaborador'],
                        'cta' => 'Comenzar Gratis',
                        'popular' => false,
                        'color' => 'outline-primary'
                    ],
                    [
                        'name' => 'Pro',
                        'tagline' => 'Más popular',
                        'price_annual' => '49.99',
                        'price_monthly' => '4.99',
                        'features' => ['Rotafolios Ilimitados', '1GB de espacio', 'Todos los layouts', 'Hasta 10 colaboradores', 'Exportar a PDF/Imagen', 'Soporte prioritario', 'Plantillas premium'],
                        'cta' => 'Elegir Pro',
                        'popular' => true,
                        'color' => 'primary'
                    ],
                    [
                        'name' => 'Equipos',
                        'tagline' => 'Para empresas',
                        'price_annual' => '199.99',
                        'price_monthly' => '19.99',
                        'features' => ['Todo lo del Pro', '5GB de espacio', 'Hasta 50 colaboradores', 'Estadísticas avanzadas', 'API de integración', 'Soporte 24/7', 'SSO y LDAP'],
                        'cta' => 'Contactar Ventas',
                        'popular' => false,
                        'color' => 'outline-primary'
                    ]
                ];

                foreach ($planes as $index => $plan):
                ?>
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="rota-card pricing-card p-4 h-100 position-relative <?= $plan['popular'] ? 'popular' : '' ?>">
                            <?php if ($plan['popular']): ?>
                                <div class="popular-badge">RECOMENDADO</div>
                            <?php endif; ?>

                            <div class="text-center mb-4">
                                <h4 class="fw-bold mb-1"><?= $plan['name'] ?></h4>
                                <p class="text-muted small mb-3"><?= $plan['tagline'] ?></p>
                                <div class="my-3">
                                    <span class="display-3 fw-bold">$<?= $plan['price_annual'] ?></span>
                                    <span class="text-muted">/año</span>
                                </div>
                                <small class="text-muted">O $<?= $plan['price_monthly'] ?>/mes facturado mensualmente</small>
                            </div>

                            <hr class="my-4">

                            <ul class="list-unstyled mb-4">
                                <?php foreach ($plan['features'] as $feature): ?>
                                    <li class="mb-3">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        <?= $feature ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mt-auto">
                                <a href="auth/registro.php?plan=<?= strtolower($plan['name']) ?>"
                                    class="btn btn-<?= $plan['color'] ?> w-100 py-3 fw-bold">
                                    <?= $plan['cta'] ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5" data-aos="fade-up">
                <p class="text-muted">
                    <i class="bi bi-shield-check text-primary me-1"></i>
                    Todos los planes incluyen 3 rotafolios gratis para siempre. Garantía de reembolso de 30 días.
                </p>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="section-padding bg-white">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill mb-3">CONFIANZA</span>
                <h2 class="display-5 fw-bold mb-3">Lo que dicen nuestros <span class="text-gradient">usuarios</span></h2>
                <p class="lead text-muted mx-auto" style="max-width: 700px;">Más de 10,000 equipos y creadores confían en Rotafolio.</p>
            </div>

            <div class="row g-4">
                <?php
                $testimonios = [
                    [
                        'name' => 'María Rodríguez',
                        'role' => 'Directora de Proyectos',
                        'company' => 'TechSolutions RD',
                        'text' => 'Rotafolio transformó nuestra forma de trabajar. Ahora todas nuestras reuniones y planning son visuales y colaborativas.',
                        'avatar' => 'M'
                    ],
                    [
                        'name' => 'Carlos Sánchez',
                        'role' => 'Profesor Universitario',
                        'company' => 'UNPHU',
                        'text' => 'Mis estudiantes están más comprometidos. Usamos rotafolios para trabajos grupales y los resultados son increíbles.',
                        'avatar' => 'C'
                    ],
                    [
                        'name' => 'Laura Gómez',
                        'role' => 'Diseñadora UX/UI',
                        'company' => 'Freelance',
                        'text' => 'Perfecto para presentar mis diseños a clientes. La colaboración en tiempo real hace el feedback mucho más eficiente.',
                        'avatar' => 'L'
                    ]
                ];

                foreach ($testimonios as $index => $testimonio):
                ?>
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="rota-card p-4 h-100">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 60px; height: 60px; font-size: 1.5rem; font-weight: bold;">
                                    <?= $testimonio['avatar'] ?>
                                </div>
                                <div class="ms-3">
                                    <h6 class="fw-bold mb-0"><?= $testimonio['name'] ?></h6>
                                    <small class="text-muted"><?= $testimonio['role'] ?></small><br>
                                    <small class="text-primary"><?= $testimonio['company'] ?></small>
                                </div>
                            </div>
                            <p class="text-muted mb-0">"<?= $testimonio['text'] ?>"</p>
                            <div class="text-warning mt-3">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-5" style="background: var(--rota-gradient-dark);">
        <div class="container py-5">
            <div class="row justify-content-center text-center text-white" data-aos="fade-up">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-4">¿Listo para transformar tu forma de trabajar?</h2>
                    <p class="lead mb-5 opacity-75">Únete a miles de equipos y creadores que ya usan Rotafolio.</p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="auth/registro.php" class="btn btn-light btn-lg px-5 fw-bold text-primary">
                            <i class="bi bi-lightning-fill me-2"></i>Crear Cuenta Gratis
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-5">
                            <i class="bi bi-play-circle me-2"></i>Ver Demo en Video
                        </a>
                    </div>
                    <p class="mt-4 small opacity-75">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Sin tarjeta de crédito • Setup en 30 segundos • 3 rotafolios gratis para siempre
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="rota-footer pt-5">
        <div class="container pt-4">
            <div class="row g-4">
                <div class="col-lg-4">
                    <a class="navbar-brand fw-bold fs-3 text-white mb-3 d-block" href="/">
                        <i class="bi bi-grid-3x3-gap-fill me-2"></i>Rotafolio
                    </a>
                    <p class="mb-4" style="color: #adb5bd;">
                        Plataforma para crear, compartir y colaborar en tableros visuales.
                        Diseñado con ❤️ en República Dominicana.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white opacity-75 hover-opacity-100" style="transition: opacity 0.3s;">
                            <i class="bi bi-twitter fs-5"></i>
                        </a>
                        <a href="#" class="text-white opacity-75 hover-opacity-100" style="transition: opacity 0.3s;">
                            <i class="bi bi-facebook fs-5"></i>
                        </a>
                        <a href="#" class="text-white opacity-75 hover-opacity-100" style="transition: opacity 0.3s;">
                            <i class="bi bi-instagram fs-5"></i>
                        </a>
                        <a href="#" class="text-white opacity-75 hover-opacity-100" style="transition: opacity 0.3s;">
                            <i class="bi bi-linkedin fs-5"></i>
                        </a>
                        <a href="#" class="text-white opacity-75 hover-opacity-100" style="transition: opacity 0.3s;">
                            <i class="bi bi-youtube fs-5"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-4">
                    <h6 class="text-white fw-bold mb-3">Producto</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#features">Características</a></li>
                        <li class="mb-2"><a href="#layouts">Layouts</a></li>
                        <li class="mb-2"><a href="#pricing">Precios</a></li>
                        <li class="mb-2"><a href="#use-cases">Casos de Uso</a></li>
                        <li class="mb-2"><a href="#">Actualizaciones</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4">
                    <h6 class="text-white fw-bold mb-3">Recursos</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#">Documentación</a></li>
                        <li class="mb-2"><a href="#">Blog</a></li>
                        <li class="mb-2"><a href="#">Plantillas</a></li>
                        <li class="mb-2"><a href="#">API</a></li>
                        <li class="mb-2"><a href="#">Soporte</a></li>
                    </ul>
                </div>

                <div class="col-lg-4">
                    <h6 class="text-white fw-bold mb-3">Compañía</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="#">Sobre Nosotros</a></li>
                        <li class="mb-2"><a href="#">Carreras</a></li>
                        <li class="mb-2"><a href="#">Contacto</a></li>
                        <li class="mb-2"><a href="#">Legal</a></li>
                        <li class="mb-2"><a href="#">Privacidad</a></li>
                    </ul>
                </div>
            </div>

            <hr class="my-4 border-white-10">

            <div class="row">
                <div class="col-md-6">
                    <p class="small mb-0" style="color: #6c757d;">
                        &copy; 2024 Rotafolio by NovaExperto. Todos los derechos reservados.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="small text-decoration-none me-3" style="color: #6c757d;">Términos de Servicio</a>
                    <a href="#" class="small text-decoration-none me-3" style="color: #6c757d;">Política de Privacidad</a>
                    <a href="#" class="small text-decoration-none" style="color: #6c757d;">Cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>




    <script>
        // Inicializar AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.rota-navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Solo para enlaces internos (no para # solo)
                if (href !== '#') {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        const navbarHeight = document.querySelector('.rota-navbar').offsetHeight;
                        const targetPosition = target.offsetTop - navbarHeight - 20;

                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });

                        // Cerrar navbar en móvil
                        const navbarCollapse = document.querySelector('.navbar-collapse');
                        if (navbarCollapse.classList.contains('show')) {
                            new bootstrap.Collapse(navbarCollapse).toggle();
                        }
                    }
                }
            });
        });

        // Demo interactivo del editor
        document.addEventListener('DOMContentLoaded', function() {
            const draggables = document.querySelectorAll('[style*="cursor: move"]');

            draggables.forEach(draggable => {
                draggable.addEventListener('mousedown', function(e) {
                    e.preventDefault();

                    const initialX = e.clientX;
                    const initialY = e.clientY;
                    const element = this;
                    const initialLeft = parseInt(element.style.left) || 50;
                    const initialTop = parseInt(element.style.top) || 40;

                    function onMouseMove(e) {
                        const dx = e.clientX - initialX;
                        const dy = e.clientY - initialY;

                        element.style.left = (initialLeft + dx) + 'px';
                        element.style.top = (initialTop + dy) + 'px';
                        element.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                    }

                    function onMouseUp() {
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                        element.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                    }

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            });
        });

        // Contador de usuarios (simulado)
        function updateUserCount() {
            const countElement = document.querySelector('.user-count');
            if (countElement) {
                let count = 10000;
                setInterval(() => {
                    count += Math.floor(Math.random() * 10);
                    countElement.textContent = count.toLocaleString();
                }, 5000);
            }
        }

        // Inicializar contador
        updateUserCount();
    </script>
</body>

</html>