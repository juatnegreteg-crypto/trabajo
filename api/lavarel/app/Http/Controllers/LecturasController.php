<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Services\WifiStoreService;

/**
 * - POST /api/lecturas (form-data 'body' = JSON string)
 *   Devuelve {"status":"ok"} y, si aplica, {"wifi_update":{version,networks}}
 * - GET /api/lecturas (opcional) devuelve últimas 20 lecturas en JSON para debug
 */
class LecturasController extends Controller
{
    protected WifiStoreService $store;
    protected string $cacheKey = 'lecturas_recent';
    protected int $maxLecturas = 20;

    public function __construct(WifiStoreService $store)
    {
        $this->store = $store;
    }

    public function postLecturas(Request $req): JsonResponse
    {
        $body = $req->input('body'); // viene como form-data string
        if (!$body || !is_string($body)) {
            return response()->json(['error' => 'No hay datos'], 400);
        }
        $doc = json_decode($body, true);
        if (!is_array($doc)) {
            return response()->json(['error' => 'JSON inválido'], 400);
        }

        // Guardamos campos relevantes en cache (circular de 20)
        $lectura = [
            'hora' => gmdate('Y-m-d H:i:s'),
            'CBI' => $doc['CBI'] ?? 'N/A',
            'estado' => $doc['estado'] ?? 'N/A',
            'L1_voltaje' => $doc['unidad_exterior']['voltaje'] ?? 0,
            'L1_corriente' => $doc['unidad_exterior']['corriente'] ?? 0,
            'L1_potencia' => $doc['unidad_exterior']['potencia'] ?? 0,
            'L2_voltaje' => $doc['unidad_interior']['voltaje'] ?? 0,
            'L2_corriente' => $doc['unidad_interior']['corriente'] ?? 0,
            'L2_potencia' => $doc['unidad_interior']['potencia'] ?? 0,
            'temp_ds18b20' => $doc['unidad_interior']['temperatura_ds18b20'] ?? 0,
            'temp_amb' => $doc['ambiente']['temperatura'] ?? 0,
            'humedad' => $doc['ambiente']['humedad'] ?? 0,
            'alerta' => $doc['alerta'] ?? '-',
            'SSID' => $doc['Conexion']['SSID'] ?? 'N/A',
            'IP' => $doc['Conexion']['IP'] ?? 'N/A',
            'MAC' => $doc['Conexion']['MAC'] ?? 'N/A',
        ];
        $list = Cache::get($this->cacheKey, []);
        $list[] = $lectura;
        if (count($list) > $this->maxLecturas) {
            $list = array_slice($list, -$this->maxLecturas);
        }
        Cache::put($this->cacheKey, $list, 3600);

        // Lógica de wifi_update: comparar versión del dispositivo con servidor (global y CBI)
        $resp = ['status' => 'ok'];
        $deviceVer = (int)($doc['wifi_config_version'] ?? 0);
        $bestEntry = null;
        $bestVer = -1;

        // Global "*"
        $broadcast = $this->store->getEntry('*');
        if (is_array($broadcast)) {
            $bver = (int)($broadcast['version'] ?? 0);
            if ($bver > $deviceVer && $bver > $bestVer) {
                $bestEntry = $broadcast;
                $bestVer = $bver;
            }
        }
        // Específica por CBI
        $cbi = $doc['CBI'] ?? null;
        if ($cbi) {
            $entry = $this->store->getEntry($cbi);
            if (is_array($entry)) {
                $serverVer = (int)($entry['version'] ?? 0);
                if ($serverVer > $deviceVer && $serverVer > $bestVer) {
                    $bestEntry = $entry;
                    $bestVer = $serverVer;
                }
            }
        }

        if ($bestEntry) {
            $resp['wifi_update'] = [
                'version' => (int)($bestEntry['version'] ?? 0),
                'networks' => $bestEntry['networks'] ?? [],
            ];
        }

        return response()->json($resp, 200);
    }

    public function getLecturas(): JsonResponse
    {
        // Retorna las últimas lecturas (debug / monitoreo simple)
        $list = Cache::get($this->cacheKey, []);
        return response()->json(['lecturas' => $list], 200);
    }
}