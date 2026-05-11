# Apache HTTPS Deployment Guide - Windows

## Quick Start: Enable HTTPS on Apache 2.4 (Windows)

### Step 1: Verify Apache Modules are Enabled

```powershell
# Check if required modules are active
cd "C:\Apache24\bin"
.\httpd.exe -M | findstr "ssl_module rewrite_module headers_module"
```

**Required output**:
```
ssl_module (shared)
rewrite_module (shared)
headers_module (shared)
```

If not present, uncomment these lines in `C:\Apache24\conf\httpd.conf`:
```apache
LoadModule ssl_module modules/mod_ssl.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
```

### Step 2: Check Current Apache Configuration

```powershell
# View Apache configuration for current setup
Get-Content "C:\Apache24\conf\httpd.conf" | Select-String "^Listen|^ServerName|DocumentRoot" | Select-Object -First 5
```

**Current configuration shows**:
- Listen port: 8080
- ServerName: localhost:8080
- DocumentRoot: C:\Apache24\htdocs

### Step 3: Create SSL Certificate (Self-Signed for Testing)

For **development/testing**, use self-signed certificate:

```powershell
# Create self-signed certificate valid for 365 days
cd "C:\Apache24\bin"
.\openssl.exe req -new -x509 -days 365 -nodes `
  -out ..\conf\server.crt `
  -keyout ..\conf\server.key `
  -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"
```

For **production**, use Let's Encrypt or commercial CA certificate.

### Step 4: Configure Apache VirtualHost for HTTPS

Create or edit `C:\Apache24\conf\extra\httpd-ssl.conf` to add:

```apache
# HTTP to HTTPS redirect
<VirtualHost *:80>
    ServerName localhost
    ServerAlias 127.0.0.1
    DocumentRoot "C:\Apache24\htdocs"
    
    # Redirect all HTTP traffic to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

# HTTPS Virtual Host
<VirtualHost *:443>
    ServerName localhost
    ServerAlias 127.0.0.1
    DocumentRoot "C:\Apache24\htdocs"
    
    SSLEngine on
    SSLCertificateFile "C:\Apache24\conf\server.crt"
    SSLCertificateKeyFile "C:\Apache24\conf\server.key"
    
    # Security optimizations
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
    SSLHonorCipherOrder on
    
    # Enable HTTP/2 if mod_http2 is available
    Protocols h2 http/1.1
</VirtualHost>
```

### Step 5: Enable VirtualHosts in httpd.conf

Uncomment this line in `C:\Apache24\conf\httpd.conf`:

```apache
Include conf/extra/httpd-vhosts.conf
```

### Step 6: Test Apache Configuration

```powershell
cd "C:\Apache24\bin"
.\httpd.exe -t
# Should output: "Syntax OK"
```

### Step 7: Restart Apache Service

```powershell
# Restart Apache from PowerShell (admin required)
Stop-Service Apache2.4
Start-Service Apache2.4

# Or via Apache Service Monitor
# Right-click Apache Monitor → Restart
```

### Step 8: Verify HTTPS is Working

```powershell
# Test HTTP redirect
curl -I http://localhost:8080
# Expected: 301 Moved Permanently → https://localhost:8080

# Test HTTPS connection
curl -k -I https://localhost:8080
# Expected: 200 OK + security headers
```

### Step 9: Verify Security Headers

```powershell
curl -k -I https://localhost:8080 | findstr /I "Strict-Transport|X-Frame|X-Content-Type"

# Expected output:
# Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
# X-Frame-Options: SAMEORIGIN
# X-Content-Type-Options: nosniff
```

---

## For Production with Let's Encrypt

### Install Certbot on Windows Subsystem for Linux (WSL)

```bash
# In WSL Ubuntu:
sudo apt update
sudo apt install certbot python3-certbot-apache
sudo certbot certonly --webroot -w /mnt/c/Apache24/htdocs -d yourdomain.com
```

This creates certificates in `/etc/letsencrypt/live/yourdomain.com/`

### Update Apache Configuration with Let's Encrypt Certs

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot "C:\Apache24\htdocs"
    
    SSLEngine on
    SSLCertificateFile "/mnt/c/letsencrypt/live/yourdomain.com/fullchain.pem"
    SSLCertificateKeyFile "/mnt/c/letsencrypt/live/yourdomain.com/privkey.pem"
</VirtualHost>
```

### Auto-Renew Certificates (via Windows Task Scheduler)

Create a batch file `C:\Scripts\renew-ssl.bat`:
```batch
@echo off
cd C:\Apache24\bin
httpd.exe -k stop
wsl sudo certbot renew
httpd.exe -k start
```

Add to Windows Task Scheduler to run monthly.

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **SSL module not loading** | Uncomment `LoadModule ssl_module` in httpd.conf, restart |
| **Port 443 already in use** | `netstat -ano \| findstr :443` to find process, change port or kill process |
| **Certificate not trusted** | Install CA certificate in browser trust store for self-signed certs |
| **Mixed content warning** | Update internal links from `http://` to `https://` or use `//` protocol-relative URLs |
| **Redirect loop** | Check `.htaccess` - ensure not both redirecting and Apache config redirecting |

---

## Current CMMS Configuration Status

✅ `.htaccess` HTTPS redirect: **CONFIGURED**
✅ Secure session cookies: **CONFIGURED** 
✅ Security headers: **CONFIGURED**
⚠️ SSL Certificate: **PENDING** (awaiting production domain/certificate)
⚠️ Apache VirtualHosts: **NEEDS SETUP** (requires certificate installation)

**To complete**: Install SSL certificate and update Apache VirtualHost configuration per Step 4-5 above.
