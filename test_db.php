<?php
// Test database connection
require_once 'config/db.php';

// Create database object
$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "<h1>Database connection successful!</h1>";
    echo "<p>You've successfully connected to the ShaSha database.</p>";
    
    // Test query to verify tables
    try {
        $query = "SHOW TABLES";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        echo "<h2>Database Tables:</h2>";
        echo "<ul>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<li>" . $row['Tables_in_shasha_db'] . "</li>";
        }
        echo "</ul>";
        
    } catch(PDOException $e) {
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h1>Database connection failed!</h1>";
    echo "<p>Please check your database configuration in config/db.php</p>";
}
?>