   <!-- Sidebar -->
   <div class="sidebar" id="sidebar">
       <div class="position-sticky pt-0">
           <div class="logo">
               <div class="d-flex align-items-center">
                   <span class="logo-icon"><i class="bi bi-bank"></i></span>
                   <span class="logo-text ms-2">Cajero Bancario | NovaExperto</span>
               </div>
               <button class="sidebar-toggle" id="sidebarToggle">
                   <i class="bi bi-list"></i>
               </button>
           </div>

           <div class="sidebar-inner">
               <ul class="nav flex-column mt-3">
                   <li class="nav-item">
                       <a class="nav-link " href="../tablero_admin/">
                           <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                       </a>
                   </li>
                   <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle " href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <!-- Cambiado a true -->
                           <i class="bi bi-cash-stack"></i> <span>Transacciones </span> <i class="bi bi-caret-down-fill small"></i>
                       </a>
                       <ul class="dropdown-menu"> <!-- Agregada clase show -->
                           <li><a class="dropdown-item <?php echo $active1 ?>" href="../tablero_admin/transaciones/deposito.php"><i class="bi bi-arrow-up-circle me-2"></i>Depósitos</a></li>
                           <li><a class="dropdown-item <?php echo $active2 ?>" href="../tablero_admin/transaciones/retiro.php"><i class="bi bi-arrow-down-circle me-2"></i>Retiros</a></li>
                           <li><a class="dropdown-item <?php echo $active3 ?>" href="../tablero_admin/transaciones/trasnferencia.php"><i class="bi bi-arrow-left-right me-2"></i>Transferencias</a></li>
                       </ul>
                   </li>

                   <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="true">
                           <i class="bi bi-people"></i> <span>Clientes</span> <i class="bi bi-caret-down-fill small"></i>
                       </a>
                       <ul class="dropdown-menu">
                           <li><a class="dropdown-item show" href="../tablero_admin/cliente/nuevoCliente.php"><i class="bi bi-person-plus me-2"></i>Nuevo Cliente</a></li>
                           <li><a class="dropdown-item" href="../tablero_admin/cliente/buscarCliente.php"><i class="bi bi-search me-2"></i>Buscar Cliente</a></li>
                           <li><a class="dropdown-item" href="../tablero_admin/cliente/crearCuentaCliente.php"><i class="bi bi-plus-circle me-2"></i>Crear Cuenta</a></li>
                           <li><a class="dropdown-item" href="../tablero_admin/cliente/desativarEliminarCuenta.php"><i class="bi bi-arrow-repeat me-2"></i>Modificar Cuentas</a></li>
                       </ul>
                   </li>

                   <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-credit-card"></i> <span>Tarjetas</span> <i class="bi bi-caret-down-fill small"></i>
                       </a>
                       <ul class="dropdown-menu">
                           <li><a class="dropdown-item" href="../tablero_admin/tarjeta/debito.php"><i class="bi bi-credit-card-2-front me-2"></i>Débito</a></li>
                           <li><a class="dropdown-item" href="../tablero_admin/tarjeta/credito.php"><i class="bi bi-credit-card-2-back me-2"></i>Crédito</a></li>
                       </ul>
                   </li>
                   <li class="nav-item dropdown">
                       <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-coin"></i> <span>Prestamo</span> <i class="bi bi-caret-down-fill small"></i>
                       </a>
                       <ul class="dropdown-menu">
                           <li><a class="dropdown-item" href="../tablero_admin/prestamo/nuevoPrestamo.php"><i class="bi bi-database-fill-add me-2"></i>Nuevo Préstamo</a></li>
                           <li><a class="dropdown-item" href="../tablero_admin/prestamo/pagoPrestamo.php"><i class="bi bi-eye-fill me-2"></i>Pagos y Renovación</a></li>
                           <li><a class="dropdown-item" href="../tablero_admin/prestamo/reportesPrestamos.php"><i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>Reportes</a></li>
                       </ul>
                   </li>

                   <li class="nav-item">
                       <a class="nav-link" href="../moduloUsuario/login/cerrar.php">
                           <i class="bi bi-box-arrow-right"></i> <span>Cerrar Sesión</span>
                       </a>
                   </li>
                   <li class="nav-item text-center">
                       Cajero.
                       <div class="small">
                           <?php echo $nombreUsuarioMuestra ?>
                       </div>
                   </li>
               </ul>
           </div>
       </div>
   </div>