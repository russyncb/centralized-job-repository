<?php
// Only allow DELETE requests
if ($method !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get category ID from URL
preg_match('/^admin\/categories\/delete\/(\d+)$/', $path, $matches);
$category_id = $matches[1] ?? null;

if (!$category_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Category ID is required']);
    exit();
}

try {
    // Check if category exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM job_categories WHERE category_id = ?");
    $stmt->execute([$category_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Category not found']);
        exit();
    }

    // Check if category has jobs
    $stmt = $db->prepare("
        SELECT c.name, COUNT(j.job_id) as job_count
        FROM job_categories c
        LEFT JOIN jobs j ON j.category = c.name
        WHERE c.category_id = ?
        GROUP BY c.category_id
    ");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['job_count'] > 0) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Cannot delete category with active jobs',
            'job_count' => $result['job_count']
        ]);
        exit();
    }

    // Delete category
    $stmt = $db->prepare("DELETE FROM job_categories WHERE category_id = ?");
    $stmt->execute([$category_id]);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Category deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 