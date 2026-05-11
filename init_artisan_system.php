<?php
/**
 * Initialize Artisan Management System
 * Run this script once to create all artisan tables
 * Usage: php init_artisan_system.php
 */

require_once 'config.inc.php';
require_once 'libraries/artisan_schema.php';

echo "==================================\n";
echo "Artisan Management System Initialization\n";
echo "==================================\n\n";

// Create schema
echo "Creating artisan database tables...\n\n";
$result = create_artisan_tables();

if ($result) {
    echo "\n✅ Artisan management system initialized successfully!\n";
    echo "\n📝 Next steps:\n";
    echo "1. Navigate to manage_artisans.php to create technician profiles\n";
    echo "2. Add skills and certifications to technicians\n";
    echo "3. Assign technicians to sites\n";
    echo "4. Technician performance will be automatically tracked\n";
} else {
    echo "\n❌ Error initializing artisan system\n";
    exit(1);
}
?>
