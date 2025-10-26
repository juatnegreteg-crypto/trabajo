<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Almacén JSON con bloqueo de archivo (flock) para evitar carreras.
 * Estructura: [ cbi => ["version"=>int,"networks"=>[],"updated_at"=>iso8601], "*" => ... ]
 */
class WifiStoreService
{
    protected string $path;

    public function __construct()
    {
        // Ruta configurable por .env (default storage/app/wifi_store.json)
        $this->path = env('WIFI_STORE_PATH', 'storage/app/wifi_store.json');
    }

    protected function load(): array
    {
        $full = base_path($this->path);
        if (!file_exists($full)) {
            return [];
        }
        $fp = fopen($full, 'r');
        if (!$fp) return [];
        try {
            // Bloqueo compartido para lectura
            flock($fp, LOCK_SH);
            $json = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
        $data = json_decode($json ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    protected function save(array $data): void
    {
        $full = base_path($this->path);
        $dir = dirname($full);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fp = fopen($full, 'c+'); // create if not exists
        if (!$fp) throw new \RuntimeException('No se pudo abrir el store');
        try {
            // Bloqueo exclusivo para escribir
            flock($fp, LOCK_EX);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    public function getEntry(string $cbi): ?array
    {
        $all = $this->load();
        return $all[$cbi] ?? null;
    }

    public function getAll(): array
    {
        return $this->load();
    }

    /**
     * Inserta/actualiza redes para un CBI (o "*" global).
     * - replace=true: reemplaza lista completa
     * - replace=false: merge por SSID (lo nuevo pisa lo viejo)
     * Incrementa version y actualiza updated_at.
     */
    public function putEntry(string $cbi, array $networks, bool $replace = true): array
    {
        $all = $this->load();
        $entry = $all[$cbi] ?? ["version" => 0, "networks" => [], "updated_at" => ""];

        if ($replace) {
            $entry['networks'] = $networks;
        } else {
            // merge por SSID
            $merged = [];
            foreach (($entry['networks'] ?? []) as $n) {
                if (!empty($n['ssid'])) $merged[$n['ssid']] = $n;
            }
            foreach ($networks as $n) {
                if (!empty($n['ssid'])) $merged[$n['ssid']] = $n;
            }
            $entry['networks'] = array_values($merged);
        }

        $entry['version'] = (int)($entry['version'] ?? 0) + 1;
        $entry['updated_at'] = gmdate('c');

        $all[$cbi] = $entry;
        $this->save($all);
        return $entry;
    }

    /**
     * Asegura que la versión de "*" sea >= max(otras)+1
     */
    public function bumpGlobalIfNeeded(array $networks): array
    {
        $all = $this->load();
        $global = $all['*'] ?? ["version" => 0, "networks" => [], "updated_at" => ""];
        // primero guardamos (sube versión en +1)
        $global = $this->putEntry('*', $networks, true);

        $all = $this->load(); // recarga
        $maxOther = 0;
        foreach ($all as $k => $v) {
            if ($k === '*') continue;
            $maxOther = max($maxOther, (int)($v['version'] ?? 0));
        }
        if (($global['version'] ?? 0) <= $maxOther) {
            // subimos iterativamente hasta superar
            $target = $maxOther + 1;
            while ($global['version'] < $target) {
                $global = $this->putEntry('*', $networks, true);
            }
        }
        return $global;
    }
}