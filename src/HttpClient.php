<?php

/**
 * HttpClient - Handles HTTP requests with Legacy SSL support
 * 
 * Replicates Python's LegacySSLAdapter for compatibility with
 * old government servers that use outdated SSL configurations.
 * 
 * Supports session-like behavior with cookie persistence between requests,
 * mimicking Python's requests.Session() functionality.
 */
class HttpClient
{
    private array $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: */*',
        'Accept-Language: es-ES,es;q=0.9',
        'Connection: keep-alive'
    ];

    private int $timeout = 10;
    
    // Cookie jar for session persistence (like Python's requests.Session)
    private ?string $cookieFile = null;
    private bool $useCookies = false;

    public function __construct()
    {
        // Constructor - cookies disabled by default for simple requests
    }

    /**
     * Enable session-like cookie persistence
     * Mimics Python's requests.Session() behavior
     */
    public function enableSession(): self
    {
        $this->useCookies = true;
        $this->cookieFile = sys_get_temp_dir() . '/php_http_cookies_' . uniqid() . '.txt';
        return $this;
    }

    /**
     * Clean up cookie file on destruct
     */
    public function __destruct()
    {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    /**
     * Perform a GET request
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * Perform a POST request
     */
    public function post(string $url, array|string $data = null, array $headers = []): array
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * Core cURL request with Legacy SSL support
     * 
     * Mimics Python's:
     * - LegacySSLAdapter with SECLEVEL=0
     * - verify=False
     * - Custom headers
     * - Session cookie persistence
     */
    private function request(string $method, string $url, array|string|null $data = null, array $headers = []): array
    {
        $ch = curl_init();
        
        // Basic configuration
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        // Legacy SSL Configuration (matching Python's LegacySSLAdapter)
        // context.check_hostname = False
        // context.verify_mode = ssl.CERT_NONE
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // ciphers='DEFAULT@SECLEVEL=0' for old servers
        curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=0');
        
        // Cookie handling for session persistence (like Python's requests.Session)
        if ($this->useCookies && $this->cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        }
        
        // Merge headers
        $finalHeaders = array_merge($this->defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        
        // Handle POST
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $postData = is_array($data) ? http_build_query($data) : $data;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL Error [{$errno}]: {$error}");
        }
        
        return [
            'code' => $httpCode,
            'body' => $response,
            'success' => $httpCode >= 200 && $httpCode < 300
        ];
    }

    /**
     * Set request timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Parse RSS/XML from URL
     */
    public function fetchRss(string $url): ?SimpleXMLElement
    {
        try {
            $response = $this->get($url);
            
            if (!$response['success']) {
                return null;
            }
            
            // Suppress XML warnings for malformed feeds
            $xml = @simplexml_load_string($response['body']);
            return $xml ?: null;
            
        } catch (Exception $e) {
            error_log("RSS Fetch Error: " . $e->getMessage());
            return null;
        }
    }
}
