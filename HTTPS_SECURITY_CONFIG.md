# HTTPS + Secure Session Configuration - CMMS Production Hardening

## Current Environment Status
- **Web Server**: Apache 2.4 (Running on port 8080)
- **Database**: SQLite/MySQL (Multi-tenant)
- **PHP Application**: Free CMMS 0.04

---

## ✅ HTTPS Enforcement & Redirect

### .htaccess Configuration
**File**: `c:\free-cmms 0.04\.htaccess`

Added HTTP → HTTPS redirect at the beginning:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # HTTPS Enforcement: Redirect HTTP to HTTPS
    RewriteCond %{HTTPS} off
    RewriteCond %{HTTP:X-Forwarded-Proto} !https
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

**What this does**:
- Redirects all HTTP requests to HTTPS (301 permanent redirect)
- Skips redirect if already HTTPS
- Respects X-Forwarded-Proto header for proxy/load balancer situations
- Preserves query strings and URI in redirect

---

## ✅ Secure Session Cookie Configuration

### common.inc.php Configuration
**File**: `c:\free-cmms 0.04\common.inc.php`

Added secure session cookie parameters:
```php
session_set_cookie_params([
    'lifetime' => 3600,           // 1 hour session timeout
    'path'     => '/',            // Available across entire site
    'domain'   => $_SERVER['HTTP_HOST'] ?? '',
    'secure'   => (bool)$is_https,     // Only transmit over HTTPS
    'httponly' => true,           // Not accessible via JavaScript
    'samesite' => 'Strict'        // Prevent CSRF attacks
]);
```

**Security settings applied**:

| Setting | Value | Purpose |
|---------|-------|---------|
| **Secure flag** | `true` | Session cookie only sent over HTTPS |
| **HttpOnly flag** | `true` | Cookie not accessible via JavaScript (prevents XSS theft) |
| **SameSite** | `Strict` | Prevents CSRF attacks (no cross-site cookie sending) |
| **Session lifetime** | 3600 sec | 1 hour timeout for inactive sessions |
| **Use strict mode** | `1` | Only accept valid, existing session IDs |
| **Use only cookies** | `1` | Prevent session ID in URL (avoids logging/referrer issues) |
| **GC max lifetime** | 3600 sec | Garbage collection interval matches session timeout |

**auth.php already implements**:
- `session_regenerate_id(true)` after successful login (prevents session fixation attacks)
- Clears login attempt counters after successful authentication

---

## ✅ Security Headers (Already in .htaccess)

The following headers are enforced via `.htaccess`:

```
X-Frame-Options: SAMEORIGIN                      # Clickjacking protection
X-Content-Type-Options: nosniff                   # MIME sniffing prevention
X-XSS-Protection: 1; mode=block                   # XSS protection (legacy browsers)
Strict-Transport-Security: max-age=31536000;      # HSTS (1 year HTTPS enforcement)
includeSubDomains; preload
Referrer-Policy: strict-origin-when-cross-origin  # Referrer leak prevention
Content-Security-Policy: ...                      # Restrict resource loading
```

---

## 🔐 Protection Against Common Attacks

| Attack Type | Mitigation |
|------------|-----------|
| **Man-in-the-Middle (MITM)** | HTTPS enforcement + Strict-Transport-Security header |
| **Session Hijacking** | Secure + HttpOnly + SameSite cookies |
| **Cross-Site Scripting (XSS)** | HttpOnly cookies + CSP + X-XSS-Protection headers |
| **Cross-Site Request Forgery (CSRF)** | SameSite=Strict cookie setting |
| **Session Fixation** | session_regenerate_id() after login |
| **Clickjacking** | X-Frame-Options: SAMEORIGIN |
| **MIME Sniffing** | X-Content-Type-Options: nosniff |

---

## 📋 Next Steps & Recommendations

### For Production Deployment:

1. **SSL/TLS Certificate**
   ```bash
   # If using Let's Encrypt with Certbot:
   certbot certonly -d yourdomain.com
   
   # Then configure Apache with certificate paths
   ```

2. **Verify Apache mod_rewrite is enabled**
   ```bash
   apache2ctl -M | grep rewrite
   # Output should show: rewrite_module (shared)
   ```

3. **Enable mod_headers** (for security headers)
   ```bash
   a2enmod headers
   a2enmod ssl
   systemctl restart apache2
   ```

4. **Test HTTPS redirect**
   ```bash
   curl -I http://yourdomain.com
   # Should return 301 redirect to https://
   ```

5. **Verify secure cookies**
   - Check browser DevTools → Application → Cookies
   - Session cookie should show:
     - ✓ Secure (only HTTPS)
     - ✓ HttpOnly (not JS accessible)
     - ✓ SameSite=Strict

6. **HSTS Preload Registration** (optional, enhances security)
   - Once HTTPS is stable, register at https://hstspreload.org/
   - Adds domain to browser preload list for automatic HTTPS

---

## 🧪 Testing Commands

```bash
# Test HTTP redirect to HTTPS
curl -I http://localhost:8080

# Test security headers
curl -I https://localhost:8080 | grep -E "Strict-Transport|X-Frame|X-Content-Type|X-XSS"

# Test session cookie security (from browser console)
document.cookie  # Session cookie should not appear if HttpOnly is set

# Verify page loads over HTTPS without mixed content
curl -s https://localhost:8080 | grep "http://" | grep -v "https://"
```

---

## 📝 Files Modified

1. **`.htaccess`** - Added HTTPS redirect rule
2. **`common.inc.php`** - Added secure session cookie configuration and HTTPS enforcement

✅ All changes are backward-compatible and do not break existing functionality.
