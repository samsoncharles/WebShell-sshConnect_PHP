#!/bin/bash
# SSH/PHP Installer with Password Authentication

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Variables
INSTALL_USER=$(whoami)
SERVER_IP=$(hostname -I | awk '{print $1}')
APP_DIR="sshConnect_PHP"
APP_URL="http://$SERVER_IP/$APP_DIR"

# Detect Package Manager
if command -v apt-get &>/dev/null; then
    PKG_MANAGER="apt-get"
    UPDATE_CMD="apt-get update -y"
    INSTALL_CMD="apt-get install -y"
elif command -v dnf &>/dev/null; then
    PKG_MANAGER="dnf"
    UPDATE_CMD="dnf makecache"
    INSTALL_CMD="dnf install -y"
elif command -v pacman &>/dev/null; then
    PKG_MANAGER="pacman"
    UPDATE_CMD="pacman -Sy"
    INSTALL_CMD="pacman -S --noconfirm"
else
    echo -e "${RED}[✗] Unsupported package manager!${NC}"
    exit 1
fi

# Display Header & Internet Speed Alert
echo -e "${BLUE}────────────────────────────────────────────${NC}"
echo -e "    🔹 SSH-Enabled PHP Web Application Installer    "
echo -e "${BLUE}────────────────────────────────────────────${NC}"
echo -e "${YELLOW}[!] This process may take time depending on your internet speed.${NC}\n"

# Update Package Lists (Verbose)
echo -e "${YELLOW}🔄 Updating package lists...${NC}"
$UPDATE_CMD
echo -e "${GREEN}✔ Update completed.${NC}\n"

# SSH Configuration
function configure_ssh() {
    echo -e "${YELLOW}🔧 Configuring SSH...${NC}"
    sed -i 's/#PasswordAuthentication yes/PasswordAuthentication yes/' /etc/ssh/sshd_config
    sed -i 's/PasswordAuthentication no/#PasswordAuthentication no/' /etc/ssh/sshd_config
    systemctl restart ssh
    echo -e "${GREEN}✔ SSH configured.${NC}\n"
}

# Install Packages (Verbose)
function install_packages() {
    local pkgs=(
        "apache2"
        "php"
        "libapache2-mod-php"
        "php-cli"
        "php-curl"
        "php-mbstring"
        "php-xml"
        "php-zip"
        "php-ssh2"
        "php8.4-ssh2"
        "openssh-server"
    )

    for pkg in "${pkgs[@]}"; do
        echo -e "${YELLOW}📦 Installing $pkg...${NC}"
        $INSTALL_CMD $pkg
        echo -e "${GREEN}✔ $pkg installed.${NC}\n"
    done
}

# Deploy Web App
function setup_app() {
    echo -e "${YELLOW}📁 Deploying web app...${NC}"
    mkdir -p /var/www/html/$APP_DIR
    chown -R www-data:www-data /var/www/html/$APP_DIR
    chmod 750 /var/www/html/$APP_DIR

    cat > /var/www/html/$APP_DIR/index.php << 'EOL'
<?php
echo '<!DOCTYPE html><html><head><title>SSH Portal</title>';
echo '<style>body {background: #f5f5f5; font-family: sans-serif;}</style>';
echo '</head><body>';
echo '<div style=\"max-width:500px;margin:50px auto;padding:20px;background:#fff;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1)\">';
echo '<h2 style=\"color:#333;text-align:center\">SSH Authentication Portal</h2>';
if (isset(\$_SERVER['REMOTE_USER'])) {
    echo '<p style=\"color:#4CAF50\">Welcome '.htmlspecialchars(\$_SERVER['REMOTE_USER']).'</p>';
} else {
    echo '<p style=\"color:#F44336\">Please authenticate via SSH first</p>';
}
echo '</div></body></html>';
?>
EOL

    echo -e "${GREEN}✔ Web app deployed.${NC}\n"
}

# Display Installed Package Versions
function show_versions() {
    echo -e "\n${YELLOW}🔍 Installed Package Versions:${NC}"

    # Check PHP Version
    if command -v php &>/dev/null; then
        php_version=$(php -v | head -n 1)
        echo -e "${GREEN}✔ PHP:${NC} $php_version"
    else
        echo -e "${RED}✗ PHP is not installed${NC}"
    fi

    # Check if PHP-SSH2 is installed and enabled
    if php -m | grep -q ssh2; then
        echo -e "${GREEN}✔ php-ssh2:${NC} Installed and enabled"
    else
        echo -e "${RED}✗ php-ssh2 is not installed or not enabled${NC}"
    fi

    # Check if PHP8.4-SSH2 is installed
    if dpkg -l | grep -q php8.4-ssh2; then
        echo -e "${GREEN}✔ php8.4-ssh2:${NC} Installed"
    else
        echo -e "${RED}✗ php8.4-ssh2 is not installed${NC}"
    fi

    # Check Apache Version
    if command -v apache2ctl &>/dev/null; then
        apache_version=$(apache2ctl -v | head -n 1)
        echo -e "${GREEN}✔ Apache:${NC} $apache_version"
    else
        echo -e "${RED}✗ Apache is not installed${NC}"
    fi

    # Check OpenSSH Server Version
    if command -v sshd &>/dev/null; then
        ssh_version=$(sshd -V 2>&1 | head -n 1)
        echo -e "${GREEN}✔ OpenSSH Server:${NC} $ssh_version"
    else
        echo -e "${RED}✗ OpenSSH Server is not installed${NC}"
    fi
}

# Completion Message
function show_completion() {
    echo -e "\n${GREEN}✔ Installation Complete${NC}"
    echo -e "${BLUE}──────────────────────────────────────${NC}"
    echo -e "🚀 Access the application at: ${YELLOW}$APP_URL${NC}"
    echo -e "${BLUE}──────────────────────────────────────${NC}"
}

# Main Execution
configure_ssh
install_packages
setup_app
echo -e "${YELLOW}🔄 Restarting Apache service...${NC}"
systemctl restart apache2
echo -e "${GREEN}✔ Apache restarted.${NC}\n"

show_versions
show_completion
