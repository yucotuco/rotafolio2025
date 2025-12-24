<?php

function SacarDatos($sql, $pdo, $dato)
{
  global $dato1;

  $sentUsu = $pdo->prepare($sql);
  $sentUsu->execute(array());
  $resulUsu = $sentUsu->fetch();
  $dato1 = $resulUsu[$dato];

  if (is_array($resulUsu)) {
    $dato1 = $resulUsu[$dato];
  } else {
    $dato1 = "";
  }
}

function efectivoDisponible($cuentaCliente)
{
  global $pdoConn;
  global $dineroDisponibleCuentaFuncion;

  //retiros
  $sql = "SELECT sum(monto) as monto, cuenta_cliente FROM `cb_retiro` WHERE `cuenta_cliente`=$cuentaCliente";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array());
  $result = $sentencia->fetch();
  $montoRetiro = $result["monto"];


  //Depositos
  $sql = "SELECT sum(monto) as monto, `cuenta_cliente` FROM `cb_deposito` WHERE `cuenta_cliente`=$cuentaCliente";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array());
  $result = $sentencia->fetch();
  $montoDeposito = $result["monto"];

  $dineroDisponibleCuentaFuncion = $montoDeposito - $montoRetiro;


  //echo "montoDeposito=$montoDeposito <br> cuentaCliente=$cuentaCliente ";
}


function efectivoCaja($fechaHoy)
{
  global $pdoConn;
  global $efectivoCaja;
  global $banco;
  global $idUsuario;

  $sql = "SELECT * FROM cb_apertura_caja where DATE(`fecha_registro`) = ? and `banco`=? and `id_cajero`=? ";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array($fechaHoy, $banco,  $idUsuario));
  $resultado = $sentencia->fetch();
  $montoAperturaCajaHoy = $resultado["monto"];

  // sacar los depositos de hoy a este cajero y los retiros y los reembolsos tipo 2 si los hay 
  $sql = "SELECT banco, id_cajero, fecha_registro, sum(monto) as monto  FROM cb_deposito where DATE(`fecha_registro`) = ? and `banco`=? and `id_cajero`=? ";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array($fechaHoy, $banco,  $idUsuario));
  $resultado = $sentencia->fetch();
  $montoDepositoHoy = $resultado["monto"];

  $sql = "SELECT banco, id_cajero, fecha_registro, sum(monto) as monto  FROM  cb_retiro where DATE(`fecha_registro`) = ? and `banco`=? and `id_cajero`=? ";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array($fechaHoy, $banco,  $idUsuario));
  $resultado = $sentencia->fetch();
  $montoRetiroHoy = $resultado["monto"];

  $sql = "SELECT banco, id_cajero, fecha_registro, finalidad, sum(monto_apertura) as monto  FROM  cb_reembolso where DATE(`fecha_registro`) = ? and `banco`=? and `id_cajero`=? and finalidad=1";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array($fechaHoy, $banco,  $idUsuario));
  $resultado = $sentencia->fetch();
  $montoReembolsoHoy = $resultado["monto"];

  $efectivoCaja = $montoAperturaCajaHoy + $montoDepositoHoy - $montoRetiroHoy - $montoReembolsoHoy;


  //echo "<br>montoAperturaCajaHoy=$montoAperturaCajaHoy | <br> montoDepositoHoy=$montoDepositoHoy| <br> montoRetiroHoy =$montoRetiroHoy | montoReembolsoHoy=$montoReembolsoHoy<br>"; 

}


function accionesUsuarios($idUsuario)
{

  global $pdoConn;
  global $dato1;
  global $banco;
  global $nivelUsuario;
  global $nombreCajero;

  $sql = "SELECT * FROM usuario where id= $idUsuario";

  SacarDatos($sql, $pdoConn, "nivel");
  $nivelUsuario = $dato1;

  SacarDatos($sql, $pdoConn, "banco");
  $banco = $dato1;

  SacarDatos($sql, $pdoConn, "id");
  $usuarioExiste = $dato1;

  if ($usuarioExiste == "") {
    header("location: ../usuarioControl/login/cerrar.php");
    die();
  }

  //sacar nombre cajero
  $sql = "SELECT * FROM `usuario` WHERE `id`=$idUsuario";
  SacarDatos($sql, $pdoConn, "nombre");
  $nom = $dato1;
  SacarDatos($sql, $pdoConn, "apellido");
  $ape1 = $dato1;
  SacarDatos($sql, $pdoConn, "apellido2");
  $ape2 = $dato1;
  $nombreCajero = "$nom $ape1  $ape2";
  $nombreUsuario = $nombreCajero;
}



function obtenerDatosUsuario($pdoConn, $id)
{
  try {
    // Consulta SQL para buscar el usuario por su cédula
    $query = "SELECT * FROM usuario WHERE id = ?";
    $sentence = $pdoConn->prepare($query);
    $sentence->execute([$id]);

    // Verifica si hay resultados
    if ($result = $sentence->fetch(PDO::FETCH_ASSOC)) {
      return [

        'id' => $result['id'],
        'usuario' => $result['usuario'],
        'nombre' => $result['nombre'],
        'clave' => $result['clave'],
        'correo' => $result['correo'],
        'apellido' => $result['apellido'],
        'nivel' => $result['nivel'],
        'idusuariocrea' => $result['idusuariocrea'],
        'codigocorreo' => $result['codigocorreo'],
        'estado' => $result['estado'],
        'fecha' => $result['fecha'],
        'banco' => $result['banco'],
        'codigoReiniciaSistema' => $result['codigoReiniciaSistema']
      ];
    } else {
      // Retorna null si no se encuentra el usuario
      return null;
    }
  } catch (PDOException $e) {
    // Manejo de errores
    throw new Exception("Error al obtener los datos del usuario: " . $e->getMessage());
  }
}

/* Sacar nombre del banco  */
function nombreBanco($idBanco)
{
  global $pdoConn;
  global $nombreBancoBBDD;
  //retiros
  $sql = "SELECT `nombre`,`id` FROM `bancos` WHERE  `id`=?";
  $sentencia = $pdoConn->prepare($sql);
  $sentencia->execute(array($idBanco));
  $result = $sentencia->fetch();
  $nombreBancoBBDD = $result["nombre"];
}
