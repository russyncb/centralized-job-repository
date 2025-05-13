<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get POST data
$query_type = $_POST['query_type'] ?? '';
$query_text = $_POST['query_text'] ?? '';

if (empty($query_type) || empty($query_text)) {
    http_response_code(400);
    echo json_encode(['error' => 'Query type and text are required']);
    exit;
}

try {
    // Insert the query
    $query = "INSERT INTO admin_queries (user_id, query_type, query_text, status, created_at) 
              VALUES (?, ?, ?, 'pending', NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id']);
    $stmt->bindParam(2, $query_type);
    $stmt->bindParam(3, $query_text);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 