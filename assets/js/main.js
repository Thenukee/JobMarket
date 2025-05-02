/**
 * AmmooJobs - Main JavaScript File
 * Handles core functionality, UI interactions, and AJAX requests
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 * @author AmmooJobs Development Team
 */

// Use strict mode for better error catching and preventing use of undeclared variables
'use strict';

// Main AmmooJobs object to prevent global namespace pollution
const AmmooJobs = {
    // Configuration and settings
    config: {
        apiUrl: '/api/v1',
        debugMode: false,
        notificationCheckInterval: 60000, // 60 seconds
        sessionTimeoutWarning: 1680000,   // 28 minutes (assuming 30-min sessions)
        sessionTimeoutRedirect: 1800000,  // 30 minutes
        maxUploadSize: 5242880,           // 5MB in bytes
        allowedImageTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        allowedResumeTypes: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    },

    // Current user information (populated on init if logged in)
    user: {
        id: null,
        name: null,
        userType: null,
        isLoggedIn: false
    },

    // Store DOM elements for reuse
    elements: {},

    // Initialize the application
    init: function() {
        // Log initialization in debug mode
        this.log('Initializing AmmooJobs application...');

        // Store commonly used elements
        this.cacheElements();
        
        // Set up event handlers
        this.setupEventListeners();
        
        // Initialize components
        this.initializeComponents();
        
        // Set up session monitoring
        this.setupSessionMonitoring();
        
        // Check for system messages on page load
        this.checkSystemMessages();
        
        // Start notification polling if user is logged in
        if (document.body.dataset.userLoggedIn === 'true') {
            this.user.isLoggedIn = true;
            this.user.id = document.body.dataset.userId || null;
            this.user.name = document.body.dataset.userName || null;
            this.user.userType = document.body.dataset.userType || null;
            
            // Start notification polling
            this.startNotificationPolling();
        }

        // Debug information
        this.log('Initialization complete!');
        this.log('Current time: 2025-05-01 17:28:05');
        this.log('Current user: HasinduNimesh');
    },

    // Cache DOM elements for reuse
    cacheElements: function() {
        this.elements = {
            body: document.body,
            notificationBell: document.getElementById('notificationDropdown'),
            notificationList: document.querySelector('.notification-list'),
            backToTop: document.getElementById('backToTop'),
            cookieBanner: document.getElementById('cookieConsentBanner'),
            forms: document.querySelectorAll('form:not(.no-validate)'),
            jobSearchForm: document.querySelector('.job-search-form'),
            passwordFields: document.querySelectorAll('.password-field'),
            fileUploads: document.querySelectorAll('.custom-file-upload'),
            themeToggle: document.getElementById('themeToggle')
        };
    },

    // Setup event listeners
    setupEventListeners: function() {
        // Back to top button functionality
        if (this.elements.backToTop) {
            window.addEventListener('scroll', this.handleScroll.bind(this));
            this.elements.backToTop.addEventListener('click', this.scrollToTop.bind(this));
        }

        // Cookie consent
        if (this.elements.cookieBanner) {
            const acceptButton = document.getElementById('acceptCookies');
            if (acceptButton) {
                acceptButton.addEventListener('click', this.acceptCookies.bind(this));
            }
        }

        // Form validation
        this.elements.forms.forEach(form => {
            form.addEventListener('submit', this.validateForm.bind(this));
        });

        // Password visibility toggle
        this.elements.passwordFields.forEach(field => {
            const toggleBtn = field.parentElement.querySelector('.password-toggle-btn');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', this.togglePasswordVisibility.bind(this));
            }
        });

        // File upload previews
        this.elements.fileUploads.forEach(upload => {
            const input = upload.querySelector('input[type="file"]');
            if (input) {
                input.addEventListener('change', this.handleFileUpload.bind(this));
            }
        });

        // Theme toggle
        if (this.elements.themeToggle) {
            this.elements.themeToggle.addEventListener('click', this.toggleTheme.bind(this));
        }

        // Job search form special handling
        if (this.elements.jobSearchForm) {
            this.elements.jobSearchForm.addEventListener('submit', this.handleJobSearch.bind(this));
        }

        // Anchor smooth scroll
        document.querySelectorAll('a[href^="#"]:not([data-bs-toggle])').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    },

    // Initialize various components
    initializeComponents: function() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Show cookie banner if not previously accepted
        if (this.elements.cookieBanner && !localStorage.getItem('cookieConsent')) {
            this.elements.cookieBanner.style.display = 'block';
        }

        // Activate star ratings
        this.initializeStarRatings();
    },

    // Handle scroll events
    handleScroll: function() {
        if (window.pageYOffset > 300) {
            this.elements.backToTop.classList.add('show');
        } else {
            this.elements.backToTop.classList.remove('show');
        }
    },

    // Scroll to top function
    scrollToTop: function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    },

    // Handle cookie acceptance
    acceptCookies: function() {
        localStorage.setItem('cookieConsent', 'accepted');
        this.elements.cookieBanner.style.display = 'none';
        
        // Optional: Log cookie acceptance via AJAX
        this.log('Cookies accepted by user');
    },

    // Form validation
    validateForm: function(e) {
        const form = e.target;
        
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }

        form.classList.add('was-validated');
    },

    // Toggle password visibility
    togglePasswordVisibility: function(e) {
        const button = e.currentTarget;
        const passwordInput = button.closest('.input-group').querySelector('input');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            button.setAttribute('title', 'Hide password');
        } else {
            passwordInput.type = 'password';
            button.innerHTML = '<i class="fas fa-eye"></i>';
            button.setAttribute('title', 'Show password');
        }

        // Refresh tooltip if present
        const tooltip = bootstrap.Tooltip.getInstance(button);
        if (tooltip) {
            tooltip.dispose();
            new bootstrap.Tooltip(button);
        }
    },

    // Handle file uploads
    handleFileUpload: function(e) {
        const input = e.target;
        const preview = document.getElementById(input.dataset.preview);
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // Check file size
            if (file.size > this.config.maxUploadSize) {
                this.showAlert('error', 'File is too large. Maximum size is 5MB.');
                input.value = '';
                return;
            }
            
            // Check file type based on input accept attribute
            const fileType = file.type;
            const inputAccept = input.accept.split(',').map(type => type.trim());
            
            if (inputAccept.length > 0 && !inputAccept.includes(fileType) && !inputAccept.includes('.' + file.name.split('.').pop())) {
                this.showAlert('error', 'Invalid file type. Please upload a valid file.');
                input.value = '';
                return;
            }

            // Show preview if it's an image and preview element exists
            if (preview && file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
            
            // Show filename
            const filenameElement = document.querySelector(`label[for="${input.id}"] .file-name`);
            if (filenameElement) {
                filenameElement.textContent = file.name;
            }
        }
    },

    // Toggle light/dark theme
    toggleTheme: function() {
        const currentTheme = localStorage.getItem('theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        localStorage.setItem('theme', newTheme);
        document.body.classList.toggle('dark-theme', newTheme === 'dark');
        
        // Send theme preference to server (optional)
        this.saveUserPreference('theme', newTheme);
        
        this.log(`Theme changed to ${newTheme}`);
    },

    // Handle job search form
    handleJobSearch: function(e) {
        // Check empty search
        const keywordInput = e.target.querySelector('input[name="keyword"]');
        const locationInput = e.target.querySelector('input[name="location"]');
        
        if (!keywordInput.value.trim() && !locationInput.value.trim()) {
            e.preventDefault();
            this.showAlert('info', 'Please enter a keyword or location to search for jobs.');
        }
    },

    // Initialize star ratings
    initializeStarRatings: function() {
        const ratingInputs = document.querySelectorAll('.rating-input');
        
        ratingInputs.forEach(container => {
            const stars = container.querySelectorAll('.rating-star');
            
            stars.forEach((star, index) => {
                star.addEventListener('mouseover', () => {
                    // Highlight stars on hover
                    for (let i = 0; i <= index; i++) {
                        stars[i].querySelector('i').className = 'fas fa-star text-warning';
                    }
                    for (let i = index + 1; i < stars.length; i++) {
                        stars[i].querySelector('i').className = 'far fa-star';
                    }
                });
                
                star.addEventListener('click', () => {
                    // Select the rating
                    const input = star.querySelector('input');
                    if (input) {
                        input.checked = true;
                        
                        // Reset all stars
                        stars.forEach(s => {
                            s.querySelector('i').className = 'far fa-star';
                        });
                        
                        // Fill in stars up to selected one
                        for (let i = 0; i <= index; i++) {
                            stars[i].querySelector('i').className = 'fas fa-star text-warning';
                        }
                    }
                });
            });
            
            // Reset stars on mouse leave
            container.addEventListener('mouseleave', () => {
                const checkedInput = container.querySelector('input:checked');
                const checkedValue = checkedInput ? parseInt(checkedInput.value) : 0;
                
                stars.forEach((star, index) => {
                    if (index < checkedValue) {
                        star.querySelector('i').className = 'fas fa-star text-warning';
                    } else {
                        star.querySelector('i').className = 'far fa-star';
                    }
                });
            });
        });
    },
    
    // Session monitoring
    setupSessionMonitoring: function() {
        if (!this.user.isLoggedIn) return;
        
        // Set timeout warning
        setTimeout(() => {
            this.showSessionTimeoutWarning();
        }, this.config.sessionTimeoutWarning);
        
        // Set session timeout redirect
        setTimeout(() => {
            window.location.href = '/logout.php?session_expired=1';
        }, this.config.sessionTimeoutRedirect);
        
        // Reset timers on user activity
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        events.forEach(event => {
            document.addEventListener(event, this.resetSessionTimers.bind(this), false);
        });
    },
    
    // Reset session timers
    resetSessionTimers: function() {
        // Only reset once per minute to prevent excessive AJAX calls
        if (this._lastReset && (Date.now() - this._lastReset) < 60000) {
            return;
        }
        
        this._lastReset = Date.now();
        
        // Clear existing timeouts
        if (this._sessionWarningTimeout) {
            clearTimeout(this._sessionWarningTimeout);
        }
        
        if (this._sessionRedirectTimeout) {
            clearTimeout(this._sessionRedirectTimeout);
        }
        
        // Set new timeouts
        this._sessionWarningTimeout = setTimeout(() => {
            this.showSessionTimeoutWarning();
        }, this.config.sessionTimeoutWarning);
        
        this._sessionRedirectTimeout = setTimeout(() => {
            window.location.href = '/logout.php?session_expired=1';
        }, this.config.sessionTimeoutRedirect);
        
        // Send heartbeat to server
        this.sendHeartbeat();
    },
    
    // Show session timeout warning
    showSessionTimeoutWarning: function() {
        const modal = new bootstrap.Modal(document.getElementById('sessionTimeoutModal'));
        const remainingTime = Math.floor((this.config.sessionTimeoutRedirect - this.config.sessionTimeoutWarning) / 60000);
        
        if (document.getElementById('sessionTimeoutModal')) {
            document.getElementById('remainingSessionTime').textContent = remainingTime;
            modal.show();
        } else {
            // Create modal dynamically if it doesn't exist
            const modalHTML = `
                <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Session Timeout Warning</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Your session will expire in <span id="remainingSessionTime">${remainingTime}</span> minutes due to inactivity.</p>
                                <p>Click "Continue Session" to stay logged in.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Logout Now</button>
                                <button type="button" class="btn btn-primary" id="extendSession">Continue Session</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHTML;
            document.body.appendChild(modalContainer);
            
            const modal = new bootstrap.Modal(document.getElementById('sessionTimeoutModal'));
            modal.show();
            
            // Add event listener for extend button
            document.getElementById('extendSession').addEventListener('click', () => {
                this.resetSessionTimers();
                modal.hide();
            });
        }
    },
    
    // Send heartbeat to keep session alive
    sendHeartbeat: function() {
        fetch('/api/heartbeat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(response => response.json())
        .then(data => {
            this.log('Heartbeat sent successfully');
        })
        .catch(error => {
            this.log('Error sending heartbeat:', error);
        });
    },

    // Start notification polling
    startNotificationPolling: function() {
        if (!this.user.isLoggedIn) return;
        
        // Check notifications immediately
        this.checkNotifications();
        
        // Set interval for checking
        this._notificationInterval = setInterval(this.checkNotifications.bind(this), this.config.notificationCheckInterval);
        
        this.log('Notification polling started');
    },

    // Check for new notifications
    checkNotifications: function() {
        if (!this.user.isLoggedIn) return;
        
        fetch('/api/notifications.php?unread_only=1', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateNotificationBadge(data.unread_count);
                
                // Update notification dropdown if it's currently open
                if (document.querySelector('.notification-dropdown.show')) {
                    this.updateNotificationList(data.notifications);
                }
                
                // Show desktop notification for new notifications
                if (data.new_notifications && data.new_notifications.length > 0) {
                    this.showDesktopNotification(data.new_notifications[0]);
                }
            }
        })
        .catch(error => {
            this.log('Error checking notifications:', error);
        });
    },

    // Update notification badge count
    updateNotificationBadge: function(count) {
        const badge = this.elements.notificationBell.querySelector('.badge');
        
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.style.display = 'block';
            } else {
                const newBadge = document.createElement('span');
                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                newBadge.textContent = count > 9 ? '9+' : count;
                newBadge.innerHTML += '<span class="visually-hidden">unread notifications</span>';
                this.elements.notificationBell.appendChild(newBadge);
            }
        } else if (badge) {
            badge.style.display = 'none';
        }
    },

    // Update notification list in dropdown
    updateNotificationList: function(notifications) {
        if (!this.elements.notificationList) return;
        
        if (notifications.length === 0) {
            this.elements.notificationList.innerHTML = '<li><div class="dropdown-item text-center py-3 text-muted">No notifications</div></li>';
            return;
        }
        
        let html = '';
        
        notifications.forEach(notification => {
            let iconClass = 'fas fa-bell';
            let bgClass = 'bg-info';
            
            switch (notification.type) {
                case 'application':
                    iconClass = 'far fa-file-alt';
                    bgClass = 'bg-primary';
                    break;
                case 'message':
                    iconClass = 'far fa-envelope';
                    bgClass = 'bg-success';
                    break;
                case 'alert':
                    iconClass = 'fas fa-exclamation-circle';
                    bgClass = 'bg-danger';
                    break;
            }
            
            const isUnread = notification.is_read == 0;
            
            html += `
                <li>
                    <a href="${notification.link ? this.htmlEscape(notification.link) : 'notifications.php?mark_read=' + notification.notification_id}" 
                       class="dropdown-item notification-item ${isUnread ? 'unread' : ''}">
                        <div class="d-flex align-items-center">
                            <div class="notification-icon rounded-circle me-3 ${bgClass}">
                                <i class="${iconClass} text-white"></i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1">${this.htmlEscape(notification.message)}</p>
                                <span class="small text-muted">${notification.time_ago}</span>
                            </div>
                            ${isUnread ? '<span class="unread-indicator"></span>' : ''}
                        </div>
                    </a>
                </li>
            `;
        });
        
        this.elements.notificationList.innerHTML = html;
    },

    // Show desktop notification
    showDesktopNotification: function(notification) {
        // Check if desktop notifications are supported and permitted
        if (!("Notification" in window)) {
            this.log("This browser does not support desktop notifications");
            return;
        }
        
        if (Notification.permission === "granted") {
            const title = "AmmooJobs Notification";
            const options = {
                body: notification.message,
                icon: "/assets/img/logo-small.png"
            };
            
            const n = new Notification(title, options);
            
            n.onclick = function() {
                window.focus();
                window.location.href = notification.link || 'notifications.php';
                n.close();
            };
            
            setTimeout(n.close.bind(n), 5000);
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    this.showDesktopNotification(notification);
                }
            });
        }
    },

    // Save user preference to server
    saveUserPreference: function(key, value) {
        if (!this.user.isLoggedIn) return;
        
        fetch('/api/save_preference.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ key: key, value: value })
        })
        .then(response => response.json())
        .then(data => {
            this.log(`User preference saved: ${key}=${value}`);
        })
        .catch(error => {
            this.log('Error saving user preference:', error);
        });
    },

    // Check for system messages
    checkSystemMessages: function() {
        // System maintenance check
        if (document.body.dataset.maintenanceMode === 'true') {
            this.showAlert('warning', 'System maintenance is scheduled. Some features may be temporarily unavailable.', 10000);
        }
    },

    // Show alert message
    showAlert: function(type, message, duration = 5000) {
        const alertContainer = document.getElementById('alertContainer') || this.createAlertContainer();
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.role = 'alert';
        
        let icon = 'info-circle';
        switch (type) {
            case 'success': icon = 'check-circle'; break;
            case 'danger': icon = 'exclamation-circle'; break;
            case 'warning': icon = 'exclamation-triangle'; break;
        }
        
        alert.innerHTML = `
            <i class="fas fa-${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Remove after duration
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }
        }, duration);
        
        return alert;
    },

    // Create alert container if it doesn't exist
    createAlertContainer: function() {
        const container = document.createElement('div');
        container.id = 'alertContainer';
        container.className = 'alert-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1050';
        document.body.appendChild(container);
        return container;
    },

    // HTML escape helper
    htmlEscape: function(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    // Logging function (only logs in debug mode)
    log: function(...args) {
        if (this.config.debugMode) {
            console.log('[AmmooJobs]', ...args);
        }
    }
};

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the application
    AmmooJobs.init();
    
    // Initialize any page-specific scripts (to be overridden by page scripts)
    if (typeof PageScripts !== 'undefined' && typeof PageScripts.init === 'function') {
        PageScripts.init();
    }
});

// Expose AmmooJobs to global scope for debugging
window.AmmooJobs = AmmooJobs;

/**
 * Generated by HasinduNimesh
 * Last updated: 2025-05-01 17:28:05
 */