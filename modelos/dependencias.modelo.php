<?php

require_once "conexion.php";

/**
 * ModeloDependencias
 * 
 * Capa de persistencia PDO para la gestión de dependencias institucionales
 * y la asignación / edición del usuario encargado responsable de cada área.
 * Cumple con RF-40, RF-41 y RNF-14.
 */
class ModeloDependencias {

    /**
     * Lista todas las dependencias con datos de su encargado responsable,
     * conteo de usuarios y radicados asociados, o consulta una dependencia específica por campo.
     * 
     * @param string|null $tabla Nombre de la tabla (retrocompatibilidad).
     * @param string|null $campo Campo de filtro opcional (id_dependencia, codigo, nombre).
     * @param mixed $valor Valor del filtro.
     * @return array|false
     */
    public static function mdlMostrarDependencias($tabla = "dependencias", $campo = null, $valor = null) {
        $pdo = Conexion::conectar();

        if ($campo !== null) {
            $camposPermitidos = ["id_dependencia", "codigo", "nombre", "estado"];
            if (!in_array($campo, $camposPermitidos, true)) {
                $campo = "id_dependencia";
            }

            try {
                $sql = "SELECT d.id_dependencia,
                               d.nombre,
                               d.dependencia,
                               d.codigo,
                               d.descripcion,
                               d.tipo,
                               d.estado,
                               d.id_usuario_responsable,
                               u.nombre AS encargado_nombre,
                               u.correo AS encargado_correo,
                               COALESCE(r.nombre, 'Encargado del área') AS encargado_rol,
                               (SELECT COUNT(*) FROM usuarios u2 WHERE u2.id_dependencia = d.id_dependencia) AS total_usuarios,
                               (SELECT COUNT(*) FROM radicados r2 WHERE r2.id_dependencia = d.id_dependencia) AS total_radicados
                        FROM dependencias d
                        LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario_responsable
                        LEFT JOIN roles r ON r.id_rol = u.id_rol
                        WHERE d.{$campo} = :valor
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                if ($campo === "id_dependencia") {
                    $idVal = (int)$valor;
                    $stmt->bindParam(":valor", $idVal, PDO::PARAM_INT);
                } else {
                    $strVal = (string)$valor;
                    $stmt->bindParam(":valor", $strVal, PDO::PARAM_STR);
                }
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Error en mdlMostrarDependencias ({$campo}): " . $e->getMessage());
                return false;
            }
        }

        try {
            $sql = "SELECT d.id_dependencia,
                           d.nombre,
                           d.dependencia,
                           d.codigo,
                           d.descripcion,
                           d.tipo,
                           d.estado,
                           d.id_usuario_responsable,
                           u.nombre AS encargado_nombre,
                           u.correo AS encargado_correo,
                           COALESCE(r.nombre, 'Encargado del área') AS encargado_rol,
                           (SELECT COUNT(*) FROM usuarios u2 WHERE u2.id_dependencia = d.id_dependencia) AS total_usuarios,
                           (SELECT COUNT(*) FROM radicados r2 WHERE r2.id_dependencia = d.id_dependencia) AS total_radicados
                    FROM dependencias d
                    LEFT JOIN usuarios u ON u.id_usuario = d.id_usuario_responsable
                    LEFT JOIN roles r ON r.id_rol = u.id_rol
                    ORDER BY d.id_dependencia ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en mdlMostrarDependencias (listado): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las métricas generales del módulo de dependencias (KPIs).
     * 
     * @return array
     */
    public static function mdlEstadisticasDependencias(): array {
        $pdo = Conexion::conectar();
        try {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM dependencias")->fetchColumn();
            $activas = (int)$pdo->query("SELECT COUNT(*) FROM dependencias WHERE estado IN ('Activo', 'Activa')")->fetchColumn();
            $inactivas = (int)$pdo->query("SELECT COUNT(*) FROM dependencias WHERE estado NOT IN ('Activo', 'Activa')")->fetchColumn();
            $conEncargado = (int)$pdo->query("SELECT COUNT(*) FROM dependencias WHERE id_usuario_responsable IS NOT NULL")->fetchColumn();

            return [
                "total"        => $total,
                "activas"      => $activas,
                "inactivas"    => $inactivas,
                "conEncargado" => $conEncargado
            ];
        } catch (PDOException $e) {
            error_log("Error en mdlEstadisticasDependencias: " . $e->getMessage());
            return [
                "total"        => 0,
                "activas"      => 0,
                "inactivas"    => 0,
                "conEncargado" => 0
            ];
        }
    }

    /**
     * Registra una nueva dependencia institucional con su usuario encargado responsable.
     * 
     * @param array $datos
     * @return int|false ID de la dependencia creada o false en caso de error.
     */
    public static function mdlCrearDependencia(array $datos) {
        $pdo = Conexion::conectar();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO dependencias (nombre, descripcion, estado, dependencia, codigo, tipo, id_usuario_responsable)
                 VALUES (:nombre, :descripcion, :estado, :dependencia, :codigo, :tipo, :id_usuario_responsable)"
            );

            $nombre        = $datos["nombre"] ?? "";
            $descripcion   = $datos["descripcion"] !== "" ? $datos["descripcion"] : ("Unidad organizacional: " . $nombre);
            $estado        = $datos["estado"] ?? "Activo";
            $dependencia   = $datos["dependencia"] ?? $nombre;
            $codigo        = $datos["codigo"] ?? "";
            $tipo          = $datos["tipo"] ?? "operativa";
            $idResponsable = !empty($datos["id_usuario_responsable"]) ? (int)$datos["id_usuario_responsable"] : null;

            $stmt->bindParam(":nombre",                 $nombre, PDO::PARAM_STR);
            $stmt->bindParam(":descripcion",            $descripcion, PDO::PARAM_STR);
            $stmt->bindParam(":estado",                 $estado, PDO::PARAM_STR);
            $stmt->bindParam(":dependencia",            $dependencia, PDO::PARAM_STR);
            $stmt->bindParam(":codigo",                 $codigo, PDO::PARAM_STR);
            $stmt->bindParam(":tipo",                   $tipo, PDO::PARAM_STR);
            $stmt->bindValue(":id_usuario_responsable",  $idResponsable, $idResponsable !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->execute();

            $idNuevaDep = (int)$pdo->lastInsertId();

            // Si se asignó un usuario encargado, asociarlo a esta dependencia
            if ($idResponsable !== null && $idNuevaDep > 0) {
                $stmtUser = $pdo->prepare("UPDATE usuarios SET id_dependencia = :id_dep WHERE id_usuario = :id_user");
                $stmtUser->bindValue(":id_dep", $idNuevaDep, PDO::PARAM_INT);
                $stmtUser->bindValue(":id_user", $idResponsable, PDO::PARAM_INT);
                $stmtUser->execute();
            }

            return $idNuevaDep;
        } catch (PDOException $e) {
            error_log("Error en mdlCrearDependencia: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza los datos de una dependencia institucional existente, incluyendo su encargado responsable.
     * 
     * @param int $idDependencia
     * @param array $datos
     * @return bool
     */
    public static function mdlEditarDependencia(int $idDependencia, array $datos): bool {
        $pdo = Conexion::conectar();

        try {
            $stmt = $pdo->prepare(
                "UPDATE dependencias
                 SET nombre = :nombre,
                     descripcion = :descripcion,
                     estado = :estado,
                     dependencia = :dependencia,
                     codigo = :codigo,
                     tipo = :tipo,
                     id_usuario_responsable = :id_usuario_responsable
                 WHERE id_dependencia = :id_dependencia"
            );

            $nombre        = $datos["nombre"] ?? "";
            $descripcion   = $datos["descripcion"] !== "" ? $datos["descripcion"] : ("Unidad organizacional: " . $nombre);
            $estado        = $datos["estado"] ?? "Activo";
            $dependencia   = $datos["dependencia"] ?? $nombre;
            $codigo        = $datos["codigo"] ?? "";
            $tipo          = $datos["tipo"] ?? "operativa";
            $idResponsable = (isset($datos["id_usuario_responsable"]) && $datos["id_usuario_responsable"] !== "" && $datos["id_usuario_responsable"] !== null)
                                ? (int)$datos["id_usuario_responsable"]
                                : null;

            $stmt->bindParam(":nombre",                 $nombre, PDO::PARAM_STR);
            $stmt->bindParam(":descripcion",            $descripcion, PDO::PARAM_STR);
            $stmt->bindParam(":estado",                 $estado, PDO::PARAM_STR);
            $stmt->bindParam(":dependencia",            $dependencia, PDO::PARAM_STR);
            $stmt->bindParam(":codigo",                 $codigo, PDO::PARAM_STR);
            $stmt->bindParam(":tipo",                   $tipo, PDO::PARAM_STR);
            $stmt->bindValue(":id_usuario_responsable",  $idResponsable, $idResponsable !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindParam(":id_dependencia",         $idDependencia, PDO::PARAM_INT);

            $ok = $stmt->execute();

            if ($ok && $idResponsable !== null) {
                // Sincronizar el usuario con la dependencia
                $stmtUser = $pdo->prepare("UPDATE usuarios SET id_dependencia = :id_dep WHERE id_usuario = :id_user");
                $stmtUser->bindValue(":id_dep", $idDependencia, PDO::PARAM_INT);
                $stmtUser->bindValue(":id_user", $idResponsable, PDO::PARAM_INT);
                $stmtUser->execute();

                // Sincronizar trámites asignados a esta dependencia con el nuevo encargado
                $stmtTram = $pdo->prepare("UPDATE tramites SET id_usuario_responsable = :id_user WHERE id_dependencia_responsable = :id_dep");
                $stmtTram->bindValue(":id_user", $idResponsable, PDO::PARAM_INT);
                $stmtTram->bindValue(":id_dep", $idDependencia, PDO::PARAM_INT);
                $stmtTram->execute();
            }

            return $ok;
        } catch (PDOException $e) {
            error_log("Error en mdlEditarDependencia: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza atómicamente el estado de una dependencia (Activo / Inactivo).
     * 
     * @param int $idDependencia
     * @param string $estado
     * @return bool
     */
    public static function mdlActualizarEstado(int $idDependencia, string $estado): bool {
        try {
            $stmt = Conexion::conectar()->prepare("UPDATE dependencias SET estado = :estado WHERE id_dependencia = :id_dependencia");
            $stmt->bindParam(":estado",         $estado, PDO::PARAM_STR);
            $stmt->bindParam(":id_dependencia", $idDependencia, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en ModeloDependencias::mdlActualizarEstado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina una dependencia validando integridad referencial con usuarios y radicados.
     * 
     * @param int $idDependencia
     * @return string "ok", "en_uso" o "error"
     */
    public static function mdlEliminarDependencia(int $idDependencia): string {
        $pdo = Conexion::conectar();

        try {
            $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_dependencia = :id_dependencia");
            $stmtUsers->bindParam(":id_dependencia", $idDependencia, PDO::PARAM_INT);
            $stmtUsers->execute();
            if ((int)$stmtUsers->fetchColumn() > 0) {
                return "en_uso";
            }

            $stmtRads = $pdo->prepare("SELECT COUNT(*) FROM radicados WHERE id_dependencia = :id_dependencia");
            $stmtRads->bindParam(":id_dependencia", $idDependencia, PDO::PARAM_INT);
            $stmtRads->execute();
            if ((int)$stmtRads->fetchColumn() > 0) {
                return "en_uso";
            }

            $stmtDel = $pdo->prepare("DELETE FROM dependencias WHERE id_dependencia = :id_dependencia");
            $stmtDel->bindParam(":id_dependencia", $idDependencia, PDO::PARAM_INT);
            $stmtDel->execute();

            return "ok";
        } catch (PDOException $e) {
            error_log("Error en mdlEliminarDependencia: " . $e->getMessage());
            return "error";
        }
    }
}
