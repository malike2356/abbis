# Starting XAMPP Server

## Quick Start

To start XAMPP services, run these commands in your terminal:

```bash
# Start XAMPP (Apache + MySQL)
sudo /opt/lampp/lampp start

# Check status
sudo /opt/lampp/lampp status

# Stop XAMPP
sudo /opt/lampp/lampp stop

# Restart XAMPP
sudo /opt/lampp/lampp restart
```

## Alternative: XAMPP Control Panel

You can also use the graphical control panel:

```bash
# Launch XAMPP Control Panel
/opt/lampp/manager-linux-x64.run
```

Or if you have the manager script:
```bash
sudo /opt/lampp/manager-linux-x64.run
```

## Access Your Application

Once XAMPP is running:

- **ABBIS Application:** http://localhost:8080/abbis3.2
- **XAMPP Dashboard:** http://localhost:8080/dashboard
- **phpMyAdmin:** http://localhost:8080/phpmyadmin

## Port Configuration

XAMPP is configured to use:
- **Apache:** Port 8080 (to avoid conflicts with other web servers)
- **MySQL:** Port 3306 (default)

## Troubleshooting

### If Apache won't start:
```bash
# Check if port 8080 is already in use
sudo netstat -tlnp | grep 8080

# Or check Apache error logs
sudo tail -f /opt/lampp/logs/error_log
```

### If MySQL won't start:
```bash
# Check MySQL error logs
sudo tail -f /opt/lampp/logs/mysql_error.log

# Check if port 3306 is in use
sudo netstat -tlnp | grep 3306
```

### Permission Issues:
```bash
# Fix XAMPP permissions (if needed)
sudo chown -R malike:malike /opt/lampp/htdocs/abbis3.2
sudo chmod -R 755 /opt/lampp/htdocs/abbis3.2
```

## Auto-Start on Boot (Optional)

To start XAMPP automatically on system boot:

```bash
# Create systemd service (if using systemd)
sudo nano /etc/systemd/system/xampp.service
```

Add this content:
```ini
[Unit]
Description=XAMPP
After=network.target

[Service]
Type=forking
ExecStart=/opt/lampp/lampp start
ExecStop=/opt/lampp/lampp stop
User=root

[Install]
WantedBy=multi-user.target
```

Then enable it:
```bash
sudo systemctl enable xampp.service
sudo systemctl start xampp.service
```

---

**Note:** You'll need to enter your sudo password when running these commands.

