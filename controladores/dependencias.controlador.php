<?php

require_once __DIR__ . "/../modelos/dependencias.modelo.php";
require_once __DIR__ . "/../api/helpers/respuesta.helper.php";

/**
 * ControladorDependencias
 * 
 * Controlador RESTful para la gestión de dependencias institucionales
 * y la administración de encargados responsables de área.
 * Cumple con RF-40, RF-41 y RNF-14.
 */
class ControladorDependencias {

    /*=============================================
    API REST: LISTAR DEPENDENCIAS (GET /api/dependencias)
    =============================================*/
    public static function ctrListar(): void {
        try {
            $dependencias = ModeloDependencias::mdlMostrarDependencias("dependencias");
            RespuestaHelper::exito($dependencias, "Listado de dependencias obtenido correctamente");
        } catch (Exception $e) {
            error_log("Error en ControladorDependencias::ctrListar: " . $e->getMessage());
            RespuestaHelper::error("Error interno al obtener el listado de dependencias", 500);
        }
    }

    /*=============================================
    API REST: ESTADÍSTICAS DE DEPENDENCIAS (GET /api/dependencias/stats)
    =============================================*/
    public static function ctrEstadisticas(): void {
        try {
            $stats = ModeloDependencias::mdlEstadisticasDependencias();
            RespuestaHelper::exito($stats, "Estadísticas de dependencias obtenidas correctamente");
        } catch (Exception $e) {
            error_log("Error en ControladorDependencias::ctrEstadisticas: " . $e->getMessage());
            RespuestaHelper::error("Error al calcular estadísticas de dependencias", 500);
        }
    }

    /*=============================================
    API REST: OBTENER DEPENDENCIA (GET /api/dependencias/{id})
    =============================================*/
    public static function ctrObtener(int $id): void {
        try {
            $dependencia = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $id);
            if ($dependencia) {
                RespuestaHelper::exito($dependencia, "Dependencia encontrada");
            } else {
                RespuestaHelper::error("Dependencia no encontrada", 404);
            }
        } catch (Exception $e) {
            error_log("Error en ControladorDependencias::ctrObtener: " . $e->getMessage());
            RespuestaHelper::error("Error interno al obtener los datos de la dependencia", 500);
        }
    }

    /*=============================================
    API REST: CREAR DEPENDENCIA (POST /api/dependencias)
    =============================================*/
    public static function ctrCrear(array $datos): void {
        $nombre        = trim($datos["nombre"] ?? "");
        $codigo        = strtoupper(trim($datos["codigo"] ?? $datos["dependencia"] ?? ""));
        $descripcion   = trim($datos["descripcion"] ?? "");
        $tipo          = strtolower(trim($datos["tipo"] ?? "operativa"));
        $estadoRaw     = trim($datos["estado"] ?? "Activo");
        $estado        = in_array($estadoRaw, ["Activo", "Activa"], true) ? "Activo" : "Inactivo";
        $idResponsable = !empty($datos["id_usuario_responsable"]) ? (int)$datos["id_usuario_responsable"] : null;

        if (empty($nombre) || empty($codigo)) {
            RespuestaHelper::error("El nombre y el código/sigla de la dependencia son obligatorios", 400);
        }

        if (!in_array($tipo, ["directiva", "administrativa", "operativa"], true)) {
            $tipo = "operativa";
        }

        $existeNombre = ModeloDependencias::mdlMostrarDependencias("dependencias", "nombre", $nombre);
        if ($existeNombre) {
            RespuestaHelper::error("Ya existe una dependencia registrada con ese nombre", 409);
        }

        $existeCodigo = ModeloDependencias::mdlMostrarDependencias("dependencias", "codigo", $codigo);
        if ($existeCodigo) {
            RespuestaHelper::error("El código o sigla ya se encuentra asignado a otra dependencia", 409);
        }

        $idNuevo = ModeloDependencias::mdlCrearDependencia([
            "nombre"                 => $nombre,
            "codigo"                 => $codigo,
            "dependencia"            => $nombre,
            "descripcion"            => $descripcion !== "" ? $descripcion : ("Unidad organizacional: " . $nombre),
            "tipo"                   => $tipo,
            "estado"                 => $estado,
            "id_usuario_responsable" => $idResponsable
        ]);

        if ($idNuevo) {
            RespuestaHelper::exito(["id_dependencia" => $idNuevo], "Dependencia creada exitosamente", 201);
        } else {
            RespuestaHelper::error("No fue posible registrar la dependencia en el sistema", 500);
        }
    }

    /*=============================================
    API REST: ACTUALIZAR DEPENDENCIA (PUT /api/dependencias/{id})
    =============================================*/
    public static function ctrActualizar(int $id, array $datos): void {
        $existente = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $id);
        if (!$existente) {
            RespuestaHelper::error("La dependencia que intenta actualizar no existe", 404);
        }

        $nombre        = trim($datos["nombre"] ?? "");
        $codigo        = strtoupper(trim($datos["codigo"] ?? $existente["codigo"] ?? ""));
        $descripcion   = trim($datos["descripcion"] ?? $existente["descripcion"] ?? "");
        $tipo          = strtolower(trim($datos["tipo"] ?? $existente["tipo"] ?? "operativa"));
        $estadoRaw     = trim($datos["estado"] ?? $existente["estado"] ?? "Activo");
        $estado        = in_array($estadoRaw, ["Activo", "Activa"], true) ? "Activo" : "Inactivo";

        $idResponsable = null;
        if (isset($datos["id_usuario_responsable"]) && $datos["id_usuario_responsable"] !== "" && $datos["id_usuario_responsable"] !== null) {
            $idResponsable = (int)$datos["id_usuario_responsable"];
        }

        if (empty($nombre) || empty($codigo)) {
            RespuestaHelper::error("El nombre y el código/sigla son obligatorios para actualizar", 400);
        }

        $otraNombre = ModeloDependencias::mdlMostrarDependencias("dependencias", "nombre", $nombre);
        if ($otraNombre && (int)$otraNombre["id_dependencia"] !== $id) {
            RespuestaHelper::error("Ya existe otra dependencia con ese nombre", 409);
        }

        $otraCodigo = ModeloDependencias::mdlMostrarDependencias("dependencias", "codigo", $codigo);
        if ($otraCodigo && (int)$otraCodigo["id_dependencia"] !== $id) {
            RespuestaHelper::error("El código o sigla ya está en uso por otra dependencia", 409);
        }

        $actualizado = ModeloDependencias::mdlEditarDependencia($id, [
            "nombre"                 => $nombre,
            "codigo"                 => $codigo,
            "dependencia"            => $nombre,
            "descripcion"            => $descripcion !== "" ? $descripcion : ("Unidad organizacional: " . $nombre),
            "tipo"                   => $tipo,
            "estado"                 => $estado,
            "id_usuario_responsable" => $idResponsable
        ]);

        if ($actualizado) {
            RespuestaHelper::exito(["id_dependencia" => $id], "Dependencia actualizada correctamente", 200);
        } else {
            RespuestaHelper::error("No fue posible actualizar la información de la dependencia", 500);
        }
    }

    /*=============================================
    API REST: CAMBIAR ESTADO (PATCH /api/dependencias/{id}/estado)
    =============================================*/
    public static function ctrCambiarEstado(int $id, string $nuevoEstado): void {
        $estadoNormalizado = (strtolower(trim($nuevoEstado)) === "inactivo") ? "Inactivo" : "Activo";

        $existente = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $id);
        if (!$existente) {
            RespuestaHelper::error("La dependencia solicitada no existe", 404);
        }

        $actualizado = ModeloDependencias::mdlActualizarEstado($id, $estadoNormalizado);
        if ($actualizado) {
            RespuestaHelper::exito([
                "id_dependencia" => $id,
                "estado"         => $estadoNormalizado
            ], "Estado de dependencia actualizado a {$estadoNormalizado}");
        } else {
            RespuestaHelper::error("No fue posible cambiar el estado de la dependencia", 500);
        }
    }

    /*=============================================
    API REST: ELIMINAR DEPENDENCIA (DELETE /api/dependencias/{id})
    =============================================*/
    public static function ctrEliminar(int $id): void {
        $existente = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $id);
        if (!$existente) {
            RespuestaHelper::error("La dependencia que intenta eliminar no existe", 404);
        }

        $resultado = ModeloDependencias::mdlEliminarDependencia($id);

        if ($resultado === "ok") {
            RespuestaHelper::exito(["id_dependencia" => $id], "Dependencia eliminada del sistema");
        } elseif ($resultado === "en_uso") {
            RespuestaHelper::error("No se puede eliminar la dependencia porque tiene usuarios o radicados vinculados", 409);
        } else {
            RespuestaHelper::error("Error interno al intentar eliminar la dependencia", 500);
        }
    }

    /**
     * Método retrocompatible para llamadas PHP directas desde vistas antiguas.
     */
    public static function ctrMostrarDependencias($tabla = "dependencias") {
        return ModeloDependencias::mdlMostrarDependencias($tabla);
    }
}
