<?php
ob_start();
session_start();
require_once "../conn/connrota.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuración de zona horaria
date_default_timezone_set("America/Santo_Domingo");
$fechaHoraHoy = date('Y-m-d H:i:s');

// ============================================
// 1. VALIDACIÓN SIMPLE DE reCAPTCHA v2
// ============================================
$claveSecretaCaptcha = "6LeQvDAsAAAAALlD6xZC14xjOQm5cXZZaKCe6qZa";

if (isset($_POST['g-recaptcha-response'])) {
    $recaptcha_response = $_POST['g-recaptcha-response'];

    // Verificar reCAPTCHA
    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
    $post_data = http_build_query([
        'secret' => $claveSecretaCaptcha,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ]);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $post_data
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($verify_url, false, $context);

    if ($response === false) {
        error_log("Error al conectar con reCAPTCHA. Continuando sin validación.");
    } else {
        $result = json_decode($response);

        if (!$result->success) {
            error_log("reCAPTCHA falló: " . print_r($result->{'error-codes'}, true));
            header("Location: registro.php?error=captcha");
            exit();
        }
    }
} else {
    error_log("No se recibió respuesta de reCAPTCHA");
    header("Location: registro.php?error=captcha");
    exit();
}

// ============================================
// 2. VALIDACIÓN DE CAMPOS REQUERIDOS
// ============================================
$requiredFields = ['usuario', 'correo', 'clave1', 'clave2', 'nombre', 'apellido'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field]) || trim($_POST[$field]) === '') {
        header("Location: registro.php?error=campos_requeridos");
        exit();
    }
}

// ============================================
// 3. SANITIZACIÓN Y VALIDACIÓN BÁSICA
// ============================================
$usuario = filter_var(trim($_POST['usuario']), FILTER_SANITIZE_STRING);
$correo = filter_var(trim($_POST['correo']), FILTER_SANITIZE_EMAIL);
$nombre = strtoupper(filter_var(trim($_POST['nombre']), FILTER_SANITIZE_STRING));
$apellido = strtoupper(filter_var(trim($_POST['apellido']), FILTER_SANITIZE_STRING));
$clave1 = $_POST['clave1'];
$clave2 = $_POST['clave2'];

// Validar formato de correo
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header("Location: registro.php?error=correo_invalido");
    exit();
}

// Validar usuario (mínimo 3 caracteres)
if (strlen($usuario) < 3) {
    header("Location: registro.php?error=usuario_corto");
    exit();
}

// Validar que las contraseñas coincidan
if ($clave1 !== $clave2) {
    header("Location: registro.php?error=claves_no_coinciden");
    exit();
}

// Validar longitud de contraseña
if (strlen($clave1) < 6) {
    header("Location: registro.php?error=clave_corta");
    exit();
}

// ============================================
// 4. VERIFICAR USUARIO/CORREO EXISTENTES
// ============================================
try {
    // Verificar si el nombre de usuario ya existe
    $sql = "SELECT COUNT(*) as total FROM usuario WHERE usuario = ?";
    $sentencia = $pdoRota->prepare($sql);
    $sentencia->execute([$usuario]);
    $resultado = $sentencia->fetch();

    if ($resultado['total'] > 0) {
        header("Location: registro.php?error=usuarioExiste");
        exit();
    }

    // Verificar si el correo ya existe
    $sql = "SELECT COUNT(*) as total FROM usuario WHERE correo = ?";
    $sentencia = $pdoRota->prepare($sql);
    $sentencia->execute([$correo]);
    $resultado = $sentencia->fetch();

    if ($resultado['total'] > 0) {
        header("Location: registro.php?error=correoExiste");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error verificando usuario/correo: " . $e->getMessage());
    header("Location: registro.php?error=error_servidor");
    exit();
}

// ============================================
// 5. PROCESO DE REGISTRO
// ============================================
try {
    // Generar código de verificación
    $codigocorreo = rand(100000, 199999);
    $clave_hash = password_hash($clave1, PASSWORD_DEFAULT);

    // Configurar parámetros del usuario
    $correo_verificado = 'no';
    $activo = '1';
    $nivel = 3;
    $plan_inicial = 'free';
    $rotafolios_count = 0;
    $espacio_disponible_mb = 100;
    $codigos_enviados = 1;
    $intentos_verificacion = 0;

    // Registrar usuario
    $sql = "INSERT INTO usuario (usuario, nombre, clave, correo, apellido, codigocorreo, fecha, nivel, correo_verificado, activo, intentos_verificacion, codigos_enviados, fecha_ultimo_codigo, plan, rotafolios_count, espacio_disponible_mb, fecha_vencimiento, stripe_customer_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdoRota->prepare($sql);
    $resultadoInsercion = $stmt->execute([
        $usuario,
        $nombre,
        $clave_hash,
        $correo,
        $apellido,
        $codigocorreo,
        $fechaHoraHoy,
        $nivel,
        $correo_verificado,
        $activo,
        $intentos_verificacion,
        $codigos_enviados,
        $fechaHoraHoy,
        $plan_inicial,
        $rotafolios_count,
        $espacio_disponible_mb,
        NULL,
        NULL
    ]);

    if (!$resultadoInsercion) {
        throw new Exception("Error al insertar usuario");
    }

    // Obtener ID del nuevo usuario
    $id_usuario = $pdoRota->lastInsertId();

    // Crear sesión temporal para verificación
    $_SESSION['temp_user_id'] = $id_usuario;
    $_SESSION['temp_user_data'] = [
        'id' => $id_usuario,
        'usuario' => $usuario,
        'correo' => $correo,
        'nombre' => $nombre
    ];

    // ============================================
    // 6. ENVIAR CORREO DE VERIFICACIÓN
    // ============================================
    function enviarCorreoVerificacionRegistro($correo, $nombre, $codigo)
    {
        try {
            require_once "../src_correo/PHPMailer.php";
            require_once "../src_correo/Exception.php";
            require_once "../src_correo/SMTP.php";

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'novaexperto.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'info@novaexperto.com';
            $mail->Password = 'En942944/';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom('info@novaexperto.com', 'Rotafolio NovaExperto');
            $mail->addAddress($correo);
            $mail->addReplyTo('info@novaexperto.com', 'Rotafolio NovaExperto');

            $mail->isHTML(true);
            $mail->Subject = 'Verifica tu cuenta en Rotafolio - Código: ' . $codigo;

            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border: 1px solid #dee2e6; border-top: none; }
                    .code { font-size: 32px; font-weight: bold; color: #0d6efd; text-align: center; margin: 20px 0; letter-spacing: 5px; }
                    .btn { display: inline-block; background: #0dcaf0; color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: bold; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
                    .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>¡Hola $nombre!</h1>
                        <p>Verifica tu cuenta en Rotafolio NovaExperto</p>
                    </div>
                    
                    <div class='content'>
                        <p>Gracias por registrarte en Rotafolio. Para completar tu registro, necesitamos verificar tu dirección de correo electrónico.</p>
                        
                        <p>Tu código de verificación es:</p>
                        
                        <div class='code'>$codigo</div>
                        
                        <div class='alert-warning'>
                            <strong>⚠️ Importante:</strong><br>
                            • Este código expira en 24 horas<br>
                            • Es válido para un solo uso<br>
                            • Intento 1 de 3 códigos disponibles<br>
                            • Máximo 5 intentos fallidos antes de bloqueo
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>Este es un correo automático, por favor no respondas.<br>
                        Si no solicitaste este registro, ignora este mensaje.</p>
                        <p>© " . date('Y') . " Rotafolio NovaExperto. Todos los derechos reservados.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->AltBody = "Hola $nombre,\n\nTu código de verificación para Rotafolio es: $codigo\n\nIngresa este código en la página de verificación para activar tu cuenta.\n\n⚠️ IMPORTANTE:\n- Este código expira en 24 horas\n- Es válido para un solo uso\n- Intento 1 de 3 códigos disponibles\n- Máximo 5 intentos fallidos antes de bloqueo\n\nSaludos,\nRotafolio NovaExperto";

            if ($mail->send()) {
                error_log("Correo enviado exitosamente a: $correo");
                return true;
            } else {
                error_log("Error PHPMailer: " . $mail->ErrorInfo);
                return false;
            }
        } catch (Exception $e) {
            error_log("Exception al enviar correo: " . $e->getMessage());
            return false;
        }
    }

    // Enviar correo de verificación
    $correo_enviado = enviarCorreoVerificacionRegistro($correo, $nombre, $codigocorreo);

    if (!$correo_enviado) {
        error_log("⚠️ Advertencia: No se pudo enviar el correo a $correo, pero el usuario fue registrado.");
        $sql_update = "UPDATE usuario SET codigos_enviados = 0 WHERE id = ?";
        $stmt_update = $pdoRota->prepare($sql_update);
        $stmt_update->execute([$id_usuario]);
    }

    // ============================================
    // 7. REDIRECCIÓN EXITOSA
    // ============================================
    header("Location: verificar_correo.php?registro=exitoso&primer_envio=1");
    exit();
} catch (Exception $e) {
    error_log("Error en registro: " . $e->getMessage());

    // Detectar error de duplicado
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        if (strpos($e->getMessage(), 'usuario') !== false) {
            header("Location: registro.php?error=usuarioExiste");
        } else {
            header("Location: registro.php?error=correoExiste");
        }
    } else {
        header("Location: registro.php?error=error_servidor");
    }
    exit();
}
