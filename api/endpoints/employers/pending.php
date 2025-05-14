<?php
// Only allow GET requests
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get pending employers with their details
    $stmt = $db->prepare("
        SELECT 
            u.user_id as id,
            u.email,
            u.first_name,
            u.last_name,
            u.phone,
            e.company_name,
            e.industry,
            e.company_size,
            e.website,
            e.description,
            e.location,
            e.business_file
        FROM users u
        JOIN employer_profiles e ON u.user_id = e.user_id
        WHERE u.role = 'employer' 
        AND u.status = 'pending'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $employers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return employers list
    echo json_encode([
        'employers' => $employers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 