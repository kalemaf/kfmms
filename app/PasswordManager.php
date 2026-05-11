<?php
/**
 * Password Management for CMMS
 * Handles temporary password generation, validation, and first-login password changes
 */

class PasswordManager {
    
    /**
     * Generate a random temporary password
     * Format: 8-12 characters with mix of uppercase, lowercase, numbers, and symbols
     *
     * @return string Generated temporary password
     */
    public static function generateTemporaryPassword($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%&*-_+=';
        
        $all_chars = $uppercase . $lowercase . $numbers . $symbols;
        $password = '';
        
        // Ensure at least one character from each category
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
        }
        
        // Shuffle the password to avoid predictable patterns
        $password = str_shuffle($password);
        
        return $password;
    }
    
    /**
     * Format password for display in email/print
     * Breaks long password into chunks for readability
     *
     * @param string $password The password to format
     * @param int $chunk_size Characters per chunk (default: 4)
     * @return string Formatted password
     */
    public static function formatPasswordForDisplay($password, $chunk_size = 4) {
        $chunks = str_split($password, $chunk_size);
        return implode(' - ', $chunks);
    }
    
    /**
     * Validate a password meets security requirements
     * Requirements: 
     * - Minimum 8 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one number
     * - At least one special character or uppercase letter
     *
     * @param string $password Password to validate
     * @return array ['valid' => bool, 'errors' => array of error messages]
     */
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        // Check for special characters or multiple uppercase
        $has_special = preg_match('/[!@#$%&*\-_+=\[\]{};:\'",.<>?\/\\|`~]/', $password);
        $uppercase_count = preg_match_all('/[A-Z]/', $password);
        
        if (!$has_special && $uppercase_count < 2) {
            $errors[] = 'Password must contain a special character or at least two uppercase letters.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Hash a password using BCRYPT
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify a plain text password against a hash
     *
     * @param string $password Plain text password to verify
     * @param string $hash Hashed password to verify against
     * @return bool True if password matches hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if password needs rehashing (for hash upgrades)
     *
     * @param string $hash Password hash to check
     * @return bool True if password should be rehashed
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

?>
