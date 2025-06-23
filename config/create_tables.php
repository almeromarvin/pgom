<?php
require_once 'database.php';

try {
    // Create user_history table
    $sql = "CREATE TABLE IF NOT EXISTS user_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        admin_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (admin_id) REFERENCES users(id)
    )";
    
    $pdo->exec($sql);
    
    // Add indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_history_user_id ON user_history(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_history_admin_id ON user_history(admin_id)");
    
    echo "User history table created successfully!\n";
    
} catch(PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?> 