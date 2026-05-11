#!/bin/bash
# Verification Checklist for User Creation System

echo "Checking User Creation System Implementation..."
echo "==============================================="
echo ""

# Check if all required files exist
echo "1. Checking file existence..."
FILES_TO_CHECK=(
    "app/PasswordManager.php"
    "force_password_change.php"
    "auth.php"
    "admin_roles.php"
    "config.inc.php"
    "clean_security.sql"
    "minimal_security.sql"
    "USER_CREATION_WORKFLOW.md"
)

for file in "${FILES_TO_CHECK[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✓ $file exists"
    else
        echo "   ✗ $file MISSING"
    fi
done

echo ""
echo "2. Checking for key code patterns..."

# Check PasswordManager has required methods
if grep -q "public static function generateTemporaryPassword" app/PasswordManager.php 2>/dev/null; then
    echo "   ✓ PasswordManager::generateTemporaryPassword found"
else
    echo "   ✗ generateTemporaryPassword method NOT found"
fi

if grep -q "public static function validatePassword" app/PasswordManager.php 2>/dev/null; then
    echo "   ✓ PasswordManager::validatePassword found"
else
    echo "   ✗ validatePassword method NOT found"
fi

# Check auth.php has force_password_change redirect
if grep -q "force_password_change.php" auth.php 2>/dev/null; then
    echo "   ✓ auth.php redirects to force_password_change.php"
else
    echo "   ✗ force_password_change redirect NOT found in auth.php"
fi

# Check admin_roles.php uses PasswordManager
if grep -q "PasswordManager::generateTemporaryPassword" admin_roles.php 2>/dev/null; then
    echo "   ✓ admin_roles.php uses PasswordManager"
else
    echo "   ✗ PasswordManager NOT used in admin_roles.php"
fi

# Check database schema has new columns
if grep -q "must_change_password" config.inc.php 2>/dev/null; then
    echo "   ✓ config.inc.php has must_change_password column"
else
    echo "   ✗ must_change_password NOT found in config.inc.php"
fi

if grep -q "must_change_password" clean_security.sql 2>/dev/null; then
    echo "   ✓ clean_security.sql has must_change_password column"
else
    echo "   ✗ must_change_password NOT found in clean_security.sql"
fi

if grep -q "must_change_password" minimal_security.sql 2>/dev/null; then
    echo "   ✓ minimal_security.sql has must_change_password column"
else
    echo "   ✗ must_change_password NOT found in minimal_security.sql"
fi

echo ""
echo "3. Checking force_password_change.php structure..."

if grep -q "force_password_change.php" force_password_change.php 2>/dev/null && grep -q "must_change_password" force_password_change.php 2>/dev/null; then
    echo "   ✓ force_password_change.php has password change logic"
else
    echo "   ✗ force_password_change.php may be incomplete"
fi

echo ""
echo "==============================================="
echo "Verification complete!"
echo ""
echo "Next steps:"
echo "1. Verify app/ directory exists in root"
echo "2. Run database migrations if needed"
echo "3. Test user creation through admin interface"
echo "4. Test first login with temporary password"
echo "5. Verify password change workflow"
