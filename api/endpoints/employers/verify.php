<?php
// Only allow POST requests
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);
$employer_id = $data['employer_id'] ?? null;
$action = $data['action'] ?? null; // 'approve' or 'reject'

if (!$employer_id || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Employer ID and action are required']);
    exit();
}

try {
    // Start transaction
    $db->beginTransaction();

    // Update user status
    $new_status = $action === 'approve' ? 'active' : 'rejected';
    $stmt = $db->prepare("
        UPDATE users 
        SET status = ? 
        WHERE user_id = ? AND role = 'employer'
    ");
    $stmt->execute([$new_status, $employer_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Employer not found');
    }

    if ($action === 'approve') {
        // Update employer profile
        $stmt = $db->prepare("
            UPDATE employer_profiles 
            SET verified = true,
                verified_at = NOW(),
                verified_by = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $employer_id]);

        // Create notification
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message)
            VALUES (?, 'Account Verified', 'Your employer account has been verified. You can now post jobs.')
        ");
        $stmt->execute([$employer_id]);
    }

    // Commit transaction
    $db->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Employer successfully " . ($action === 'approve' ? 'verified' : 'rejected')
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 