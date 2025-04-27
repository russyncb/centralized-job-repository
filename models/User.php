<?php
// User Model - use absolute paths
require_once BASE_PATH . '/config/db.php';

class User {
    // Database connection and table name
    private $conn;
    private $table_name = "users";
    
    // User properties
    public $user_id;
    public $email;
    public $password;
    public $role;
    public $first_name;
    public $last_name;
    public $phone;
    public $profile_image;
    public $created_at;
    public $updated_at;
    public $last_login;
    public $status;
    
    // Constructor with database connection
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Get user by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->user_id = $row['user_id'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->role = $row['role'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->phone = $row['phone'];
            $this->profile_image = $row['profile_image'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_login = $row['last_login'];
            $this->status = $row['status'];
            return true;
        }
        
        return false;
    }
    
    // Get user by email
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->user_id = $row['user_id'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->role = $row['role'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->phone = $row['phone'];
            $this->profile_image = $row['profile_image'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_login = $row['last_login'];
            $this->status = $row['status'];
            return true;
        }
        
        return false;
    }
    
    // Create new user
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (email, password, role, first_name, last_name, phone, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT); // Hash the password
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind parameters
        $stmt->bindParam(1, $this->email);
        $stmt->bindParam(2, $this->password);
        $stmt->bindParam(3, $this->role);
        $stmt->bindParam(4, $this->first_name);
        $stmt->bindParam(5, $this->last_name);
        $stmt->bindParam(6, $this->phone);
        $stmt->bindParam(7, $this->status);
        
        // Execute query
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Update user details
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET first_name = ?, last_name = ?, phone = ?, status = ? 
                 WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind parameters
        $stmt->bindParam(1, $this->first_name);
        $stmt->bindParam(2, $this->last_name);
        $stmt->bindParam(3, $this->phone);
        $stmt->bindParam(4, $this->status);
        $stmt->bindParam(5, $this->user_id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Update user's last login
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . " 
                 SET last_login = NOW() 
                 WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->user_id);
        
        return $stmt->execute();
    }
    
    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }
}
?>