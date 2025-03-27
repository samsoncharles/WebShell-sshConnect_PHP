<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['ssh_connected']) || $_SESSION['ssh_connected'] !== true) {
    header('Location: index.php');
    exit;
}

function executeSSHCommand($command, $getExitCode = false) {
    $connection = ssh2_connect($_SESSION['ssh_host'], 22);
    if ($connection && ssh2_auth_password($connection, $_SESSION['ssh_user'], $_SESSION['ssh_pass'])) {
        $stream = ssh2_exec($connection, $command);
        $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        
        stream_set_blocking($stream, true);
        stream_set_blocking($errorStream, true);
        
        $output = stream_get_contents($stream);
        $error = stream_get_contents($errorStream);
        
        if ($getExitCode) {
            $exitCode = ssh2_exec($connection, 'echo $?');
            stream_set_blocking($exitCode, true);
            $exitCode = intval(stream_get_contents($exitCode));
        }
        
        fclose($stream);
        fclose($errorStream);
        ssh2_disconnect($connection);
        
        if ($getExitCode) {
            return [
                'output' => $output,
                'error' => $error,
                'exit_code' => $exitCode
            ];
        }
        
        return $output . ($error ? "\n" . $error : '');
    }
    return false;
}

// Handle command execution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cmd'])) {
        $cmd = $_POST['cmd'];
        if (!empty($cmd)) {
            header('Content-Type: application/json');
            
            // Handle cd command specially
            if (preg_match('/^\s*cd\s+(.+)$/', $cmd, $matches)) {
                $dir = trim($matches[1]);
                if ($dir === "~") $dir = $_POST['home'];
                
                $result = executeSSHCommand("cd $dir && pwd", true);
                
                if ($result['exit_code'] === 0) {
                    $newDir = trim($result['output']);
                    echo json_encode([
                        'output' => "Changed directory to: $newDir",
                        'new_dir' => $newDir
                    ]);
                } else {
                    echo json_encode([
                        'output' => "cd: no such file or directory: $dir\n" . $result['error'],
                        'new_dir' => $_POST['cwd']
                    ]);
                }
                exit;
            }
            
            // Execute regular command
            $output = executeSSHCommand("cd {$_POST['cwd']} && $cmd 2>&1");
            echo json_encode([
                'output' => $output ? $output : "Command execution failed",
                'new_dir' => $_POST['cwd'] // Directory doesn't change for regular commands
            ]);
            exit;
        }
    }
    elseif (isset($_POST['clear'])) {
        exit(); // Just return empty response for clear
    }
    elseif (isset($_POST['init'])) {
        // Initial connection - get current directory and home
        header('Content-Type: application/json');
        $home = trim(executeSSHCommand('echo $HOME'));
        $cwd = trim(executeSSHCommand('pwd'));
        echo json_encode([
            'home' => $home,
            'cwd' => $cwd,
            'hostname' => trim(executeSSHCommand('hostname')),
            'username' => $_SESSION['ssh_user']
        ]);
        exit;
    }
    elseif (isset($_POST['change_theme'])) {
        // Change theme
        $_SESSION['theme'] = $_POST['theme'];
        exit;
    }
    elseif (isset($_POST['get_system_info'])) {
        // Get system info
        header('Content-Type: application/json');
        
        // CPU usage (average over 1 minute)
        $cpu = trim(executeSSHCommand("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - $1}'"));
        
        // RAM usage
        $ramTotal = trim(executeSSHCommand("free -m | grep Mem | awk '{print $2}'"));
        $ramUsed = trim(executeSSHCommand("free -m | grep Mem | awk '{print $3}'"));
        $ramPercent = round(($ramUsed / $ramTotal) * 100);
        
        // Disk usage (root partition)
        $diskPercent = trim(executeSSHCommand("df -h / | tail -1 | awk '{print $5}' | tr -d '%'"));
        
        // Temperature (if available)
        $temp = trim(executeSSHCommand("cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null | head -1 | awk '{print \$1/1000}'") ?: 'N/A');
        
        echo json_encode([
            'cpu' => $cpu,
            'ram' => $ramPercent,
            'disk' => $diskPercent,
            'temp' => $temp,
            'ram_used' => $ramUsed,
            'ram_total' => $ramTotal
        ]);
        exit;
    }
}

// Get initial info
$initInfo = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($initInfo['init'])) {
    $home = trim(executeSSHCommand('echo $HOME'));
    $cwd = trim(executeSSHCommand('pwd'));
    header('Content-Type: application/json');
    echo json_encode([
        'home' => $home,
        'cwd' => $cwd,
        'hostname' => trim(executeSSHCommand('hostname')),
        'username' => $_SESSION['ssh_user']
    ]);
    exit;
}

// Set default theme if not set
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'default';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>SSH Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Theme Variables - Match dashboard.php */
        :root {
            --body-bg: #121212;
            --header-bg: #1a1a1a;
            --text-color: #ffffff;
            --card-bg: #1e1e1e;
            --button-bg: #2d2d2d;
            --button-hover-bg: #3d3d3d;
            --modal-bg: #252525;
            --border-color: #333333;
            --accent-color: #4a6fa5;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --prompt-color: #4CAF50;
            --root-prompt-color: #ff5555;
            --scrollbar-thumb: #4CAF50;
            --scrollbar-track: #121212;
            --config-bg: #1e1e1e;
        }

        /* Dark Blue Theme */
        .dark-blue-theme {
            --body-bg: #0a192f;
            --header-bg: #172a45;
            --text-color: #e6f1ff;
            --card-bg: #112240;
            --button-bg: #1f3a68;
            --button-hover-bg: #2a4a7a;
            --modal-bg: #1a2a4a;
            --border-color: #303f60;
            --accent-color: #64ffda;
            --success-color: #20c997;
            --danger-color: #ff6b6b;
            --warning-color: #ffd43b;
            --info-color: #4dabf7;
            --prompt-color: #64ffda;
            --root-prompt-color: #ff6b6b;
            --scrollbar-thumb: #64ffda;
            --scrollbar-track: #0a192f;
            --config-bg: #112240;
        }

        /* Dark Green Theme */
        .dark-green-theme {
            --body-bg: #0d1f1d;
            --header-bg: #1a3330;
            --text-color: #d4edec;
            --card-bg: #142a28;
            --button-bg: #1e4a45;
            --button-hover-bg: #2a5a55;
            --modal-bg: #1a3a35;
            --border-color: #2d4d48;
            --accent-color: #5dffc3;
            --success-color: #38d9a9;
            --danger-color: #ff8787;
            --warning-color: #ffd8a8;
            --info-color: #69db7c;
            --prompt-color: #5dffc3;
            --root-prompt-color: #ff8787;
            --scrollbar-thumb: #5dffc3;
            --scrollbar-track: #0d1f1d;
            --config-bg: #142a28;
        }

        /* Light Theme */
        .light-theme {
            --body-bg: #f5f7fa;
            --header-bg: #e1e5eb;
            --text-color: #2d3748;
            --card-bg: #ffffff;
            --button-bg: #e2e8f0;
            --button-hover-bg: #cbd5e0;
            --modal-bg: #ffffff;
            --border-color: #cbd5e0;
            --accent-color: #4a6fa5;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --prompt-color: #4a6fa5;
            --root-prompt-color: #dc3545;
            --scrollbar-thumb: #4a6fa5;
            --scrollbar-track: #f5f7fa;
            --config-bg: #ffffff;
        }

        /* Base Styles */
        body {
            font-family: 'Courier New', monospace;
            background-color: var(--body-bg);
            color: var(--text-color);
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        
        /* Main container */
        .main-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        
        /* Terminal container */
        .terminal-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            background-color: var(--body-bg);
            position: relative;
        }
        
        /* Control panel */
        .control-panel {
            position: fixed;
            right: 0;
            top: 0;
            width: 60px;
            height: 100%;
            background-color: var(--config-bg);
            border-left: 1px solid var(--border-color);
            padding: 15px 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            z-index: 1000;
            transition: width 0.3s ease;
            overflow: hidden;
        }
        
        .control-panel.expanded {
            width: 180px;
        }
        
        .expand-btn {
            position: absolute;
            top: 10px;
            left: 10px;
            cursor: pointer;
            color: var(--prompt-color);
            font-size: 1.2rem;
        }
        
        /* Terminal header (fixed) */
        #terminal-header {
            position: sticky;
            top: 0;
            background-color: var(--header-bg);
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-right: 60px;
        }
        
        #terminal-title {
            color: var(--prompt-color);
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        /* Terminal output area */
        #terminal {
            flex-grow: 1;
            overflow-y: auto;
            padding: 15px;
            white-space: pre-wrap;
            word-break: break-all;
            background-color: var(--body-bg);
            padding-top: 60px; /* Space for header */
        }
        
        /* Input area */
        #input-container {
            padding: 0 15px 15px 15px;
            background-color: var(--body-bg);
        }
        
        /* Prompt styling */
        .prompt-line {
            display: flex;
            white-space: nowrap;
        }
        .prompt-arrow { color: var(--prompt-color); }
        .prompt-user { color: var(--prompt-color); }
        .prompt-symbol { color: var(--prompt-color); }
        .prompt-host { color: var(--prompt-color); }
        .prompt-path { color: var(--prompt-color); font-weight: bold; }
        .prompt-dollar { color: var(--prompt-color); }
        
        /* Root user styling */
        .root-user .prompt-user,
        .root-user .prompt-symbol,
        .root-user .prompt-dollar {
            color: var(--root-prompt-color);
        }
        
        /* Command input */
        #cmd {
            background: transparent;
            border: none;
            color: var(--text-color);
            font-family: 'Courier New', monospace;
            font-size: 1em;
            width: 100%;
            outline: none;
            flex-grow: 1;
            margin-left: 5px;
            autocomplete: "off";
            autocorrect: "off";
            autocapitalize: "off";
            spellcheck: false;
        }
        
        /* Command history styling */
        .command { 
            margin-bottom: 5px;
            color: var(--prompt-color);
        }
        .output { 
            margin-bottom: 10px;
            white-space: pre-wrap;
            color: var(--text-color);
        }
        .command-line {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
        }
        
        /* Welcome message */
        .welcome-message {
            color: var(--prompt-color);
            margin-bottom: 15px;
        }
        
        /* Control buttons */
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--button-bg);
            color: var(--text-color);
            border: 1px solid var(--prompt-color);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
            position: relative;
        }
        
        .control-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 10px var(--accent-color);
        }
        
        .btn-logout {
            background-color: var(--btn-danger);
            border: 1px solid var(--root-prompt-color);
        }
        
        .btn-label {
            position: absolute;
            left: 60px;
            white-space: nowrap;
            background: var(--config-bg);
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .control-panel.expanded .btn-label {
            opacity: 1;
        }
        
        /* Themes selector */
        .themes-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
            margin-bottom: 20px;
        }
        
        .theme-item {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
        }
        
        .theme-item:hover {
            transform: scale(1.1);
        }
        
        .theme-active {
            border-color: var(--accent-color);
            box-shadow: 0 0 10px var(--accent-color);
        }
        
        .theme-label {
            position: absolute;
            left: 40px;
            white-space: nowrap;
            background: var(--config-bg);
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .control-panel.expanded .theme-label {
            opacity: 1;
        }
        
        /* Typing animation for terminal title */
        .typing-cursor {
            display: inline-block;
            width: 10px;
            height: 1em;
            background-color: var(--prompt-color);
            animation: blink 1s infinite;
            vertical-align: middle;
            margin-left: 2px;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        
        /* Alert modal */
        .alert-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: var(--header-bg);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 5px;
            z-index: 2000;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        
        .alert-title {
            color: var(--root-prompt-color);
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .alert-message {
            margin-bottom: 15px;
        }
        
        .countdown-bar {
            height: 5px;
            background-color: var(--border-color);
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .countdown-progress {
            height: 100%;
            background-color: var(--root-prompt-color);
            width: 100%;
            transition: width 2s linear;
        }

        /* Transparent logout button */
        .btn-transparent {
            background-color: transparent;
            border: 1px solid var(--text-color);
            color: var(--text-color);
        }

        .btn-transparent:hover {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--text-color);
        }

        /* Floating buttons */
        .logout-float {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        /* Terminal-specific styles */
        .terminal-status {
            color: var(--text-color);
            opacity: 0.8;
            font-size: 0.9rem;
        }

        /* Animation for terminal elements */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #terminal-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }
            
            #terminal-title, #terminal-status {
                margin-bottom: 5px;
            }
            
            .control-panel {
                width: 50px;
            }
            
            .control-panel.expanded {
                width: 160px;
            }
        }
    </style>
</head>
<body class="<?= ($_SESSION['ssh_user'] === 'root') ? 'root-user' : '' ?> <?= $_SESSION['theme'] !== 'default' ? $_SESSION['theme'].'-theme' : '' ?>">
    <!-- Alert modal -->
    <div class="alert-modal" id="alert-modal">
        <div class="alert-title">WARNING</div>
        <div class="alert-message">No sudoers are allowed here. This message will disappear in 2 seconds.</div>
        <div class="countdown-bar">
            <div class="countdown-progress" id="countdown-progress"></div>
        </div>
    </div>
    
    <div class="main-container">
        <!-- Terminal container -->
        <div class="terminal-container">
            <!-- Fixed terminal header -->
            <div id="terminal-header">
                <div id="terminal-title">
                    <i class="bi bi-terminal"></i> SSH Terminal
                </div>
                <div id="terminal-status">
                    Connected as: <?= htmlspecialchars($_SESSION['ssh_user']) ?>@<?= htmlspecialchars(executeSSHCommand('hostname')) ?>
                </div>
            </div>
            
            <div id="terminal">
                <div class="welcome-message">
                    SSH Terminal - Connecting to server...
                </div>
            </div>
            <div id="input-container" style="display: none;">
                <div class="prompt-line">
                    <span class="prompt-arrow">┌──</span>
                    (<span class="prompt-user" id="prompt-username">user</span>
                    <span class="prompt-symbol">㉿</span>
                    <span class="prompt-host" id="prompt-hostname">host</span>)
                    -[<span class="prompt-path" id="prompt-path">~</span>]
                </div>
                <div class="prompt-line">
                    <span class="prompt-arrow">└─</span>
                    <span class="prompt-dollar">$</span>
                    <input type="text" id="cmd" autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                </div>
                <input type="hidden" id="cwd">
                <input type="hidden" id="home">
            </div>
        </div>
        
        <!-- Control panel -->
        <div class="control-panel" id="control-panel">
            <div class="expand-btn" id="expand-btn">⋮</div>
            
            <div class="control-btn" title="Home" onclick="window.location.href='dashboard.php'">
                <i class="bi bi-house"></i>
                <span class="btn-label">Dashboard</span>
            </div>
            
            <div class="control-btn btn-logout" title="Disconnect" onclick="window.location.href='logout.php'">
                <i class="bi bi-plug"></i>
                <span class="btn-label">Disconnect</span>
            </div>
            
            <div class="themes-selector">
                <div class="theme-item <?= $_SESSION['theme'] === 'default' ? 'theme-active' : '' ?>" data-theme="default" style="background: #121212;">
                    <span class="theme-label">Dark Theme</span>
                </div>
                <div class="theme-item <?= $_SESSION['theme'] === 'dark-blue' ? 'theme-active' : '' ?>" data-theme="dark-blue" style="background: #0a192f;">
                    <span class="theme-label">Ocean Theme</span>
                </div>
                <div class="theme-item <?= $_SESSION['theme'] === 'dark-green' ? 'theme-active' : '' ?>" data-theme="dark-green" style="background: #0d1f1d;">
                    <span class="theme-label">Forest Theme</span>
                </div>
                <div class="theme-item <?= $_SESSION['theme'] === 'light' ? 'theme-active' : '' ?>" data-theme="light" style="background: #f5f7fa;">
                    <span class="theme-label">Light Theme</span>
                </div>
            </div>
        </div>
        
        <div class="panel-overlay" id="panel-overlay"></div>
    </div>

    <!-- Floating buttons -->
    /* <div class="logout-float">
        <a href="logout.php" class="btn btn-transparent animate__animated animate__fadeInUp">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        <a href="dashboard.php" class="btn btn-warning animate__animated animate__fadeInUp">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </div>  */

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="themes.js"></script>
    <script>
        const terminal = document.getElementById('terminal');
        const cmdInput = document.getElementById('cmd');
        const cwdInput = document.getElementById('cwd');
        const homeInput = document.getElementById('home');
        const inputContainer = document.getElementById('input-container');
        const promptUsername = document.getElementById('prompt-username');
        const promptHostname = document.getElementById('prompt-hostname');
        const promptPath = document.getElementById('prompt-path');
        const controlPanel = document.getElementById('control-panel');
        const expandBtn = document.getElementById('expand-btn');
        const panelOverlay = document.getElementById('panel-overlay');
        const alertModal = document.getElementById('alert-modal');
        const countdownProgress = document.getElementById('countdown-progress');
        
        // Command history
        let history = [];
        let historyIndex = -1;
        
        // Show alert modal with countdown
        function showAlertWithCountdown() {
            alertModal.style.display = 'block';
            countdownProgress.style.width = '0%';
            
            // Start countdown
            setTimeout(() => {
                countdownProgress.style.width = '100%';
            }, 10);
            
            // Hide after 2 seconds
            setTimeout(() => {
                alertModal.style.display = 'none';
            }, 2000);
        }
        
        // Initialize terminal
        function initTerminal() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ init: true })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Set initial values
                cwdInput.value = data.cwd;
                homeInput.value = data.home;
                promptUsername.textContent = data.username;
                promptHostname.textContent = data.hostname;
                updatePrompt(data.cwd);
                
                // Show welcome message
                terminal.innerHTML = `
                    <div class="welcome-message">
                        SSH Terminal - Connected to ${data.hostname}<br>
                        User: ${data.username}<br>
                        Current directory: ${data.cwd.replace(data.home, '~')}
                    </div>
                `;
                
                // Show input container
                inputContainer.style.display = 'block';
                cmdInput.focus();
                
                // Show the alert modal
                showAlertWithCountdown();
            })
            .catch(error => {
                console.error('Error:', error);
                terminal.innerHTML += `<div class="output" style="color:red">Error initializing terminal: ${error.message}</div>`;
            });
        }
        
        // Update prompt with current directory
        function updatePrompt(currentDir) {
            promptPath.textContent = currentDir.replace(homeInput.value, '~');
        }
        
        // Handle command execution
        cmdInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const command = cmdInput.value.trim();
                
                if (command === 'clear') {
                    // Clear terminal
                    terminal.innerHTML = '';
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'clear=true'
                    });
                    cmdInput.value = '';
                    return;
                }
                
                if (command) {
                    // Display command
                    const cmdElement = document.createElement('div');
                    cmdElement.className = 'command-line';
                    
                    const promptLine1 = document.createElement('div');
                    promptLine1.className = 'prompt-line';
                    promptLine1.innerHTML = `
                        <span class="prompt-arrow">┌──</span>
                        (<span class="prompt-user">${promptUsername.textContent}</span>
                        <span class="prompt-symbol">㉿</span>
                        <span class="prompt-host">${promptHostname.textContent}</span>)
                        -[<span class="prompt-path">${promptPath.textContent}</span>]
                    `;
                    
                    const promptLine2 = document.createElement('div');
                    promptLine2.className = 'prompt-line';
                    promptLine2.innerHTML = `
                        <span class="prompt-arrow">└─</span>
                        <span class="prompt-dollar">$</span>
                        <span>${command}</span>
                    `;
                    
                    cmdElement.appendChild(promptLine1);
                    cmdElement.appendChild(promptLine2);
                    terminal.appendChild(cmdElement);
                    
                    // Execute command
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `cmd=${encodeURIComponent(command)}&cwd=${encodeURIComponent(cwdInput.value)}&home=${encodeURIComponent(homeInput.value)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.output.trim() !== '') {
                            const outputElement = document.createElement('div');
                            outputElement.className = 'output';
                            outputElement.textContent = data.output;
                            terminal.appendChild(outputElement);
                        }
                        
                        // Update working directory if changed
                        if (data.new_dir && data.new_dir !== cwdInput.value) {
                            cwdInput.value = data.new_dir;
                            updatePrompt(data.new_dir);
                        }
                        
                        // Add to history
                        history.push(command);
                        historyIndex = history.length;
                        
                        // Scroll to bottom
                        terminal.scrollTop = terminal.scrollHeight;
                    })
                    .catch(error => {
                        const errorElement = document.createElement('div');
                        errorElement.className = 'output';
                        errorElement.style.color = 'red';
                        errorElement.textContent = 'Error executing command';
                        terminal.appendChild(errorElement);
                    });
                    
                    // Reset input
                    cmdInput.value = '';
                }
            }
            // History navigation
            else if (e.key === 'ArrowUp') {
                if (historyIndex > 0) {
                    historyIndex--;
                    cmdInput.value = history[historyIndex];
                }
                e.preventDefault();
            }
            else if (e.key === 'ArrowDown') {
                if (historyIndex < history.length - 1) {
                    historyIndex++;
                    cmdInput.value = history[historyIndex];
                } else {
                    historyIndex = history.length;
                    cmdInput.value = '';
                }
                e.preventDefault();
            }
        });
        
        // Keep input focused when clicking anywhere
        document.addEventListener('click', (e) => {
            if (!controlPanel.contains(e.target) && e.target !== expandBtn) {
                cmdInput.focus();
            }
        });
        
        // Theme switching
        document.querySelectorAll('.theme-item').forEach(item => {
            item.addEventListener('click', function() {
                const theme = this.dataset.theme;
                
                // Remove all theme classes first
                document.body.classList.remove(
                    'dark-blue-theme', 
                    'dark-green-theme', 
                    'light-theme'
                );
                
                // Add selected theme class
                if (theme !== 'default') {
                    document.body.classList.add(`${theme}-theme`);
                }
                
                // Update active state
                document.querySelectorAll('.theme-item').forEach(i => {
                    i.classList.remove('theme-active');
                });
                this.classList.add('theme-active');
                
                // Save theme to session
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `change_theme=true&theme=${theme}`
                });
            });
        });
        
        // Toggle control panel expansion
        expandBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            controlPanel.classList.toggle('expanded');
            panelOverlay.style.display = controlPanel.classList.contains('expanded') ? 'block' : 'none';
        });
        
        // Close panel when clicking outside
        panelOverlay.addEventListener('click', function() {
            controlPanel.classList.remove('expanded');
            panelOverlay.style.display = 'none';
        });
        
        // Initialize terminal when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initTerminal();
        });
        
        // Disable browser suggestions
        cmdInput.setAttribute('autocomplete', 'off');
        cmdInput.setAttribute('autocorrect', 'off');
        cmdInput.setAttribute('autocapitalize', 'off');
        cmdInput.setAttribute('spellcheck', 'false');
    </script>
</body>
</html>
