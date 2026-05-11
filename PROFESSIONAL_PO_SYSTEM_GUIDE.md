# Professional Purchase Order System - Complete Guide

## Overview

The Maintenix system now includes a complete professional Purchase Order (PO) display and delivery system with:

- **Professional HTML Layout**: Business-grade formatting with company header, vendor details, shipping information
- **Printable Format**: CSS media queries optimized for A4 paper printing
- **PDF Export**: Download POs as PDF files (requires browser or optional DomPDF library)
- **Email Delivery**: Send POs directly to vendors with professional formatting
- **Complete Schema**: All professional PO fields including ship-to address, requisitioner info, and tracking

## Database Schema

### New Professional PO Fields (Migration 007)

Installed fields added to `purchase_orders` table:

```
Ship-to Address:
  - ship_to_name VARCHAR(100)
  - ship_to_address TEXT
  - ship_to_city VARCHAR(50)
  - ship_to_state VARCHAR(50)
  - ship_to_postal_code VARCHAR(20)
  - ship_to_phone VARCHAR(20)

Requisitioner Information:
  - requisitioner_name VARCHAR(100)
  - requisitioner_email VARCHAR(100)

Shipping & Terms:
  - ship_via VARCHAR(50)           (e.g., "Ground", "Air", "Courier")
  - fob VARCHAR(50)                (e.g., "Shipping Point", "Destination")
  - shipping_terms VARCHAR(100)    (e.g., "Net 30", "2/10 Net 30")

Financial Summary:
  - tax_percent DECIMAL(5,2)       (Tax rate percentage)
  - other_cost DECIMAL(14,2)       (Additional costs)
  - total_amount DECIMAL(14,2)     (Grand total)

Comments & Tracking:
  - comments TEXT                  (Special instructions/notes)
  - email_sent_to VARCHAR(100)     (Email address PO was sent to)
  - email_sent_at TIMESTAMP        (When email was sent)
```

## Using the Professional PO System

### 1. View Professional PO Display

**URL:** `http://localhost:8000/po_display.php?id=<PO_ID>&action=view`

**Features:**
- Complete vendor and buyer information
- Ship-to address display
- Requisitioner and shipping terms
- Professional line items table
- Financial summary with tax and shipping
- Special instructions/comments section
- Professional header with company logo area
- Professional footer with generation timestamp

**Example:** `http://localhost:8000/po_display.php?id=1&action=view`

### 2. Print PO to Paper/PDF

**Method 1: Browser Print-to-PDF (Recommended)**
1. Open the PO display page
2. Press `Ctrl+P` (Windows) or `Cmd+P` (Mac)
3. Select "Save as PDF" as printer
4. Choose A4 paper size, portrait orientation
5. Click "Save"
6. Enter filename like "PO-2026-00001.pdf"

**Print Settings for Best Results:**
- Paper size: A4
- Orientation: Portrait
- Margins: Default (25mm)
- Headers/footers: Uncheck
- Background graphics: Check
- Scale: 100%

**Result:** Professional, print-ready PDF document

### 3. Download PO as PDF (Browser HTML)

**URL:** `http://localhost:8000/po_display.php?id=<PO_ID>&action=pdf`

**Features:**
- Generates standalone HTML document
- Can be opened in any browser
- Can be printed to PDF from browser
- Includes all professional formatting

**Example:** `http://localhost:8000/po_display.php?id=1&action=pdf`

### 4. Email PO to Vendor

**URL:** `http://localhost:8000/po_display.php?id=<PO_ID>&action=send_email`

**Prerequisites:**
1. Configure SMTP settings in `config.inc.php`
2. Enable email via `SMTP_ENABLED = true`
3. Vendor must have email address in database

**Configuration Steps:**

Edit `config.inc.php` and add:

```php
// Email Settings
define('SMTP_ENABLED', true);              // Enable email
define('SMTP_HOST', 'smtp.gmail.com');     // Gmail SMTP
define('SMTP_PORT', 587);                  // Port 587 (TLS)
define('SMTP_SECURE', 'tls');              // Use TLS encryption
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');  // Use app password for Gmail
define('SMTP_FROM_EMAIL', 'noreply@freecmms.org');
define('SMTP_FROM_NAME', 'Maintenix');
```

**Gmail Setup (Recommended for Testing):**
1. Go to account.google.com/security
2. Enable 2-Factor Authentication
3. Generate App Password
4. Use generated password in SMTP_PASS

**Features:**
- Sends HTML-formatted PO to vendor
- Tracks email delivery (email_sent_to, email_sent_at fields)
- Professional subject line: "Purchase Order {PO_NUMBER} from {COMPANY_NAME}"
- Fallback support if configured incorrectly

### 5. PO Actions from Purchase Order List

In the main PO list view (`/purchase_order.php`), each PO has action buttons:

- **📋 View** - Open professional PO display in new window
- **✏️ Edit** - Edit PO details and line items
- **📦 GRN** - Create Goods Receipt Note (if PO status allows)

Example: `http://localhost:8000/purchase_order.php`

## Creating a Test PO

### Step 1: Create Vendor (if needed)

Navigate to: `/vendors.php?action=create`

Enter:
- Vendor Name: "Acme Corporation"
- Contact Person: "John Smith"
- Email: "john@acmecorp.com"
- Phone: "+256 800 123456"
- Address: "123 Business Street"
- City: "Kampala"
- Country: "Uganda"

### Step 2: Create Purchase Order

Navigate to: `/purchase_order.php?action=create`

Enter:
- Vendor: "Acme Corporation"
- Expected Delivery Date: (future date)
- Requisitioner: (auto-populated from user)
- Ship Via: "Ground"
- Payment Terms: "Net 30"

### Step 3: Add Line Items

Click "Add Line Item" and enter:
- Part Number: "PART-001"
- Description: "Office Supplies"
- Quantity: 100
- Unit Price: $10.50
- Tax: ✓ Checked

### Step 4: View Professional  PO

After saving PO (e.g., ID=5):

1. Click "📋 View" button, OR
2. Navigate to: `/po_display.php?id=5&action=view`

Result: Full professional PO with all details calculated and displayed

## Advanced Features

### Customizing Company Information

Edit `po_display.php` line 46:

```php
$company_name = "Your Company Name";
```

Also update the company details section (lines 325-329):

```php
<h4><?= htmlspecialchars($company_name) ?></h4>
<p>Your company subtitle</p>
<p>Your city, Country</p>
<p>your-email@company.com</p>
```

### Modifying Professional Appearance

CSS styling is in `po_display.php` lines 63-196. Key classes:

```css
.po-header          /* Header styling */
.company-details    /* Company info */
.po-title          /* PO title and number */
.section-box       /* Information sections */
.table             /* Line items table */
.totals-section    /* Grand total display */
```

### Email Template Customization

Edit the `generate_po_html()` function (lines 583-611) to modify the email format.

### Installing DomPDF for Native PDF Export

If you want native PDF export without browser:

1. Enable required PHP extensions in `C:\php\php.ini`:
   ```
   extension=php_mbstring.dll
   extension=php_curl.dll
   extension=php_gd2.dll
   ```

2. Restart PHP service and install DomPDF:
   ```
   cd c:\free-cmms 0.04
   php composer.phar require dompdf/dompdf
   ```

3. Then `po_display.php?action=pdf` will create native PDF

## System Status

**Current Implementation:**
- ✓ Professional PO Schema - Complete
- ✓ HTML Display Template - Complete
- ✓ Print CSS Stylesheet - Complete
- ✓ Browser Print-to-PDF - Ready
- ✓ Email Support - Ready (requires SMTP config)
- ✓ HTML PDF Export - Ready
- ○ Native PDF (DomPDF) - Optional (requires PHP extension setup)

**Test Endpoints:**
- Verification: `http://localhost:8000/test_po_system_status.php`
- PO List: `http://localhost:8000/purchase_order.php`
- Create PO: `http://localhost:8000/purchase_order.php?action=create`

## Troubleshooting

### PO Display Shows "No PO specified"
- Ensure you're using correct PO ID in URL
- Check if PO exists in database: `SELECT * FROM purchase_orders WHERE id = <ID>`

### Email Not Sending
1. Check if SMTP_ENABLED = true in config.inc.php
2. Verify SMTP server settings (host, port, secure method)
3. Check error log: `php_error.log`
4. Ensure vendor has email address

### Print Layout Issues
- Ensure browser zoom is 100%
- Check paper size is A4
- Disable margins if printing crops edges
- Try different browser (Chrome recommended)

### PDF Button Downloads HTML Instead
- This is normal fallback behavior
- Save HTML file and open in browser
- Print to PDF from browser for better quality
- Alternatively, install DomPDF for native PDF generation

## Database Verification

Check schema installation:

```sql
USE free_cmms;

-- Verify professional PO columns
SHOW COLUMNS FROM purchase_orders 
WHERE Field IN ('ship_to_name', 'requisitioner_name', 'tax_percent', 'total_amount', 'email_sent_at');

-- Should return 5 rows with all fields
```

## Next Steps

1. **Test the system:**
   - Create test vendor
   - Create test PO with items
   - View professional display
   - Test print-to-PDF

2. **Configure email (optional):**
   - Set up SMTP in config.inc.php
   - Test email delivery
   - Verify email_sent_at tracking

3. **Customize for your business:**
   - Update company name and details
   - Adjust CSS styling
   - Modify email templates

4. **Deploy to production:**
   - Backup database
   - Verify all test cases work
   - Train staff on PO creation and viewing

## Support

For issues or enhancements:
1. Check error_log file
2. Review database schema
3. Test with browser developer tools (F12)
4. Review PHP error messages

---

*Professional PO System - Maintenix v0.04*  
*Last Updated: 2026-02-28*
