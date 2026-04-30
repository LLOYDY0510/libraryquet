# 📚 LibraryQuiet Monitoring System (LQMS)

A web-based noise monitoring system designed for libraries. It tracks sound levels across zones, generates alerts when thresholds are exceeded, and provides analytics through reports and dashboards.

---

# 🔐 Credentials

## FTP Access
- Host: ftp.ics-dev.io  
- User: u442411629.librarysaba  
- Password: 6nV6$5BSLjjl  
- Port: 21  

## Database Access
- phpMyAdmin: https://auth-db19821.hstgr.io  
- Database Name: u442411629_librarysaba  
- User: u442411629_dev_library  
- Password: 6nV6$5BSLjjl  

---

# 📁 Project Structure

> ⚠️ Upload this folder **outside `public_html`**


/librarysaba/
├── index.php
├── dashboard.php
├── zones.php
├── alerts.php
├── reports.php
├── users.php
├── setup.sql
│
├── includes/
│ ├── config.php
│ ├── auth.php
│ ├── layout.php
│ └── layout_footer.php
│
├── css/
│ ├── main.css
│ └── components.css
│
├── js/
│ ├── app.js
│ └── charts.js
│
├── php/
│ ├── logout.php
│ └── simulate_noise.php
│
└── api/
├── active_alerts_count.php
├── zone_levels.php
└── trigger_sim.php


---

# ⚙️ Deployment Guide

## 1. Setup Database
- Go to phpMyAdmin: https://auth-db19821.hstgr.io  
- Select database: `u442411629_librarysaba`  
- Import or paste `setup.sql`  

## 2. Upload Files (FTP)
- Open FileZilla  
- Connect using FTP credentials  
- Navigate outside `public_html`  
- Upload the entire `/librarysaba/` folder  

## 3. Configure Application URL
Edit:

includes/config.php


Set:
```php
define('BASE_URL', '/librarysaba');

If subdomain points directly:

define('BASE_URL', '');
4. Setup Cron Job (Automation)

Schedule:



Command (CLI):

php /path/to/librarysaba/php/simulate_noise.php

OR HTTP:

curl -s "https://yoursubdomain.ics-dev.io/librarysaba/api/trigger_sim.php"
🔄 System Behavior
Automatic Simulation
Runs every 7 minutes
Updates zone noise levels
Inserts alerts only when thresholds are exceeded
Dashboard Behavior
Auto-refreshes zone data
Polls alerts every 30 seconds
Triggers simulation when active
👤 Default Accounts
Name	Email	Password	Role
Johnlloyd P.	admin@library.edu
	admin123	Admin
James Anticamars	james@library.edu
	james123	Manager
Dimavier	staff@library.edu
	staff123	Staff

⚠️ Change passwords immediately after first login

🔐 Security Notes
Passwords are currently stored in plaintext
Recommended Upgrade
password_hash()
password_verify()
🧑‍💼 Role Permissions
Admin
Full system access
Manager
Manage zones and alerts
View reports
Staff
View dashboard and alerts only
🔊 Noise Thresholds
Quiet: < 40 dB (Green)
Moderate: 40–74 dB (Amber)
Loud: ≥ 75 dB (Red)

Can be customized per zone

🛡️ Performance & Safety
Minimal database load
No looping queries
Controlled simulation interval (7 min)
Lightweight API polling (~50 bytes)
✅ Final Notes
Works with or without cron (fallback via frontend)
Optimized for shared hosting (Hostinger)
Scalable for multiple zones

---


- `#` = Main title (biggest)
- `##` = Section headers
- `###` = Subsections
- Tables + code blocks preserved

---

Now when you paste this into `README.md`, it will **actually resize properly** (unlike your current plain text).

If it *still* looks the same after pasting, then:
👉 you’re probably viewing it as a `.txt` file instead of `.md`

If you want, :contentReference[oaicite:0]{index=0}.