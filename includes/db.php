<?php
/**
 * Database connection class for AmmooJobs platform
 * 
 * Provides centralized database connection and query methods
 * Last updated: 2025-05-02
 * Author: AmmooJobs Development Team
 */

class Database {
    private $connection;
    private $host = DB_HOST;
    private $dbname = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    /**
     * Constructor - Establish database connection
     */
    public function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password, $this->options);
            
            // Log connection if in debug mode
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("[" . date('Y-m-d H:i:s') . "] Database connection established successfully");
            }
        } catch (PDOException $e) {
            // Log error
            $errorMsg = "Database Connection Error: " . $e->getMessage();
            error_log($errorMsg);
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                // Show detailed error in development
                die("Database Connection Failed: " . $e->getMessage());
            } else {
                // Show generic error in production
                die("A database error has occurred. Please try again later or contact support.");
            }
        }
    }
    
    /**
     * Execute a query and return all results
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return array Results as associative array
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $params);
            return [];
        }
    }
    
    /**
     * Execute a query and return a single row
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return array|false Single result row or false if no results
     */
    public function fetchSingle($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $params);
            return false;
        }
    }
    
    /**
     * Execute a query that doesn't return results (INSERT, UPDATE, DELETE)
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return bool True on success, False on failure
     */
    public function executeNonQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $params);
            return false;
        }
    }
    
    /**
     * Execute a query and return the number of affected rows
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public function executeWithRowCount($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $params);
            return 0;
        }
    }
    
    /**
     * Get the ID of the last inserted row
     * 
     * @return string|false Last inserted ID or false on failure
     */
    public function getLastId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Begin a transaction
     * 
     * @return bool True on success, False on failure
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit a transaction
     * 
     * @return bool True on success, False on failure
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Roll back a transaction
     * 
     * @return bool True on success, False on failure
     */
    public function rollBack() {
        return $this->connection->rollBack();
    }
    
    /**
     * Check if a table exists in the database
     * 
     * @param string $tableName Name of the table to check
     * @return bool True if exists, False if not
     */
    public function tableExists($tableName) {
        try {
            $result = $this->connection->query("SHOW TABLES LIKE '{$tableName}'");
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            $this->handleError($e);
            return false;
        }
    }
    
    /**
     * Check if a column exists in a table
     * 
     * @param string $tableName Name of the table
     * @param string $columnName Name of the column to check
     * @return bool True if column exists, False if not
     */
    public function columnExists($tableName, $columnName) {
        try {
            // Sanitize input to prevent SQL injection
            $tableName = $this->escape($tableName);
            $columnName = $this->escape($columnName);
            
            // Query to check if column exists
            $stmt = $this->connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->handleError($e, "SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
            return false;
        }
    }
    
    /**
     * Get all column names for a table
     * 
     * @param string $tableName Name of the table
     * @return array Array of column names or empty array if error
     */
    public function getTableColumns($tableName) {
        try {
            // Sanitize input to prevent SQL injection
            $tableName = $this->escape($tableName);
            
            // Query to get all columns
            $stmt = $this->connection->query("SHOW COLUMNS FROM `{$tableName}`");
            $columns = [];
            
            while ($row = $stmt->fetch()) {
                $columns[] = $row['Field'];
            }
            
            return $columns;
        } catch (PDOException $e) {
            $this->handleError($e, "SHOW COLUMNS FROM `{$tableName}`");
            return [];
        }
    }
    
    /**
     * Escape a string for safe use in SQL queries
     * Note: Generally, you should use prepared statements instead
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    public function escape($string) {
        return substr($this->connection->quote($string), 1, -1);
    }
    
    /**
     * Get database connection status
     *
     * @return bool True if connected, False otherwise
     */
    public function isConnected() {
        return $this->connection !== null;
    }
    
    /**
     * Get PDO connection object (advanced usage)
     * 
     * @return PDO PDO connection object
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Log and handle database errors
     * 
     * @param PDOException $e Exception object
     * @param string $sql SQL query that caused the error (optional)
     * @param array $params Query parameters (optional)
     */
    private function handleError($e, $sql = '', $params = []) {
        // Build error message
        $errorMsg = "Database Error: " . $e->getMessage();
        
        // In debug mode, add query details
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $errorMsg .= "\nQuery: " . $sql;
            if (!empty($params)) {
                $errorMsg .= "\nParameters: " . json_encode($params);
            }
        }
        
        // Log error with timestamp
        $timestamp = date('Y-m-d H:i:s'); // Current time: 2025-05-02 09:37:45
        $currentUser = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'; // Current user: HasinduNimesh
        error_log("[{$timestamp}] [{$currentUser}] {$errorMsg}");
        
        // Additional error information for system administrators
        $errorDetails = [
            'timestamp' => $timestamp,
            'user' => $currentUser,
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'query' => $sql,
            'trace' => $e->getTraceAsString()
        ];
        
        // Store detailed error in database for admin review if possible
        if ($this->connection && $this->tableExists('system_errors')) {
            try {
                $errorStmt = $this->connection->prepare("
                    INSERT INTO system_errors (timestamp, user_id, error_type, error_message, error_details) 
                    VALUES (NOW(), ?, 'database', ?, ?)
                ");
                $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $errorStmt->execute([$userId, $e->getMessage(), json_encode($errorDetails)]);
            } catch (PDOException $logEx) {
                // If we can't log to database, at least write to error log
                error_log("Failed to log error to database: " . $logEx->getMessage());
            }
        }
        
        // In development mode, display errors
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;'>";
            echo "<h3>Database Error</h3>";
            echo "<p>Message: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>SQL: " . htmlspecialchars($sql) . "</p>";
            if (!empty($params)) {
                echo "<p>Parameters: " . htmlspecialchars(json_encode($params)) . "</p>";
            }
            echo "</div>";
        }
    }
    
    /**
     * Destructor - close connection
     */
    public function __destruct() {
        $this->connection = null;
    }
}

// Initialize database connection
$db = new Database();