<?php

require_once __DIR__ . '/HttpClient.php';

/**
 * AlertService - Handles river alerts from Hidro RSS feed
 * 
 * API Endpoint: /api/alertas
 * Source: https://www.hidro.gob.ar/RSS/AACrioplarss.asp
 */
class AlertService
{
    private const RSS_URL = 'https://www.hidro.gob.ar/RSS/AACrioplarss.asp';
    
    private HttpClient $http;
    
    // In-memory cache (matches Python's RSS_CACHE)
    private static array $cache = [];
    private const CACHE_DURATION = 600; // 10 minutes
    private const CACHE_STALE_MAX = 1800; // 30 minutes - serve stale if needed

    public function __construct(?HttpClient $http = null)
    {
        $this->http = $http ?? new HttpClient();
    }

    /**
     * Get alerts from RSS feed
     * 
     * Matches Python's parse_rss_with_timeout with aggressive caching
     * for free tier hosting (Render, etc.)
     * 
     * @return array List of alert descriptions
     */
    public function getAlerts(): array
    {
        $cacheKey = self::RSS_URL;
        $now = time();
        
        // Check cache first (aggressive caching strategy from Python)
        if (isset(self::$cache[$cacheKey])) {
            [$cachedData, $cachedTime] = self::$cache[$cacheKey];
            $age = $now - $cachedTime;
            
            // Fresh cache (< 10 min)
            if ($age < self::CACHE_DURATION) {
                error_log("✓ Using fresh alert cache ({$age}s old)");
                return $cachedData;
            }
            
            // Stale cache but acceptable (< 30 min)
            if ($age < self::CACHE_STALE_MAX) {
                error_log("⚠️ Using stale alert cache ({$age}s old)");
                
                // Try background refresh (don't wait)
                $this->tryRefreshCache($cacheKey);
                
                return $cachedData;
            }
        }
        
        // No cache or too old - must fetch
        return $this->fetchAlerts($cacheKey);
    }

    /**
     * Fetch alerts from RSS source
     */
    private function fetchAlerts(string $cacheKey): array
    {
        try {
            error_log("⏳ Fetching fresh alerts from RSS");
            
            $xml = $this->http->fetchRss(self::RSS_URL);
            
            if (!$xml) {
                error_log("❌ Failed to parse alerts RSS");
                return [];
            }
            
            $alerts = [];
            
            foreach ($xml->channel->item as $item) {
                if (isset($item->description)) {
                    $alerts[] = (string) $item->description;
                }
            }
            
            // Update cache
            self::$cache[$cacheKey] = [$alerts, time()];
            
            error_log("✓ Successfully fetched " . count($alerts) . " alerts");
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("❌ Alert fetch error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Attempt to refresh cache without blocking
     */
    private function tryRefreshCache(string $cacheKey): void
    {
        try {
            $http = new HttpClient();
            $http->setTimeout(5); // Short timeout for background refresh
            
            $xml = $http->fetchRss(self::RSS_URL);
            
            if ($xml) {
                $alerts = [];
                foreach ($xml->channel->item as $item) {
                    if (isset($item->description)) {
                        $alerts[] = (string) $item->description;
                    }
                }
                
                self::$cache[$cacheKey] = [$alerts, time()];
                error_log("✓ Background alert refresh succeeded");
            }
            
        } catch (Exception $e) {
            // Silently fail - we have cache
        }
    }
}
