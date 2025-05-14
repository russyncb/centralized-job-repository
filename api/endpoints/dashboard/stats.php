<?php
// Only allow GET requests
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get total users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total employers
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'employer'");
    $stmt->execute();
    $total_employers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total jobseekers
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'jobseeker'");
    $stmt->execute();
    $total_jobseekers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total jobs
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM jobs");
    $stmt->execute();
    $total_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get pending employers
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'employer' AND status = 'pending'");
    $stmt->execute();
    $pending_employers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get active jobs
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM jobs WHERE status = 'active'");
    $stmt->execute();
    $active_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Return stats
    echo json_encode([
        'total_users' => (int)$total_users,
        'total_employers' => (int)$total_employers,
        'total_jobseekers' => (int)$total_jobseekers,
        'total_jobs' => (int)$total_jobs,
        'pending_employers' => (int)$pending_employers,
        'active_jobs' => (int)$active_jobs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
} 