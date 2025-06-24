<?php
/**
 * Generate Encryption Key Script
 * 
 * This script generates a secure 32-character encryption key for AES-256-CBC.
 * Run this script once to generate a key, then copy the output to your encryption_config.php file.
 * 
 * Usage: php generate_encryption_key.php
 */

echo "=== Secure Encryption Key Generator ===\n\n";

// Generate a secure 32-character key
$key = base64_encode(random_bytes(32));

echo "Generated Encryption Key:\n";
echo "========================\n";
echo $key . "\n\n";

echo "Instructions:\n";
echo "=============\n";
echo "1. Copy the key above\n";
echo "2. Open config/encryption_config.php\n";
echo "3. Replace 'your-secret-key-32-chars-long!' with the generated key\n";
echo "4. Save the file\n";
echo "5. Delete this script for security\n\n";

echo "Example:\n";
echo "========\n";
echo "Change this line in encryption_config.php:\n";
echo "define('ENCRYPTION_KEY', 'your-secret-key-32-chars-long!');\n\n";
echo "To:\n";
echo "define('ENCRYPTION_KEY', '" . $key . "');\n\n";

echo "Security Notes:\n";
echo "==============\n";
echo "- Keep this key secret and secure\n";
echo "- Never share or commit this key to version control\n";
echo "- In production, use environment variables\n";
echo "- The encryption_config.php file is already in .gitignore\n\n";

echo "Press Enter to exit...";
fgets(STDIN);
?> 