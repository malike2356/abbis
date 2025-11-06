# ABBIS - Advanced Borehole Business Intelligence System

A comprehensive borehole drilling operations management system with field reporting, payroll, materials tracking, financial analytics, and loan management.

## 🚀 Features

- **Field Operations Reporting** - Complete drilling and construction data capture
- **Payroll Management** - Worker wages, benefits, and loan deductions
- **Materials Inventory** - Track screen pipes, plain pipes, and gravel
- **Financial Analytics** - Real-time profit/loss calculations and reporting
- **Loan Management** - Worker loans and repayment tracking
- **Dashboard** - Comprehensive KPI dashboard with recent activity
- **Multi-user Support** - Role-based access control (Admin, Manager, Supervisor, Clerk)
- **Responsive Design** - Works on desktop, tablet, and mobile devices
- **Dark/Light Theme** - User-selectable theme preference

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## 🛠️ Installation

### 1. Download and Extract
Download the ABBIS system files and extract them to your web server directory.

### 2. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE abbis_3_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p abbis_3_2 < database/schema.sql


3. Configuration
Update the database configuration in config/database.php:
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'abbis_3_2');

4. Web Server Setup
Ensure your web server is configured to serve PHP files and point the document root to the ABBIS directory.

5. Permissions
Set appropriate file permissions:
chmod 755 config/
chmod 644 config/database.php
chmod 755 uploads/ # if using file uploads


🔐 Default Login
Username: admin
Password: password

Important: Change the default password after first login.

📁 Directory Structure
abbis-system/
├── index.php              # Main dashboard
├── config/               # Configuration files
├── includes/             # Core PHP classes
├── modules/              # Feature modules
├── assets/               # CSS, JS, images
├── api/                  # API endpoints
├── database/             # Database schema
└── README.md            # This file


Deployment
Option 1: XAMPP (Easiest for Beginners)

🚀 Install XAMPP:
# Download XAMPP
wget https://downloadsapachefriends.global.ssl.fastly.net/xampp-files/8.2.12/xampp-linux-x64-8.2.12-0-installer.run?from_af=true -O xampp-installer.run

# Make executable and install
chmod +x xampp-installer.run
sudo ./xampp-installer.run

# Start XAMPP
sudo /opt/lampp/lampp start


Deploy ABBIS:
# Copy your ABBIS files to XAMPP htdocs
sudo cp -r ~/abbis3 /opt/lampp/htdocs/

# Set permissions
sudo chown -R malike:malike /opt/lampp/htdocs/abbis3
sudo chmod -R 755 /opt/lampp/htdocs/abbis3

Access: http://localhost/abbis3/login.php

🐳 Option 3: Docker (Most Flexible)
Install Docker:
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add your user to docker group
sudo usermod -aG docker $USER
# Log out and log back in for group changes to take effect

Create docker-compose.yml:

version: '3.8'
services:
  web:
    image: php:8.2-apache
    ports:
      - "8080:80"
    volumes:
      - ./abbis3:/var/www/html
      - ./apache-config.conf:/etc/apache2/sites-available/000-default.conf
    depends_on:
      - db
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: abbis_3_2
      MYSQL_USER: abbis_user
      MYSQL_PASSWORD: abbis_password
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql

volumes:
  mysql_data:



  Create apache-config.conf:

<VirtualHost *:80>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

Run:
cd ~/abbis3
docker-compose up -d

Access: http://localhost:8080/login.php




