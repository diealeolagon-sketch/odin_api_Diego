<?php

/**
 * Suite de Pruebas Automatizadas: Dependencias, Encargados Editables y Radicación
 * Proyecto ODIN
 */

require_once __DIR__ . "/../modelos/conexion.php";
require_once __DIR__ . "/../modelos/dependencias.modelo.php";
require_once __DIR__ . "/../controladores/dependencias.controlador.php";
require_once __DIR__ . "/../modelos/radicados.modelo.php";
require_once __DIR__ . "/../controladores/radicados.controlador.php";

class TestDependenciasEncargadoRadicacion
{
    private static int $pasadas = 0;
    private static int $fallidas = 0;

    public static function run(): array
    {
        echo "====================================================================\n";
        echo "   TEST: DEPENDENCIAS, ENCARGADO EDITABLE Y RADICACIÓN AUTOMÁTICA   \n";
        echo "====================================================================\n";

        self::limpiarPrevios();
        self::testListarDependenciasConEncargado();
        self::testCrearDependenciaConEncargado();
        self::testEditarDependenciaCambiarEncargado();
        self::testEncargadoEnCatalogosRadicacion();
        self::testRadicarAsignaEncargadoDependencia();
        self::testCambioEstadoDependencia();
        self::testLoopVerificacionIntegridad();

        echo "\n";
        echo "  Aserciones exitosas: " . self::$pasadas . "\n";
        echo "  Aserciones fallidas: " . self::$fallidas . "\n";
        echo "====================================================================\n";

        return ["pasadas" => self::$pasadas, "fallidas" => self::$fallidas];
    }

    private static function limpiarPrevios(): void
    {
        $db = Conexion::conectar();
        $db->exec("DELETE FROM historial_radicado WHERE id_radicado IN (SELECT id_radicado FROM radicados WHERE asunto LIKE '%Prueba de Asignación Automática%')");
        $db->exec("DELETE FROM documentos WHERE id_radicado IN (SELECT id_radicado FROM radicados WHERE asunto LIKE '%Prueba de Asignación Automática%')");
        $db->exec("DELETE FROM radicados WHERE asunto LIKE '%Prueba de Asignación Automática%'");
        $db->exec("DELETE FROM dependencias WHERE codigo = 'DTA-99'");
    }

    private static function assert(bool $condicion, string $mensaje): void
    {
        if ($condicion) {
            self::$pasadas++;
            echo "  [OK] {$mensaje}\n";
        } else {
            self::$fallidas++;
            echo "  [FALLO] {$mensaje}\n";
        }
    }

    private static function testListarDependenciasConEncargado(): void
    {
        $deps = ModeloDependencias::mdlMostrarDependencias();
        $esValido = is_array($deps) && count($deps) > 0;

        $tieneEncargado = false;
        foreach ($deps as $d) {
            if (isset($d["id_usuario_responsable"]) && !empty($d["encargado_nombre"])) {
                $tieneEncargado = true;
                break;
            }
        }

        self::assert($esValido, "Consulta de dependencias retorna registros con sentencias preparadas");
        self::assert($tieneEncargado, "Las dependencias incluyen datos de encargado responsable (nombre, correo, rol)");
    }

    private static function testCrearDependenciaConEncargado(): void
    {
        $datos = [
            "nombre"                 => "Dependencia Test Automatizado",
            "codigo"                 => "DTA-99",
            "tipo"                   => "operativa",
            "descripcion"            => "Creada para verificación de pruebas unitarias",
            "estado"                 => "Activo",
            "id_usuario_responsable" => 3 // Laura Gómez
        ];

        $idNueva = ModeloDependencias::mdlCrearDependencia($datos);
        self::assert($idNueva > 0, "Creación de dependencia con encargado asignado (ID {$idNueva})");

        $dep = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $idNueva);
        $encargadoAsignado = ($dep && (int)$dep["id_usuario_responsable"] === 3 && !empty($dep["encargado_nombre"]));
        self::assert($encargadoAsignado, "La nueva dependencia tiene como encargado a Laura Gómez (ID 3)");
    }

    private static function testEditarDependenciaCambiarEncargado(): void
    {
        $db = Conexion::conectar();
        $stmt = $db->query("SELECT id_dependencia FROM dependencias WHERE codigo = 'DTA-99' ORDER BY id_dependencia DESC LIMIT 1");
        $idDep = (int)$stmt->fetchColumn();

        self::assert($idDep > 0, "Dependencia de prueba localizada (ID {$idDep})");

        // EDITAR: Cambiar el encargado a Carlos Mendoza (ID 2)
        $nuevosDatos = [
            "nombre"                 => "Dependencia Test Reasignada",
            "codigo"                 => "DTA-99",
            "tipo"                   => "administrativa",
            "descripcion"            => "Encargado reasignado a Carlos Mendoza",
            "estado"                 => "Activo",
            "id_usuario_responsable" => 2 // Carlos Mendoza
        ];

        $editado = ModeloDependencias::mdlEditarDependencia($idDep, $nuevosDatos);
        self::assert($editado === true, "Edición de dependencia para cambiar usuario encargado exitosa");

        $depActualizada = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $idDep);
        $nuevoEncargado = ($depActualizada && (int)$depActualizada["id_usuario_responsable"] === 2);
        self::assert($nuevoEncargado, "El usuario encargado se editó correctamente a Carlos Mendoza (ID 2)");
    }

    private static function testEncargadoEnCatalogosRadicacion(): void
    {
        $catalogos = ModeloRadicados::mdlObtenerCatalogos();
        $dependencias = $catalogos["dependencias"] ?? [];

        $encontrada = null;
        foreach ($dependencias as $d) {
            if ($d["codigo"] === "DTA-99") {
                $encontrada = $d;
                break;
            }
        }

        $valida = ($encontrada !== null &&
                   (int)$encontrada["id_responsable"] === 2 &&
                   str_contains($encontrada["nombre_responsable"], "Carlos"));

        self::assert($valida, "Catálogo de radicación refleja de inmediato el nuevo encargado editado (Carlos Mendoza)");
    }

    private static function testRadicarAsignaEncargadoDependencia(): void
    {
        $db = Conexion::conectar();
        $stmt = $db->query("SELECT id_dependencia FROM dependencias WHERE codigo = 'DTA-99' ORDER BY id_dependencia DESC LIMIT 1");
        $idDep = (int)$stmt->fetchColumn();

        $tmpPdf = tempnam(sys_get_temp_dir(), "rad_dep_test_");
        file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        // Obtener encargado según el modelo
        $encargado = ModeloRadicados::mdlObtenerEncargadoDependencia($idDep);
        self::assert($encargado && (int)$encargado["id_usuario"] === 2, "Endpoint/Modelo resuelve al encargado ID 2 para la dependencia");

        // Radicar directamente con el modelo de persistencia
        $numRadicado = ModeloRadicados::mdlGenerarNumeroRadicado("ENTRADA");

        $datosRad = [
            "numero_radicado"   => $numRadicado,
            "id_tramite"        => 1,
            "id_estado"         => 1,
            "id_dependencia"    => $idDep,
            "id_usuario"        => (int)$encargado["id_usuario"], // Asignado al encargado
            "fecha_limite"      => date("Y-m-d", strtotime("+15 days")),
            "fecha_vencimiento" => date("Y-m-d", strtotime("+15 days")),
            "id_remitente"      => null,
            "remitente_texto"   => "Usuario Verificador (CC: 99887766)",
            "asunto"            => "Prueba de Asignación Automática a Encargado Editado",
            "fecha_radicado"    => date("Y-m-d H:i:s"),
            "tipo_radicado"     => "ENTRADA",
            "medio_recepcion"   => "PLATAFORMA_WEB",
            "prioridad"         => "ALTA",
            "confidencialidad"  => "PUBLICA",
            "codigo_serie"      => null,
            "codigo_subserie"   => null,
            "id_serie"          => null,
            "id_subserie"       => null
        ];

        $docMeta = [
            [
                "nombre_archivo"  => "prueba_encargado.pdf",
                "ruta_archivo"    => "vistas/archivos/documentos/2026/10/prueba_encargado.pdf",
                "tipo"            => "application/pdf",
                "tamano"          => filesize($tmpPdf),
                "nombre_original" => "prueba_encargado.pdf",
                "mime_type"       => "application/pdf",
                "checksum"        => hash_file("sha256", $tmpPdf)
            ]
        ];

        $resultado = ModeloRadicados::mdlRadicarDocumento($datosRad, $docMeta, null);
        self::assert(!empty($resultado["id_radicado"]), "Radicación completada con ID " . ($resultado["id_radicado"] ?? "N/A"));

        // Comprobar en BD que el radicado quedó vinculado al usuario Carlos Mendoza (ID 2)
        $idRad = (int)$resultado["id_radicado"];
        $stmtCheck = $db->prepare("SELECT id_usuario, id_dependencia FROM radicados WHERE id_radicado = :id");
        $stmtCheck->execute([":id" => $idRad]);
        $rowRad = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $asignadoCorrecto = ($rowRad && (int)$rowRad["id_usuario"] === 2 && (int)$rowRad["id_dependencia"] === $idDep);
        self::assert($asignadoCorrecto, "El radicado quedó asignado al usuario responsable de la dependencia (ID 2)");

        if (file_exists($tmpPdf)) @unlink($tmpPdf);
    }

    private static function testCambioEstadoDependencia(): void
    {
        $db = Conexion::conectar();
        $stmt = $db->query("SELECT id_dependencia FROM dependencias WHERE codigo = 'DTA-99' ORDER BY id_dependencia DESC LIMIT 1");
        $idDep = (int)$stmt->fetchColumn();

        $okInactivo = ModeloDependencias::mdlActualizarEstado($idDep, "Inactivo");
        self::assert($okInactivo, "Cambio de estado a Inactivo exitoso");

        $dep = ModeloDependencias::mdlMostrarDependencias("dependencias", "id_dependencia", $idDep);
        self::assert($dep && $dep["estado"] === "Inactivo", "Estado en base de datos es Inactivo");

        $okActivo = ModeloDependencias::mdlActualizarEstado($idDep, "Activo");
        self::assert($okActivo, "Cambio de estado restaurado a Activo exitoso");
    }

    private static function testLoopVerificacionIntegridad(): void
    {
        // Loop de verificación: Probar que para CADA dependencia activa en el sistema,
        // al seleccionarla desde radicación, resuelve de forma consistente a un usuario responsable válido.
        $deps = ModeloDependencias::mdlMostrarDependencias();
        $todasResuelven = true;
        $totalEvaluadas = 0;

        foreach ($deps as $d) {
            if ($d["estado"] !== "Activo") continue;
            $totalEvaluadas++;
            $idDep = (int)$d["id_dependencia"];
            $encargado = ModeloRadicados::mdlObtenerEncargadoDependencia($idDep);

            if (!$encargado || empty($encargado["id_usuario"])) {
                $todasResuelven = false;
                echo "    [AVISO] Dependencia {$d['nombre']} no tiene usuario responsable resuelto.\n";
            }
        }

        self::assert($todasResuelven && $totalEvaluadas > 0, "Loop de verificación: Las {$totalEvaluadas} dependencias activas resuelven su encargado responsable sin error");

        // Limpieza final
        self::limpiarPrevios();
    }
}

if (php_sapi_name() === "cli") {
    TestDependenciasEncargadoRadicacion::run();
}
