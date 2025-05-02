<?php
/**
 * Footer template for AmmooJobs platform
 * Includes closing HTML tags, footer content, and JavaScript includes
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 */

// Calculate page load time if debug mode is enabled
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $pageEndTime = microtime(true);
    $pageLoadTime = round(($pageEndTime - $GLOBALS['page_start_time']), 4);
}
?>

    </div><!-- End .container main-content -->
    
    <!-- Footer -->
    <footer class="footer mt-auto py-4 bg-dark text-light">
        <div class="container">
            <div class="row">
                <!-- Company Info -->
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5 class="text-uppercase">AmmooJobs</h5>
                    <p class="small">
                        Connecting talented professionals with 
                        the best employment opportunities since 2023.
                    </p>
                    <div class="social-links">
                        <a href="https://facebook.com/ammoojobs" class="text-light me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/ammoojobs" class="text-light me-2"><i class="fab fa-twitter"></i></a>
                        <a href="https://linkedin.com/company/ammoojobs" class="text-light me-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="https://instagram.com/ammoojobs" class="text-light"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="jobs.php" class="text-light">Browse Jobs</a></li>
                        <li><a href="employers.php" class="text-light">Browse Employers</a></li>
                        <li><a href="reviews.php" class="text-light">Company Reviews</a></li>
                        <li><a href="resources.php" class="text-light">Career Resources</a></li>
                        <li><a href="pricing.php" class="text-light">Pricing</a></li>
                    </ul>
                </div>
                
                <!-- For Employers -->
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5>For Employers</h5>
                    <ul class="list-unstyled">
                        <li><a href="post_job.php" class="text-light">Post a Job</a></li>
                        <li><a href="search_resumes.php" class="text-light">Search Resumes</a></li>
                        <li><a href="employer_resources.php" class="text-light">Recruitment Resources</a></li>
                        <li><a href="employer_pricing.php" class="text-light">Employer Plans</a></li>
                    </ul>
                </div>
                
                <!-- About & Support -->
                <div class="col-lg-3 col-md-6">
                    <h5>About & Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="about.php" class="text-light">About Us</a></li>
                        <li><a href="contact.php" class="text-light">Contact Us</a></li>
                        <li><a href="faq.php" class="text-light">FAQ</a></li>
                        <li><a href="privacy.php" class="text-light">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-light">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-3 bg-light">
            
            <!-- Bottom Footer -->
            <div class="row align-items-center">
                <div class="col-md-8 text-center text-md-start">
                    <p class="small mb-0">
                        &copy; <?= COPYRIGHT_YEAR ?> AmmooJobs. All rights reserved.
                        <span class="d-none d-md-inline">|</span>
                        <br class="d-md-none">
                        <span class="ms-md-2">Version <?= VERSION ?></span>
                    </p>
                </div>
                <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                    <div class="dropdown dropup">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe me-1"></i> English
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                            <li><a class="dropdown-item active" href="#">English</a></li>
                            <li><a class="dropdown-item" href="#">Español</a></li>
                            <li><a class="dropdown-item" href="#">Français</a></li>
                            <li><a class="dropdown-item" href="#">Deutsch</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <!-- Debug Information (only visible in DEBUG_MODE) -->
                <div class="debug-info mt-3 p-2 bg-dark border border-secondary rounded small">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted">Page generated in <?= $pageLoadTime ?? 'N/A' ?> seconds</p>
                            <p class="mb-1 text-muted">Current server time (UTC): <?= date('Y-m-d H:i:s') ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1 text-muted">
                                User: <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Guest' ?>
                                (<?= isset($_SESSION['user_type']) ? ucfirst($_SESSION['user_type']) : 'Not logged in' ?>)
                            </p>
                            <p class="mb-1 text-muted">Session ID: <?= session_id() ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </footer>
    
    <!-- Cookie Consent Banner -->
    <div id="cookieConsentBanner" class="cookie-banner" style="display: none;">
        <div class="container">
            <div class="row align-items-center py-3">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <p class="mb-0">
                        This website uses cookies to ensure you get the best experience. By using our website, you agree to our
                        <a href="privacy.php" class="text-primary">privacy policy</a>.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <button id="acceptCookies" class="btn btn-sm btn-primary me-2">Accept All Cookies</button>
                    <button id="cookieSettings" class="btn btn-sm btn-outline-secondary">Cookie Settings</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back to top button -->
    <a href="#" class="back-to-top" id="backToTop">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js?v=<?= VERSION ?>"></script>
    
    <?php if (isset($pageScripts) && is_array($pageScripts)): ?>
        <!-- Page Specific Scripts -->
        <?php foreach($pageScripts as $script): ?>
            <script src="<?= $script ?>?v=<?= VERSION ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Live Chat Widget (only displays for logged in users) -->
    <?php if (isLoggedIn()): ?>
    <script>
        (function() {
            var s = document.createElement('script');
            s.src = 'https://widget.livechat.com/ammoojobs.js';
            s.async = true;
            document.body.appendChild(s);
        })();
    </script>
    <?php endif; ?>
    
    <script>
        // Display cookie consent banner if not previously accepted
        document.addEventListener('DOMContentLoaded', function() {
            if (!localStorage.getItem('cookieConsent')) {
                document.getElementById('cookieConsentBanner').style.display = 'block';
            }
            
            document.getElementById('acceptCookies').addEventListener('click', function() {
                localStorage.setItem('cookieConsent', 'accepted');
                document.getElementById('cookieConsentBanner').style.display = 'none';
            });
            
            document.getElementById('cookieSettings').addEventListener('click', function() {
                window.location.href = 'cookie-policy.php';
            });
            
            // Back to top button functionality
            const backToTopButton = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopButton.classList.add('show');
                } else {
                    backToTopButton.classList.remove('show');
                }
            });
            
            backToTopButton.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
        });
    </script>
    
    <?php
    // Output current date and user in a comment for debugging purposes
    $currentDate = date('Y-m-d H:i:s'); // Current server time: 2025-05-01 17:07:03
    $currentUser = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'; // Current user: HasinduNimesh
    echo "<!-- Page rendered at $currentDate by $currentUser -->";
    ?>
</body>
</html>