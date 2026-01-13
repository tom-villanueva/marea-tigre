<?php
/**
 * Marea Tigre - River Level Monitoring Application
 * 
 * Main entry point and router for the API.
 * 
 * Endpoints:
 * - GET /                  - HTML frontend
 * - GET /api/alertas       - River alerts from Hidro RSS
 * - GET /api/altura_sf     - San Fernando height + tendency
 * - GET /api/tendencia_sf  - San Fernando tendency only
 * - GET /api/telemetria    - Pilote Norden telemetry
 * - GET /api/sudestada     - Sudestada status + Tigre prediction
 */

// Error handling (disable display in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load services
require_once __DIR__ . '/src/DataManager.php';
require_once __DIR__ . '/src/HttpClient.php';
require_once __DIR__ . '/src/AlertService.php';
require_once __DIR__ . '/src/SanFernandoService.php';
require_once __DIR__ . '/src/SudestadaService.php';
require_once __DIR__ . '/src/TelemetryService.php';

// Initialize data files on startup (matching Python's __main__ block)
$dataManager = new DataManager();
$dataManager->initializeFiles();

// Parse request
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Set JSON header by default for API routes
if (strpos($requestUri, '/api/') === 0) {
    header('Content-Type: application/json; charset=utf-8');
}

// Route handling
switch ($requestUri) {
    // ─────────────────────────────────────────────────────────────
    // STATIC ROUTES
    // ─────────────────────────────────────────────────────────────
    
    case '/':
    case '/index.html':
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/templates/index.html');
        break;

    case '/manifest.json':
        header('Content-Type: application/manifest+json');
        if (file_exists(__DIR__ . '/static/manifest.json')) {
            readfile(__DIR__ . '/static/manifest.json');
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Manifest not found']);
        }
        break;

    case '/favicon.ico':
        if (file_exists(__DIR__ . '/static/favicon.ico')) {
            header('Content-Type: image/x-icon');
            readfile(__DIR__ . '/static/favicon.ico');
        } else {
            http_response_code(404);
        }
        break;

    case '/apple-touch-icon.png':
        if (file_exists(__DIR__ . '/static/apple-touch-icon.png')) {
            header('Content-Type: image/png');
            readfile(__DIR__ . '/static/apple-touch-icon.png');
        } else {
            http_response_code(404);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // API ROUTES
    // ─────────────────────────────────────────────────────────────

    case '/api/alertas':
        $alertService = new AlertService();
        echo json_encode($alertService->getAlerts());
        break;

    case '/api/altura_sf':
        $sfService = new SanFernandoService();
        echo json_encode($sfService->getHeight());
        break;

    case '/api/tendencia_sf':
        $sfService = new SanFernandoService();
        echo json_encode($sfService->getTendency());
        break;

    case '/api/telemetria':
        $telemetryService = new TelemetryService();
        echo json_encode($telemetryService->getTelemetry());
        break;

    case '/api/sudestada':
        $sudestadaService = new SudestadaService();
        echo json_encode($sudestadaService->getStatus());
        break;

    // ─────────────────────────────────────────────────────────────
    // STATIC FILE FALLBACK
    // ─────────────────────────────────────────────────────────────
    
    default:
        // Handle static files (CSS, JS, images)
        $filePath = __DIR__ . $requestUri;
        
        if (file_exists($filePath) && is_file($filePath)) {
            $ext = strtolower(pathinfo($requestUri, PATHINFO_EXTENSION));
            
            $mimeTypes = [
                'css'  => 'text/css',
                'js'   => 'application/javascript',
                'json' => 'application/json',
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'svg'  => 'image/svg+xml',
                'ico'  => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2'
            ];
            
            if (isset($mimeTypes[$ext])) {
                header("Content-Type: {$mimeTypes[$ext]}");
            }
            
            readfile($filePath);
        } else {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Endpoint not found']);
        }
        break;
}