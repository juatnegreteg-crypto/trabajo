<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\WifiStoreService;

/**
 * Endpoints:
 * - POST /api/wifi/put       (por CBI)
 * - GET  /api/wifi/get?cbi=...
 * - POST /api/wifi/put_all   (broadcast "*")
 */
class WifiController extends Controller
{
    protected WifiStoreService $store;

    public function __construct(WifiStoreService $store)
    {
        $this->store = $store;
    }

    protected function normalizeNetworks(array $nets): array
    {
        $cleaned = [];
        foreach ($nets as $n) {
            $ssid = trim((string)($n['ssid'] ?? ''));
            if ($ssid === '') continue;
            $password = (string)($n['password'] ?? '');
            $priority = (int)($n['priority'] ?? 0);
            $enabled = (bool)($n['enabled'] ?? true);
            $cleaned[] = compact('ssid','password','priority','enabled');
        }
        return $cleaned;
    }

    public function put(Request $req): JsonResponse
    {
        $cbi = (string)$req->input('CBI', '');
        $nets = $req->input('networks', []);
        $replace = (bool)$req->input('replace', true);

        if ($cbi === '' || !is_array($nets) || count($nets) === 0) {
            return response()->json(['error' => 'CBI o networks inválidos'], 400);
        }
        $cleaned = $this->normalizeNetworks($nets);
        if (count($cleaned) === 0) {
            return response()->json(['error' => 'Lista de networks vacía tras limpieza'], 400);
        }
        $entry = $this->store->putEntry($cbi, $cleaned, $replace);
        return response()->json(['status' => 'ok', 'version' => $entry['version']], 200);
    }

    public function putAll(Request $req): JsonResponse
    {
        $nets = $req->input('networks', []);
        $replace = (bool)$req->input('replace', true); // replace no se usa directamente, siempre sobrescribimos global

        if (!is_array($nets) || count($nets) === 0) {
            return response()->json(['error' => 'networks inválidos'], 400);
        }
        $cleaned = $this->normalizeNetworks($nets);
        if (count($cleaned) === 0) {
            return response()->json(['error' => 'Lista de networks vacía tras limpieza'], 400);
        }
        $entry = $this->store->bumpGlobalIfNeeded($cleaned);
        return response()->json(['status' => 'ok', 'version' => (int)$entry['version']], 200);
    }

    public function get(Request $req): JsonResponse
    {
        $cbi = trim((string)$req->query('cbi', ''));
        if ($cbi === '') {
            return response()->json(['error' => 'cbi requerido'], 400);
        }
        $entry = $this->store->getEntry($cbi);
        if (!$entry) {
            return response()->json(['version' => 0, 'networks' => []], 200);
        }
        return response()->json($entry, 200);
    }
}