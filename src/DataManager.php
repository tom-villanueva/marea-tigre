<?php

/**
 * DataManager - Handles JSON file storage with concurrency safety
 * 
 * Provides thread-safe read/write operations for:
 * - San Fernando height history
 * - Pilote Norden history
 * - Sudestada tracking
 */
class DataManager
{
    private string $dataDir;
    
    // File paths
    public const FILE_ALTURAS = 'alturas_historico.json';
    public const FILE_PILOTE = 'pilote_historico.json';
    public const FILE_SUDESTADA = 'sudestada_actual.json';
    
    // Default structures matching Python app.py
    private const DEFAULTS = [
        self::FILE_ALTURAS => ['sf' => []],
        self::FILE_PILOTE => ['registros' => []],
        self::FILE_SUDESTADA => [
            'activa' => false,
            'pico_maximo' => 0,
            'hora_pico' => null,
            'timestamp_pico' => null,
            'inicio' => null
        ]
    ];

    public function __construct(?string $dataDir = null)
    {
        $this->dataDir = $dataDir ?? __DIR__ . '/../data';
        $this->ensureDataDirectory();
    }

    /**
     * Read JSON file safely
     */
    public function read(string $filename): array
    {
        $filepath = $this->getPath($filename);
        
        if (!file_exists($filepath)) {
            return self::DEFAULTS[$filename] ?? [];
        }
        
        $content = file_get_contents($filepath);
        $data = json_decode($content, true);
        
        return $data ?? (self::DEFAULTS[$filename] ?? []);
    }

    /**
     * Write JSON file with atomic operation
     */
    public function write(string $filename, array $data): bool
    {
        $filepath = $this->getPath($filename);
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Atomic write: write to temp file, then rename
        $tempFile = $filepath . '.tmp';
        if (file_put_contents($tempFile, $json, LOCK_EX) !== false) {
            return rename($tempFile, $filepath);
        }
        
        return false;
    }

    /**
     * Update JSON file with callback (thread-safe)
     * 
     * Matches Python's file handling with proper locking
     */
    public function update(string $filename, callable $callback): bool
    {
        $filepath = $this->getPath($filename);
        $this->ensureFileExists($filename);
        
        $fp = fopen($filepath, 'c+');
        if (!$fp) {
            return false;
        }
        
        try {
            // Acquire exclusive lock
            if (!flock($fp, LOCK_EX)) {
                throw new Exception("Could not acquire file lock");
            }
            
            // Read current content
            $size = filesize($filepath);
            $content = $size > 0 ? fread($fp, $size) : '';
            $data = json_decode($content, true) ?? (self::DEFAULTS[$filename] ?? []);
            
            // Apply callback transformation
            $newData = $callback($data);
            
            // Write new content
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            
            // Release lock
            flock($fp, LOCK_UN);
            
            return true;
            
        } catch (Exception $e) {
            error_log("DataManager Error: " . $e->getMessage());
            return false;
            
        } finally {
            fclose($fp);
        }
    }

    /**
     * Append record to array in JSON file (with limit)
     */
    public function appendRecord(string $filename, string $key, array $record, int $maxRecords = 72): bool
    {
        return $this->update($filename, function($data) use ($key, $record, $maxRecords) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }
            
            $data[$key][] = $record;
            
            // Trim to max records (matching Python's logic)
            if (count($data[$key]) > $maxRecords) {
                $data[$key] = array_slice($data[$key], -$maxRecords);
            }
            
            return $data;
        });
    }

    /**
     * Get full path for a data file
     */
    public function getPath(string $filename): string
    {
        return $this->dataDir . '/' . $filename;
    }

    /**
     * Ensure data directory exists
     */
    private function ensureDataDirectory(): void
    {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * Ensure file exists with default content
     */
    private function ensureFileExists(string $filename): void
    {
        $filepath = $this->getPath($filename);
        
        if (!file_exists($filepath)) {
            $default = self::DEFAULTS[$filename] ?? [];
            file_put_contents(
                $filepath, 
                json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * Initialize all data files (called on app startup)
     */
    public function initializeFiles(): void
    {
        foreach (self::DEFAULTS as $filename => $default) {
            $this->ensureFileExists($filename);
        }
    }
}
