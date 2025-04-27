<?php
// Authentication Controller - use absolute paths
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/models/User.php';


class AuthController {
    private $db;
    private $user;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }
    
    // Handle login process
    public function login($email, $password) {
        // Check if user exists
        if($this->user->getByEmail($email)) {
            // Verify password
            if($this->user->verifyPassword($password)) {
                // Check if user is active
                if($this->user->status != 'active') {
                    return ['success' => false, 'message' => 'Your account is not active. Please contact admin.'];
                }
                
                // Update last login time
                $this->user->updateLastLogin();
                
                // Set session data
                $_SESSION['user_id'] = $this->user->user_id;
                $_SESSION['email'] = $this->user->email;
                $_SESSION['role'] = $this->user->role;
                $_SESSION['first_name'] = $this->user->first_name;
                $_SESSION['last_name'] = $this->user->last_name;
                $_SESSION['logged_in'] = true;
                
                return ['success' => true, 'user' => $this->user, 'message' => 'Login successful!'];
            } else {
                return ['success' => false, 'message' => 'Invalid password.'];
            }
        } else {
            return ['success' => false, 'message' => 'User not found.'];
        }
    }
    
    // Handle registration process
    public function register($userData) {
        // Check if email already exists
        if($this->user->getByEmail($userData['email'])) {
            return ['success' => false, 'message' => 'Email is already registered.'];
        }
        
        // Set user properties
        $this->user->email = $userData['email'];
        $this->user->password = $userData['password'];
        $this->user->role = $userData['role'];
        $this->user->first_name = $userData['first_name'];
        $this->user->last_name = $userData['last_name'];
        $this->user->phone = $userData['phone'] ?? '';
        $this->user->status = $userData['role'] == 'jobseeker' ? 'active' : 'pending'; // Jobseekers active by default, employers need verification
        
        // Create user
        $user_id = $this->user->create();
        
        if($user_id) {
            // Create role-specific profile
            switch($userData['role']) {
                case 'employer':
                    // Create employer profile
                    $query = "INSERT INTO employer_profiles (user_id, company_name) VALUES (?, ?)";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->bindParam(2, $userData['company_name']);
                    $stmt->execute();
                    break;
                    
                case 'jobseeker':
                    // Create jobseeker profile
                    $query = "INSERT INTO jobseeker_profiles (user_id) VALUES (?)";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->execute();
                    break;
                    
                case 'admin':
                    // Create admin profile
                    $query = "INSERT INTO admin_profiles (user_id) VALUES (?)";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(1, $user_id);
                    $stmt->execute();
                    break;
            }
            
            return ['success' => true, 'user_id' => $user_id, 'message' => 'Registration successful!'];
        } else {
            return ['success' => false, 'message' => 'Registration failed.'];
        }
    }
    
    // Handle logout process
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy the session
        session_destroy();
        
        return ['success' => true, 'message' => 'Logout successful!'];
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Check if user has specific role
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }
}
?>