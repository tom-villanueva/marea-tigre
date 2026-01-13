<?php

require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/DataManager.php';

/**
 * SanFernandoService - Handles San Fernando river height and tendency
 * 
 * API Endpoints:
 * - /api/altura_sf - Current height with tendency
 * - /api/tendencia_sf - Just tendency info
 * 
 * Source: https://www.hidro.gob.ar/rss/AHrss.asp
 */
class SanFernandoService
{
    private const RSS_URL = 'https://www.hidro.gob.ar/rss/AHrss.asp';
    private const MAX_HISTORY_RECORDS = 72;
    
    // Tendency threshold in meters (2cm)
    private const TENDENCY_THRESHOLD = 0.02;
    
    private HttpClient $http;
    private DataManager $data;
    
    // Cache
    private static array $cache = [];
    private const CACHE_DURATION = 600;
    private const CACHE_STALE_MAX = 1800;

    public function __construct(?HttpClient $http = null, ?DataManager $data = null)
    {
        $this->http = $http ?? new HttpClient();
        $this->data = $data ?? new DataManager();
    }

    /**
     * Get current height at San Fernando with tendency
     * 
     * Matches Python's api_altura_sf()
     * 
     * @return array Height data with tendency
     */
    public function getHeight(): array
    {
        $cacheKey = self::RSS_URL;
        $now = time();
        
        // Check cache
        if (isset(self::$cache[$cacheKey])) {
            [$cachedData, $cachedTime] = self::$cache[$cacheKey];
            $age = $now - $cachedTime;
            
            if ($age < self::CACHE_DURATION) {
                error_log("✓ Using fresh SF height cache ({$age}s old)");
                return $cachedData;
            }
            
            if ($age < self::CACHE_STALE_MAX) {
                error_log("⚠️ Using stale SF height cache ({$age}s old)");
                return $cachedData;
            }
        }
        
        return $this->fetchHeight($cacheKey);
    }

    /**
     * Get only tendency information
     * 
     * Matches Python's get_tendencia_sf()
     */
    public function getTendency(): array
    {
        return $this->calculateTendency();
    }

    /**
     * Fetch height from RSS feed
     */
    private function fetchHeight(string $cacheKey): array
    {
        try {
            error_log("⏳ Fetching San Fernando height from RSS");
            
            $xml = $this->http->fetchRss(self::RSS_URL);
            
            if (!$xml) {
                return ['error' => 'No hay datos disponibles'];
            }
            
            foreach ($xml->channel->item as $item) {
                if (!isset($item->description)) {
                    continue;
                }
                
                $desc = (string) $item->description;
                $text = strip_tags($desc);
                
                // Match: "San Fernando: X,XX m"
                if (preg_match('/San Fernando:\s*([\d,]+)\s*m/i', $text, $matches)) {
                    $altura = str_replace(',', '.', $matches[1]);
                    
                    // Match: "FECHA y HORA: DD/MM/YYYY HH:MM:SS"
                    $hora = null;
                    if (preg_match('/FECHA y HORA:\s*([0-9\/:\s]+)/', $text, $timeMatch)) {
                        $hora = trim($timeMatch[1]);
                    }
                    
                    // Save to history
                    $this->saveHeight((float) $altura, $hora);
                    
                    // Calculate tendency
                    $tendencia = $this->calculateTendency();
                    
                    $result = [
                        'altura' => $altura,
                        'altura_formatted' => str_replace('.', ',', $altura) . ' m',
                        'hora' => $hora,
                        'tendencia' => $tendencia['tendencia'],
                        'tendencia_label' => $tendencia['tendencia_label'],
                        'cambio' => $tendencia['cambio'],
                        'cambio_formatted' => $tendencia['cambio_formatted']
                    ];
                    
                    // Update cache
                    self::$cache[$cacheKey] = [$result, time()];
                    
                    error_log("✓ San Fernando: {$altura}m at {$hora}");
                    
                    return $result;
                }
            }
            
            return ['error' => 'No se encontraron datos de San Fernando'];
            
        } catch (Exception $e) {
            error_log("❌ SF height error: " . $e->getMessage());
            return ['error' => 'Servicio temporalmente no disponible'];
        }
    }

    /**
     * Save height to history file
     * 
     * Matches Python's guardar_altura_sf()
     */
    private function saveHeight(float $altura, ?string $hora): void
    {
        $record = [
            'altura' => $altura,
            'hora' => $hora,
            'timestamp' => date('c') // ISO 8601
        ];
        
        $this->data->appendRecord(
            DataManager::FILE_ALTURAS,
            'sf',
            $record,
            self::MAX_HISTORY_RECORDS
        );
    }

    /**
     * Calculate river tendency (rising/falling/stable)
     * 
     * Matches Python's calcular_tendencia_sf() - VERSIÓN SIMPLE
     */
    private function calculateTendency(): array
    {
        $default = [
            'tendencia' => 'estable',
            'tendencia_label' => 'ESTABLE',
            'cambio' => 0,
            'cambio_formatted' => '±0,00 m'
        ];
        
        try {
            $history = $this->data->read(DataManager::FILE_ALTURAS);
            $registros = $history['sf'] ?? [];
            
            if (count($registros) < 2) {
                return $default;
            }
            
            // Get most recent height
            $alturaActual = end($registros)['altura'];
            
            // Search backwards for a different height value
            // (matching Python's exact logic)
            $alturaAnterior = null;
            
            for ($i = count($registros) - 2; $i >= 0; $i--) {
                if (abs($registros[$i]['altura'] - $alturaActual) > 0.001) {
                    $alturaAnterior = $registros[$i]['altura'];
                    break;
                }
            }
            
            // If no different value found, use the oldest
            if ($alturaAnterior === null && count($registros) > 1) {
                $alturaAnterior = $registros[0]['altura'];
            }
            
            // Still no previous? Use penultimate
            if ($alturaAnterior === null && count($registros) >= 2) {
                $alturaAnterior = $registros[count($registros) - 2]['altura'];
            }
            
            // Calculate change
            if ($alturaAnterior !== null) {
                $cambio = round($alturaActual - $alturaAnterior, 2);
                
                // Rising: more than 2cm increase
                if ($cambio > self::TENDENCY_THRESHOLD) {
                    return [
                        'tendencia' => 'subiendo',
                        'tendencia_label' => 'SUBIENDO',
                        'cambio' => $cambio,
                        'cambio_formatted' => '+' . number_format($cambio, 2, ',', '') . ' m'
                    ];
                }
                
                // Falling: more than 2cm decrease
                if ($cambio < -self::TENDENCY_THRESHOLD) {
                    return [
                        'tendencia' => 'bajando',
                        'tendencia_label' => 'BAJANDO',
                        'cambio' => $cambio,
                        'cambio_formatted' => number_format($cambio, 2, ',', '') . ' m'
                    ];
                }
            }
            
            return $default;
            
        } catch (Exception $e) {
            error_log("Error calculating tendency: " . $e->getMessage());
            return [
                'tendencia' => 'error',
                'tendencia_label' => 'ERROR',
                'cambio' => 0,
                'cambio_formatted' => '- m'
            ];
        }
    }
}
