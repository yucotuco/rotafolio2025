<?php
ob_start();
session_start();
require_once "../conn/connrota.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$titulo = "Verificar Correo - Rotafolio";

// ============================================
// 1. VERIFICAR SESIÓN TEMPORAL O COMPLETA
// ============================================
if (isset($_SESSION['user_id']) && isset($_SESSION['user_data']['correo_verificado']) && $_SESSION['user_data']['correo_verificado'] == 'si') {
    header("Location: ../dashboard/");
    exit();
}

// Si hay sesión completa pero no verificado
if (isset($_SESSION['user_id'])) {
    $usuario_id = $_SESSION['user_id'];
    $user_data = $_SESSION['user_data'];
    $correo = $user_data['correo'];
    $nombre = $user_data['nombre'];
    $usuario = $user_data['usuario'];
}
// Si hay sesión temporal (desde login)
elseif (isset($_SESSION['temp_user_id'])) {
    $usuario_id = $_SESSION['temp_user_id'];
    $user_data = $_SESSION['temp_user_data'];
    $correo = $user_data['correo'];
    $nombre = $user_data['nombre'];
    $usuario = $user_data['usuario'];
}
// Si no hay sesión, redirigir a login
else {
    header("Location: login.php?error=sesion_expirada");
    exit();
}

// Obtener datos actualizados de la base de datos
try {
    $sql = "SELECT correo_verificado, activo, codigocorreo, codigos_enviados FROM usuario WHERE id = ?";
    $stmt = $pdoRota->prepare($sql);
    $stmt->execute([$usuario_id]);
    $usuario_db = $stmt->fetch();

    if (!$usuario_db) {
        session_destroy();
        header("Location: login.php?error=usuario_no_encontrado");
        exit();
    }

    // Si ya está verificado, redirigir
    if ($usuario_db['correo_verificado'] == 'si') {
        // Actualizar sesión
        $_SESSION['user_id'] = $usuario_id;
        $_SESSION['user_data'] = array_merge($user_data, $usuario_db);
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_user_data']);
        header("Location: ../dashboard/");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error al obtener datos del usuario: " . $e->getMessage());
}

// Ocultar parte del correo para mostrar en pantalla
function ocultarCorreo($email)
{
    $parts = explode('@', $email);
    if (count($parts) == 2) {
        $username = $parts[0];
        $domain = $parts[1];
        $username_oculto = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
        return $username_oculto . '@' . $domain;
    }
    return $email;
}

$correo_oculto = ocultarCorreo($correo);

// ============================================
// 2. VARIABLES PARA MENSAJES
// ============================================
$mensaje_exito = "";
$mensaje_error = "";
$mensaje_exito_reenvio = "";
$mensaje_error_reenvio = "";
$verificado = false;
$cuenta_bloqueada = false;
$reenvio_exitoso = false;
$mostrar_instrucciones = true;

// ============================================
// 3. FUNCIÓN PARA ENVIAR CORREO (MEJORADA)
// ============================================
function enviarCorreoVerificacion($correo, $nombre, $codigo, $intento = 1)
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
        $mail->Subject = 'Nuevo código de verificación - Rotafolio';

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
                .alert { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Hola $nombre</h1>
                    <p>Solicitaste un nuevo código de verificación</p>
                </div>
                
                <div class='content'>
                    <p>Tu nuevo código de verificación para Rotafolio es:</p>
                    
                    <div class='code'>$codigo</div>
                    
                    <p>Ingresa este código en la página de verificación para continuar.</p>
                    
                    <div class='alert'>
                        <strong>⚠️ Intento $intento de 3</strong><br>
                        Te quedan " . (3 - $intento) . " códigos disponibles antes de que la cuenta sea bloqueada.
                    </div>
                    
                    <p>Si no solicitaste este código, puedes ignorar este mensaje.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "Hola $nombre,\n\nTu nuevo código de verificación para Rotafolio es: $codigo\n\nIngresa este código en la página de verificación para activar tu cuenta.\n\n⚠️ Intento $intento de 3\nTe quedan " . (3 - $intento) . " intentos antes de que la cuenta sea bloqueada.\n\nSaludos,\nRotafolio NovaExperto";

        if ($mail->send()) {
            error_log("Correo de verificación reenviado a: $correo");
            return true;
        } else {
            error_log("Error al reenviar correo: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception al reenviar correo: " . $e->getMessage());
        return false;
    }
}

// ============================================
// 4. FUNCIÓN PARA REENVIAR CÓDIGO
// ============================================
function reenviarCodigo($pdoRota, $usuario_id, $correo, $nombre)
{
    // Verificar estado actual
    $sql = "SELECT codigos_enviados, activo FROM usuario WHERE id = ?";
    $stmt = $pdoRota->prepare($sql);
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        return ['error' => 'usuario', 'mensaje' => 'Usuario no encontrado.'];
    }

    // Si ya está bloqueado
    if ($usuario['activo'] == '3') {
        return ['error' => 'bloqueado', 'mensaje' => 'Cuenta bloqueada por máximo de intentos.'];
    }

    // Si ya envió 3 códigos
    if ($usuario['codigos_enviados'] >= 3) {
        // Bloquear cuenta
        $sql_bloqueo = "UPDATE usuario SET activo = '3' WHERE id = ?";
        $stmt_bloqueo = $pdoRota->prepare($sql_bloqueo);
        $stmt_bloqueo->execute([$usuario_id]);

        return ['error' => 'limite', 'mensaje' => 'Límite de códigos alcanzado. Cuenta bloqueada.'];
    }

    // Generar nuevo código
    $nuevo_codigo = rand(100000, 199999);
    $fecha_hora = date('Y-m-d H:i:s');

    // Actualizar en base de datos
    $sql_update = "UPDATE usuario SET 
        codigocorreo = ?, 
        codigos_enviados = codigos_enviados + 1, 
        fecha_ultimo_codigo = ?,
        intentos_verificacion = 0
        WHERE id = ?";

    $stmt_update = $pdoRota->prepare($sql_update);
    if ($stmt_update->execute([$nuevo_codigo, $fecha_hora, $usuario_id])) {
        // Enviar correo
        $correo_enviado = enviarCorreoVerificacion($correo, $nombre, $nuevo_codigo, $usuario['codigos_enviados'] + 1);

        if ($correo_enviado) {
            return [
                'success' => true,
                'codigo' => $nuevo_codigo,
                'intento_actual' => $usuario['codigos_enviados'] + 1,
                'intentos_restantes' => 3 - ($usuario['codigos_enviados'] + 1)
            ];
        } else {
            return [
                'error' => 'correo_fallido',
                'mensaje' => 'No se pudo enviar el correo. Intenta nuevamente o contacta al soporte.'
            ];
        }
    }

    return ['error' => 'general', 'mensaje' => 'Error al actualizar código.'];
}

// ============================================
// 5. PROCESAR REENVÍO DE CÓDIGO
// ============================================
if (isset($_POST['reenviar_codigo'])) {
    $resultado = reenviarCodigo($pdoRota, $usuario_id, $correo, $nombre);

    if (isset($resultado['success'])) {
        $mensaje_exito_reenvio = "✅ Nuevo código enviado a " . $correo_oculto .
            ". Intento " . $resultado['intento_actual'] . " de 3.";
        $reenvio_exitoso = true;
        $mostrar_instrucciones = true;
    } else {
        $mensaje_error_reenvio = "❌ " . $resultado['mensaje'];
        if ($resultado['error'] == 'bloqueado' || $resultado['error'] == 'limite') {
            $cuenta_bloqueada = true;
            session_destroy();
        }
    }
}

// ============================================
// 6. PROCESAR VERIFICACIÓN POR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verificar']) && !isset($_POST['reenviar_codigo'])) {
    $codigo_ingresado = trim($_POST['codigo'] ?? '');

    if (!empty($codigo_ingresado)) {
        // Verificar que sea exactamente 6 dígitos
        if (!preg_match('/^\d{6}$/', $codigo_ingresado)) {
            $mensaje_error = "❌ El código debe tener exactamente 6 dígitos numéricos.";
            $mostrar_instrucciones = false;
        } else {
            // Verificar estado primero
            $sql_estado = "SELECT activo, codigocorreo FROM usuario WHERE id = ?";
            $stmt_estado = $pdoRota->prepare($sql_estado);
            $stmt_estado->execute([$usuario_id]);
            $usuario = $stmt_estado->fetch();

            if ($usuario['activo'] == '3') {
                $cuenta_bloqueada = true;
                session_destroy();
                $mensaje_error = "❌ Cuenta bloqueada por máximo de intentos.";
            } elseif ($usuario['codigocorreo'] == $codigo_ingresado) {
                // Actualizar a verificado
                $sql_update = "UPDATE usuario SET correo_verificado = 'si', activo = '1', intentos_verificacion = 0 WHERE id = ?";
                $stmt_update = $pdoRota->prepare($sql_update);
                if ($stmt_update->execute([$usuario_id])) {
                    // Obtener datos completos del usuario
                    $sql_user = "SELECT * FROM usuario WHERE id = ?";
                    $stmt_user = $pdoRota->prepare($sql_user);
                    $stmt_user->execute([$usuario_id]);
                    $user_completo = $stmt_user->fetch();

                    // Crear sesión completa
                    $_SESSION['user_id'] = $usuario_id;
                    $_SESSION['user_data'] = [
                        'id' => $user_completo['id'],
                        'usuario' => $user_completo['usuario'],
                        'nombre' => $user_completo['nombre'],
                        'apellido' => $user_completo['apellido'],
                        'correo' => $user_completo['correo'],
                        'nivel' => $user_completo['nivel'],
                        'plan' => $user_completo['plan'],
                        'correo_verificado' => 'si',
                        'activo' => '1',
                        'fecha_login' => date('Y-m-d H:i:s')
                    ];

                    // Eliminar sesión temporal
                    unset($_SESSION['temp_user_id']);
                    unset($_SESSION['temp_user_data']);

                    $mensaje_exito = "✅ ¡Correo verificado exitosamente!";
                    $verificado = true;
                    $mostrar_instrucciones = false;
                }
            } else {
                // Incrementar intentos fallidos
                $sql_intentos = "UPDATE usuario SET intentos_verificacion = intentos_verificacion + 1 WHERE id = ?";
                $stmt_intentos = $pdoRota->prepare($sql_intentos);
                $stmt_intentos->execute([$usuario_id]);

                // Verificar intentos
                $sql_check = "SELECT intentos_verificacion FROM usuario WHERE id = ?";
                $stmt_check = $pdoRota->prepare($sql_check);
                $stmt_check->execute([$usuario_id]);
                $intentos = $stmt_check->fetch();

                if ($intentos['intentos_verificacion'] >= 5) {
                    $sql_bloqueo = "UPDATE usuario SET activo = '3' WHERE id = ?";
                    $stmt_bloqueo = $pdoRota->prepare($sql_bloqueo);
                    $stmt_bloqueo->execute([$usuario_id]);
                    $cuenta_bloqueada = true;
                    session_destroy();
                    $mensaje_error = "❌ Demasiados intentos fallidos. Cuenta bloqueada.";
                } else {
                    $intentos_restantes = 5 - $intentos['intentos_verificacion'];
                    $mensaje_error = "❌ Código incorrecto. Intento " . $intentos['intentos_verificacion'] . " de 5.";
                }
                $mostrar_instrucciones = false;
            }
        }
    } else {
        $mensaje_error = "❌ Por favor ingresa el código de verificación.";
    }
}

// ============================================
// 7. OBTENER INFORMACIÓN PARA MODAL DE REENVÍO
// ============================================
$sql_info = "SELECT codigos_enviados FROM usuario WHERE id = ?";
$stmt_info = $pdoRota->prepare($sql_info);
$stmt_info->execute([$usuario_id]);
$info = $stmt_info->fetch();
$codigos_usados = $info['codigos_enviados'] ?? 0;
$codigos_restantes = 3 - $codigos_usados;

// Si es el primer acceso (viene del registro), mostrar instrucciones
if (isset($_GET['registro']) && $_GET['registro'] == 'exitoso') {
    $mostrar_instrucciones = true;
}

// Preservar el código ingresado si hay error
$codigo_preservado = isset($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : '';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --rota-primary: #0dcaf0;
            --rota-gradient: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
            --rota-success: #198754;
            --rota-danger: #dc3545;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-card {
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(13, 202, 240, 0.15);
            overflow: hidden;
        }

        .verification-header {
            background: var(--rota-gradient);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .verification-body {
            padding: 2rem;
            background: white;
        }

        .code-input {
            font-size: 32px;
            text-align: center;
            letter-spacing: 10px;
            font-weight: bold;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 15px;
            width: 100%;
            margin: 1rem 0;
        }

        .code-input:focus {
            border-color: var(--rota-primary);
            box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25);
        }

        .btn-verify {
            background: var(--rota-gradient);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(13, 202, 240, 0.2);
        }

        .email-info {
            background: #e9f7fe;
            border-radius: 10px;
            padding: 15px;
            margin: 1rem 0;
            text-align: center;
            border: 1px solid #0dcaf0;
        }

        .email-text {
            font-weight: bold;
            color: #0d6efd;
            font-size: 1.1rem;
        }

        .instructions-box {
            background: #fff8e1;
            border-radius: 10px;
            padding: 20px;
            margin: 1.5rem 0;
            border: 1px solid #ffeaa7;
        }

        .step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: var(--rota-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
    </style>
</head>

<body>
    <div class="verification-card">
        <div class="verification-header">
            <h1 class="display-6 fw-bold"><i class="bi bi-shield-check me-2"></i>Verificar Correo</h1>
            <p class="mb-0">Último paso para activar tu cuenta</p>
        </div>

        <div class="verification-body">
            <?php if ($cuenta_bloqueada): ?>
                <!-- CUENTA BLOQUEADA -->
                <div class="alert alert-danger text-center">
                    <i class="bi bi-shield-exclamation-fill fs-1"></i>
                    <h4 class="mt-3">¡CUENTA BLOQUEADA!</h4>
                    <p>Has superado el límite máximo de intentos.</p>
                    <div class="mt-3">
                        <a href="mailto:info@novaexperto.com" class="btn btn-outline-danger">
                            <i class="bi bi-envelope me-2"></i>Contactar administrador
                        </a>
                    </div>
                </div>

            <?php elseif ($verificado): ?>
                <!-- VERIFICACIÓN EXITOSA -->
                <div class="alert alert-success text-center">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <h4 class="mt-2">¡Verificación completada!</h4>
                    <p><?= htmlspecialchars($mensaje_exito) ?></p>
                    <div class="mt-3">
                        <a href="../dashboard/" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Ir al Dashboard
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- FORMULARIO DE VERIFICACIÓN -->

                <?php if ($mostrar_instrucciones): ?>
                    <!-- INSTRUCCIONES -->
                    <div class="instructions-box">
                        <h5><i class="bi bi-info-circle text-warning me-2"></i>Instrucciones:</h5>
                        <div class="step">
                            <div class="step-number">1</div>
                            <div>Revisa tu correo electrónico</div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div>Busca el email con el código de 6 dígitos</div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div>Ingresa el código aquí para verificar</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="email-info">
                    <i class="bi bi-envelope-fill text-primary me-2"></i>
                    <span class="email-text">Código enviado a: <?= htmlspecialchars($correo_oculto) ?></span>
                </div>

                <?php if ($reenvio_exitoso): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?= htmlspecialchars($mensaje_exito_reenvio) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($mensaje_error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($mensaje_error_reenvio)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($mensaje_error_reenvio) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="verificationForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ingresa el código de 6 dígitos:</label>
                        <input type="text"
                            name="codigo"
                            class="form-control code-input"
                            maxlength="6"
                            pattern="\d{6}"
                            placeholder="000000"
                            required
                            autocomplete="off"
                            autofocus
                            value="<?= $codigo_preservado ?>">
                        <div class="text-muted small text-center mt-2">
                            El código debe tener exactamente 6 dígitos
                        </div>
                    </div>

                    <button type="submit" name="verificar" class="btn btn-verify">
                        <i class="bi bi-check-circle me-2"></i>Verificar Cuenta
                    </button>

                    <button type="button"
                        class="btn btn-outline-primary w-100 mt-2"
                        data-bs-toggle="modal"
                        data-bs-target="#reenviarModal">
                        <i class="bi bi-arrow-clockwise me-2"></i>Reenviar Código
                    </button>
                </form>

                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>
                        <strong>⚠️ Límites de seguridad:</strong><br>
                        • Máximo 3 códigos enviados por cuenta<br>
                        • Máximo 5 intentos fallidos de verificación<br>
                        • Superar estos límites bloqueará la cuenta
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para reenviar código -->
    <div class="modal fade" id="reenviarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-clockwise text-primary me-2"></i>Reenviar Código</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p>¿Estás seguro de que quieres solicitar un nuevo código de verificación?</p>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>
                                <strong>Códigos disponibles:</strong> <?= $codigos_restantes ?> de 3<br>
                                <strong>Correo destino:</strong> <?= htmlspecialchars($correo_oculto) ?>
                            </small>
                        </div>

                        <?php if ($codigos_restantes <= 0): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <small>No tienes códigos disponibles. La cuenta será bloqueada.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="reenviar_codigo" class="btn btn-primary" <?= ($codigos_restantes <= 0) ? 'disabled' : '' ?>>
                            <i class="bi bi-send me-2"></i>Sí, reenviar código
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.querySelector('input[name="codigo"]');

            // Solo permitir números
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 6);
            });

            // Prevenir auto-submit
            codeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });

            // Prevenir caracteres no numéricos
            codeInput.addEventListener('keydown', function(e) {
                // Permitir teclas de control
                if (e.key.length === 1 && !/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });

            // Auto-focus
            codeInput.focus();
            codeInput.setSelectionRange(codeInput.value.length, codeInput.value.length);
        });
    </script>
</body>

</html>