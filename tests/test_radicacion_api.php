<?php

require_once __DIR__ . "/../controladores/radicados.controlador.php";

class TestRadicacionApi
{
    private static int $pasadas = 0;
    private static int $fallidas = 0;

    public static function run(): array
    {
        echo "=== [TEST] Capa de Controlador RESTful de Radicación ===\n";

        self::testCatalogosEndpoint();
        self::testValidacionAsuntoRequerido();
        self::testValidacionAdjuntoRequerido();
        self::testBuscarRemitenteVacio();
        self::testObtenerEncargadoDependencia();
        self::testRadicarExitosoConDocumento();

        echo "\n";
        return ["pasadas" => self::$pasadas, "fallidas" => self::$fallidas];
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

    private static function ejecutarWorker(string $caso, string $extra = ""): string
    {
        $archivoActual = escapeshellarg(__FILE__);
        $phpBin = defined('PHP_BINARY') && PHP_BINARY ? escapeshellarg(PHP_BINARY) : 'php';
        $cmd = "{$phpBin} {$archivoActual} --worker={$caso} {$extra}";
        return (string)shell_exec($cmd);
    }

    private static function testCatalogosEndpoint(): void
    {
        $salida = self::ejecutarWorker("catalogos");
        $json = json_decode(trim($salida), true);

        $esValido = is_array($json) &&
                    ($json["status"] ?? 0) === 200 &&
                    isset($json["data"]["tramites"]) &&
                    isset($json["data"]["dependencias"]) &&
                    isset($json["data"]["estados"]) &&
                    isset($json["data"]["series"]) &&
                    isset($json["data"]["subseries"]);

        self::assert(
            $esValido,
            "Endpoint de Catálogos retorna HTTP 200 con trámites, dependencias, estados, series y subseries en formato JSON estándar"
        );
    }

    private static function testValidacionAsuntoRequerido(): void
    {
        $salida = self::ejecutarWorker("asunto_req");
        $json = json_decode(trim($salida), true);

        $rechazado = is_array($json) &&
                     ($json["status"] ?? 0) === 400 &&
                     str_contains($json["message"] ?? "", "asunto");

        self::assert(
            $rechazado,
            "Petición sin asunto rechazada con código HTTP 400 y mensaje semántico"
        );
    }

    private static function testValidacionAdjuntoRequerido(): void
    {
        $salida = self::ejecutarWorker("adjunto_req");
        $json = json_decode(trim($salida), true);

        $rechazado = is_array($json) &&
                     ($json["status"] ?? 0) === 400 &&
                     str_contains($json["message"] ?? "", "adjuntar");

        self::assert(
            $rechazado,
            "Petición sin documentos adjuntos rechazada con código HTTP 400"
        );
    }

    private static function testBuscarRemitenteVacio(): void
    {
        $salida = self::ejecutarWorker("remitente_vacio");
        $json = json_decode(trim($salida), true);

        $rechazado = is_array($json) &&
                     ($json["status"] ?? 0) === 400;

        self::assert(
            $rechazado,
            "Consulta de remitente sin número de documento rechazada con HTTP 400"
        );
    }

    private static function testObtenerEncargadoDependencia(): void
    {
        $salida = self::ejecutarWorker("encargado_dep", "1");
        $json = json_decode(trim($salida), true);

        $esValido = is_array($json) && in_array($json["status"] ?? 0, [200, 404, 500], true);

        self::assert(
            $esValido,
            "Endpoint de consulta de encargado por dependencia emite respuesta JSON estructurada"
        );
    }

    private static function testRadicarExitosoConDocumento(): void
    {
        $tmpPdf = tempnam(sys_get_temp_dir(), "api_pdf_test_");
        $contenidoPdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF";
        file_put_contents($tmpPdf, $contenidoPdf);

        $salida = self::ejecutarWorker("radicar_ok", escapeshellarg($tmpPdf));
        $json = json_decode(trim($salida), true);

        $exito = is_array($json) &&
                 ($json["status"] ?? 0) === 201 &&
                 isset($json["data"]["numero_radicado"]) &&
                 str_starts_with($json["data"]["numero_radicado"], "E-");

        self::assert(
            $exito,
            "Radicación completa vía Controlador REST exitosa con código HTTP 201 y número: " . ($json["data"]["numero_radicado"] ?? "N/A")
        );

        if (file_exists($tmpPdf)) @unlink($tmpPdf);
    }

    /**
     * Dispatcher interno para subprocesos de prueba aislados
     */
    public static function despacharWorker(string $worker, array $args): void
    {
        switch ($worker) {
            case "catalogos":
                ControladorRadicados::ctrCatalogos();
                break;

            case "remitente_vacio":
                ControladorRadicados::ctrBuscarRemitente("");
                break;

            case "encargado_dep":
                $idDep = isset($args[2]) ? (int)$args[2] : 1;
                ControladorRadicados::ctrObtenerResponsableDependencia($idDep);
                break;

            case "asunto_req":
                $post = ["asunto" => "", "tipoRadicacion" => "entrada"];
                ControladorRadicados::ctrRadicar($post, []);
                break;

            case "adjunto_req":
                $post = [
                    "asunto" => "Solicitud válida",
                    "tipoRadicacion" => "entrada",
                    "nombreSolicitante" => "Ana Torres",
                    "numeroDocumento" => "102030"
                ];
                ControladorRadicados::ctrRadicar($post, []);
                break;

            case "radicar_ok":
                $tmpPdf = $args[2] ?? "";
                $post = [
                    "asuntoDocumento"   => "Petición de Información Pública - Test API",
                    "tipoRadicacion"    => "entrada",
                    "canalRecepcion"    => "plataforma_web",
                    "prioridad"         => "media",
                    "tramite"           => 1,
                    "estadoInicial"     => 1,
                    "dependenciaDestino"=> 1,
                    "tipoDocumento"     => "cc",
                    "numeroDocumento"   => "1020304050",
                    "nombreSolicitante" => "Ana Maria Torres",
                    "correoSolicitante" => "ana.torres@example.com"
                ];
                $files = [
                    "adjuntos" => [
                        "name"     => ["peticion_firmada.pdf"],
                        "type"     => ["application/pdf"],
                        "tmp_name" => [$tmpPdf],
                        "error"    => [UPLOAD_ERR_OK],
                        "size"     => [file_exists($tmpPdf) ? filesize($tmpPdf) : 100]
                    ]
                ];
                ControladorRadicados::ctrRadicar($post, $files);
                break;
        }
        exit;
    }
}

// Punto de entrada CLI
if (php_sapi_name() === "cli" && basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"] ?? "")) {
    $modoWorker = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--worker=")) {
            $modoWorker = substr($arg, 9);
            break;
        }
    }

    if ($modoWorker) {
        TestRadicacionApi::despacharWorker($modoWorker, $argv);
    } else {
        TestRadicacionApi::run();
    }
}
