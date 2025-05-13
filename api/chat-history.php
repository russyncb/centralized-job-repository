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

try {
    // Get chat history for the user
    $query = "SELECT message, is_assistant, created_at 
              FROM chat_messages 
              WHERE user_id = ? 
              ORDER BY created_at ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id']);
    $stmt->execute();
    
    $chat_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($chat_history);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 