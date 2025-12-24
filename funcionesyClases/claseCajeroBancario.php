<?php
class ProcesaDDBB
{
    private $pdoConn;
    private $tblname;
    private $form_data;
    private $consulta;
    private $idDato;
    private $redirecciona;
    public $resultado;
    public $resultadoUno;
    public $redireccionaMensaje = "";

    public function __construct(PDO $pdoConnA, string $tblnameA, array $form_dataA, string $consultaA = "", $idDatoA = "", string $redireccionaA = "")
    {
        $this->pdoConn = $pdoConnA;
        $this->tblname = $tblnameA;
        $this->form_data = $form_dataA;
        $this->consulta = $consultaA;
        $this->idDato = $idDatoA;
        $this->redirecciona = $redireccionaA;
    }

    public function insertar(): bool
    {
        try {
            $fields = array_keys($this->form_data);
            $placeholders = implode(',', array_fill(0, count($fields), '?'));

            $sql = "INSERT INTO " . $this->tblname . " (" . implode(',', $fields) . ") VALUES (" . $placeholders . ")";
            $sentencia = $this->pdoConn->prepare($sql);

            $this->resultado = $sentencia->execute(array_values($this->form_data)) ? "correcto" : "error";
            return $this->resultado === "correcto";
        } catch (PDOException $e) {
            error_log("Error en insertar: " . $e->getMessage());
            $this->resultado = "error";
            return false;
        }
    }

    public function actualizar(): bool
    {
        try {
            if (empty($this->consulta)) {
                throw new Exception("Consulta WHERE no proporcionada para actualización");
            }

            $setParts = [];
            $values = [];

            foreach ($this->form_data as $key => $val) {
                $setParts[] = "$key = ?";
                $values[] = $val;
            }

            $sql = "UPDATE " . $this->tblname . " SET " . implode(', ', $setParts) . " " . $this->consulta;
            $sentencia = $this->pdoConn->prepare($sql);

            $this->resultado = $sentencia->execute($values) ? "correcto" : "error";
            return $this->resultado === "correcto";
        } catch (Exception $e) {
            error_log("Error en actualizar: " . $e->getMessage());
            $this->resultado = "error";
            return false;
        }
    }

    public function mostrarDatosTodos(): array
    {
        try {
            $sentencia = $this->pdoConn->prepare($this->consulta);
            $sentencia->execute();
            $this->resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            return $this->resultado;
        } catch (PDOException $e) {
            error_log("Error en mostrarDatosTodos: " . $e->getMessage());
            $this->resultado = [];
            return [];
        }
    }

    public function mostrarDatosUno(): ?array
    {
        try {
            $sentencia = $this->pdoConn->prepare($this->consulta);
            $sentencia->execute();
            $this->resultadoUno = $sentencia->fetch(PDO::FETCH_ASSOC);
            return $this->resultadoUno ?: null;
        } catch (PDOException $e) {
            error_log("Error en mostrarDatosUno: " . $e->getMessage());
            $this->resultadoUno = null;
            return null;
        }
    }

    public function eliminar(): bool
    {
        try {
            if (empty($this->consulta)) {
                throw new Exception("Consulta WHERE no proporcionada para eliminación");
            }

            $sql = "DELETE FROM " . $this->tblname . " " . $this->consulta;
            $sentencia = $this->pdoConn->prepare($sql);

            $this->resultado = $sentencia->execute() ? "correcto" : "error";
            return $this->resultado === "correcto";
        } catch (Exception $e) {
            error_log("Error en eliminar: " . $e->getMessage());
            $this->resultado = "error";
            return false;
        }
    }

    public function redirecciona(): void
    {
        if (!headers_sent()) {
            $params = [];

            if ($this->resultado === "correcto") {
                $params['correcto'] = 'si';
                if (!empty($this->redireccionaMensaje)) {
                    $params['mensaje'] = urlencode($this->redireccionaMensaje);
                }
            } else {
                $params['error'] = 'si';
            }

            $queryString = http_build_query($params);
            $location = $this->redirecciona . (!empty($queryString) ? '?' . $queryString : '');

            header("Location: " . $location);
            exit();
        }
    }
}
