#!/bin/bash
# Displaying the "hacker" theme intro
echo "────────────────────────────────────────────────"
echo "       Welcome to the Automated Setup Script"
echo "    Installing Apache2, PHP, and Requirements"
echo "────────────────────────────────────────────────"
echo "    [*] Preparing to unleash the power..."
sleep 1

# Step 1: Update and upgrade the system
echo "[+] Updating package lists..."
sudo apt update -y && sudo apt upgrade -y

# Step 2: Install Apache2
echo "[+] Installing Apache2 web server..."
sudo apt install -y apache2

# Step 3: Install PHP and necessary modules
echo "[+] Installing PHP, necessary PHP modules, and libraries..."
sudo apt install -y php php-cli php-dev php-pear libapache2-mod-php libssh2-1-dev libssh2-1 autoconf make gcc phpize

# Step 4: Enable Apache2 to start on boot
echo "[+] Configuring Apache2 to start on boot..."
sudo systemctl enable apache2

# Step 5: Start Apache2 service
echo "[+] Starting Apache2 service..."
sudo systemctl start apache2

# Step 6: Check installation and versions
echo "────────────────────────────────────────────────"
echo "[*] Checking installed versions..."

echo "[+] Apache2 version:"
apache2 -v

echo "[+] PHP version:"
php -v

echo "[+] Checking if SSH2 extension is installed..."
php -m | grep ssh2 && echo "[+] SSH2 extension is installed!" || echo "[!] SSH2 extension is NOT installed."

# Step 7: Set up basic Apache2 configuration (optional)
echo "[+] Configuring Apache2..."
echo "ServerName localhost" | sudo tee -a /etc/apache2/apache2.conf > /dev/null
sudo systemctl restart apache2

# Step 8: Final message
echo "────────────────────────────────────────────────"
echo "    [*] Setup Complete! Apache2 and PHP are now ready!"
echo "    [*] Visit 'http://localhost' to see your Apache server."
echo "────────────────────────────────────────────────"

# Define the directory where the SSH2 extension is located
SSH2_DIR="$(pwd)/sshConnect_PHP/ssh2-1.4.1"

# Ensure the directory exists
if [ ! -d "$SSH2_DIR" ]; then
    echo "Error: Directory $SSH2_DIR does not exist. Make sure the SSH2 source is extracted."
    exit 1
fi

# Update package lists
sudo apt update

# Install necessary dependencies
sudo apt install -y php-dev libssh2-1-dev libssh2-1 autoconf make gcc phpize

# Navigate to the SSH2 extension directory
cd "$SSH2_DIR" || exit

# Prepare the build environment
phpize
./configure
make

# Install the extension
sudo make install

# Enable the extension in PHP configuration
echo "extension=ssh2.so" | sudo tee -a /etc/php/$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')/cli/php.ini > /dev/null

# Restart web server if applicable
if command -v systemctl &> /dev/null; then
    sudo systemctl restart apache2 2>/dev/null || sudo systemctl restart php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm 2>/dev/null
fi

# Verify installation
php -m | grep ssh2 && echo "SSH2 extension installed successfully!" || echo "Installation failed."
