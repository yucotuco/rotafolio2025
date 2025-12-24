<?php
// Consulta para obtener alertas
$sqlAlertas = "
SELECT 
    'cliente_sin_movimientos' AS tipo_alerta,
    c.id AS id_origen,
    CONCAT(c.nombre) AS nombre_cliente,
    c.cedula,
    'Cliente sin transacciones hoy' AS mensaje,
    CONCAT('Cliente registrado sin depósitos ni retiros el ', DATE_FORMAT(CURDATE(), '%d/%m/%Y')) AS descripcion,
    'warning' AS nivel_alerta,
    CURDATE() AS fecha_registro  -- Se añade fecha_registro con valor actual para poder ordenar
FROM 
    cb_cliente c
LEFT JOIN 
    cb_cliente_cuentas cc ON cc.id_cliente = c.id AND cc.banco = ?
LEFT JOIN 
    cb_retiro r ON r.cuenta_cliente = cc.id AND DATE(r.fecha_sola) = '$fechaHoy' 
LEFT JOIN 
    cb_deposito d ON d.cuenta_cliente = cc.id AND DATE(d.fecha_sola) = '$fechaHoy'  AND d.tipo_deposito NOT IN (12, 13)
WHERE 
    c.banco = ?
GROUP BY 
    c.id, c.nombre, c.cedula
HAVING 
    COUNT(r.id) = 0 AND COUNT(d.id) = 0

UNION ALL

SELECT 
    'caja_no_cerrada' AS tipo_alerta,
    ac.id AS id_origen,
    CONCAT(u.nombre) AS nombre_cliente,
    u.id,
    'Caja no cerrada' AS mensaje,
    CONCAT('Caja abierta el ', DATE_FORMAT(ac.fecha_sola, '%d/%m/%Y'), ' por ', CONCAT(u.nombre)) AS descripcion,
    'danger' AS nivel_alerta,
    ac.fecha_registro
FROM 
    cb_apertura_caja ac
JOIN 
    usuario u ON ac.id_cajero = u.id
WHERE 
    (ac.fecha_cierre_caja IS NULL OR ac.fecha_cierre_caja <= '1970-01-01 00:00:01')
    AND DATE(ac.fecha_sola) < CURDATE()
    AND ac.banco = ?

UNION ALL

SELECT 
    'usuario_desactualizado' AS tipo_alerta,
    u.id AS id_origen,
    CONCAT(u.nombre) AS nombre_cliente,
    u.id,
    'Usuario desactualizado' AS mensaje,
    'Faltan datos importantes en el perfil del usuario' AS descripcion,
    'danger' AS nivel_alerta,
    u.fecha AS fecha_registro  -- Se usa u.fecha como fecha_registro para consistencia
FROM 
    usuario u
WHERE 
    (u.apellido IS NULL OR u.apellido = '' OR 
     u.apellido2 IS NULL OR u.apellido2 = '' OR 
     u.fecha_nacimiento IS NULL OR u.fecha_nacimiento <= '1970-01-01' OR 
     u.sexo IS NULL OR u.sexo = '' OR 
     u.whatsapp IS NULL OR u.whatsapp = '')
    AND u.id = ?

ORDER BY 
    fecha_registro DESC, nivel_alerta DESC";

$stmtAlertas = $pdoConn->prepare($sqlAlertas);
$stmtAlertas->execute([$idBanco, $idBanco, $idBanco, $idUsuario]);
$alertas = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);
