<?php
// Limpiamos cualquier salida previa
ob_clean();

// Simulamos datos (en un caso real, esto vendría de una consulta a la base de datos)
$datos = [
    [
        'id' => 1,
        'usuario' => 'admin',
        'nombre' => 'Lola',
        'clave' => '***',
        'correo' => 'lola@example.com',
        'apellido' => 'Pérez',
        'nivel' => 'Admin',
        'estado' => 'Activo',
        'fecha' => '2023-01-01',
        'banco' => 'Banco XYZ',
        'codigo_reinicio' => 'ABC123'
    ]
];

// Construimos las filas de la tabla
$html = '';
foreach ($datos as $fila) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($fila['id']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['usuario']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['nombre']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['clave']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['correo']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['apellido']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['nivel']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['estado']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['fecha']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['banco']) . '</td>';
    $html .= '<td>' . htmlspecialchars($fila['codigo_reinicio']) . '</td>';
    $html .= '</tr>';
}

// Enviamos solo el HTML de las filas
echo $html;
exit(); // Importante para evitar que se envíe más contenido
