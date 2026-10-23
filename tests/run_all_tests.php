<?php

/**
 * Suite Unificada de Pruebas Automatizadas y de Regresión Continua — Proyecto ODIN
 * 
 * Ejecuta la verificación integral de:
 * 1. Servicio de Archivos y Validación Binaria MIME (Anti-suplantación y límites)
 * 2. Modelo de Persistencia de Radicación en MySQL Local (Consecutivos, transacciones)
 * 3. Controlador RESTful de Radicación (Códigos HTTP, payloads y respuestas JSON)
 * 4. Módulo de Dependencias y Encargados Responsables (Persistencia, edición y radicación)
 * 5. Módulo de Consulta, Seguimiento y Trazabilidad (AGN / Ley 594 de 2000)
 * 
 * Uso:
 *   php tests/run_all_tests.php
 */

require_once __DIR__ . "/test_archivo_servicio.php";
require_once __DIR__ . "/test_radicacion_modelo.php";
require_once __DIR__ . "/test_radicacion_api.php";
require_once __DIR__ . "/test_dependencias_encargado_radicacion.php";
require_once __DIR__ . "/test_modulo_seguimiento_completo.php";

echo "====================================================================\n";
echo "   ODIN GESTOR DOCUMENTAL — SUITE DE PRUEBAS AUTOMATIZADAS          \n";
echo "====================================================================\n";
echo "Fecha y hora de ejecución: " . date("Y-m-d H:i:s") . "\n\n";

$inicio = microtime(true);
$totalPasadas = 0;
$totalFallidas = 0;

// 1. Suite de Archivos y MIME
$r1 = TestArchivoServicio::run();
$totalPasadas  += $r1["pasadas"];
$totalFallidas += $r1["fallidas"];

// 2. Suite de Modelo y Persistencia MySQL
$r2 = TestRadicacionModelo::run();
$totalPasadas  += $r2["pasadas"];
$totalFallidas += $r2["fallidas"];

// 3. Suite de Controlador RESTful
$r3 = TestRadicacionApi::run();
$totalPasadas  += $r3["pasadas"];
$totalFallidas += $r3["fallidas"];

// 4. Suite de Dependencias y Encargados Responsables
$r4 = TestDependenciasEncargadoRadicacion::run();
$totalPasadas  += $r4["pasadas"];
$totalFallidas += $r4["fallidas"];

// 5. Suite de Seguimiento y Trazabilidad
$r5 = TestSeguimientoCompleto::run();
$totalPasadas  += $r5["pasadas"];
$totalFallidas += $r5["fallidas"];

$duracion = round(microtime(true) - $inicio, 2);

echo "====================================================================\n";
echo "   RESUMEN FINAL DE LA SUITE DE REGRESIÓN                          \n";
echo "====================================================================\n";
echo "  Total de aserciones ejecutadas : " . ($totalPasadas + $totalFallidas) . "\n";
echo "  Aserciones exitosas (PASS)     : {$totalPasadas}\n";
echo "  Aserciones fallidas (FAIL)     : {$totalFallidas}\n";
echo "  Tiempo de ejecución            : {$duracion} segundos\n";
echo "====================================================================\n";

if ($totalFallidas === 0) {
    echo "  RESULTADO: [EXITOSO] Todos los componentes funcionan correctamente.\n";
    echo "====================================================================\n\n";
    exit(0);
} else {
    echo "  RESULTADO: [FALLO] Se detectaron errores de regresión en las pruebas.\n";
    echo "====================================================================\n\n";
    exit(1);
}
