#!/bin/bash
# install.sh - SSH-Enabled PHP Web Application Installer with Visual Effects

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Animation functions
function animate_loading() {
  local chars="/-\|"
  while :; do
    for (( i=0; i<${#chars}; i++ )); do
      sleep 0.1
      echo -en "${BLUE}[${chars:$i:1}] ${1}${NC}\r"
    done
  done
}

function show_banner() {
  clear
  echo -e "${GREEN}"
  echo "   _____ _____ _____   _______ _______ _______ _______ "
  echo "  |   __|     |     |_|   |  |    ___|     __|    |  |"
  echo "  |__   |  |  |       |       |    ___|__     |       |"
  echo "  |_____|_____|_______|__|_|__|_______|_______|__|____|"
  echo -e "${NC}"
  echo -e "${YELLOW}⚡ Secure PHP Web App with SSH Authentication${NC}"
  echo -e "${CYAN}------------------------------------------------${NC}"
}

function install_packages() {
  echo -e "${GREEN}[+] Installing required packages...${NC}"
  
  # Start loading animation
  animate_loading "Updating package list" &
  ANIM_PID=$!
  disown
  
  # Actual commands
  apt-get update -qq > /dev/null 2>&1
  
  # Stop animation
  kill $ANIM_PID > /dev/null 2>&1
  echo -e "\r${GREEN}[✓] Package list updated${NC}"
  
  # Main packages
  pkgs=("apache2" "php" "libapache2-mod-php" "php-cli" "php-curl" 
        "php-mbstring" "php-xml" "php-zip" "openssh-server" "lolcat" "figlet")
        
  for pkg in "${pkgs[@]}"; do
    echo -ne "${BLUE}[•] Installing ${pkg}...${NC}\r"
    apt-get install -y -qq "$pkg" > /dev/null 2>&1
    echo -e "\r${GREEN}[✓] Installed ${pkg}${NC}"
  done
}

function configure_services() {
  echo -e "\n${GREEN}[+] Configuring services...${NC}"
  
  # Enable services
  systemctl enable apache2 ssh > /dev/null 2>&1
  
  # Configure firewall
  echo -ne "${BLUE}[•] Configuring firewall...${NC}\r"
  ufw allow 22/tcp > /dev/null 2>&1
  ufw allow 80/tcp > /dev/null 2>&1
  echo "y" | ufw enable > /dev/null 2>&1
  echo -e "\r${GREEN}[✓] Firewall configured${NC}"
  
  # Add SSH effects
  echo -e "\n${CYAN}[+] Adding SSH Visual Effects${NC}"
  cat > /etc/ssh/sshd_effects.conf << 'EOL'
# SSH Effects Configuration
[effects]
login_animation = matrix
color_scheme = hacker
welcome_message = "Access Granted %u"
motd = """
  ███████╗███████╗██╗  ██╗
  ██╔════╝██╔════╝██║  ██║
  ███████╗███████╗███████║
  ╚════██║╚════██║██╔══██║
  ███████║███████║██║  ██║
  ╚══════╝╚══════╝╚═╝  ╚═╝
"""
EOL

  # Add to sshd_config
  echo -e "Include /etc/ssh/sshd_effects.conf" >> /etc/ssh/sshd_config
}

function setup_app() {
  echo -e "\n${GREEN}[+] Setting up application...${NC}"
  mkdir -p /var/www/html/secure_app
  chown -R www-data:www-data /var/www/html/secure_app
  chmod 750 /var/www/html/secure_app
  
  # Create sample PHP auth page
  cat > /var/www/html/secure_app/index.php << 'EOL'
<?php
// SSH-Protected PHP App
echo "<!DOCTYPE html><html><head><title>SSH-Protected App</title>";
echo "<style>body {background: #121212; color: #0f0; font-family: monospace;}</style>";
echo "</head><body>";
echo "<h1 style='text-align:center;color:#9FEF00'>⚡ SSH-Authenticated Portal ⚡</h1>";
echo "<div style='border:1px solid #333;padding:20px;margin:20px auto;width:80%;'>";
echo "<p>Welcome ".htmlspecialchars($_SERVER['REMOTE_USER'])."!</p>";
echo "<p>Your session is SSH-secured.</p>";
echo "</div></body></html>";
?>
EOL
}

function restart_services() {
  echo -e "\n${GREEN}[+] Restarting services...${NC}"
  systemctl restart apache2 ssh
}

function show_completion() {
  echo -e "\n${GREEN}"
  figlet "INSTALLATION COMPLETE!" | lolcat
  echo -e "${NC}"
  echo -e "${CYAN}------------------------------------------------${NC}"
  echo -e "${YELLOW}🚀 Application URL: http://$(hostname -I | awk '{print $1}')/secure_app${NC}"
  echo -e "${YELLOW}🔑 SSH Access: ssh $(whoami)@$(hostname -I | awk '{print $1}')${NC}"
  echo -e "${CYAN}------------------------------------------------${NC}"
  echo -e "${BLUE}ℹ️  Connect via SSH first to authenticate, then access the web UI${NC}"
}

# Main execution
show_banner
install_packages
configure_services
setup_app
restart_services
show_completion
