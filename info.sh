#!/bin/bash

# Define the directory where the SSH2 extension is located
SSH2_DIR="$HOME/Desktop/ssh2-1.4.1"

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
