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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

try {
    // Store user message
    $query = "INSERT INTO chat_messages (user_id, message, is_assistant, created_at) 
              VALUES (?, ?, 0, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id']);
    $stmt->bindParam(2, $message);
    $stmt->execute();
    
    // Generate assistant response based on keywords
    $response = generateAssistantResponse($message);
    
    // Store assistant response
    $query = "INSERT INTO chat_messages (user_id, message, is_assistant, created_at) 
              VALUES (?, ?, 1, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $_SESSION['user_id']);
    $stmt->bindParam(2, $response);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'response' => $response]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

function generateAssistantResponse($message) {
    $message = strtolower($message);
    
    if(strpos($message, 'post job') !== false || strpos($message, 'create job') !== false) {
        return "To post a new job, click on 'Post a Job' in the sidebar menu. You'll need to fill in details like job title, description, requirements, and other relevant information.";
    }
    
    if(strpos($message, 'application') !== false || strpos($message, 'applicant') !== false) {
        return "You can view and manage all job applications in the 'Applications' section. There you can review candidates, update application status, and contact applicants.";
    }
    
    if(strpos($message, 'category') !== false) {
        return "To request a new job category, please use the 'Submit Query to Admin' form on the right and select 'Add New Job Category' as the query type.";
    }
    
    if(strpos($message, 'profile') !== false || strpos($message, 'company') !== false) {
        return "You can update your company profile by clicking on 'Company Profile' in the sidebar. Make sure to keep your information up to date!";
    }
    
    if(strpos($message, 'verify') !== false || strpos($message, 'verification') !== false) {
        return "Company verification is handled by our admin team. If your company is not verified yet, please submit a verification request using the 'Account Support' query type.";
    }
    
    return "I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They'll be happy to help!";
} 