<?php
// Database Configuration for ShaSha CJRS

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private $driver;

    public function __construct() {
        // Check if we're in a production environment (Render)
        if (getenv('DB_HOST')) {
            // Use environment variables in production
            $this->host = getenv('DB_HOST');
            $this->port = getenv('DB_PORT') ? getenv('DB_PORT') : '5432'; // PostgreSQL default port
            $this->db_name = getenv('DB_DATABASE');
            $this->username = getenv('DB_USERNAME');
            $this->password = getenv('DB_PASSWORD');
            $this->driver = 'pgsql'; // PostgreSQL on Render
        } else {
            // Use local development settings with MySQL
            $this->host = 'localhost';
            $this->port = '3306'; // MySQL default port
            $this->db_name = 'shasha_db';
            $this->username = 'root';
            $this->password = '';
            $this->driver = 'mysql'; // MySQL locally
        }
    }

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            if ($this->driver == 'mysql') {
                // MySQL connection
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
            } else {
                // PostgreSQL connection
                $this->conn = new PDO(
                    "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";sslmode=require",
                    $this->username,
                    $this->password
                );
            }
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if ($this->driver == 'mysql') {
                $this->conn->exec("set names utf8");
            }
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->conn;
    }
}
?>