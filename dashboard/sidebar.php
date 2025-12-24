<?php
// dashboard/sidebar.php
?>
<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header p-4 border-bottom">
        <a href="index.php" class="logo text-decoration-none">
            <i class="bi bi-grid-3x3-gap-fill text-primary"></i>
            <span class="fw-bold text-primary ms-2">Rotafolio</span>
        </a>
    </div>

    <div class="sidebar-content py-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="mis_rotafolios.php" class="nav-link active">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <span>Mis Rotafolios</span>
                    <?php if (isset($total_rotafolios) && $total_rotafolios > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-auto"><?= $total_rotafolios ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#crearRotafolioModal">
                    <i class="bi bi-plus-circle"></i>
                    <span>Nuevo Rotafolio</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="estadisticas.php" class="nav-link">
                    <i class="bi bi-bar-chart"></i>
                    <span>Estadísticas</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="plantillas.php" class="nav-link">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Plantillas</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="configuracion.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span>Configuración</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="planes.php" class="nav-link">
                    <i class="bi bi-star"></i>
                    <span>Planes</span>
                </a>
            </li>

            <li class="nav-item mt-4">
                <div class="px-4">
                    <small class="text-muted text-uppercase fw-bold">CONFIGURACIÓN</small>
                </div>
            </li>

            <li class="nav-item">
                <a href="../auth/cerrar.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer p-4 border-top">
        <div class="d-flex align-items-center">
            <div class="user-avatar me-3">
                <?= strtoupper(substr($usuario_nombre, 0, 2)) ?>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold text-truncate"><?= htmlspecialchars($usuario_nombre) ?></div>
                <div class="text-muted small">Plan <?= ucfirst($usuario_plan) ?></div>
            </div>
        </div>
    </div>
</nav>

<!-- Botón para móvil -->
<button class="btn btn-outline-primary d-lg-none position-fixed top-2 start-2" id="sidebarToggle">
    <i class="bi bi-list"></i>
</button>

<style>
    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }

    .nav-link {
        color: #495057;
        padding: 0.75rem 1.5rem;
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
</style>

<script>
    // Toggle sidebar en móvil
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
</script>