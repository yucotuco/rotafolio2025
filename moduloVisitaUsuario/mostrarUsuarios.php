<?php
ob_start();
session_start();
require_once "../conn/connrota.php";
require_once "../funcionesyClases/claseCajeroBancario.php";
require_once "../funcionesyClases/funcionesComunes.php";

// Check if user is logged in
if (isset($_SESSION['rotaforlioyuco2025'])) {
    $idUsuario = $_SESSION['rotaforlioyuco2025'];

    if (isset($_GET["activa"])) {
        $modalAviso = "si";
        $idClienteGet = $_GET["idCliente"];

        /*   echo "    idCliente    $idClienteGet";
        die(); */
    } else {
        $modalAviso = "no";
        $idClienteGet = "";
    }

    /* Sacar el Idbanco del usuario */
    $sql = "SELECT * FROM usuario WHERE id =$idUsuario";
    SacarDatos($sql, $pdoConn, "banco");
    $idBanco = $dato1;
    nombreBanco($idBanco);
    $nombreBanco = $nombreBancoBBDD;
    SacarDatos($sql, $pdoConn, "nombre");
    $nombreUsuario1 = strtoupper($dato1);
    SacarDatos($sql, $pdoConn, "apellido");
    $apellidoUsuario = strtoupper($dato1);
    $nombreUsuarioMuestra = "$nombreUsuario1  $apellidoUsuario";
    // Get user status and email   
    SacarDatos($sql, $pdoConn, "estado");
    $estado = $dato1;

    if ($estado != 1) {
        unset($_SESSION["vistaplataforma"]);
        header("location: index.php");
        die();
    }

    SacarDatos($sql, $pdoConn, "correo");
    $correo = $dato1;

    SacarDatos($sql, $pdoConn, "id");
    $usuarioExiste = $dato1;
    if ($usuarioExiste == "") {
        header("location: ../moduloUsuario/login/cerrar.php");
        die();
    }

    // Sacar el menu activo 
    $active1 = "noactive";
    $active2 = "no_active";
    $active3 = "no_active";
    $active4 = "no_active";
    $active5 = "no_active";

    $nombrePagina = "Mostrar Usuarios";
    $accionVisita =  $nombrePagina;
    require_once "visita_acciones.php";


    //() FECHA
    date_default_timezone_set('UTC');
    date_default_timezone_set("America/Santo_Domingo");
    setlocale(LC_ALL, "es_ES");
    $fechaHoraHoy = date('Y-m-d H:i:s');
    $fechaHoraHoy_muestra = date('d/m/Y H:i:s');
    $fechaHoy = date('Y-m-d');

    //Fin logica deposito 
    // Modales alertas 
    if (isset($_GET["correcto"])) {
        $modalAviso = "si";
        switch ($_GET["correcto"]) {
            case "depositoCorrecto":
                $mensajeModal = "Depósito Agregado Correctamente";
                break;
            case "reembolso":
                $mensajeModal = "Reembolso Agregado Correctamente";
                break;
            case "procedencia":
                $mensajeModal = "Caja Creada Correctamente";
                break;
            case "ClaveCambiada":
                $mensajeModal = "Clave Cambiada Correctamente";
                break;
            default:
                $mensajeModal = "Listo!";
        }
    }

    if (isset($_GET["error"])) {
        $modalAvisoError = "si";
        switch ($_GET["error"]) {
            case "errorUsuario":
                $mensajeModalError = "Usuario o Clave incorrecta!";
                break;
            case "ClaveCambiada":
                $mensajeModalError = "Clave Cambiada Correctamente";
                break;
            case "montoescero":
                $mensajeModalError = "Por Favor!. El Monto RD$ no puede ser 0 (Cero)";
                break;
            case "montosuperior":
                $mensajeModalError = "Por Favor!. El Monto RD$ no puede ser mayor al fondo del proyecto";
                break;
            default:
                $mensajeModalError = "Error!, No se ejecutó la acción";
        }
    }
} else {
    header("location: ../index.php");
    die();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo  $nombrePagina ?> | Cajero Bancario</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">


    <!-- Data table  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.1.2/css/buttons.bootstrap5.css">

    <!-- Datable reponsivas -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.3/css/responsive.bootstrap5.css">

    <!-- Jquery -->
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>


    <!-- Css Principal -->
    <link rel="stylesheet" href="../css/css_principal.css">

    <!-- El ccs local del Modal -->
    <link rel="stylesheet" href="../css/css_modal.css">

    <style>
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .preloader-hidden {
            opacity: 0;
            pointer-events: none;
        }
    </style>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-body-secondary">
    <!-- Sidebar -->
    <?php

    include_once "./menuIzquierda.php";

    ?>

    <!-- Overlay para móviles -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <nav class="navbar top-bar navbar-expand navbar-light bg-white">
            <div class="container-fluid">
                <!-- Botón para mostrar/ocultar sidebar en móviles -->
                <button class="mobile-menu-toggle me-2" id="mobileSidebarToggle">
                    <i class="bi bi-list"></i>
                </button>

                <!-- Menú superior derecho -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="badge bg-danger rounded-pill notification-badge">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li>
                                <h6 class="dropdown-header">Notificaciones</h6>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0 text-success">
                                            <i class="bi bi-cash-stack"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="mb-0">Nuevo depósito realizado</p>
                                            <small class="text-muted">Hace 5 minutos</small>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0 text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="mb-0">Billetes de $20 bajos</p>
                                            <small class="text-muted">Hace 1 hora</small>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-center" href="../reportes/muestraNotificaciones.php">Ver todas</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <span class="d-none d-md-inline">Admin</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li>
                                <h6 class="dropdown-header">Administrador</h6>
                            </li>
                            <li><a class="dropdown-item" href="../configuracion/perfilUsuario.php"><i class="bi bi-person me-2"></i>Perfil</a></li>
                            <li><a class="dropdown-item" href="../configuracion/configuracion.php"><i class="bi bi-gear me-2"></i>Configuración</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item" href="../moduloUsuario/login/cerrar.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Contenido principal -->
        <div class="container-fluid px-4 py-4">
            <!-- El contenido del dashboard iría aquí -->
            <div class="small">
                <div class="row">
                    <div class="col-6">
                        <a class="btn" href="../tablero_admin/index.php"><i class="bi bi-folder-x fs-4"></i> Salir</a>
                    </div>
                    <div class="col-6 text-end">
                        <div class="small">
                            <?php echo $fechaHoraHoy_muestra ?>
                        </div>
                        <h2 class="fw-bold display-5 mb-1"><?php echo $nombrePagina ?></h2>


                    </div>
                </div>
            </div>


            <div class="row g-4 justify-content-center my-3">
                <div class="col-lg-12">
                    <div class="bg-white p-4 rounded-4 shadow-sm h-100"> <!--  -->
                        <?php
                        $query = "SELECT `id`, `usuario`, `nombre`, `clave`, `correo`, `apellido`, `nivel`,  `estado`, `fecha`, `banco`, `codigoReiniciaSistema`  FROM `usuario`";
                        $sentence = $pdoConn->prepare($query);
                        $sentence->execute(array());
                        $result_control_usuario = $sentence->fetchAll(PDO::FETCH_ASSOC);  ?>

                        <!-- Tabla -->
                        <table id="tablaDato" class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Apellido</th>
                                    <th>Correo</th>
                                    <th>Nivel</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Banco</th>
                                    <th>Código Reinicio Sistema</th>
                                    <th>Curso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($result_control_usuario as $row) {

                                    $idBanco = $row['banco'];

                                    $sql = "SELECT * FROM `bancos` WHERE `id`= $idBanco";
                                    SacarDatos($sql, $pdoConn, "nombre");
                                    $nombreBanco = $dato1;



                                    // Aquí puedes usar las variables según necesites
                                ?>

                                    <tr class="small">
                                        <td class="text-small text-uppercase text-start">
                                            <?php echo htmlspecialchars($row['id']); ?>
                                        </td>
                                        <td class="text-small text-uppercase text-start">
                                            <?php echo htmlspecialchars($row['usuario']); ?>
                                        </td>
                                        <td class="text-small text-uppercase text-start">
                                            <?php echo htmlspecialchars($row['nombre']); ?>
                                        </td>
                                        <td class="text-small text-uppercase text-start">
                                            <?php echo htmlspecialchars($row['apellido']); ?>
                                        </td>
                                        <td class="text-small text-start">
                                            <?php echo htmlspecialchars($row['correo']); ?>
                                        </td>

                                        <td class="text-small text-start">
                                            <?php echo htmlspecialchars($row['nivel']); ?>
                                        </td>
                                        <td class="text-small text-start">
                                            <?php echo htmlspecialchars($row['estado']); ?>
                                        </td>
                                        <td class="text-small text-start">
                                            <?php echo htmlspecialchars($row['fecha']); ?>
                                        </td>
                                        <td class="text-small text-uppercase text-start">
                                            <?php echo htmlspecialchars($nombreBanco);
                                            echo "<br> ID =  $idBanco ";
                                            ?>
                                        </td>
                                        <td class="text-small text-start">
                                            <?php echo htmlspecialchars($row['codigoReiniciaSistema']); ?>
                                        </td>
                                        <td>

                                        </td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                            <!-- <tbody id="cuerpoTabla">
                            </tbody> -->
                        </table>




                    </div>
                </div>
            </div>


        </div>
    </div>

    <!-- Footer mejorado (sin afectar CSS existente) -->
    <footer class="bg-secondary text-white py-4 mt-auto">
        <div class="container">
            <div class="row text-center my-5">
                <div class="col-md-2">

                </div>
                <div class="col-md-10">
                    <h2 class="fw-bold display-5 mb-3">Información de Contacto</h2>
                </div>

            </div>
            <div class="row mt-md-5">
                <div class="col-md-2">

                </div>
                <div class="col-md-5">
                    <h5 class="mb-3">Contacto</h5>
                    <p><i class="bi bi-envelope me-2"></i> Email: info@novaexperto.com</p>
                    <p><i class="bi bi-phone me-2"></i> Teléfono: +1 809 873 4531</p>
                </div>
                <div class="col-md-5">
                    <h5 class="mb-3">Aviso</h5>
                    <div class="small">
                        Plataforma de simulación de funciones bancarias que permite practicar las tareas habituales de un cajero. Ofrece un enfoque genérico, no vinculado a ninguna entidad financiera en particular, lo que facilita el aprendizaje de las operaciones comunes en cualquier banco. Incluye un mes de acceso gratuito.
                    </div>
                    <div class="small my-2">
                        Desarrollada por: <strong>Edward Nova</strong>
                    </div>
                    <ul class="list-unstyled">
                        <!-- <li><a href="#" class="text-white text-decoration-none">Inicio</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Servicios</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Políticas</a></li> -->
                    </ul>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2">

                </div>
                <div class="col-md-10">
                    <hr class="my-3 opacity-50">
                    <p class="mb-0 text-center">&copy; 2023 Cajero Bancario NovaExperto. Todos los derechos reservados.</p>
                </div>
            </div>

        </div>
    </footer>


    <div id="divMdal"></div><!-- este codigo permite mostrar el modal  -->
    <!-- Modal Alerta -->
    <?php

    include_once "../../alerta/modal_Alerta.php";
    ?>

    <!-- Bootstrap 5.3 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTable -->

    <!-- Datatable Yuco -->
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.1.2/js/dataTables.buttons.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.bootstrap5.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.1.2/js/buttons.colVis.min.js"></script>

    <!-- Bibliotecas para cambiar formato al texto  -->
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.flash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.excel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons/js/buttons.excelStyles.min.js"></script>

    <!-- Datatable reponsive -->
    <script src="https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.3/js/responsive.bootstrap5.js"></script>

    <!-- Controlar menu izquierda  -->
    <script>
        // Control del menú
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarToggle = document.getElementById('sidebarToggle');

        // Mostrar/ocultar menú en móviles
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            sidebarOverlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
        });

        // Ocultar menú al hacer clic en el overlay
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.style.display = 'none';
        });

        // Toggle sidebar en desktop
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            document.getElementById('mainContent').classList.toggle('main-content-collapsed');
        });

        // Inicializar dropdowns del sidebar
        const sidebarDropdowns = [].slice.call(document.querySelectorAll('.sidebar .dropdown-toggle'));
        sidebarDropdowns.map(function(dropdownToggle) {
            dropdownToggle.addEventListener('click', function(e) {
                // En móviles, evitar que se cierre el menú al abrir dropdowns
                if (window.innerWidth <= 768) {
                    e.stopPropagation();
                }
            });
        });

        // Ocultar menú al hacer clic en un enlace (para móviles)
        document.querySelectorAll('.sidebar .nav-link:not(.dropdown-toggle)').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.style.display = 'none';
                }
            });
        });
    </script>


    <!-- Tabla  tablaDeposito -->
    <script>
        $(document).ready(function() {
            $('#tablaDato').DataTable({
                responsive: true,
                language: {
                    "decimal": "",
                    "emptyTable": "No hay datos disponibles en la tabla",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                    "infoFiltered": "(filtrado de _MAX_ registros totales)",
                    "thousands": ",",
                    "lengthMenu": "Mostrar _MENU_",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "search": "Buscar:",
                    "zeroRecords": "No se encontraron registros coincidentes",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    },
                    "aria": {
                        "sortAscending": ": activar para ordenar la columna de manera ascendente",
                        "sortDescending": ": activar para ordenar la columna de manera descendente"
                    }
                },
                dom: '<"row mb-3"<"col-sm-12 col-md-6 d-flex align-items-center"lB><"col-sm-12 col-md-6"f>>' +
                    '<"row"<"col-sm-12"tr>>' +
                    '<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [{
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i>Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i>PDF',
                        className: 'btn btn-danger btn-sm',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ], // Esta línea ordena por la primera columna (índice 0) de forma descendente
                initComplete: function() {
                    // Personalizar botones después de renderizar la tabla
                    const $buttonsContainer = $(this.api().buttons().container());
                    $buttonsContainer
                        .appendTo('#tablaUsuarios_wrapper .col-md-6:eq(0)')
                        .addClass('d-flex justify-content-start ms-3');
                }
            });

            // Aplicar clases de Bootstrap y FontAwesome a los botones de exportación
            $('.dt-buttons button').each(function() {
                $(this).removeClass('dt-button').addClass('btn');
            });
        });
    </script>


</body>

</html>