<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/router.php';

// Initialize session
initializeSession();

// Ensure database exists
ensureDatabaseExists();

// Get database connection
$pdo = getConnection();

// Initialize database schema
initializeSchema($pdo);

// Seed lookup tables
seedLookupTables($pdo);

// Check if this is an API request
if (isset($_GET['api'])) {
    // Handle API requests through router
    $router = new Router($pdo);
    $router->dispatch();
} else {
    // Render HTML view
    $lookups = getLookupData($pdo);
    require __DIR__ . '/view.php';
}
