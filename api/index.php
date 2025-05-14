<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Get the request path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/systems/claude/api/', '', $request_uri);
$method = $_SERVER['REQUEST_METHOD'];

// Get Authorization token
$headers = getallheaders();
$auth_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

// Routes that don't require authentication
$public_routes = ['admin/login'];

// Check authentication for protected routes
if (!in_array($path, $public_routes)) {
    if (!$auth_token) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }
    
    // Verify token (you'll need to implement this)
    if (!verify_admin_token($auth_token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit();
    }
}

// Route the request
try {
    switch ($path) {
        case 'admin/login':
            require 'endpoints/auth/login.php';
            break;
            
        case 'admin/dashboard/stats':
            require 'endpoints/dashboard/stats.php';
            break;
            
        case 'admin/employers/pending':
            require 'endpoints/employers/pending.php';
            break;
            
        case 'admin/employers/verify':
            require 'endpoints/employers/verify.php';
            break;
            
        case 'admin/categories':
            require 'endpoints/categories/list.php';
            break;
            
        case 'admin/categories/add':
            require 'endpoints/categories/add.php';
            break;
            
        case 'admin/categories/update':
            require 'endpoints/categories/update.php';
            break;
            
        case (preg_match('/^admin\/categories\/delete\/\d+$/', $path) ? true : false):
            require 'endpoints/categories/delete.php';
            break;
            
        case 'admin/queries':
            require 'endpoints/queries/list.php';
            break;
            
        case 'admin/queries/respond':
            require 'endpoints/queries/respond.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function verify_admin_token($token) {
    global $db;
    
    try {
        // Check if token exists and is valid
        $stmt = $db->prepare("
            SELECT u.user_id, u.role 
            FROM users u
            JOIN admin_tokens t ON u.user_id = t.user_id
            WHERE t.token = ? AND t.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] === 'admin';
    } catch (PDOException $e) {
        return false;
    }
} 