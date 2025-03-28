# Web-Based SSH & WebShell Manager
## (Apt package manager Based)



<div align="center">
  <img src="https://i.imghippo.com/files/ILw7451YOw.png" style="width: 80%; max-width: 800px; border-radius: 8px; margin: 20px 0;">
  <img src="https://i.imghippo.com/files/bGEc4899hqo.png" style="width: 80%; max-width: 800px; border-radius: 8px; margin: 20px 0;">
</div>

## 📊 GitHub Statistics
<div align="center">
  <img src="https://github-readme-stats.vercel.app/api?username=samsoncharles&theme=shadow_green&hide_border=false&include_all_commits=true&count_private=true" alt="GitHub Stats">
  <img src="https://nirzak-streak-stats.vercel.app/?user=samsoncharles&theme=shadow_green&hide_border=false" alt="GitHub Streak">
  <img src="https://github-readme-stats.vercel.app/api/top-langs/?username=samsoncharles&theme=shadow_green&hide_border=false&include_all_commits=true&count_private=true&layout=compact" alt="Top Languages">
</div>

## 📚 Definitions and Technical Background

### What is a WebShell?
A WebShell is a script-based web interface that enables remote system administration through a browser. Key characteristics:

- **Frontend**: HTML/CSS/JavaScript interface
- **Backend**: PHP/Python/Node.js command execution
- **Protocol**: Typically HTTP/HTTPS
- **Versions**:
  - PHP WebShell (most common)
  - JSP WebShell (Java environments)
  - ASPX WebShell (Windows servers)
  - Python WebShell (WSGI implementations)

### What is SSH?
Secure Shell (SSH) is the standard for secure remote access:

- **Protocol Versions**:
  - SSH-1 (legacy, insecure)
  - SSH-2 (current standard)
- **Common Implementations**:
  - OpenSSH (most widely used)
  - Dropbear (embedded systems)
  - Tectia (commercial solution)

## ⚠️ Critical Disclaimer
**This project is for educational and authorized administrative use only.** By using this software, you agree that:

1. You will only install on systems you own or have explicit permission to manage
2. You understand the legal implications of unauthorized access
3. You accept all responsibility for your use of this tool

## 🌟 Project Overview
This PHP-based solution combines WebShell and SSH functionality with:

✔ Browser-based SSH terminal  
✔ WebShell command execution  
✔ System monitoring dashboard  
✔ Package management interface  
✔ Multi-user capability (when properly configured)

## 🛠️ Technical Specifications

| Component           | Implementation Details                  |
|---------------------|----------------------------------------|
| Core Language       | PHP 7.4+ with secure execution wrapper |
| Web Server          | Apache 2.4+/Nginx 1.18+                |
| Database            | SQLite3 (optional)                     |
| Frontend Framework  | Bootstrap 5.1 + Terminal.js            |
| Security Layers     | Input sanitization, command whitelisting|
## 📦 Complete Installation Guide

### Prerequisites
- Linux system with APT package manager
- PHP 7.4+ with required extensions
- Apache/Nginx web server
- Git client

### Installation Steps
```bash
# Clone repository with submodules
sudo git clone --recursive https://github.com/samsoncharles/sshConnect_PHP.git

# Set executable permissions
sudo chmod +x info.sh
sudo chmod +x sshConnect_PHP/requirements.sh

# Run installation (auto-detects dependencies)
sudo ./info.sh
sudo ./sshConnect_PHP/requirements.sh
sudo ./info.sh

# Directories Setup:
sudo mkdir -p /var/www/html/sshConnect_PHP
sudo cp -r sshConnect_PHP/* /var/www/html/sshConnect_PHP/
sudo chown -R www-data:www-data /var/www/html/sshConnect_PHP
sudo chmod -R 777 /var/www/html/sshConnect_PHP

# Start service
sudo systemctl restart apache2
firefox localhost/sshConnect_PHP
