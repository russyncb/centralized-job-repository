<?php
// Only allow GET requests
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get query parameters
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

    // Build query
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = "q.status = ?";
        $params[] = $status;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM admin_queries q 
        $whereClause
    ";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get queries
    $query = "
        SELECT 
            q.query_id,
            q.query_type,
            q.query_text,
            q.status,
            q.created_at,
            q.responded_at,
            q.response,
            u.first_name,
            u.last_name,
            u.email
        FROM admin_queries q
        JOIN users u ON q.user_id = u.user_id
        $whereClause
        ORDER BY 
            CASE 
                WHEN q.status = 'pending' THEN 1
                WHEN q.status = 'in_progress' THEN 2
                ELSE 3
            END,
            q.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate pagination
    $total_pages = ceil($total / $limit);

    // Return response
    echo json_encode([
        'queries' => $queries,
        'pagination' => [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $total_pages
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 