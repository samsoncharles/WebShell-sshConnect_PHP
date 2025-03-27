#!/bin/bash
# install.sh - SSH/PHP Installer with Password Authentication

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
INSTALL_USER=$(whoami)
SERVER_IP=$(hostname -I | awk '{print $1}')
APP_DIR="sshConnect_PHP"
APP_URL="http://$SERVER_IP/$APP_DIR"

function show_header() {
  echo -e "${BLUE}"
  echo "SSH-Enabled PHP Web Application Installer"
  echo -e "${NC}"
}

function run_with_progress() {
  echo -n "[ ] $1..."
  $2 > /dev/null 2>&1 &
  local pid=$!
  
  local spin='-\|/'
  local i=0
  while kill -0 $pid 2>/dev/null; do
    i=$(( (i+1) %4 ))
    printf "\r[${spin:$i:1}]"
    sleep 0.1
  done
  
  wait $pid
  if [ $? -eq 0 ]; then
    printf "\r[✓] $1\n"
  else
    printf "\r[✗] $1\n"
  fi
}

function configure_ssh() {
  # Ensure password authentication is enabled
  run_with_progress "Configuring SSH" "
    sed -i 's/#PasswordAuthentication yes/PasswordAuthentication yes/' /etc/ssh/sshd_config
    sed -i 's/PasswordAuthentication no/#PasswordAuthentication no/' /etc/ssh/sshd_config
    systemctl restart ssh
  "
}

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
    "openssh-server"
  )

  for pkg in "${pkgs[@]}"; do
    run_with_progress "Installing $pkg" "apt-get install -y -qq $pkg"
  done
}

function setup_app() {
  run_with_progress "Deploying web app" "
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
  "
}

function show_completion() {
  echo -e "\n${GREEN}✔ Installation Complete${NC}"
  echo -e "${BLUE}──────────────────────────────────────${NC}"
  echo -e "Access the application at: ${YELLOW}$APP_URL${NC}"
  echo -e "Login via SSH with: ${YELLOW}ssh $INSTALL_USER@$SERVER_IP${NC}"
  echo -e "Use your current system password"
  echo -e "${BLUE}──────────────────────────────────────${NC}"
}

# Main execution
show_header
run_with_progress "Updating packages" "apt-get update -qq"
configure_ssh
install_packages
setup_app
run_with_progress "Starting services" "systemctl restart apache2"
show_completion
                 
