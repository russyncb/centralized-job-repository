<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/systems/claude/shasha/bootstrap.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get all active jobs with passed deadlines
$query = "UPDATE jobs 
          SET status = 'closed', 
              updated_at = NOW() 
          WHERE status = 'active' 
          AND application_deadline IS NOT NULL 
          AND application_deadline < CURDATE()";

$stmt = $db->prepare($query);
$result = $stmt->execute();

if($result) {
    $affected_rows = $stmt->rowCount();
    echo "Successfully closed " . $affected_rows . " expired job(s)\n";
    
    // If any jobs were closed, notify the employers
    if($affected_rows > 0) {
        // Get the closed jobs and their employers
        $notify_query = "SELECT j.job_id, j.title, u.user_id, u.first_name, u.last_name 
                        FROM jobs j
                        JOIN employer_profiles e ON j.employer_id = e.employer_id
                        JOIN users u ON e.user_id = u.user_id
                        WHERE j.status = 'closed' 
                        AND j.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        
        $notify_stmt = $db->prepare($notify_query);
        $notify_stmt->execute();
        $closed_jobs = $notify_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert notifications for each employer
        $notification_query = "INSERT INTO notifications (user_id, title, message) 
                             VALUES (?, ?, ?)";
        $notification_stmt = $db->prepare($notification_query);
        
        foreach($closed_jobs as $job) {
            $title = "Job Posting Expired";
            $message = "Your job posting '{$job['title']}' has been automatically closed as it has reached its application deadline.";
            
            $notification_stmt->execute([
                $job['user_id'],
                $title,
                $message
            ]);
        }
    }
} else {
    echo "Error closing expired jobs\n";
} 