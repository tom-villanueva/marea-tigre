<?php

require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/DataManager.php';
require_once __DIR__ . '/SudestadaService.php';

/**
 * TelemetryService - Handles Pilote Norden telemetry data
 * 
 * API Endpoint: /api/telemetria
 * Source: https://meteo.comisionriodelaplata.org/
 * 
 * This service scrapes the special JSON** format from the
 * Comisión Río de la Plata weather station and returns
 * pre-parsed, structured data for the frontend.
 */
class TelemetryService
{
    private const BASE_URL = 'https://meteo.comisionriodelaplata.org/';
    private const TARGET_URL = 'https://meteo.comisionriodelaplata.org/ecsCommand.php?c=telemetry%2FupdateTelemetry&s=0.21097539498237183';
    
    private const PAYLOAD = [
        'p' => '1',
        'p1' => '2',
        'p2' => '1',
        'p3' => '1'
    ];
    
    private const HEADERS = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: */*',
        'Accept-Language: es-ES,es;q=0.9',
        'Referer: https://meteo.comisionriodelaplata.org/',
        'Origin: https://meteo.comisionriodelaplata.org',
        'X-Requested-With: XMLHttpRequest',
        'Connection: keep-alive'
    ];
    
    private const MAX_PILOTE_RECORDS = 100;
    
    private const COMPASS_DIRECTIONS = [
        'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
        'S', 'SSO', 'SO', 'OSO', 'O', 'ONO', 'NO', 'NNO'
    ];
    
    private HttpClient $http;
    private DataManager $data;
    private SudestadaService $sudestada;

    public function __construct(
        ?HttpClient $http = null, 
        ?DataManager $data = null,
        ?SudestadaService $sudestada = null
    ) {
        $this->http = $http ?? new HttpClient();
        $this->data = $data ?? new DataManager();
        $this->sudestada = $sudestada ?? new SudestadaService($this->data);
    }

    /**
     * Get telemetry data from Pilote Norden
     * 
     * Returns pre-parsed, structured data ready for frontend consumption.
     * 
     * @return array Parsed telemetry data or error
     */
    public function getTelemetry(): array
    {
        try {
            $rawData = $this->fetchRawTelemetry();
            
            if (isset($rawData['error'])) {
                return $rawData;
            }
            
            // Parse and structure the data
            $tide = $this->parseTideData($rawData['tide']['latest'] ?? null);
            $wind = $this->parseWindData($rawData['wind']['latest'] ?? null);
            
            // Save to history if we got valid tide data
            if ($tide['height'] !== null) {
                $this->savePiloteHistory($tide['height'], $tide['datetime_raw']);
            }
            
            return [
                'tide' => $tide,
                'wind' => $wind,
                'updated' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Telemetry error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetch raw telemetry data from remote source
     */
    private function fetchRawTelemetry(): array
    {
        // Create a session-enabled HTTP client
        $session = (new HttpClient())->enableSession();
        
        // First, visit base URL to establish session/cookies
        $session->get(self::BASE_URL);
        
        // Make the actual telemetry request with the same session
        $response = $session->post(self::TARGET_URL, self::PAYLOAD, self::HEADERS);
        
        if (!$response['success']) {
            return ['error' => "Error remoto: {$response['code']}"];
        }
        
        $rawText = $response['body'];
        
        // Check for security block
        if (strpos($rawText, '<!DOCTYPE html') !== false || strpos($rawText, 'redirect_form') !== false) {
            return ['error' => 'Bloqueo de seguridad'];
        }
        
        // Parse JSON** format
        if (strpos($rawText, 'JSON**') === false) {
            return ['error' => 'Formato inesperado'];
        }
        
        $parts = explode('JSON**', $rawText);
        $jsonPart = $parts[1] ?? '';
        
        $data = json_decode($jsonPart, true);
        
        if (!$data) {
            return ['error' => 'Error parsing JSON'];
        }
        
        return $data;
    }

    /**
     * Parse tide HTML table and extract structured data
     * 
     * @param string|null $tideHtml URL-encoded HTML table
     * @return array Parsed tide data
     */
    private function parseTideData(?string $tideHtml): array
    {
        $default = [
            'datetime' => null,
            'datetime_raw' => null,
            'height' => null,
            'height_formatted' => 'No disponible'
        ];
        
        if (!$tideHtml) {
            return $default;
        }
        
        // Decode URL-encoded HTML (+ becomes space)
        $decoded = urldecode(str_replace('+', ' ', $tideHtml));
        
        // Find table in HTML
        if (!preg_match('/<table[^>]*>(.*?)<\/table>/is', $decoded, $tableMatch)) {
            return $default;
        }
        
        // Find all rows
        if (!preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableMatch[1], $rowMatches)) {
            return $default;
        }
        
        $rows = $rowMatches[1];
        if (empty($rows)) {
            return $default;
        }
        
        // Get last row (most recent reading)
        $lastRow = end($rows);
        
        // Extract cells
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $lastRow, $cellMatches)) {
            return $default;
        }
        
        $cells = $cellMatches[1];
        if (count($cells) < 2) {
            return $default;
        }
        
        // Extract datetime
        $datetimeRaw = trim(strip_tags($cells[0]));
        $datetime = $this->formatDateTime($datetimeRaw);
        
        // Extract height
        $heightText = trim(strip_tags($cells[1]));
        $height = null;
        
        if (preg_match('/([\d\.]+)/', $heightText, $heightMatch)) {
            $height = (float) $heightMatch[1];
        }
        
        return [
            'datetime' => $datetime,
            'datetime_raw' => $datetimeRaw,
            'height' => $height,
            'height_formatted' => $height !== null ? number_format($height, 2, ',', '') . ' m' : 'No disponible'
        ];
    }

    /**
     * Parse wind HTML table and extract structured data
     * 
     * @param string|null $windHtml URL-encoded HTML table
     * @return array Parsed wind data
     */
    private function parseWindData(?string $windHtml): array
    {
        $default = [
            'speed_knots' => null,
            'speed_kmh' => null,
            'speed_formatted' => 'No disponible',
            'direction_deg' => null,
            'direction_compass' => null,
            'direction_formatted' => '-'
        ];
        
        if (!$windHtml) {
            return $default;
        }
        
        // Decode URL-encoded HTML
        $decoded = urldecode(str_replace('+', ' ', $windHtml));
        
        // Find table in HTML
        if (!preg_match('/<table[^>]*>(.*?)<\/table>/is', $decoded, $tableMatch)) {
            return $default;
        }
        
        // Find all rows
        if (!preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableMatch[1], $rowMatches)) {
            return $default;
        }
        
        $rows = $rowMatches[1];
        if (empty($rows)) {
            return $default;
        }
        
        // Get last row
        $lastRow = end($rows);
        
        // Extract cells
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $lastRow, $cellMatches)) {
            return $default;
        }
        
        $cells = $cellMatches[1];
        
        // Extract speed (cell index 1)
        $speedKnots = null;
        $speedKmh = null;
        $speedFormatted = 'No disponible';
        
        if (isset($cells[1])) {
            $speedText = trim(strip_tags($cells[1]));
            if (is_numeric($speedText)) {
                $speedKnots = (float) $speedText;
                $speedKmh = round($speedKnots * 1.852, 1);
                $speedFormatted = "{$speedKnots} kn ({$speedKmh} km/h)";
            }
        }
        
        // Extract direction (cell index 4)
        $directionDeg = null;
        $directionCompass = null;
        $directionFormatted = '-';
        
        if (isset($cells[4])) {
            $dirText = trim(strip_tags($cells[4]));
            if (is_numeric($dirText)) {
                $directionDeg = (float) $dirText;
                $directionCompass = $this->degreesToCompass($directionDeg);
                $directionFormatted = round($directionDeg) . "° {$directionCompass}";
            }
        }
        
        return [
            'speed_knots' => $speedKnots,
            'speed_kmh' => $speedKmh,
            'speed_formatted' => $speedFormatted,
            'direction_deg' => $directionDeg,
            'direction_compass' => $directionCompass,
            'direction_formatted' => $directionFormatted
        ];
    }

    /**
     * Format raw datetime string to localized format
     */
    private function formatDateTime(string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        
        try {
            $dt = new DateTime($raw);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            // Return raw if parsing fails
            return $raw;
        }
    }

    /**
     * Convert wind degrees to compass direction (Spanish)
     */
    private function degreesToCompass(float $degrees): string
    {
        $index = (int) floor($degrees / 22.5 + 0.5) % 16;
        return self::COMPASS_DIRECTIONS[$index];
    }

    /**
     * Save Pilote height to history
     */
    private function savePiloteHistory(float $altura, ?string $hora): void
    {
        $record = [
            'altura' => $altura,
            'hora' => $hora,
            'timestamp' => date('c'),
            'timestamp_unix' => time()
        ];
        
        $this->data->appendRecord(
            DataManager::FILE_PILOTE,
            'registros',
            $record,
            self::MAX_PILOTE_RECORDS
        );
        
        error_log("Pilote guardado: {$altura}m a las {$hora}");
        
        // Check for sudestada (>=2m) and update peak
        if ($altura >= 2.0) {
            $this->sudestada->updatePeak($altura, $hora);
        }
    }
}
