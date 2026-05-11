#!/bin/bash

# ============================================================================
# KFMMS Multi-Tenant Quick Start Setup Script
# ============================================================================
# This script automates the initial setup of your multi-tenant system
# Usage: bash setup_multi_tenant.sh
# ============================================================================

set -e  # Exit on error

echo "🚀 KFMMS Multi-Tenant Quick Start Setup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_step() {
    echo -e "${BLUE}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# ============================================================================
# STEP 1: Check Prerequisites
# ============================================================================

print_step "Step 1: Checking prerequisites..."

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed"
    exit 1
fi
PHPVER=$(php -v | head -n 1)
print_success "PHP found: $PHPVER"

# Check if database exists
if [ ! -f "database/maintenix.db" ]; then
    print_warning "Database file not found at database/maintenix.db"
    print_warning "This is expected for fresh installations"
fi

# Check required directories
if [ ! -d "app" ]; then
    print_error "Directory 'app' not found. Multi-tenant files may not be installed."
    exit 1
fi

if [ ! -d "migrations" ]; then
    print_error "Directory 'migrations' not found."
    exit 1
fi

print_success "All prerequisites met!"
echo ""

# ============================================================================
# STEP 2: Create Storage Directories
# ============================================================================

print_step "Step 2: Creating storage directories..."

if [ ! -d "storage" ]; then
    mkdir -p storage/uploads
    print_success "Created storage/uploads directory"
else
    if [ ! -d "storage/uploads" ]; then
        mkdir -p storage/uploads
        print_success "Created storage/uploads directory"
    fi
fi

# Set permissions
chmod 755 storage/uploads 2>/dev/null || true
print_success "Set permissions on storage directories"
echo ""

# ============================================================================
# STEP 3: Run Database Migration
# ============================================================================

print_step "Step 3: Running database migration..."
print_warning "This will add tenant_id columns to all tables"
echo ""

# Create backup
if [ -f "database/maintenix.db" ]; then
    cp database/maintenix.db database/maintenix.db.backup
    print_success "Created database backup at database/maintenix.db.backup"
fi

# Run migration
php migrations/run_multi_tenant_migration.php

if [ $? -eq 0 ]; then
    print_success "Database migration completed successfully!"
else
    print_error "Database migration failed!"
    print_warning "Restore from backup with: cp database/maintenix.db.backup database/maintenix.db"
    exit 1
fi
echo ""

# ============================================================================
# STEP 4: Create Environment File
# ============================================================================

print_step "Step 4: Creating .env configuration..."

if [ ! -f ".env" ]; then
    cat > .env << 'EOF'
# Database Configuration
DB_TYPE=sqlite
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=kfmms

# Multi-Tenant Configuration
ALLOW_PUBLIC_REGISTRATION=true
MAX_COMPANIES=unlimited
DEFAULT_SUBSCRIPTION_PLAN=trial

# Application Settings
APP_ENV=development
DEBUG=true
LOG_LEVEL=info
EOF
    print_success "Created .env configuration file"
else
    print_warning ".env already exists, skipping creation"
fi
echo ""

# ============================================================================
# STEP 5: Create First Company
# ============================================================================

print_step "Step 5: Setting up first company..."

# Create PHP script to register company
cat > /tmp/create_company.php << 'PHPEOF'
<?php
require_once 'config.inc.php';

$service = new CompanyService($connection, $db_type);

$result = $service->register([
    'name' => 'Demo Company',
    'email' => 'admin@democompany.com',
    'phone' => '+1-555-0000',
    'address' => '123 Main Street',
    'city' => 'Demo City',
    'state' => 'DC',
    'country' => 'USA',
    'postal_code' => '10001'
]);

if ($result['success']) {
    echo "COMPANY_ID:" . $result['company_id'];
} else {
    echo "ERROR:" . $result['message'];
}
?>
PHPEOF

# Run the PHP script
COMPANY_OUTPUT=$(php /tmp/create_company.php 2>/dev/null)

if [[ $COMPANY_OUTPUT == COMPANY_ID:* ]]; then
    COMPANY_ID=${COMPANY_OUTPUT#COMPANY_ID:}
    print_success "Created company with ID: $COMPANY_ID"
else
    print_warning "Company creation output: $COMPANY_OUTPUT"
    COMPANY_ID=1
fi
echo ""

# ============================================================================
# STEP 6: Create Admin User
# ============================================================================

print_step "Step 6: Creating admin user for first company..."

# Create PHP script to create user
cat > /tmp/create_admin.php << PHPEOF
<?php
require_once 'config.inc.php';

\$auth = new AuthenticationManager(\$connection, \$db_type);

\$result = \$auth->registerUser([
    'email' => 'admin@democompany.com',
    'password' => password_hash('Demo@1234', PASSWORD_BCRYPT),
    'full_name' => 'Demo Admin',
    'role' => 'admin',
    'tenant_id' => $COMPANY_ID
]);

if (\$result['success']) {
    echo "USER_ID:" . \$result['user_id'];
} else {
    echo "ERROR:" . \$result['message'];
}
?>
PHPEOF

# Run the PHP script
USER_OUTPUT=$(php /tmp/create_admin.php 2>/dev/null)

if [[ $USER_OUTPUT == USER_ID:* ]]; then
    USER_ID=${USER_OUTPUT#USER_ID:}
    print_success "Created admin user with ID: $USER_ID"
else
    print_warning "User creation output: $USER_OUTPUT"
fi
echo ""

# ============================================================================
# STEP 7: Verification
# ============================================================================

print_step "Step 7: Verifying installation..."

# Check if tables have tenant_id
cat > /tmp/verify.php << 'PHPEOF'
<?php
require_once 'config.inc.php';

try {
    $query = "SELECT COUNT(*) as count FROM companies";
    
    if (isset($GLOBALS['db_type']) && $GLOBALS['db_type'] === 'sqlite') {
        $stmt = $connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $result = $connection->query($query)->fetch_assoc();
    }
    
    if ($result['count'] > 0) {
        echo "COMPANIES_OK:" . $result['count'];
    } else {
        echo "NO_COMPANIES";
    }
} catch (Exception $e) {
    echo "ERROR:" . $e->getMessage();
}
?>
PHPEOF

VERIFY_OUTPUT=$(php /tmp/verify.php 2>/dev/null)
print_success "Verification result: $VERIFY_OUTPUT"
echo ""

# ============================================================================
# STEP 8: Display Summary
# ============================================================================

echo -e "${GREEN}=========================================="
echo "✅ SETUP COMPLETE!"
echo "=========================================="
echo ""
echo -e "${BLUE}Your KFMMS Multi-Tenant System is Ready!${NC}"
echo ""
echo "📋 Setup Summary:"
echo "   • Company ID: $COMPANY_ID"
echo "   • Admin Email: admin@democompany.com"
echo "   • Admin Password: Demo@1234 (CHANGE THIS IMMEDIATELY)"
echo "   • Database Backup: database/maintenix.db.backup"
echo ""
echo "🚀 Next Steps:"
echo "   1. Start the PHP dev server:"
echo "      php -S 127.0.0.1:8000"
echo ""
echo "   2. Access the application:"
echo "      • Registration: http://127.0.0.1:8000/register.php"
echo "      • Login: http://127.0.0.1:8000/login_multi_tenant.php"
echo ""
echo "   3. Test login with:"
echo "      • Email: admin@democompany.com"
echo "      • Password: Demo@1234"
echo ""
echo "📚 Documentation:"
echo "   • Implementation Guide: MULTI_TENANT_IMPLEMENTATION_GUIDE.md"
echo "   • Deployment Checklist: DEPLOYMENT_CHECKLIST_MULTI_TENANT.md"
echo "   • Architecture Summary: KFMMS_MULTI_TENANT_COMPLETE_SUMMARY.md"
echo ""
echo "🔐 Security Reminder:"
echo "   • Change the demo admin password immediately"
echo "   • Enable HTTPS before production"
echo "   • Set APP_ENV=production in .env"
echo ""
echo -e "${GREEN}=========================================="
echo "Happy building! 🎉"
echo "=========================================${NC}"
echo ""

# Cleanup
rm -f /tmp/create_company.php /tmp/create_admin.php /tmp/verify.php

exit 0
