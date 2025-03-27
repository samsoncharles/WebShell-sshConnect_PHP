#!/bin/bash

echo "Updating package lists..."
sudo apt update -y || { echo "Failed to update package lists!"; exit 1; }

echo "Installing Apache2..."
sudo apt install apache2 -y || { echo "Failed to install Apache2!"; exit 1; }

echo "Installing PHP and required extensions..."
sudo apt install php php-cli php-ssh2 libapache2-mod-php -y || { echo "Failed to install PHP or extensions!"; exit 1; }

echo "Installing OpenSSH server and client..."
sudo apt install openssh-server openssh-client -y || { echo "Failed to install OpenSSH!"; exit 1; }

echo "Enabling and starting Apache2..."
sudo systemctl enable apache2 && sudo systemctl start apache2 || { echo "Failed to enable/start Apache2!"; exit 1; }

echo "Enabling and starting SSH server..."
sudo systemctl enable ssh && sudo systemctl start ssh || { echo "Failed to enable/start SSH!"; exit 1; }

echo "Installation complete! Checking service statuses..."

echo "Apache2 status:"
sudo systemctl status apache2 --no-pager -l | grep "Active:"

echo "SSH status:"
sudo systemctl status ssh --no-pager -l | grep "Active:"

echo "PHP version:"
php -v

echo "Apache2 version:"
apache2 -v

echo "SSH version:"
ssh -V
