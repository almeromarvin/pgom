<?php
// Database connection parameters
$host = 'localhost';
$dbname = 'pgom_facilities';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create user_history table
  
    
    // Add indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_history_user_id ON user_history(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_history_admin_id ON user_history(admin_id)");
    
    echo "User history table created successfully!";
    
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 