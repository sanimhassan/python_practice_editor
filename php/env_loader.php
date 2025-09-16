<?php
/**
 * Environment variables loader
 * Loads environment variables from .env file or environment
 */

class EnvLoader {
    private static $variables = [];
    
    /**
     * Load environment variables from .env file
     * @param string $path Path to .env file
     * @return bool Success status
     */
    public static function load($path = null) {
        if ($path === null) {
            // Default to project root (one level up from php directory)
            $path = dirname(__DIR__) . '/.env';
        }
        
        if (!file_exists($path)) {
            error_log("Warning: .env file not found at $path");
            return false;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse line
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Store variable
            self::$variables[$name] = $value;
            
            // Also set as environment variable if not already set
            if (!getenv($name)) {
                putenv("$name=$value");
            }
        }
        
        return true;
    }
    
    /**
     * Get environment variable value
     * @param string $name Variable name
     * @param mixed $default Default value if not found
     * @return mixed Variable value or default
     */
    public static function get($name, $default = null) {
        // First check actual environment
        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }
        
        // Then check loaded variables
        if (isset(self::$variables[$name])) {
            return self::$variables[$name];
        }
        
        // Return default if not found
        return $default;
    }
}

// Auto-load environment variables when included
EnvLoader::load();

/**
 * Helper function to get environment variables
 * @param string $name Variable name
 * @param mixed $default Default value if not found
 * @return mixed Variable value or default
 */
function env($name, $default = null) {
    return EnvLoader::get($name, $default);
}
