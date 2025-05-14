<?php
// Only allow POST requests
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? '';
$description = $data['description'] ?? '';

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Category name is required']);
    exit();
}

try {
    // Check if category already exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM job_categories WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Category already exists']);
        exit();
    }

    // Insert new category
    $stmt = $db->prepare("
        INSERT INTO job_categories (name, description)
        VALUES (?, ?)
    ");
    $stmt->execute([$name, $description]);

    // Get the new category
    $category_id = $db->lastInsertId();
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
        'message' => 'Category added successfully',
        'category' => $category
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 