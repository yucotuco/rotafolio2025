<?php

//git remote add origin https://github.com/yucotuco/rotafolio2025.git

$bd_host = "localhost";
$bd_usuario = "root";
$bd_contrasena = "";
$bd_name = "rotafolio_novaexperto";

try {
    $pdoRota = new PDO(
        'mysql:host=' . $bd_host . ';dbname=' . $bd_name . ';charset=utf8mb4',
        $bd_usuario,
        $bd_contrasena,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        )
    );
} catch (PDOException $e) {
    error_log("Error de conexión Rota: " . $e->getMessage());
    die("Error en la conexión a la base de datos. Por favor, intente más tarde.");
}



// En connrota.php, después de las otras constantes, agrega:
define('SITIO_NOMBRE', 'Rotafolio NovaExperto');
define('SITIO_URL', 'https://rotafolio.novaexperto.com');
define('DASHBOARD_URL', 'https://rotafolio.novaexperto.com/dashboard/'); // NUEVA CONSTANTE
define('COLOR_PRINCIPAL', '#0dcaf0');
define('MAX_ROTAFOLIOS_FREE', 5);
define('MAX_ESPACIO_MB_FREE', 100);
