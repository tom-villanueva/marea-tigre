<?php

require_once __DIR__ . '/DataManager.php';

/**
 * SudestadaService - Tracks sudestada events and predicts Tigre levels
 * 
 * API Endpoint: /api/sudestada
 * 
 * Sudestada Detection:
 * - Activates when Pilote Norden >= 2.0m
 * - Tracks peak maximum during event
 * - Deactivates when < 1.8m for 4+ hours
 * 
 * Tigre Prediction:
 * - Time: +3.5 hours from Pilote peak
 * - Height: +35cm from Pilote peak
 */
class SudestadaService
{
    // Sudestada thresholds (matching Python)
    private const ACTIVATION_THRESHOLD = 2.0;    // meters to activate
    private const DEACTIVATION_THRESHOLD = 1.8;  // meters to consider ending
    private const DEACTIVATION_TIME = 14400;     // 4 hours in seconds
    
    // Tigre prediction offsets
    private const TIGRE_TIME_OFFSET_HOURS = 3.5;
    private const TIGRE_HEIGHT_OFFSET = 0.35;    // meters
    
    private DataManager $data;

    public function __construct(?DataManager $data = null)
    {
        $this->data = $data ?? new DataManager();
    }

    /**
     * Get current sudestada status and Tigre prediction
     * 
     * Matches Python's get_sudestada()
     * 
     * @return array Sudestada status with Tigre prediction
     */
    public function getStatus(): array
    {
        $sudestada = $this->data->read(DataManager::FILE_SUDESTADA);
        
        if (empty($sudestada) || !$sudestada['activa']) {
            return [
                'activa' => false,
                'mensaje' => 'No hay sudestada activa'
            ];
        }
        
        $pico = $sudestada['pico_maximo'];
        $horaPico = $sudestada['hora_pico'];
        
        // Calculate Tigre time (+3.5 hours)
        $horaTigre = $this->calculateTigreTime($horaPico);
        
        // Calculate Tigre height (+35cm)
        $alturaTigre = round($pico + self::TIGRE_HEIGHT_OFFSET, 2);
        
        // Pre-format for frontend display
        $picoFormatted = number_format($pico, 2, ',', '') . ' m';
        $alturaTigreFormatted = number_format($alturaTigre, 2, ',', '') . ' m';
        
        return [
            'activa' => true,
            'pico_maximo' => $pico,
            'pico_maximo_formatted' => $picoFormatted,
            'hora_pico' => $horaPico,
            'altura_tigre_estimada' => $alturaTigre,
            'altura_tigre_formatted' => $alturaTigreFormatted,
            'hora_tigre_estimada' => $horaTigre,
            'mensaje' => "Pico detectado: {$picoFormatted} a las {$horaPico}",
            'prediccion_tigre' => "Tigre: ~{$alturaTigreFormatted} para las {$horaTigre}"
        ];
    }

    /**
     * Update sudestada peak when new high detected
     * 
     * Matches Python's actualizar_pico_sudestada()
     * Called from TelemetryService when Pilote height >= 2.0m
     */
    public function updatePeak(float $altura, string $hora): void
    {
        $this->data->update(DataManager::FILE_SUDESTADA, function($sudestada) use ($altura, $hora) {
            // Initialize if needed
            if (!isset($sudestada['activa'])) {
                $sudestada = [
                    'activa' => false,
                    'pico_maximo' => 0,
                    'hora_pico' => null,
                    'timestamp_pico' => null,
                    'inicio' => null
                ];
            }
            
            // First time exceeding threshold - activate sudestada
            if (!$sudestada['activa'] && $altura >= self::ACTIVATION_THRESHOLD) {
                $sudestada['activa'] = true;
                $sudestada['pico_maximo'] = $altura;
                $sudestada['hora_pico'] = $hora;
                $sudestada['timestamp_pico'] = time();
                $sudestada['inicio'] = date('c');
                
                error_log("⚠️ SUDESTADA DETECTADA: {$altura}m a las {$hora}");
            }
            // Already active - check for new peak
            elseif ($sudestada['activa'] && $altura > $sudestada['pico_maximo']) {
                $oldPeak = $sudestada['pico_maximo'];
                $sudestada['pico_maximo'] = $altura;
                $sudestada['hora_pico'] = $hora;
                $sudestada['timestamp_pico'] = time();
                
                error_log("⚠️ NUEVO PICO SUDESTADA: {$altura}m (anterior: {$oldPeak}m)");
            }
            
            return $sudestada;
        });
    }

    /**
     * Check if sudestada has ended
     * 
     * Called periodically to check if water level dropped
     * below threshold for extended time
     */
    public function checkDeactivation(float $currentHeight): void
    {
        $this->data->update(DataManager::FILE_SUDESTADA, function($sudestada) use ($currentHeight) {
            if (!$sudestada['activa']) {
                return $sudestada;
            }
            
            // Check if dropped below deactivation threshold
            if ($currentHeight < self::DEACTIVATION_THRESHOLD) {
                // Check if enough time passed since peak
                $timeSincePeak = time() - ($sudestada['timestamp_pico'] ?? 0);
                
                if ($timeSincePeak > self::DEACTIVATION_TIME) {
                    $sudestada['activa'] = false;
                    error_log("✅ SUDESTADA TERMINADA. Pico máximo: {$sudestada['pico_maximo']}m");
                }
            }
            
            return $sudestada;
        });
    }

    /**
     * Calculate estimated time for Tigre
     * 
     * Matches Python's hora_tigre calculation with multiple format attempts
     */
    private function calculateTigreTime(?string $horaPico): string
    {
        if (!$horaPico) {
            return 'No disponible';
        }
        
        try {
            // Clean up the time string
            $horaLimpia = trim(explode(' ', $horaPico)[0] ?? $horaPico);
            
            // Try various time formats (matching Python's formatos list)
            $formats = ['H:i', 'H:i:s', 'H.i', 'g:i A', 'g:iA'];
            
            foreach ($formats as $format) {
                $parsed = DateTime::createFromFormat($format, $horaLimpia);
                if ($parsed !== false) {
                    // Add 3.5 hours
                    $parsed->modify('+3 hours +30 minutes');
                    return $parsed->format('H:i');
                }
            }
            
            // Try PHP's flexible strtotime
            // Normalize slashes for date parsing
            $normalized = str_replace('/', '-', $horaPico);
            $timestamp = strtotime($normalized);
            
            if ($timestamp !== false) {
                $tigreTimestamp = $timestamp + (int)(self::TIGRE_TIME_OFFSET_HOURS * 3600);
                return date('H:i', $tigreTimestamp);
            }
            
            // Fallback: show approximate
            return "~{$horaLimpia} + 3.5h";
            
        } catch (Exception $e) {
            error_log("Error calculating Tigre time: " . $e->getMessage());
            return "~{$horaPico} + 3.5h";
        }
    }

    /**
     * Get historical sudestada data
     */
    public function getHistory(): array
    {
        return $this->data->read(DataManager::FILE_PILOTE);
    }

    /**
     * Reset sudestada tracking (for testing/maintenance)
     */
    public function reset(): bool
    {
        return $this->data->write(DataManager::FILE_SUDESTADA, [
            'activa' => false,
            'pico_maximo' => 0,
            'hora_pico' => null,
            'timestamp_pico' => null,
            'inicio' => null
        ]);
    }
}
