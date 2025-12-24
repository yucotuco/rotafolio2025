<?php
session_start();
require_once "../modulo_conn/conpdo.php";
require_once "../modulo_conn/conpdoAlerta.php";

$fechaActualizacion = $_SESSION["actualiza"];


if (isset($_SESSION['rotaforlioyuco2025'])) {
    /* Control Visita en cada pagina debe haber una accion de las visitas  */
    $accionVisita = "visitas_usuario.php";
    require_once "./control_visita.php";


    $cedulaUsuario = $_SESSION['rotaforlioyuco2025'];
    /*  echo "    $cedulaUsuario" . $_SESSION['rotaforlioyuco2025']; */
    /* 
    die(); */


    $query = "SELECT t1.id, t1.id_usuario, t1.visitas_diarias_id, t1.fecha, t1.hora, t1.accion
FROM visitas_acciones t1
INNER JOIN (
    SELECT id_usuario, MAX(CONCAT(fecha, ' ', hora)) AS max_fecha_hora
    FROM visitas_acciones
    GROUP BY id_usuario
) t2
ON CONCAT(t1.fecha, ' ', t1.hora) = t2.max_fecha_hora
AND t1.id_usuario = t2.id_usuario
ORDER BY t1.fecha DESC, t1.hora DESC;";

    $sentence =  $pdoConn->prepare($query);
    $sentence->execute(array());
    $result_visita = $sentence->fetchAll(PDO::FETCH_ASSOC);
} else {
    header("location: ../index.php?result=intento_fallido_de_inicio_de_sesion");
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ayuda SIGERD - NovaExperto.com - <?php echo $fechaActualizacion ?></title>

    <!-- Estilo Boostrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Incluir estilo de los btotones -->
    <?php include_once "./dataTableHeader.php" ?>


    <!-- Styles Select2 v4.1.x-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Or for RTL support -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" />





</head>

<body style="background: #f2f2f2;" class="m-0 p-0">
    <div class="container-fluid mx-0 px-0 bg-white fixed-top">
        <div class="container ">
            <div class="row">
                <div class="col-12 ">
                    <nav class="navbar navbar-expand-lg bg-body-info">
                        <div class="container-fluid">
                            <a class="navbar-brand" href="#">NovaExperto.com</a>
                            <div class="navbar-toggler" type="div" data-bs-toggle="collapse" data-bs-target="#navbarScroll" aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
                                <span class="navbar-toggler-icon"></span>
                            </div>
                            <div class="collapse navbar-collapse" id="navbarScroll">
                                <ul class="navbar-nav me-auto my-2 my-lg-0 navbar-nav-scroll" style="--bs-scroll-height: 100px;">
                                    <li class="nav-item">
                                        <a class=" btn btn-info btn-sm" aria-current="page" href="index.php">Inicio</a>
                                    </li>

                                </ul>
                                <form class="d-flex" role="search">
                                    Hola! <strong class="mx-2"><?php echo $nombreUsuario ?></strong> | <a class="mx-2 btn btn-sm btn-dark" href="../modulo_usuario/cerrar.php">Cerrar Sesión</a>
                                </form>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
        <hr class="m-0 p-0">
    </div>



    <?php

    if ($cedulaUsuario == "01000942944") {
        $query = "SELECT `id`, `superadministrador`, `cedula`, `contrasena`, `clave_generica`, `correoInstitucional`, `nombre_completo`, `mensaje_enviado`, `correo`, `usuarioarea`, `nivel_usuario`, `codigo_sigerd`, `funcion`, `whatsapp`, `manejo`, `nombrado`, `codigoCorreo`, `activo`, `distrito`, `online`, `rutaimagen`, `formatoFile`, `fechaRegistro`, `verificadoSIGERD`, `activo_temporal` FROM `usuario`";

        $sentence =  $pdoConn->prepare($query);
        $sentence->execute(array());
        $result_control_usuario = $sentence->fetchAll(PDO::FETCH_ASSOC);

    ?>
        <!-- Control Usuario -->
        <div class="container-fluid py-5 bg-secondary ">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="shadow-sm p-3 bg-white rounded">
                            <div class="fs-3 my-4 text-center">
                                Control de Usuario
                            </div>
                            <div>
                                <a href="">Nuevo usuario</a>
                            </div>
                            <!--  <table id="controlUsuario" class="table table-striped"> -->
                            <!-- <table class="display nowrap" style="width:100%"> -->
                            <table id="controlUsuario" class="table table-sm table-striped nowrap" style="width:100%">
                                <thead>
                                    <tr class="small">
                                        <th>Id</th>
                                        <th>Cedula</th>
                                        <th>Nombre completo</th>
                                        <th>
                                            Detalles
                                        </th>
                                        <th>
                                            Estado
                                        </th>

                                        <th>Nivel</th>
                                        <th>
                                            Distrito
                                        </th>
                                        <th>
                                            Centro
                                        </th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>



                                    <?php
                                    foreach ($result_control_usuario as $row) {


                                        $idUsuario = $row["id"];
                                        $cedula_bbdd = $row["cedula"];
                                        $nombre_usuario_bbdd = $row["nombre_completo"];
                                        $estado_bbdd = $row["activo"];
                                        $clave_generica = $row["clave_generica"];
                                        $whatsappBBDD = $row["whatsapp"];
                                        $centroBBDD = $row["codigo_sigerd"];
                                        $distritoBBDD = $row["distrito"];
                                        $nivelBBDD = $row["nivel_usuario"];

                                    ?>

                                        <tr class="small">
                                            <td class=" text-small text-uppercase text-start">
                                                <?php echo htmlspecialchars($idUsuario);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>

                                            <td class=" text-small text-uppercase">
                                                <?php echo htmlspecialchars($cedula_bbdd);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>
                                            <td class=" text-small text-uppercase">
                                                <?php echo htmlspecialchars($nombre_usuario_bbdd);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>
                                            <td class=" text-small text-uppercase">
                                                <?php echo htmlspecialchars($nombre_usuario_bbdd);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>
                                            <td>
                                                <!--  <div class="progress" role="progressbar" aria-label="Example with label" aria-valuenow="<?php echo  $porciento_asistencia  ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar " style="width: <?php echo  $porciento_asistencia  ?>%"><?php echo  $porciento_asistencia  ?>%</div>
                                            </div> -->

                                                <?php

                                                if ($estado_bbdd == 1) {
                                                    echo htmlspecialchars("Activo");
                                                } else if ($estado_bbdd == 2) {
                                                    echo htmlspecialchars("Bloqueado");
                                                } else {
                                                    echo "No Definido";
                                                }

                                                ?>




                                            </td>


                                            <td class=" text-small text-uppercase">
                                                <?php echo htmlspecialchars($nivelBBDD);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>
                                            <td class=" text-small text-uppercase">
                                                <?php echo htmlspecialchars($distritoBBDD);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>
                                            <td class=" text-small text-uppercase">
                                                <?php echo htmlspecialchars($centroBBDD);

                                                // Escapar HTML para evitar inyección 
                                                ?>
                                            </td>
                                            <td>
                                                <!-- Button trigger modal -->
                                                <a onclick="modalModificaUsuario('<?php echo     $idUsuario  ?>')" href="javascript:void(0)" class="btn btn-sm btn-secondary my-md-1">Modificar</a>

                                                <a class="btn btn-danger btn-sm m-1" onclick="return confirmarEliminacion()" href="visitas_usuarios_eliminar_usuario.php?id_usuario=<?php echo  $idUsuario  ?>">Eliminar</a>

                                                <a class="btn btn-primary btn-sm m-1" onclick="return confirmarReinicio()" href="visitas_usuarios_procesa_clave.php?id_usuario=<?php echo  $idUsuario  ?>">Reiniciar Clave</a>

                                                <a class="btn btn-success btn-sm m-1" href="https://wa.me/<?php echo  "$whatsappBBDD?" ?>text=*NovaExperto.com*%0ARegional%20de%20Educación%2003%2C%20Azua%0A%0A%0AHola%2C%20<?php echo "*$nombre_usuario_bbdd*" ?>%0A%0ALe%20informamos%20que%20su%20usuario%20fue%20reiniciado%20y%20puede%20entrar%20usando%20su%20cédula%20en%20usuario%20y%20en%20la%20clave%20y%20luego%20cambiarla%20a%20la%20que%20desee%0A%0A" target="_blank">
                                                    WhatsApp
                                                </a>
                                                <a class="btn btn-success btn-sm m-1" href="https://wa.me/<?php echo  "$whatsappBBDD?" ?>text=*NovaExperto.com*%0ARegional%20de%20Educación%2003%2C%20Azua%0A%0A%0AHola%2C%20<?php echo "*$nombre_usuario_bbdd*" ?>%0A%0ALe%20informamos%20que%20su%20usuario%20fue%20*Creado%20Correctamente*%20en%20el%20centro:%20<?php echo $centroBBDD ?>%20y%20puede%20entrar%20usando%20su%20cédula%20en%20usuario%20y%20en%20la%20clave%20y%20luego%20cambiarla%20a%20la%20que%20desee%0A%0A" target="_blank">
                                                    Nuevo Usuario
                                                </a>
                                            </td>

                                        </tr>

                                    <?php
                                    }
                                    ?>



                                </tbody>
                            </table>



                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    ?>


    <div class="container-fluid mt-5  px-md-5">
        <div class="row">
            <div class="col-12 text-center my-5">
                <div class="fs-1">
                    Control de Visitas
                </div>
            </div>

            <div class="col-md-4">
                <div class="shadow-sm p-3 bg-white rounded">
                    <div class="fs-3 my-4 text-center">
                        Ultimas Visitas
                    </div>

                    <table id="muestraVisitas" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha </th>
                                <th>Usuario</th>
                                <th>Centro</th>

                            </tr>
                        </thead>
                        <tbody>



                            <?php
                            foreach ($result_visita as $row) {
                                $idUsuario = $row["id_usuario"];
                                $accion = $row["accion"];
                                $fecha = $row["fecha"];
                                $hora = $row["hora"];

                                // Combina la fecha y la hora
                                $fechaHora = $fecha . ' ' . $hora;

                                // Convierte esto en un objeto DateTime si necesitas manipularlo más adelante
                                $dateTime = new DateTime($fechaHora);

                                // Formato en cadena, por ejemplo 'YYYY-MM-DD HH:MM:SS'
                                $fechaHora = $dateTime->format('Y-m-d H:i:s');

                                // Obtener nombre del usuario
                                $query = "SELECT * FROM `usuario` WHERE `id`=? LIMIT 1";
                                $sentence =  $pdoConn->prepare($query);
                                $sentence->execute([$idUsuario]);  // Usar array en lugar de array()
                                $result_usuario = $sentence->fetch();

                                if ($result_usuario) {
                                    $nombre_usuario = $result_usuario["nombre_completo"];
                                    $codigo_sigerd = $result_usuario["codigo_sigerd"];
                                    $nivelUsuario = $result_usuario["nivel_usuario"];
                                } else {
                                    $nombre_usuario = "No Definido";
                                    $codigo_sigerd = 0;
                                    $nivelUsuario = "";
                                }

                                $nombreUsuario =

                                    // Obtener centro del usuario
                                    $query = "SELECT `Centro` FROM `matriculaporservicios` WHERE `codigo_sigerd`=?";
                                $sentence = $pdoAlerta->prepare($query);
                                $sentence->execute([$codigo_sigerd]);  // Usar array en lugar de array()
                                $result_centro = $sentence->fetch();

                                if ($result_centro) {
                                    $centro_usuario = $result_centro["Centro"];
                                } else {
                                    $centro_usuario = "Técnico";
                                }
                            ?>

                                <tr class="small">
                                    <td>
                                        <?php echo htmlspecialchars($fechaHora); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                    <td class="text-uppercase">
                                        <?php echo htmlspecialchars($nombre_usuario); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($centro_usuario); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                </tr>

                            <?php
                            }
                            ?>



                        </tbody>
                    </table>


                </div>
            </div>
            <div class="col-md-4">
                <div class="shadow-sm p-3 bg-white rounded">
                    <div class="fs-3 my-4 text-center">
                        Usuarios en Linea
                    </div>

                    <!--  <table id="muestraVisitas" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha </th>
                                <th>Usuario</th>
                                <th>Centro</th>

                            </tr>
                        </thead>
                        <tbody>



                            <?php
                            foreach ($result_visita as $row) {
                                $idUsuario = $row["id_usuario"];
                                $accion = $row["accion"];
                                $fecha = $row["fecha"];
                                $hora = $row["hora"];

                                // Combina la fecha y la hora
                                $fechaHora = $fecha . ' ' . $hora;

                                // Convierte esto en un objeto DateTime si necesitas manipularlo más adelante
                                $dateTime = new DateTime($fechaHora);

                                // Formato en cadena, por ejemplo 'YYYY-MM-DD HH:MM:SS'
                                $fechaHora = $dateTime->format('Y-m-d H:i:s');

                                // Obtener nombre del usuario
                                $query = "SELECT * FROM `usuario` WHERE `id`=? LIMIT 1";
                                $sentence =  $pdoConn->prepare($query);
                                $sentence->execute([$idUsuario]);  // Usar array en lugar de array()
                                $result_usuario = $sentence->fetch();

                                if ($result_usuario) {
                                    $nombre_usuario = $result_usuario["nombre_completo"];
                                    $codigo_sigerd = $result_usuario["codigo_sigerd"];
                                    $nivelUsuario = $result_usuario["nivel_usuario"];
                                } else {
                                    $nombre_usuario = "No Definido";
                                    $codigo_sigerd = 0;
                                    $nivelUsuario = "";
                                }

                                // Obtener centro del usuario
                                $query = "SELECT `Centro` FROM `matriculaporservicios` WHERE `codigo_sigerd`=?";
                                $sentence = $pdoAlerta->prepare($query);
                                $sentence->execute([$codigo_sigerd]);  // Usar array en lugar de array()
                                $result_centro = $sentence->fetch();

                                if ($result_centro) {
                                    $centro_usuario = $result_centro["Centro"];
                                } else {
                                    $centro_usuario = "Técnico";
                                }
                            ?>

                                <tr class="small">
                                    <td>
                                        <?php echo htmlspecialchars($fechaHora); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($nombre_usuario); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($centro_usuario); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                </tr>

                            <?php
                            }
                            ?>



                        </tbody>
                    </table>
                        -->

                </div>
            </div>
            <div class="col-md-4">
                <div class="shadow-sm p-3 bg-white rounded">
                    <div class="fs-3 my-4 text-center">
                        Distritos Mas Activos
                    </div>

                    <!--  <table id="muestraVisitas" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha </th>
                                <th>Usuario</th>
                                <th>Centro</th>

                            </tr>
                        </thead>
                        <tbody>



                            <?php
                            foreach ($result_visita as $row) {
                                $idUsuario = $row["id_usuario"];
                                $accion = $row["accion"];
                                $fecha = $row["fecha"];
                                $hora = $row["hora"];

                                // Combina la fecha y la hora
                                $fechaHora = $fecha . ' ' . $hora;

                                // Convierte esto en un objeto DateTime si necesitas manipularlo más adelante
                                $dateTime = new DateTime($fechaHora);

                                // Formato en cadena, por ejemplo 'YYYY-MM-DD HH:MM:SS'
                                $fechaHora = $dateTime->format('Y-m-d H:i:s');

                                // Obtener nombre del usuario
                                $query = "SELECT * FROM `usuario` WHERE `id`=? LIMIT 1";
                                $sentence =  $pdoConn->prepare($query);
                                $sentence->execute([$idUsuario]);  // Usar array en lugar de array()
                                $result_usuario = $sentence->fetch();

                                if ($result_usuario) {
                                    $nombre_usuario = $result_usuario["nombre_completo"];
                                    $codigo_sigerd = $result_usuario["codigo_sigerd"];
                                    $nivelUsuario = $result_usuario["nivel_usuario"];
                                } else {
                                    $nombre_usuario = "No Definido";
                                    $codigo_sigerd = 0;
                                    $nivelUsuario = "";
                                }

                                // Obtener centro del usuario
                                $query = "SELECT `Centro` FROM `matriculaporservicios` WHERE `codigo_sigerd`=?";
                                $sentence = $pdoAlerta->prepare($query);
                                $sentence->execute([$codigo_sigerd]);  // Usar array en lugar de array()
                                $result_centro = $sentence->fetch();

                                if ($result_centro) {
                                    $centro_usuario = $result_centro["Centro"];
                                } else {
                                    $centro_usuario = "Técnico";
                                }
                            ?>

                                <tr class="small">
                                    <td>
                                        <?php echo htmlspecialchars($fechaHora); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($nombre_usuario); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($centro_usuario); // Escapar HTML para evitar inyección 
                                        ?>
                                    </td>
                                </tr>

                            <?php
                            }
                            ?>



                        </tbody>
                    </table>
                        -->

                </div>
            </div>


            <div id="divMdal"></div><!-- este codigo permite mostrar el modal  -->.
        </div>
    </div>

    <!--  <footer> -->
    <?php
    include_once "./footer.php";

    ?>






    <!-- Estilo boostrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Incluir estilo de los btotones -->
    <?php include_once "./dataTableFooter.php" ?>


    <!-- Tabla para Control de Usuario  -->
    <!-- Tabla Control ususrio  aqui se llama el script de las tablas y se usa una sola vez-->
    <?php
    /* <!-- Tabla para Visitas --> */
    $tituloTabla  = "Control de Usuario";
    $nombreArchivo = "ControldeUsuario-Novaexperto.Com";
    $IdTable = "#controlUsuario";
    /*  $tituloTabla  = "Control de Usuario | $fechaActualizacion | \nNovaexperto.Com";
    $tituloArchivo = "$fechaActualizacion-ControldeUsuario-Novaexperto.Com"; */

    /* Usados Include y no include_once porque queremos llamar varias veces la misma pagina */
    include "./dataTableScriptTable.php";

    /* <!-- Tabla para Visitas --> */
    $tituloTabla  = "Control de Usuario";
    $nombreArchivo = "ControldeUsuario-Novaexperto.Com";
    $IdTable = "#muestraVisitas";
    include "./dataTableScriptTable.php";

    ?>

    <!-- Confirmar  -->
    <script>
        function confirmarEliminacion() {
            return confirm("¿Estás seguro de que deseas reiniciar la clave?");
        }
    </script>

    <!-- Confirmar Reinicio  -->
    <script>
        function confirmarReinicio() {
            return confirm("¿Estás seguro de que deseas reiniciar la clave?");
        }
    </script>

</body>

</html>



<!-- Abrir el Mdoal  -->
<!-- Abrir Modal  -->

<script>
    function modalModificaUsuario(id) {

        var rutaEnlace = 'visitas_usuarios_Modal.php?idUsuario=' + id
        $.get(
            rutaEnlace,
            function(maria) {
                $('#divMdal').html(maria);
                $('#modalModificaUsuario').modal('show');
            });
    }
</script>