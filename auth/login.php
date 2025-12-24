<?php
// auth/login.php
session_start();
require_once "../conn/connrota.php";

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
  if (isset($_SESSION['user_data']['correo_verificado']) && $_SESSION['user_data']['correo_verificado'] == 'si') {
    header("Location: ../dashboard/");
    exit();
  } else {
    header("Location: verificar_correo.php");
    exit();
  }
}

// Solo manejar mensajes GET
$error = '';
$success = '';

// Mapear errores
$error_messages = [
  'campos_vacios' => 'Usuario y contraseña son requeridos',
  'usuario_no_encontrado' => 'Usuario o correo no encontrado',
  'contrasena_incorrecta' => 'Contraseña incorrecta',
  'cuenta_pendiente' => 'Cuenta pendiente de activación. Por favor revisa tu correo electrónico.',
  'cuenta_suspendida' => 'Cuenta suspendida temporalmente. Contacta al administrador.',
  'cuenta_bloqueada' => 'Cuenta bloqueada por seguridad. Contacta al administrador.',
  'cuenta_inactiva' => 'Cuenta inactiva. Contacta al administrador.',
  'acceso_invalido' => 'Acceso inválido al sistema.',
  'sesion_expirada' => 'Tu sesión ha expirado. Por favor inicia sesión nuevamente.'
];

// Mostrar mensaje de error si viene por GET
if (isset($_GET['error']) && isset($error_messages[$_GET['error']])) {
  $error = $error_messages[$_GET['error']];
}

// Mostrar mensaje de éxito si viene de registro
if (isset($_GET['registro']) && $_GET['registro'] == 'exito') {
  $success = "¡Registro exitoso! Por favor verifica tu correo electrónico.";
}

if (isset($_GET['verificado']) && $_GET['verificado'] == 'si') {
  $success = "¡Correo verificado exitosamente! Ya puedes iniciar sesión.";
}

if (isset($_GET['recuperacion']) && $_GET['recuperacion'] == 'exito') {
  $success = "Contraseña actualizada exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.";
}

if (isset($_GET['logout']) && $_GET['logout'] == 'exito') {
  $success = "Has cerrado sesión exitosamente.";
}

$titulo = "Iniciar Sesión - Rotafolio";
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

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --rota-primary: #0dcaf0;
      --rota-primary-dark: #0aa2c0;
      --rota-primary-light: #9eeaf9;
      --rota-gradient: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
      --rota-success: #198754;
      --rota-warning: #ffc107;
      --rota-danger: #dc3545;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e9ecef 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      padding: 20px;
    }

    .login-container {
      max-width: 400px;
      width: 100%;
      margin: 0 auto;
    }

    .login-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(13, 202, 240, 0.15);
      overflow: hidden;
      border: none;
      transition: transform 0.3s ease;
    }

    .login-card:hover {
      transform: translateY(-5px);
    }

    .login-header {
      background: var(--rota-gradient);
      color: white;
      padding: 2.5rem 2rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .login-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
      background-size: 50px 50px;
      animation: float 20s linear infinite;
      opacity: 0.3;
    }

    @keyframes float {
      0% {
        transform: translateY(0) rotate(0deg);
      }

      100% {
        transform: translateY(-50px) rotate(360deg);
      }
    }

    .logo {
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 1rem;
      display: block;
      position: relative;
      z-index: 1;
    }

    .login-header h1 {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      position: relative;
      z-index: 1;
    }

    .login-header p {
      opacity: 0.9;
      font-size: 0.95rem;
      position: relative;
      z-index: 1;
    }

    .login-body {
      padding: 2.5rem 2rem;
      background: white;
    }

    .form-control {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      font-size: 1rem;
      transition: all 0.3s;
      background: #f8f9fa;
    }

    .form-control:focus {
      border-color: var(--rota-primary);
      box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25);
      background: white;
    }

    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn-login {
      background: var(--rota-gradient);
      border: none;
      color: white;
      padding: 1rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 1rem;
      width: 100%;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: 0.5s;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(13, 202, 240, 0.3);
    }

    .btn-login:active {
      transform: translateY(-1px);
    }

    .separator {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 1.5rem 0;
      color: #6c757d;
    }

    .separator::before,
    .separator::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #dee2e6;
    }

    .separator span {
      padding: 0 1rem;
      font-size: 0.9rem;
      background: white;
      position: relative;
      z-index: 1;
    }

    .login-footer {
      text-align: center;
      padding-top: 1.5rem;
      border-top: 1px solid #e9ecef;
      margin-top: 1.5rem;
    }

    .login-footer a {
      color: var(--rota-primary);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .login-footer a:hover {
      color: var(--rota-primary-dark);
      text-decoration: underline;
    }

    .alert-custom {
      border-radius: 12px;
      border: none;
      padding: 1.25rem 1.5rem;
      margin-bottom: 1.5rem;
      border-left: 5px solid;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .alert-custom.alert-success {
      border-left-color: var(--rota-success);
      background: linear-gradient(135deg, #d1e7dd 0%, #f8f9fa 100%);
    }

    .alert-custom.alert-danger {
      border-left-color: var(--rota-danger);
      background: linear-gradient(135deg, #f8d7da 0%, #f8f9fa 100%);
    }

    .alert-custom.alert-warning {
      border-left-color: var(--rota-warning);
      background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
    }

    .password-toggle {
      position: relative;
    }

    .password-toggle-icon {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #6c757d;
      z-index: 10;
      background: white;
      padding: 5px;
      border-radius: 5px;
      transition: all 0.2s;
    }

    .password-toggle-icon:hover {
      color: var(--rota-primary);
      background: #f8f9fa;
    }

    .loading-spinner {
      display: inline-block;
      width: 1rem;
      height: 1rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s ease-in-out infinite;
      margin-right: 8px;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    @media (max-width: 576px) {
      body {
        padding: 15px;
      }

      .login-card {
        border-radius: 15px;
      }

      .login-header {
        padding: 2rem 1.5rem;
      }

      .login-body {
        padding: 2rem 1.5rem;
      }
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="logo">
          <i class="bi bi-grid-3x3-gap-fill"></i> Rotafolio
        </div>
        <h1>Bienvenido de nuevo</h1>
        <p>Inicia sesión para acceder a tu dashboard</p>
      </div>

      <div class="login-body">
        <?php if ($error): ?>
          <div class="alert alert-danger alert-custom alert-dismissible fade show">
            <div class="d-flex align-items-center">
              <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
              <div class="flex-grow-1">
                <h6 class="mb-1 fw-bold">¡Error!</h6>
                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success alert-custom alert-dismissible fade show">
            <div class="d-flex align-items-center">
              <i class="bi bi-check-circle-fill me-3 fs-4"></i>
              <div class="flex-grow-1">
                <h6 class="mb-1 fw-bold">¡Éxito!</h6>
                <p class="mb-0"><?= htmlspecialchars($success) ?></p>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="login_procesa.php" id="loginForm" autocomplete="off">
          <div class="mb-4">
            <label for="usuario" class="form-label">
              <i class="bi bi-person-fill"></i>
              <span>Usuario o Correo</span>
            </label>
            <input type="text"
              class="form-control"
              id="usuario"
              name="usuario"
              placeholder="Ingresa tu usuario o correo"
              value="<?= isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : '' ?>"
              required
              autofocus
              autocomplete="username">
            <div class="form-text mt-1">
              <small>Puedes usar tu nombre de usuario o dirección de correo</small>
            </div>
          </div>

          <div class="mb-4 password-toggle">
            <label for="clave" class="form-label">
              <i class="bi bi-lock-fill"></i>
              <span>Contraseña</span>
            </label>
            <input type="password"
              class="form-control"
              id="clave"
              name="clave"
              placeholder="Ingresa tu contraseña"
              required
              autocomplete="current-password">
            <span class="password-toggle-icon" id="togglePassword">
              <i class="bi bi-eye"></i>
            </span>
            <div class="form-text mt-1 d-flex justify-content-between">
              <small>Mínimo 6 caracteres</small>
              <a href="recuperar_clave.php" class="text-decoration-none small">
                ¿Olvidaste tu contraseña?
              </a>
            </div>
          </div>

          <div class="mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember" name="remember">
              <label class="form-check-label" for="remember">
                Recordar mi sesión en este dispositivo
              </label>
            </div>
          </div>

          <div class="d-grid mb-4">
            <button type="submit" name="login" class="btn btn-login" id="loginBtn">
              <span id="btnText">
                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
              </span>
              <span id="btnLoading" class="d-none">
                <span class="loading-spinner"></span>Iniciando sesión...
              </span>
            </button>
          </div>

          <div class="separator">
            <span>¿No tienes una cuenta?</span>
          </div>

          <div class="d-grid">
            <a href="registro.php" class="btn btn-outline-primary">
              <i class="bi bi-person-plus me-2"></i>Crear Cuenta Gratis
            </a>
          </div>
        </form>
      </div>

      <div class="login-footer">
        <p class="mb-2">
          <small>© <?= date('Y') ?> Rotafolio. Todos los derechos reservados.</small>
        </p>
        <p class="mb-0">
          <a href="../legal/terminos.php" class="me-3" target="_blank">Términos</a>
          <a href="../legal/privacidad.php" class="me-3" target="_blank">Privacidad</a>
          <a href="../legal/contacto.php" target="_blank">Contacto</a>
        </p>
      </div>
    </div>

    <!-- Características del sistema -->
    <div class="mt-4 text-center">
      <h6 class="mb-3 text-muted">¿Qué puedes hacer con Rotafolio?</h6>
      <ul class="list-unstyled">
        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Crear rotafolios interactivos</li>
        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Compartir con estudiantes o colegas</li>
        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Subir imágenes, videos y archivos</li>
        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Personalizar diseños únicos</li>
        <li><i class="bi bi-check-circle text-success me-2"></i>Acceder desde cualquier dispositivo</li>
      </ul>

      <div class="alert alert-info mt-3" style="background: rgba(13, 202, 240, 0.1); border: none;">
        <i class="bi bi-shield-check text-primary me-2"></i>
        <small>
          <strong>Seguridad garantizada:</strong> Tus datos están protegidos con encriptación SSL de 256-bit.
        </small>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Elementos del DOM
      const loginForm = document.getElementById('loginForm');
      const loginBtn = document.getElementById('loginBtn');
      const btnText = document.getElementById('btnText');
      const btnLoading = document.getElementById('btnLoading');
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('clave');
      const rememberCheckbox = document.getElementById('remember');
      const usuarioInput = document.getElementById('usuario');

      // Mostrar/ocultar contraseña
      togglePassword.addEventListener('click', function() {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        const icon = this.querySelector('i');
        icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
      });

      // Cargar usuario guardado si "Recordarme" estaba activado
      const savedUsuario = localStorage.getItem('rotafolio_usuario');
      const savedRemember = localStorage.getItem('rotafolio_remember');

      if (savedUsuario && savedRemember === 'true') {
        usuarioInput.value = savedUsuario;
        rememberCheckbox.checked = true;
      }

      // Guardar usuario si está marcado "Recordarme"
      loginForm.addEventListener('submit', function() {
        if (rememberCheckbox.checked) {
          localStorage.setItem('rotafolio_usuario', usuarioInput.value);
          localStorage.setItem('rotafolio_remember', 'true');
        } else {
          localStorage.removeItem('rotafolio_usuario');
          localStorage.removeItem('rotafolio_remember');
        }

        // Mostrar loading
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        loginBtn.disabled = true;
      });

      // Enter para enviar formulario
      document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
          const submitBtn = document.querySelector('button[name="login"]');
          if (submitBtn && !loginBtn.disabled) {
            submitBtn.click();
          }
        }
      });

      // Auto-cerrar alertas después de 5 segundos
      setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        });
      }, 5000);
    });
  </script>
</body>

</html>