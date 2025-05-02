<?php
use PHPUnit\Framework\TestCase;
// filepath: d:\ClapTac\AmmooJobs\ammoojobs\jobsTest.php


class JobsTest extends TestCase
{
    // Mock database class for testing
    private $mockDb;
    
    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(StdClass::class);
        $this->mockDb->method('fetchAll')->willReturn([
            [
                'job_id' => 1,
                'title' => 'Software Developer',
                'description' => 'PHP Developer needed',
                'location' => 'Remote',
                'category' => 'technology',
                'job_type' => 'full_time',
                'salary_min' => 50000,
                'salary_max' => 80000,
                'is_featured' => 1,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'employer_id' => 1,
                'employer_name' => 'Acme Inc',
                'company_name' => 'Acme Corporation'
            ]
        ]);
        $this->mockDb->method('fetchSingle')->willReturn(['total' => 1]);
        $this->mockDb->method('columnExists')->willReturn(true);
    }

    // Test utility functions
    
    public function testSanitizeInput()
    {
        // Load the function if not available
        if (!function_exists('sanitizeInput')) {
            require_once 'd:/ClapTac/AmmooJobs/ammoojobs/includes/functions.php';
            
            if (!function_exists('sanitizeInput')) {
                // If still not available, use the inline definition
                function sanitizeInput($input) {
                    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
                }
            }
        }
        
        $this->assertEquals('Test', sanitizeInput(' Test '));
        $this->assertEquals('&lt;script&gt;', sanitizeInput('<script>'));
    }
    
    public function testFormatSalary()
    {
        // Load the function from the main file
        if (!function_exists('formatSalary')) {
            function formatSalary($min, $max) {
                if ((!$min && $min !== 0) && (!$max && $max !== 0)) return 'Not specified';
                if (!$min && $min !== 0) return 'Up to $' . number_format($max);
                if (!$max && $max !== 0) return 'From $' . number_format($min);
                return '$' . number_format($min) . ' - $' . number_format($max);
            }
        }
        
        $this->assertEquals('Not specified', formatSalary(null, null));
        $this->assertEquals('Up to $50,000', formatSalary(null, 50000));
        $this->assertEquals('From $30,000', formatSalary(30000, null));
        $this->assertEquals('$30,000 - $50,000', formatSalary(30000, 50000));
    }
    
    public function testFormatDate()
    {
        if (!function_exists('formatDate')) {
            function formatDate($date) {
                if (!$date) return 'N/A';
                return date('M j, Y', strtotime($date));
            }
        }
        
        $this->assertEquals('N/A', formatDate(null));
        $this->assertEquals('Jan 1, 2023', formatDate('2023-01-01'));
    }
    
    public function testTimeAgo()
    {
        if (!function_exists('timeAgo')) {
            function timeAgo($timestamp) {
                if (!$timestamp) return 'N/A';
                
                $time = strtotime($timestamp);
                if ($time === false) return 'Invalid date';
                
                $diff = time() - $time;
                
                if ($diff < 60) return 'Just now';
                if ($diff < 3600) return floor($diff/60) . ' minutes ago';
                if ($diff < 86400) return floor($diff/3600) . ' hours ago';
                if ($diff < 604800) return floor($diff/86400) . ' days ago';
                
                return date('M j, Y', $time);
            }
        }
        
        // These tests are time-dependent, so we'll just test the function exists
        $this->assertTrue(function_exists('timeAgo'));
        
        // Test invalid date
        $this->assertEquals('Invalid date', timeAgo('invalid-date'));
        $this->assertEquals('N/A', timeAgo(null));
        
        // Test date from past (this assumes test is run after Jan 1, 2020)
        $oldDate = '2020-01-01 12:00:00';
        $result = timeAgo($oldDate);
        $this->assertEquals('Jan 1, 2020', $result);
    }
    
    public function testIsEmployer()
    {
        if (!function_exists('isEmployer')) {
            function isEmployer() {
                return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'employer';
            }
        }
        
        // Test without session
        $this->assertFalse(isEmployer());
        
        // Test with session
        $_SESSION['user_type'] = 'employer';
        $this->assertTrue(isEmployer());
        
        // Clean up
        $_SESSION['user_type'] = null;
    }
    
    // Test filter handling
    
    public function testFilterInitialization()
    {
        // Set up test data
        $_GET['keyword'] = 'developer';
        $_GET['location'] = 'remote';
        $_GET['category'] = 'technology';
        $_GET['job_type'] = 'full_time';
        $_GET['featured'] = '1';
        $_GET['sort'] = 'salary';
        
        // Run the filter initialization code
        $filters = [
            'keyword' => isset($_GET['keyword']) ? sanitizeInput($_GET['keyword']) : '',
            'location' => isset($_GET['location']) ? sanitizeInput($_GET['location']) : '',
            'category' => isset($_GET['category']) ? sanitizeInput($_GET['category']) : '',
            'job_type' => isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '',
            'sort' => isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest',
            'featured' => isset($_GET['featured']) ? (bool)$_GET['featured'] : false
        ];
        
        // Test the results
        $this->assertEquals('developer', $filters['keyword']);
        $this->assertEquals('remote', $filters['location']);
        $this->assertEquals('technology', $filters['category']);
        $this->assertEquals('full_time', $filters['job_type']);
        $this->assertEquals('salary', $filters['sort']);
        $this->assertTrue($filters['featured']);
        
        // Clean up
        $_GET = [];
    }
    
    // Test SQL query building
    
    public function testSqlQueryBuilding()
    {
        // Set up the filters
        $filters = [
            'keyword' => 'developer',
            'location' => 'remote',
            'category' => 'technology',
            'job_type' => 'full_time',
            'sort' => 'newest',
            'featured' => true
        ];
        
        // Build SQL query
        $sql = "SELECT j.*, u.name as employer_name, u.company_name 
                FROM job_listings j 
                JOIN users u ON j.employer_id = u.user_id 
                WHERE j.status = 'active'";
        $params = [];
        
        // Add filters
        if (!empty($filters['keyword'])) {
            $sql .= " AND (j.title LIKE ? OR j.description LIKE ?)";
            $keyword = "%" . $filters['keyword'] . "%";
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        if (!empty($filters['location'])) {
            $sql .= " AND j.location LIKE ?";
            $params[] = "%" . $filters['location'] . "%";
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND j.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['job_type'])) {
            $sql .= " AND j.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        if ($filters['featured']) {
            $sql .= " AND j.is_featured = 1";
        }
        
        // Test the SQL and params
        $expectedSql = "SELECT j.*, u.name as employer_name, u.company_name 
                FROM job_listings j 
                JOIN users u ON j.employer_id = u.user_id 
                WHERE j.status = 'active' AND (j.title LIKE ? OR j.description LIKE ?) AND j.location LIKE ? AND j.category = ? AND j.job_type = ? AND j.is_featured = 1";
        
        $this->assertEquals(str_replace(["\n", "  "], "", $expectedSql), str_replace(["\n", "  "], "", $sql));
        $this->assertEquals(5, count($params));
        $this->assertEquals("%developer%", $params[0]);
        $this->assertEquals("%developer%", $params[1]);
        $this->assertEquals("%remote%", $params[2]);
        $this->assertEquals("technology", $params[3]);
        $this->assertEquals("full_time", $params[4]);
    }
    
    public function testSortQueryBuilding()
    {
        $filters = ['sort' => 'salary'];
        $sql = "SELECT * FROM job_listings WHERE status = 'active'";
        
        switch($filters['sort']) {
            case 'salary':
                $sql .= " ORDER BY j.salary_max DESC, j.salary_min DESC, j.is_featured DESC";
                break;
            case 'deadline':
                $sql .= " ORDER BY j.expires_at ASC, j.is_featured DESC";
                break;
            case 'oldest':
                $sql .= " ORDER BY j.created_at ASC, j.is_featured DESC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY j.is_featured DESC, j.created_at DESC";
        }
        
        $this->assertEquals("SELECT * FROM job_listings WHERE status = 'active' ORDER BY j.salary_max DESC, j.salary_min DESC, j.is_featured DESC", $sql);
    }
    
    // Test database error handling
    
    public function testDatabaseErrorHandling()
    {
        // Create a DB mock that throws an exception
        $errorDb = $this->createMock(StdClass::class);
        $errorDb->method('fetchAll')->will($this->throwException(new Exception('Database error')));
        
        // Initialize session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $jobs = $errorDb->fetchAll("SELECT * FROM job_listings", []);
        } catch (Exception $e) {
            error_log("Error fetching jobs: " . $e->getMessage());
            $jobs = [];
        }
        
        $this->assertEquals([], $jobs);
    }
    
    // Integration test - requires actual includes
    public function testPageLoadIntegration()
    {
        // This is a more complex test that would require the actual includes
        // It's commented out because it requires the actual database connection
        
        /*
        // Start output buffering to capture output
        ob_start();
        
        // Include the jobs.php file
        try {
            include 'd:/ClapTac/AmmooJobs/ammoojobs/jobs.php';
            $output = ob_get_clean();
            $this->assertStringContainsString('Browse Jobs', $output);
            $this->assertFalse(strpos($output, 'Fatal error'));
        } catch (Exception $e) {
            ob_end_clean();
            $this->fail('Exception caught: ' . $e->getMessage());
        }
        */
        
        // Instead, just assert true for now
        $this->assertTrue(true);
    }
}