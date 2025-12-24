<?php
// auth/registro.php
ob_start();
session_start();
require_once "../conn/connrota.php";

$titulo = "Crear Cuenta - Rotafolio NovaExperto";

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/");
    exit;
}

// Configuración reCAPTCHA v2 Checkbox
$claveSitioCaptcha = "6LeQvDAsAAAAAOKEttAIBavp7QYhstmukK-KyeeD";
$claveSecretaCaptcha = "6LeQvDAsAAAAALlD6xZC14xjOQm5cXZZaKCe6qZa";

// Manejo de mensajes
$mensajeModalError = "";
$modalAvisoError = "no";

if (isset($_GET["error"])) {
    $modalAvisoError = "si";
    switch ($_GET["error"]) {
        case "usuarioExiste":
            $mensajeModalError = "Este nombre de usuario ya está registrado en nuestro sistema.";
            break;
        case "correoExiste":
            $mensajeModalError = "Este correo electrónico ya está en uso.";
            break;
        case "captcha":
            $mensajeModalError = "Por favor, verifica que no eres un robot.";
            break;
        case "claves_no_coinciden":
            $mensajeModalError = "Las contraseñas no coinciden.";
            break;
        case "correo_invalido":
            $mensajeModalError = "El correo electrónico no tiene un formato válido.";
            break;
        case "usuario_corto":
            $mensajeModalError = "El nombre de usuario debe tener al menos 3 caracteres.";
            break;
        case "clave_corta":
            $mensajeModalError = "La contraseña debe tener al menos 6 caracteres.";
            break;
        case "campos_requeridos":
            $mensajeModalError = "Por favor, completa todos los campos requeridos.";
            break;
        default:
            $mensajeModalError = "Ocurrió un error al procesar tu registro. Error: " . htmlspecialchars($_GET["error"]);
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- reCAPTCHA v2 -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <style>
        :root {
            --rota-primary: #0dcaf0;
            --rota-primary-dark: #0aa2c0;
            --rota-gradient: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .registration-section {
            flex: 1;
            background: linear-gradient(135deg, rgba(13, 202, 240, 0.05) 0%, rgba(13, 110, 253, 0.05) 100%);
            padding: 2rem 0;
        }

        .registration-card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(13, 202, 240, 0.15);
            overflow: hidden;
            margin: 2rem 0;
        }

        .registration-header {
            background: var(--rota-gradient);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }

        .registration-body {
            padding: 2.5rem;
            background: white;
        }

        .btn-rota {
            background: var(--rota-gradient);
            border: none;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(13, 202, 240, 0.2);
        }

        .btn-rota:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(13, 202, 240, 0.3);
            color: white;
        }

        .validation-message {
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
        }

        .validation-message.valid {
            color: #198754;
        }

        .validation-message.invalid {
            color: #dc3545;
        }

        .g-recaptcha {
            margin-bottom: 1.5rem;
            display: inline-block;
        }

        .error-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .error-modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>

<body>
    <!-- Modal de error -->
    <?php if ($modalAvisoError == "si"): ?>
        <div class="error-modal" id="errorModal">
            <div class="error-modal-content">
                <div class="text-center mb-4">
                    <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
                    <h4 class="mt-3 text-danger">Error en el registro</h4>
                    <p><?php echo $mensajeModalError; ?></p>
                </div>
                <div class="text-center">
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('errorModal').remove();">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navbar (simplificado) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Rotafolio
            </a>
            <a href="login.php" class="btn btn-outline-primary">Iniciar Sesión</a>
        </div>
    </nav>

    <!-- Registration Section -->
    <section class="registration-section">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="registration-card">
                        <div class="registration-header">
                            <h2 class="display-6 fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Registro de Usuario</h2>
                            <p class="mb-0">Completa tus datos para comenzar</p>
                        </div>

                        <div class="registration-body">
                            <form id="registrationForm" action="procesar_registro.php" method="post" autocomplete="off" novalidate>
                                <div class="row g-3">
                                    <!-- Nombre de Usuario -->
                                    <div class="col-md-6">
                                        <label for="usuario" class="form-label fw-bold">Nombre de Usuario *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-person-circle"></i>
                                            </span>
                                            <input type="text"
                                                class="form-control"
                                                name="usuario"
                                                id="usuario"
                                                placeholder="ejemplo123"
                                                required
                                                minlength="3"
                                                value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                                        </div>
                                        <div id="usuarioMsg" class="validation-message"></div>
                                    </div>

                                    <!-- Correo -->
                                    <div class="col-md-6">
                                        <label for="email" class="form-label fw-bold">Correo Electrónico *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-envelope"></i>
                                            </span>
                                            <input type="email"
                                                class="form-control"
                                                name="correo"
                                                id="email"
                                                placeholder="tu@correo.com"
                                                required
                                                value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                                        </div>
                                        <div id="emailMsg" class="validation-message"></div>
                                    </div>

                                    <!-- Nombre -->
                                    <div class="col-md-6">
                                        <label for="nombre" class="form-label fw-bold">Nombre *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-person"></i>
                                            </span>
                                            <input type="text"
                                                class="form-control text-uppercase"
                                                name="nombre"
                                                id="nombre"
                                                placeholder="JUAN"
                                                required
                                                oninput="this.value = this.value.toUpperCase()"
                                                value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Apellido -->
                                    <div class="col-md-6">
                                        <label for="apellido" class="form-label fw-bold">Apellido *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-person"></i>
                                            </span>
                                            <input type="text"
                                                class="form-control text-uppercase"
                                                name="apellido"
                                                id="apellido"
                                                placeholder="PÉREZ"
                                                required
                                                oninput="this.value = this.value.toUpperCase()"
                                                value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Contraseña -->
                                    <div class="col-md-6">
                                        <label for="clave1" class="form-label fw-bold">Contraseña *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-lock"></i>
                                            </span>
                                            <input type="password"
                                                class="form-control"
                                                name="clave1"
                                                id="clave1"
                                                placeholder="Mínimo 6 caracteres"
                                                minlength="6"
                                                required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword1">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div id="passwordStrength" class="validation-message"></div>
                                    </div>

                                    <!-- Confirmar Contraseña -->
                                    <div class="col-md-6">
                                        <label for="clave2" class="form-label fw-bold">Confirmar Contraseña *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-lock-fill"></i>
                                            </span>
                                            <input type="password"
                                                class="form-control"
                                                name="clave2"
                                                id="clave2"
                                                placeholder="Repite tu contraseña"
                                                minlength="6"
                                                required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword2">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div id="passwordMatch" class="validation-message"></div>
                                    </div>

                                    <!-- Términos -->
                                    <div class="col-12">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="termsCheck" required>
                                            <label class="form-check-label" for="termsCheck">
                                                Acepto los <a href="../legal/terminos.php" class="text-primary" target="_blank">Términos</a> y <a href="../legal/privacidad.php" class="text-primary" target="_blank">Privacidad</a>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- reCAPTCHA v2 Checkbox -->
                                    <div class="col-12">
                                        <div class="g-recaptcha" data-sitekey="<?php echo $claveSitioCaptcha; ?>"></div>
                                        <div id="captchaError" class="validation-message invalid d-none">
                                            <i class="bi bi-exclamation-circle me-1"></i>Por favor, verifica que no eres un robot.
                                        </div>
                                    </div>

                                    <!-- Botón de Registro -->
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-rota btn-lg w-100 py-3 fw-bold" id="submitBtn">
                                            <i class="bi bi-person-plus-fill me-2"></i>Crear Cuenta Gratis
                                        </button>
                                    </div>

                                    <!-- Enlace a Login -->
                                    <div class="col-12 text-center mt-3">
                                        <p class="mb-0">
                                            ¿Ya tienes una cuenta?
                                            <a href="login.php" class="text-primary fw-bold">Inicia sesión aquí</a>
                                        </p>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <p class="text-muted small mb-0">
                            <i class="bi bi-shield-check text-primary me-1"></i>
                            Tus datos están protegidos con encriptación de 256-bit.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script de validación -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            const captchaError = document.getElementById('captchaError');

            // Función para mostrar/ocultar contraseña
            function setupPasswordToggle(passwordId, toggleId) {
                const toggleBtn = document.getElementById(toggleId);
                const passwordInput = document.getElementById(passwordId);

                toggleBtn.addEventListener('click', function() {
                    const type = passwordInput.type === 'password' ? 'text' : 'password';
                    passwordInput.type = type;
                    const icon = this.querySelector('i');
                    icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
                });
            }

            setupPasswordToggle('clave1', 'togglePassword1');
            setupPasswordToggle('clave2', 'togglePassword2');

            // Validación de usuario
            document.getElementById('usuario').addEventListener('input', function() {
                const usuario = this.value.trim();
                const msg = document.getElementById('usuarioMsg');

                if (usuario.length === 0) {
                    msg.textContent = '';
                    msg.className = 'validation-message';
                    return;
                }

                if (usuario.length < 3) {
                    msg.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Mínimo 3 caracteres';
                    msg.className = 'validation-message invalid';
                } else {
                    msg.innerHTML = '<i class="bi bi-check-circle me-1"></i>Válido';
                    msg.className = 'validation-message valid';
                }
            });

            // Validación de email
            document.getElementById('email').addEventListener('input', function() {
                const email = this.value.trim();
                const msg = document.getElementById('emailMsg');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (email.length === 0) {
                    msg.textContent = '';
                    msg.className = 'validation-message';
                    return;
                }

                if (!emailRegex.test(email)) {
                    msg.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Formato inválido';
                    msg.className = 'validation-message invalid';
                } else {
                    msg.innerHTML = '<i class="bi bi-check-circle me-1"></i>Válido';
                    msg.className = 'validation-message valid';
                }
            });

            // Validación de contraseñas
            document.getElementById('clave1').addEventListener('input', validatePasswords);
            document.getElementById('clave2').addEventListener('input', validatePasswords);

            function validatePasswords() {
                const pass1 = document.getElementById('clave1').value;
                const pass2 = document.getElementById('clave2').value;
                const strengthMsg = document.getElementById('passwordStrength');
                const matchMsg = document.getElementById('passwordMatch');

                // Fortaleza
                if (pass1.length === 0) {
                    strengthMsg.textContent = '';
                    strengthMsg.className = 'validation-message';
                } else if (pass1.length < 6) {
                    strengthMsg.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Mínimo 6 caracteres';
                    strengthMsg.className = 'validation-message invalid';
                } else {
                    strengthMsg.innerHTML = '<i class="bi bi-check-circle me-1"></i>Segura';
                    strengthMsg.className = 'validation-message valid';
                }

                // Coincidencia
                if (pass2.length === 0) {
                    matchMsg.textContent = '';
                    matchMsg.className = 'validation-message';
                } else if (pass1 !== pass2) {
                    matchMsg.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>No coinciden';
                    matchMsg.className = 'validation-message invalid';
                } else {
                    matchMsg.innerHTML = '<i class="bi bi-check-circle me-1"></i>Coinciden';
                    matchMsg.className = 'validation-message valid';
                }
            }

            // Validar reCAPTCHA
            function validateCaptcha() {
                const response = grecaptcha.getResponse();
                if (response.length === 0) {
                    captchaError.classList.remove('d-none');
                    return false;
                } else {
                    captchaError.classList.add('d-none');
                    return true;
                }
            }

            // Manejar envío del formulario
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Validar reCAPTCHA
                if (!validateCaptcha()) {
                    alert('Por favor, verifica el reCAPTCHA');
                    return;
                }

                // Validar términos
                if (!document.getElementById('termsCheck').checked) {
                    alert('Debes aceptar los términos y condiciones');
                    return;
                }

                // Mostrar loading
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Procesando...';
                submitBtn.disabled = true;

                // Enviar formulario
                setTimeout(() => {
                    this.submit();
                }, 500);
            });

            // Auto-eliminar modal de error después de 5 segundos
            setTimeout(() => {
                const errorModal = document.getElementById('errorModal');
                if (errorModal) errorModal.remove();
            }, 5000);
        });
    </script>
</body>

</html>