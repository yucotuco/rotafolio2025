<?php

/* Sacar el Idbanco del usuario */
$sql = "SELECT * FROM usuario WHERE id=$idUsuario";
SacarDatos($sql, $pdoConn, "nombre");
$nombreUsuarioDesdeFuncion = strtoupper($dato1);

SacarDatos($sql, $pdoConn, "apellido");
$apellidoUsuarioDesdeFuncion = strtoupper($dato1);

SacarDatos($sql, $pdoConn, "apellido2");
$apellidoUsuarioDesdeFuncion2 = $dato1;
if ($apellidoUsuarioDesdeFuncion2 == "") {
    $apellidoUsuarioDesdeFuncion2 = "";
} else {
    $apellidoUsuarioDesdeFuncion2 = strtoupper($apellidoUsuarioDesdeFuncion2);
}

$idBancoDesdeFuncion = " $nombreUsuarioDesdeFuncion  $apellidoUsuarioDesdeFuncion  $apellidoUsuarioDesdeFuncion2 ";
SacarDatos($sql, $pdoConn, "banco");
$idBanco = $dato1;

SacarDatos($sql, $pdoConn, "estado");
$estado = $dato1;

$nombreUsuarioDesdeFuncion  = " $nombreUsuarioDesdeFuncion  $apellidoUsuarioDesdeFuncion  $apellidoUsuarioDesdeFuncion2 ";
SacarDatos($sql, $pdoConn, "banco");
$idBanco = $dato1;

/* Sacar el Idbanco del usuario */
$sql = "SELECT * FROM `bancos` WHERE `id`=$idBanco ";
SacarDatos($sql, $pdoConn, "nombre");
$nombreBancoUsuarioDesdeFuncion = strtoupper($dato1);
