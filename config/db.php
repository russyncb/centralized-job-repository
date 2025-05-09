<?php
// Database Configuration for ShaSha CJRS

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Check if we're in a production environment (Render)
        if (getenv('DB_HOST')) {
            // Use environment variables in production
            $this->host = getenv('DB_HOST');
            $this->db_name = getenv('DB_DATABASE');
            $this->username = getenv('DB_USERNAME');
            $this->password = getenv('DB_PASSWORD');
        } else {
            // Use local development settings
            $this->host = 'localhost';
            $this->db_name = 'shasha_db';
            $this->username = 'root';
            $this->password = '';
        }
    }

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->conn;
    }
}
?>