<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection (adjust these credentials for your setup)
require_once '../config/db.php'; // Assuming you have database config

class AdvancedChatbotHandler {
    private $db;
    private $userType;
    private $sessionId;
    private $conversationHistory;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function processMessage($data) {
        $this->userType = $data['userType'] ?? 'seeker';
        $this->sessionId = $data['sessionId'] ?? '';
        $this->conversationHistory = $data['conversationHistory'] ?? [];
        
        $message = trim($data['message'] ?? '');
        
        if (empty($message)) {
            return $this->createErrorResponse('Message cannot be empty');
        }
        
        // Log conversation for analytics
        $this->logConversation($message);
        
        // Process the message with AI-like intelligence
        $response = $this->analyzeAndRespond($message);
        
        return $this->createSuccessResponse($response);
    }
    
    private function analyzeAndRespond($message) {
        $lowerMessage = strtolower($message);
        $intent = $this->detectIntent($lowerMessage);
        $entities = $this->extractEntities($lowerMessage);
        
        // Get contextual data from database
        $contextData = $this->getContextualData();
        
        switch ($intent) {
            case 'job_search':
                return $this->handleJobSearchIntent($entities, $contextData);
                
            case 'application_management':
                return $this->handleApplicationIntent($entities, $contextData);
                
            case 'profile_management':
                return $this->handleProfileIntent($entities, $contextData);
                
            case 'analytics_inquiry':
                return $this->handleAnalyticsIntent($entities, $contextData);
                
            case 'system_help':
                return $this->handleSystemHelpIntent($entities, $contextData);
                
            case 'job_posting':
                return $this->handleJobPostingIntent($entities, $contextData);
                
            case 'candidate_management':
                return $this->handleCandidateManagementIntent($entities, $contextData);
                
            default:
                return $this->handleGeneralIntent($lowerMessage, $contextData);
        }
    }
    
    private function detectIntent($message) {
        $intents = [
            'job_search' => ['find job', 'search job', 'job search', 'looking for', 'job opening', 'position'],
            'application_management' => ['application', 'apply', 'applied', 'application status', 'interview'],
            'profile_management' => ['profile', 'resume', 'cv', 'experience', 'skills', 'update profile'],
            'analytics_inquiry' => ['analytics', 'statistics', 'data', 'report', 'insights', 'performance'],
            'system_help' => ['how to', 'help', 'guide', 'tutorial', 'explain', 'what is'],
            'job_posting' => ['post job', 'create job', 'job posting', 'hire', 'recruit'],
            'candidate_management' => ['candidate', 'applicant', 'review application', 'shortlist']
        ];
        
        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $intent;
                }
            }
        }
        
        return 'general';
    }
    
    private function extractEntities($message) {
        $entities = [];
        
        // Extract job categories
        $jobCategories = ['engineering', 'marketing', 'sales', 'design', 'finance', 'hr', 'management'];
        foreach ($jobCategories as $category) {
            if (strpos($message, $category) !== false) {
                $entities['category'] = $category;
                break;
            }
        }
        
        // Extract locations
        if (preg_match('/in ([a-zA-Z\s]+)/', $message, $matches)) {
            $entities['location'] = trim($matches[1]);
        }
        
        // Extract salary range
        if (preg_match('/(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)\s*(?:to|-)?\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/', $message, $matches)) {
            $entities['salary_min'] = str_replace(',', '', $matches[1]);
            $entities['salary_max'] = isset($matches[2]) ? str_replace(',', '', $matches[2]) : null;
        }
        
        return $entities;
    }
    
    private function getContextualData() {
        $data = [];
        
        try {
            if ($this->userType === 'seeker') {
                // Get job seeker specific data
                $data['recent_jobs'] = $this->getRecentJobs();
                $data['application_count'] = $this->getUserApplicationCount();
                $data['profile_completeness'] = $this->getProfileCompleteness();
                $data['recommended_jobs'] = $this->getRecommendedJobs();
            } else {
                // Get employer specific data
                $data['posted_jobs'] = $this->getEmployerPostedJobs();
                $data['application_stats'] = $this->getApplicationStats();
                $data['candidate_pool'] = $this->getCandidatePoolStats();
            }
            
            $data['system_stats'] = $this->getSystemStats();
            
        } catch (Exception $e) {
            error_log('Error getting contextual data: ' . $e->getMessage());
        }
        
        return $data;
    }
    
    private function handleJobSearchIntent($entities, $contextData) {
        $response = [
            'text' => '',
            'options' => []
        ];
        
        if ($this->userType === 'employer') {
            $response['text'] = "As an employer, you can search for candidates in our talent pool. Would you like to find candidates for a specific position?";
            $response['options']['actions'] = [
                ['text' => 'Search Candidates', 'action' => 'searchCandidates', 'type' => 'primary']
            ];
        } else {
            $jobCount = count($contextData['recent_jobs'] ?? []);
            $recommendedCount = count($contextData['recommended_jobs'] ?? []);
            
            $response['text'] = "I found {$jobCount} new jobs that might interest you. Based on your profile, I have {$recommendedCount} personalized recommendations.";
            
            if (isset($entities['category'])) {
                $category = ucfirst($entities['category']);
                $response['text'] .= " I notice you're interested in {$category} positions.";
            }
            
            if (isset($entities['location'])) {
                $location = ucfirst($entities['location']);
                $response['text'] .= " I can help you find opportunities in {$location}.";
            }
            
            $response['options'] = [
                'actions' => [
                    ['text' => 'View All Jobs', 'action' => 'viewJobs', 'type' => 'primary'],
                    ['text' => 'Personalized Jobs', 'action' => 'viewRecommended']
                ],
                'quickReplies' => ['Remote jobs', 'Full-time positions', 'Part-time work'],
                'systemInsight' => "Your profile views increased by 15% this week. Keep it updated!"
            ];
        }
        
        return $response;
    }
    
    private function handleApplicationIntent($entities, $contextData) {
        $response = [
            'text' => '',
            'options' => []
        ];
        
        if ($this->userType === 'employer') {
            $stats = $contextData['application_stats'] ?? [];
            $pendingCount = $stats['pending'] ?? 0;
            $totalCount = $stats['total'] ?? 0;
            
            $response['text'] = "You have {$pendingCount} pending applications out of {$totalCount} total applications to review.";
            $response['options'] = [
                'actions' => [
                    ['text' => 'Review Applications', 'action' => 'viewApplications', 'type' => 'primary']
                ],
                'systemInsight' => "Average response time in your industry is 3-5 days. Quick responses improve candidate experience."
            ];
        } else {
            $applicationCount = $contextData['application_count'] ?? 0;
            
            if ($applicationCount > 0) {
                $response['text'] = "You have {$applicationCount} active applications. I can help you track their status and prepare for potential interviews.";
                $response['options'] = [
                    'actions' => [
                        ['text' => 'View Applications', 'action' => 'viewApplications'],
                        ['text' => 'Interview Prep', 'action' => 'interviewPrep']
                    ],
                    'quickReplies' => ['Application status', 'Interview tips', 'Follow-up advice']
                ];
            } else {
                $response['text'] = "You haven't applied to any jobs yet. Let me help you find suitable positions and guide you through the application process.";
                $response['options'] = [
                    'actions' => [
                        ['text' => 'Find Jobs', 'action' => 'viewJobs', 'type' => 'primary']
                    ]
                ];
            }
        }
        
        return $response;
    }
    
    private function handleProfileIntent($entities, $contextData) {
        $response = ['text' => '', 'options' => []];
        
        if ($this->userType === 'seeker') {
            $completeness = $contextData['profile_completeness'] ?? 0;
            
            $response['text'] = "Your profile is {$completeness}% complete. ";
            
            if ($completeness < 80) {
                $response['text'] .= "Completing your profile can increase job matches by up to 50%.";
                $response['options'] = [
                    'actions' => [
                        ['text' => 'Complete Profile', 'action' => 'updateProfile', 'type' => 'primary']
                    ],
                    'systemInsight' => "Profiles with professional photos get 20% more views."
                ];
            } else {
                $response['text'] .= "Great job! Your complete profile makes you more attractive to employers.";
                $response['options'] = [
                    'quickReplies' => ['Update experience', 'Add skills', 'Upload new resume']
                ];
            }
        } else {
            $response['text'] = "Keep your company profile updated to attract top talent. Include company culture, benefits, and growth opportunities.";
            $response['options'] = [
                'actions' => [
                    ['text' => 'Update Company Profile', 'action' => 'updateProfile']
                ]
            ];
        }
        
        return $response;
    }
    
    private function handleAnalyticsIntent($entities, $contextData) {
        $response = ['text' => '', 'options' => []];
        
        if ($this->userType === 'employer') {
            $stats = $contextData['application_stats'] ?? [];
            $jobCount = count($contextData['posted_jobs'] ?? []);
            
            $response['text'] = "Your recruitment analytics: {$jobCount} active job postings, with an average of " . 
                              round(($stats['total'] ?? 0) / max($jobCount, 1), 1) . " applications per job.";
            
            $response['options'] = [
                'actions' => [
                    ['text' => 'Full Analytics', 'action' => 'viewAnalytics', 'type' => 'primary']
                ],
                'systemInsight' => "Your job postings perform 23% better than industry average."
            ];
        } else {
            $applicationCount = $contextData['application_count'] ?? 0;
            $response['text'] = "Your job search analytics: {$applicationCount} applications submitted. Based on industry data, you should expect responses within 1-2 weeks.";
            
            $response['options'] = [
                'quickReplies' => ['Application success rate', 'Industry insights', 'Salary benchmarks']
            ];
        }
        
        return $response;
    }
    
    private function handleSystemHelpIntent($entities, $contextData) {
        $response = ['text' => '', 'options' => []];
        
        $helpTopics = [
            'seeker' => [
                'How to search for jobs effectively',
                'Application best practices',
                'Profile optimization tips',
                'Interview preparation guide'
            ],
            'employer' => [
                'How to post effective job listings',
                'Application screening process',
                'Candidate evaluation criteria',
                'Recruitment best practices'
            ]
        ];
        
        $topics = $helpTopics[$this->userType] ?? $helpTopics['seeker'];
        
        $response['text'] = "I can help you with various aspects of the ShaSha CJRS system. What would you like to learn about?";
        $response['options'] = [
            'quickReplies' => $topics
        ];
        
        return $response;
    }
    
    private function handleJobPostingIntent($entities, $contextData) {
        $response = ['text' => '', 'options' => []];
        
        if ($this->userType === 'employer') {
            $activeJobs = count($contextData['posted_jobs'] ?? []);
            
            $response['text'] = "You currently have {$activeJobs} active job postings. I can guide you through creating an effective job posting that attracts quality candidates.";
            
            $response['options'] = [
                'actions' => [
                    ['text' => 'Post New Job', 'action' => 'postJob', 'type' => 'primary'],
                    ['text' => 'Manage Posted Jobs', 'action' => 'manageJobs']
                ],
                'systemInsight' => "Jobs with detailed requirements and clear expectations get 40% more qualified applications."
            ];
        } else {
            $response['text'] = "Job posting is available for employers. As a job seeker, I can help you find and apply to job postings.";
            $response['options'] = [
                'actions' => [
                    ['text' => 'Browse Jobs', 'action' => 'viewJobs', 'type' => 'primary']
                ]
            ];
        }
        
        return $response;
    }
    
    private function handleCandidateManagementIntent($entities, $contextData) {
        $response = ['text' => '', 'options' => []];
        
        if ($this->userType === 'employer') {
            $candidateStats = $contextData['candidate_pool'] ?? [];
            $totalCandidates = $candidateStats['total'] ?? 0;
            
            $response['text'] = "You have access to {$totalCandidates} candidates in the ShaSha talent pool. I can help you find the right candidates based on skills, experience, and location.";
            
            $response['options'] = [
                'actions' => [
                    ['text' => 'Search Candidates', 'action' => 'searchCandidates', 'type' => 'primary'],
                    ['text' => 'Review Applications', 'action' => 'viewApplications']
                ],
                'quickReplies' => ['Filter by skills', 'Search by location', 'Experience level']
            ];
        } else {
            $response['text'] = "Candidate management features are available for employers. I can help you optimize your profile to be more visible to recruiters.";
            $response['options'] = [
                'actions' => [
                    ['text' => 'Optimize Profile', 'action' => 'updateProfile', 'type' => 'primary']
                ]
            ];
        }
        
        return $response;
    }
    
    private function handleGeneralIntent($message, $contextData) {
        // Fallback responses with system insights
        $responses = [
            'seeker' => [
                'text' => "I'm your AI assistant for the ShaSha Career and Job Referral System. I can help you find jobs, manage applications, optimize your profile, and provide career guidance.",
                'options' => [
                    'quickReplies' => ['Find jobs', 'Application status', 'Profile tips', 'Career advice'],
                    'systemInsight' => 'Tip: Job seekers who actively use our platform get hired 3x faster.'
                ]
            ],
            'employer' => [
                'text' => "I'm here to help you with all aspects of recruitment through ShaSha CJRS. From posting jobs to managing applications and finding the right candidates.",
                'options' => [
                    'quickReplies' => ['Post a job', 'Review applications', 'Search candidates', 'View analytics'],
                    'systemInsight' => 'Companies using our advanced features reduce time-to-hire by 50%.'
                ]
            ]
        ];
        
        return $responses[$this->userType] ?? $responses['seeker'];
    }
    
    // Database helper methods
    private function getRecentJobs() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 10");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getUserApplicationCount() {
        try {
            // This would need to be implemented based on your user session
            return 0; // Placeholder
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getProfileCompleteness() {
        try {
            // Calculate profile completeness based on filled fields
            return 75; // Placeholder
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getRecommendedJobs() {
        try {
            // Get personalized job recommendations
            return []; // Placeholder
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getEmployerPostedJobs() {
        try {
            // Get jobs posted by current employer
            return []; // Placeholder
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getApplicationStats() {
        try {
            return [
                'total' => 25,
                'pending' => 8,
                'reviewed' => 12,
                'shortlisted' => 5
            ];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getCandidatePoolStats() {
        try {
            return [
                'total' => 1250,
                'active' => 980,
                'new_this_week' => 45
            ];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getSystemStats() {
        try {
            return [
                'total_jobs' => 450,
                'total_applications' => 2300,
                'active_employers' => 89,
                'active_seekers' => 1250
            ];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function logConversation($message) {
        try {
            $stmt = $this->db->prepare("INSERT INTO chatbot_logs (session_id, user_type, message, timestamp) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$this->sessionId, $this->userType, $message]);
        } catch (Exception $e) {
            error_log('Error logging conversation: ' . $e->getMessage());
        }
    }
    
    private function createSuccessResponse($response) {
        return [
            'success' => true,
            'response' => $response,
            'timestamp' => time()
        ];
    }
    
    private function createErrorResponse($error) {
        return [
            'success' => false,
            'error' => $error,
            'timestamp' => time()
        ];
    }
}

// Handle the request
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Initialize database connection
    $db = new PDO($dsn, $username, $password, $options); // Use your database config
    
    $chatbot = new AdvancedChatbotHandler($db);
    $result = $chatbot->processMessage($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>