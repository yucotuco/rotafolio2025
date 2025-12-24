<?php
try {
    // Llamada a la función pasando la conexión PDO y la cédula del usuario
    $usuarioActivo = $_SESSION['rotaforlioyuco2025']; // La cedula que es la variable de sesion de la plataforma 
    $usuarioActual = obtenerDatosUsuario($pdoConn, $usuarioActivo);

    if ($usuarioActual) {
        // Acceso a los datos
        $nombreUsuario = $usuarioActual['nombre'];
        $nivelUsuario = $usuarioActual['nivel'];
        $idUsuario = $usuarioActual['id'];
        $usuarioActivo = $usuarioActual['estado'];
        $usuarioBanco = $usuarioActual['banco'];

        /* echo "Nombre: $nombreUsuario, Nivel: $nivelUsuario, ID: $idUsuario, Activo: $usuarioActivo"; */
    } else {
        //echo "No se encontró el usuario con la cédula: $usuarioActivo";  revisar este codigo PENDIENTE YUCO 2025
        header("location: ../index.php?result=intento_fallido_de_inicio_de_sesion");
        die();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}


/* Esta variable esta en la pagina visitas_usurios.php lo mando a cerrar2.php porque cerrar.php tiene visitas_usurios.php y se hace un bucle  */
if ($usuarioActivo > 1) {
    unset($_SESSION['rotaforlioyuco2025']);
    die();
}

// Establece la zona horaria a la República Dominicana
date_default_timezone_set('America/Santo_Domingo');
// Obtiene la fecha y hora actual
$fechaHoraActual = new DateTime();
// Formatea la fecha y hora en el formato deseado
$fechaFormateada = $fechaHoraActual->format('Y-m-d H:i:s');
$fechaDia = $fechaHoraActual->format('Y-m-d');
$horaDia = $fechaHoraActual->format('H:i:s');

/* Control Visita */

/* Sacar Visita */
$query = "SELECT * FROM `visitas_diarias` WHERE `usuario_id`=? AND Date(`fecha_hora`) =? order by id desc limit 1 ";
$sentence =  $pdoConn->prepare($query);
$sentence->execute(array($idUsuario, $fechaDia));
$result = $sentence->fetch();

// Sacar banco de la tabla ususrio y sacar banco de visita si son diferentes agregar una nueva visita diaria 
if (is_array($result)) {

    $bancoVisita = $result["banco"];
    /*  echo "Banco visita $bancoVisita";
    die(); */
} else {
    $bancoVisita = "Sin Cambios";
}
if ($bancoVisita != $usuarioBanco) {
    //agreg
    $query = "INSERT INTO visitas_diarias (usuario_id, fecha_hora, nivel_usuario, banco) VALUES (?,?,?,?)";
    $sentence =  $pdoConn->prepare($query);
    if ($sentence->execute(array($idUsuario, $fechaFormateada, $nivelUsuario, $usuarioBanco))) {
        //echo "Visita agregada ";
        $query = "SELECT * FROM `visitas_diarias` WHERE `usuario_id`=? AND Date(`fecha_hora`) =?";
        $sentence =  $pdoConn->prepare($query);
        $sentence->execute(array($idUsuario, $fechaDia));
        $result = $sentence->fetch();
        $id_visita_diaria_BBDD = $result["id"];

        $query = "INSERT INTO `visitas_acciones`(id_usuario,`visitas_diarias_id`, `fecha`, `hora`, `accion`) VALUES (?,?,?,?,?)";
        $sentence =  $pdoConn->prepare($query);
        if ($sentence->execute(array($idUsuario, $id_visita_diaria_BBDD,  $fechaDia, $horaDia, $accionVisita))) {
            //echo "Accion Visita agregada ";
        } else {
            echo "Error Accion  Visita";
        }
    }
}







if (is_array($result)) {
    $usuario_id_BBDD = $result["usuario_id"];
} else {
    $usuario_id_BBDD = 0;
};

if ($usuario_id_BBDD != 0) {
    //echo "Visita hecha Hoy  ";

    $query = "SELECT * FROM `visitas_diarias` WHERE `usuario_id`=? AND Date(`fecha_hora`) =?";
    $sentence =  $pdoConn->prepare($query);
    $sentence->execute(array($idUsuario, $fechaDia));
    $result = $sentence->fetch();
    $id_visita_diaria_BBDD = $result["id"];


    /* Revisar si la utima visita fue cerrar sesion  */

    $query = "SELECT * FROM visitas_acciones WHERE `id_usuario` = ? and fecha =? ORDER BY id DESC LIMIT 1;";
    $sentence =  $pdoConn->prepare($query);
    $sentence->execute(array($idUsuario, $fechaDia));
    $result = $sentence->fetch();

    if (is_array($result)) {
        $accionVista_BBDD = $result["accion"];
    } else {
        $accionVista_BBDD = 0;
    };

    if ($accionVista_BBDD == 'Cerrar Sesion' or $accionVisita == 'Cerrar Sesion') {
        $query = "INSERT INTO `visitas_acciones`(id_usuario,`visitas_diarias_id`, `fecha`, `hora`, `accion`) VALUES (?,?,?,?,?)";
        $sentence =  $pdoConn->prepare($query);
        if ($sentence->execute(array($idUsuario, $id_visita_diaria_BBDD,  $fechaDia, $horaDia, $accionVisita))) {
            //echo "Accion Visita agregada despues de Cerrar Sesion";
        } else {
            echo "Error Accion  Visita";
        }
    }


    /* Carcular una hora del registro */
    $query = "SELECT * FROM visitas_acciones WHERE `id_usuario` = ? and `accion`=? and fecha =? ORDER BY id DESC LIMIT 1;";
    $sentence =  $pdoConn->prepare($query);
    $sentence->execute(array($idUsuario, $accionVisita, $fechaDia));
    $result = $sentence->fetch();

    if (is_array($result)) {
        $hora_BBDD = $result["hora"];
    } else {
        $hora_BBDD = 0;
    };

    if ($hora_BBDD == 0) {
        $query = "INSERT INTO `visitas_acciones`(id_usuario,`visitas_diarias_id`, `fecha`, `hora`, `accion`) VALUES (?,?,?,?,?)";
        $sentence =  $pdoConn->prepare($query);
        if ($sentence->execute(array($idUsuario, $id_visita_diaria_BBDD,  $fechaDia, $horaDia, $accionVisita))) {
            // echo "Accion Visita agregada ";
        } else {
            echo "Error Accion  Visita";
        }
    } else {
        // Suponiendo que tienes dos horas en formato de cadena
        $hora1 = $horaDia;
        $hora2 = $hora_BBDD;

        // Crear objetos DateTime a partir de las horas
        $datetime1 = new DateTime($hora1);
        $datetime2 = new DateTime($hora2);

        // Calcular la diferencia entre las dos horas
        $difference = $datetime1->diff($datetime2);

        // Comprobar si la diferencia es de al menos 10 minutos
        if ($difference->h > 0 || $difference->i >= 10 || $difference->days > 0) {
            $query = "INSERT INTO `visitas_acciones`(id_usuario,`visitas_diarias_id`, `fecha`, `hora`, `accion`) VALUES (?,?,?,?,?)";
            $sentence =  $pdoConn->prepare($query);
            if ($sentence->execute(array($idUsuario, $id_visita_diaria_BBDD, $fechaDia, $horaDia, $accionVisita))) {
                // echo "Acción Visita agregada";
            } else {
                echo "Error al agregar Acción Visita.";
            }
        } else {
            // echo "No ha pasado 10 minutos todavía.";
        }
    }
} else {
    //echo "No hay visita";
    $query = "INSERT INTO visitas_diarias (usuario_id, fecha, nivel_usuario, banco) VALUES (?,?,?,?)";
    $sentence =  $pdoConn->prepare($query);
    if ($sentence->execute(array($idUsuario, $fechaFormateada, $nivelUsuario, $usuarioBanco))) {
        //echo "Visita agregada ";
        $query = "SELECT * FROM `visitas_diarias` WHERE `usuario_id`=? AND Date(`fecha`) =?";
        $sentence =  $pdoConn->prepare($query);
        $sentence->execute(array($idUsuario, $fechaDia));
        $result = $sentence->fetch();
        $id_visita_diaria_BBDD = $result["id"];

        $query = "INSERT INTO `visitas_acciones`(id_usuario,`visitas_diarias_id`, `fecha`, `hora`, `accion`) VALUES (?,?,?,?,?)";
        $sentence =  $pdoConn->prepare($query);
        if ($sentence->execute(array($idUsuario, $id_visita_diaria_BBDD,  $fechaDia, $horaDia, $accionVisita))) {
            //echo "Accion Visita agregada ";
        } else {
            echo "Error Accion  Visita";
        }
    } else {
        echo "Error Visita";
    }
};
