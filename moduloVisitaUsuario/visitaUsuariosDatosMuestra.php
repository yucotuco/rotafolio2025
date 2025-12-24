<?php
if (isset($_SESSION['rotaforlioyuco2025'])) {
} else {
    header("location: ../index.php?result=intento_fallido_de_inicio_de_sesion");
}
