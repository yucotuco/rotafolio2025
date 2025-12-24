<?php
session_start();
require_once "../modulo_conn/conpdo.php";
require_once "../modulo_conn/conpdoAlerta.php";

$fechaActualizacion = $_SESSION["actualiza"];

if (isset($_SESSION['rotaforlioyuco2025'])) {

    $id_clave = $_GET["id_usuario"];

    /* Control Visita en cada pagina debe haber una accion de las visitas  */
    $accionVisita = "reinicio usuario $id_clave";
    require_once "./control_visita.php";



    $query1 = "UPDATE `usuario` SET `contrasena`='', `clave_generica`=`cedula`  WHERE `id`=?";
    $sentence =  $pdoConn->prepare($query1);

    if ($sentence->execute(array($id_clave))) {
        header("location: visitas_usuarios.php?result=clavereiniciada");
    } else {

        echo "Error";
    }
} else {
    header("location: ../index.php?result=intento_fallido_de_inicio_de_sesion");
}
