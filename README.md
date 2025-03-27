<h1 align="center">
  <img src="https://i.imghippo.com/files/ILw7451YOw.png" alt="" border="0">
  <img src="https://i.imghippo.com/files/bGEc4899hqo.png" alt="" border="0">
  SSH-Enabled PHP Application
</h1>

---

<p align="center">
  <img src="https://github.com/kaggle/spinner/raw/main/spinner.gif" width="30px">
  <b>Secure Shell Authentication for PHP + WebShell </b>
  <img src="https://github.com/kaggle/spinner/raw/main/spinner.gif" width="30px">
</p>

---

## 🚀 What is SSH?

**SSH (Secure Shell)** is a cryptographic network protocol that allows secure remote login and other network services over an insecure network. It enables users to interact with a remote server through a command-line interface (CLI) using encrypted communication.

### Key Features of SSH:
- 🔐 **Encryption** ensures secure communication.
- 👩‍💻 Allows **remote access** to servers for management.
- 🖥️ Used by system administrators and developers for **secure system control**.
- 🔄 **Replaces older protocols** like Telnet and rlogin.

---

## 📜 Requirements

To run the SSH-enabled PHP application, you need the following:

### 1. **Apache2 Web Server** 
   - Apache2 is a powerful HTTP server used to serve web pages.
   - It will host our PHP scripts and handle incoming requests.
   
### 2. **PHP 7.4+**
   - PHP is a server-side scripting language that runs the backend logic for the application.
   
### 3. **OpenSSH Server and Client**
   - OpenSSH is the suite used to manage secure shell sessions.

---

## 📥 Installation

### **For APT Package Manager (Debian/Ubuntu)**
The installation works perfectly with `apt` package manager. Here's how to install:

```sh
sudo apt update
sudo apt install apache2 php openssh-server git -y
sudo git clone https://github.com/samsoncharles/sshConnect_PHP.git
sudo chmod +x sshConnect_PHP/requirements.sh
sudo ./sshConnect_PHP/requirements.sh
sudo mkdir /var/www/html/sshConnect_PHP
sudo cp sshConnect_PHP/* /var/www/html/sshConnect_PHP
sudo chmod 777 /var/www/html/sshConnect_PHP/*
firefox localhost/sshConnect_PHP/index.php
