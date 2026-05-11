# SMTP Email Notification Fix Guide

## 🔴 Problem
```
Work order request created successfully. ✓
Notification email could not be delivered. ✗
Reason: SMTP Error: Could not connect to SMTP host.
```

---

## 🔍 Root Causes (In Order of Likelihood)

### 1. **Gmail App Password Issue** (Most Common)
- Your Gmail SMTP password may have expired or been revoked
- Two-Factor Authentication requirement
- App password not generated correctly

### 2. **Network/Firewall Blocking**
- Port 587 or 465 blocked by ISP/Firewall
- Server cannot reach smtp.gmail.com
- Network policy restricting outbound SMTP

### 3. **PHP Configuration**
- PHPMailer library not installed (`vendor/` folder missing)
- PHP OpenSSL extension not enabled
- PHP mail() function not configured

### 4. **SMTP Settings Misconfigured**
- Wrong host, port, or security settings
- Credentials (username/password) incorrect

---

## ✅ Solution Steps

### **Step 1: Run the Diagnostic Tool**
1. Open your browser and navigate to:
   ```
   http://your-server/free-cmms/diagnose_smtp.php
   ```
2. Review all the test results - they will indicate which step to follow

---

### **Step 2: Fix Gmail Authentication (Recommended)**

#### For Gmail Users:
1. Go to: [Google Account Security](https://myaccount.google.com/security)
2. Enable **2-Step Verification** (if not already enabled)
3. Go to: [App Passwords](https://myaccount.google.com/apppasswords)
   - Select "Mail" and "Windows Computer" (or other device)
   - Copy the **16-character password** generated
4. Edit `config.inc.php`:
   ```php
   $SMTP_USER = 'kalemaf876@gmail.com';
   $SMTP_PASS = 'xxxx xxxx xxxx xxxx';  // ← Paste the 16-char password here (no spaces)
   $SMTP_HOST = 'smtp.gmail.com';
   $SMTP_PORT = 587;
   $SMTP_SECURE = 'tls';
   $SMTP_ENABLED = true;
   ```
5. Save and test by creating a new work order

---

### **Step 3: Alternative - Try Different Gmail Settings**

If port 587 doesn't work, try port 465 with SSL:
```php
$SMTP_PORT = 465;
$SMTP_SECURE = 'ssl';
```

---

### **Step 4: Install PHPMailer (If Missing)**

Check if `vendor/` folder exists. If not:

#### Option A: Using Composer (Recommended)
```bash
cd c:\free-cmms
composer require phpmailer/phpmailer
```

#### Option B: Manual Installation
1. Download PHPMailer from: [GitHub Releases](https://github.com/PHPMailer/PHPMailer/releases)
2. Extract to `c:\free-cmms\vendor\phpmailer\phpmailer\`
3. Create `c:\free-cmms\vendor\autoload.php`:
   ```php
   <?php
   spl_autoload_register(function ($class) {
       $prefix = 'PHPMailer\\PHPMailer\\';
       if (strpos($class, $prefix) !== 0) return;
       $file = __DIR__ . '/phpmailer/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
       if (file_exists($file)) require $file;
   });
   ```

---

### **Step 5: Enable SSL Extension (If Missing)**

If SSL errors occur, enable OpenSSL in PHP:

**Windows (php.ini):**
```
extension=openssl
```
Then restart your web server.

---

### **Step 6: Use Fallback Option (Temporary)**

If you can't fix SMTP immediately, edit `config.inc.php`:
```php
$SMTP_ENABLED = false;  // Disable SMTP
```

This will:
- Fall back to PHP's `mail()` function
- Work if your server has local mail configured
- Emails may be slower but will be sent

---

### **Step 7: Alternative SMTP Services**

If Gmail continues to fail, consider:

#### **SendGrid** (Free tier: 100 emails/day)
```php
$SMTP_HOST = 'smtp.sendgrid.net';
$SMTP_PORT = 587;
$SMTP_USER = 'apikey';  // Literal "apikey"
$SMTP_PASS = 'YOUR_SENDGRID_API_KEY';
$SMTP_SECURE = 'tls';
```

#### **AWS SES** (Free tier available)
```php
$SMTP_HOST = 'email-smtp.YOUR_REGION.amazonaws.com';
$SMTP_PORT = 587;
$SMTP_USER = 'YOUR_AWS_USERNAME';
$SMTP_PASS = 'YOUR_AWS_PASSWORD';
$SMTP_SECURE = 'tls';
```

---

## 📋 Testing Your Fix

### **Test 1: Manual Diagnostic Test**
```bash
php c:\free-cmms\diagnose_smtp.php
```
Check browser results for all green checkmarks ✓

### **Test 2: Create a Work Order**
1. Log in to the CMMS
2. Go to Work Orders → Create Request
3. Fill in the form and submit
4. Check for: `"✓ Notification sent to technician email."`

### **Test 3: Check Email Log**
```bash
type c:\free-cmms\logs\email_send.log
```
Should see recent `[SMTP_SUCCESS]` or `[PHP_MAIL_SUCCESS]` entries

---

## 🚀 Optional: Implement Email Queue Fallback

For production stability, use the improved email handler:

### **Step 1: Create Database Table**
```sql
CREATE TABLE IF NOT EXISTS `notification_queue` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `attempts` INT DEFAULT 0,
  `last_error` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL,
  INDEX idx_status (status)
);
```

### **Step 2: Update work_order_requests.php**
Replace this line:
```php
if (!function_exists('send_notification_email')) {
```

With this line:
```php
if (!function_exists('send_notification_email_improved')) {
```

Then call it as:
```php
if (send_notification_email_improved($technicianEmail, $subject, $body)) {
    $message .= ' ✓ Notification sent.';
} else {
    // Will show error or queue status
}
```

### **Step 3: Set Up Cron Job for Retry**
Create `process_email_queue.php` and run hourly:
```bash
0 * * * * php c:\free-cmms\process_email_queue.php >> c:\free-cmms\logs\queue.log 2>&1
```

---

## 🆘 Still Not Working?

1. **Check PHP error log**: `php -l work_order_requests.php`
2. **Enable debug mode** in config.inc.php: `ini_set('display_errors', 1);`
3. **Verify Gmail**: Try sending test email from command line
4. **Check firewall**: Use `telnet smtp.gmail.com 587` to test connection
5. **Review email log**: `tail -50 c:\free-cmms\logs\email_send.log`

---

## 📞 Quick Summary Table

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| "Could not connect to SMTP host" | Firewall/Network blocked | Check server firewall, try port 465 |
| "authentication failed" | Wrong credentials | Regenerate Gmail App Password |
| "certificate problem" | SSL/TLS issue | Try different SMTP_SECURE value |
| "Timeout" | Slow connection | Increase mail->Timeout = 15 |
| "PHPMailer not found" | Missing library | Run: composer require phpmailer/phpmailer |

---

## 🔗 Useful Links
- [Google 2-Step Verification](https://support.google.com/accounts/answer/185839)
- [Google App Passwords](https://myaccount.google.com/apppasswords)
- [PHPMailer GitHub](https://github.com/PHPMailer/PHPMailer)
- [Gmail SMTP Settings](https://support.google.com/mail/answer/7126229)

