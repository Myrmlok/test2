<?php
// Database configuration
define('DB_PATH', __DIR__ . '/database.sqlite');

// JWT configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change-this-secret-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 7 * 24 * 60 * 60); // 7 days

// Cookie configuration
define('COOKIE_NAME', 'auth_token');
define('COOKIE_MAX_AGE', 7 * 24 * 60 * 60); // 7 days
define('COOKIE_PATH', '/');
define('COOKIE_SECURE', true);
define('COOKIE_HTTPONLY', true);
define('COOKIE_SAMESITE', 'Lax');

// CORS configuration
define('CORS_ALLOW_CREDENTIALS', true);
define('CORS_ALLOW_ORIGIN_REFLECT', true);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Timezone
date_default_timezone_set('UTC');
