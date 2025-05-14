<?php
// Only allow GET requests
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get all categories
    $stmt = $db->prepare("
        SELECT 
            category_id as id,
            name,
            description,
            (SELECT COUNT(*) FROM jobs WHERE category = jc.name) as job_count
        FROM job_categories jc
        ORDER BY name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return categories list
    echo json_encode([
        'categories' => $categories
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 