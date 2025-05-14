<?php
// Only allow POST requests
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);
$category_id = $data['id'] ?? null;
$name = $data['name'] ?? '';
$description = $data['description'] ?? '';

if (!$category_id || empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Category ID and name are required']);
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

    // Check if new name already exists for different category
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM job_categories WHERE name = ? AND category_id != ?");
    $stmt->execute([$name, $category_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Category name already exists']);
        exit();
    }

    // Update category
    $stmt = $db->prepare("
        UPDATE job_categories 
        SET name = ?, description = ?
        WHERE category_id = ?
    ");
    $stmt->execute([$name, $description, $category_id]);

    // Get updated category
    $stmt = $db->prepare("
        SELECT category_id as id, name, description
        FROM job_categories
        WHERE category_id = ?
    ");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Category updated successfully',
        'category' => $category
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 