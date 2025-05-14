<?php
// Only allow POST requests
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);
$query_id = $data['query_id'] ?? null;
$response = $data['response'] ?? '';
$status = $data['status'] ?? 'resolved';

if (!$query_id || empty($response)) {
    http_response_code(400);
    echo json_encode(['error' => 'Query ID and response are required']);
    exit();
}

try {
    // Start transaction
    $db->beginTransaction();

    // Update query
    $stmt = $db->prepare("
        UPDATE admin_queries 
        SET response = ?,
            status = ?,
            responded_at = NOW()
        WHERE query_id = ?
    ");
    $stmt->execute([$response, $status, $query_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Query not found');
    }

    // Get query details for notification
    $stmt = $db->prepare("
        SELECT user_id, query_type 
        FROM admin_queries 
        WHERE query_id = ?
    ");
    $stmt->execute([$query_id]);
    $query = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create notification for user
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $query['user_id'],
        'Query ' . ucfirst($status),
        'Your query regarding ' . $query['query_type'] . ' has been ' . $status
    ]);

    // Commit transaction
    $db->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Query response submitted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 