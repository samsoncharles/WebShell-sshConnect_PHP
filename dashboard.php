<?php
session_start();

if (!isset($_SESSION['ssh_connected']) || $_SESSION['ssh_connected'] !== true) {
    header('Location: index.php');
    exit;
}

function executeSSHCommand($command) {
    $connection = ssh2_connect($_SESSION['ssh_host'], 22);
    if ($connection && ssh2_auth_password($connection, $_SESSION['ssh_user'], $_SESSION['ssh_pass'])) {
        $stream = ssh2_exec($connection, $command);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        ssh2_disconnect($connection);
        return $output;
    }
    return false;
}

function checkService($service) {
    $status = executeSSHCommand("systemctl is-active $service 2>/dev/null");
    if (trim($status) === "active") {
        return '<span class="badge bg-success pulse-badge">Active 🔥</span>';
    } elseif (trim($status) === "inactive") {
        return '<span class="badge bg-danger">Inactive</span>';
    } else {
        return '<span class="badge bg-secondary">Not Installed</span>';
    }
}

// Get battery status
function getBatteryStatus() {
    $batteryInfo = executeSSHCommand("upower -i $(upower -e | grep battery) 2>/dev/null");
    if (!$batteryInfo) {
        return [
            'status' => 'Not available',
            'percentage' => 'N/A',
            'icon' => 'bi-plug'
        ];
    }

    $percentage = 0;
    $status = 'unknown';
    
    // Extract percentage
    if (preg_match('/percentage:\s*(\d+)%/', $batteryInfo, $matches)) {
        $percentage = (int)$matches[1];
    }
    
    // Extract status
    if (strpos($batteryInfo, 'charging') !== false) {
        $status = 'Charging';
        $icon = 'bi-lightning-charge';
    } elseif (strpos($batteryInfo, 'discharging') !== false) {
        $status = 'Discharging';
        $icon = 'bi-battery';
    } elseif (strpos($batteryInfo, 'fully-charged') !== false) {
        $status = 'Fully charged';
        $icon = 'bi-battery-full';
    } else {
        $status = 'Unknown';
        $icon = 'bi-battery';
    }
    
    // Determine battery level icon
    if ($percentage > 80) {
        $levelIcon = 'bi-battery-full';
    } elseif ($percentage > 60) {
        $levelIcon = 'bi-battery';
    } elseif ($percentage > 40) {
        $levelIcon = 'bi-battery-half';
    } elseif ($percentage > 20) {
        $levelIcon = 'bi-battery-low';
    } else {
        $levelIcon = 'bi-battery';
    }
    
    return [
        'status' => $status,
        'percentage' => $percentage,
        'icon' => $icon,
        'levelIcon' => $levelIcon
    ];
}

function getSystemInfo() {
    $cpuTemp = executeSSHCommand("cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null | head -n1 | awk '{print \$1/1000}'");
    $privateIp = executeSSHCommand("hostname -I | awk '{print \$1}'");
    $diskInfo = executeSSHCommand('df -hT');
    $batteryStatus = getBatteryStatus();
    
    return [
        'hostname' => executeSSHCommand('hostname'),
        'uptime' => executeSSHCommand('uptime -p'),
        'os' => executeSSHCommand('cat /etc/os-release | grep "PRETTY_NAME" | cut -d "=" -f 2 | tr -d \'"\''),
        'kernel' => executeSSHCommand('uname -r'),
        'cpu' => [
            'model' => executeSSHCommand('cat /proc/cpuinfo | grep "model name" | head -n 1 | cut -d ":" -f 2 | sed \'s/^[ \t]*//\''),
            'cores' => executeSSHCommand('nproc'),
            'temp' => $cpuTemp ? trim($cpuTemp).'°C' : 'N/A',
            'usage' => executeSSHCommand("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - \$1}'")
        ],
        'memory' => [
            'total' => executeSSHCommand('free -m | grep "Mem:" | awk \'{print $2}\''),
            'used' => executeSSHCommand('free -m | grep "Mem:" | awk \'{print $3}\''),
            'percent' => executeSSHCommand("free | grep Mem | awk '{print $3/$2 * 100.0}'")
        ],
        'disk' => [
            'total' => executeSSHCommand("df -k / | tail -1 | awk '{print \$2}'") * 1024,
            'free' => executeSSHCommand("df -k / | tail -1 | awk '{print \$4}'") * 1024,
            'percent' => trim(executeSSHCommand("df -h / | tail -1 | awk '{print \$5}' | tr -d '%'"))
        ],
        'battery' => $batteryStatus,
        'diskInfo' => $diskInfo,
        'privateIp' => trim($privateIp),
        'publicIp' => @file_get_contents('https://api.ipify.org') ?: 'Not available',
        'dateTime' => [
            'full' => date('Y-m-d H:i:s'),
            'day' => date('l'),
            'month' => date('F'),
            'year' => date('Y'),
            'week' => date('W'),
            'dayOfYear' => date('z'),
            'timezone' => date_default_timezone_get()
        ],
        'services' => [
            'all' => executeSSHCommand("systemctl list-unit-files --type=service --no-pager"),
            'running' => executeSSHCommand("systemctl list-units --type=service --state=running --no-pager"),
            'status' => executeSSHCommand("service --status-all 2>&1")
        ],
        'network' => [
            'ifconfig' => executeSSHCommand('ifconfig'),
            'ip' => executeSSHCommand('ip a')
        ],
        'processes' => executeSSHCommand('ps aux')
    ];
}

$sysInfo = getSystemInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate SSH Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Floating theme selector */
        .theme-float {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--sidebar-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            width: auto;
            max-width: 90%;
        }
        
        /* Logout button position */
        .logout-float {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        /* Battery status styling */
        .battery-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        .battery-icon {
            font-size: 1.2rem;
        }
        .battery-low {
            color: #dc3545;
        }
        .battery-medium {
            color: #ffc107;
        }
        .battery-high {
            color: #28a745;
        }
        
        /* Progress bars */
        .progress-thin {
            height: 6px;
            margin-top: 5px;
        }
        .progress-thick {
            height: 10px;
            margin-top: 5px;
        }
        
        /* Card styling */
        .info-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .card-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        /* Connection status */
        .connection-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.7; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.7; }
        }
        
        /* Typing animation */
        .cursor {
            display: inline-block;
            width: 3px;
            height: 25px;
            background: var(--text-color);
            vertical-align: middle;
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="container-fluid">
        <!-- Animated Background Elements -->
        <div class="bg-particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        
        <div class="sidebar">
            <div class="sidebar-header">
                <h4 class="animate__animated animate__fadeInDown">
                    <i class="fas fa-terminal"></i> SSH Monitor
                </h4>
                <div class="connection-status animate__animated animate__pulse animate__infinite">
                    <span class="status-dot"></span>
                    <span>Connected to <?= htmlspecialchars($_SESSION['ssh_user']) ?></span>
                </div>
            </div>
            
            <div class="sidebar-info">
                <div class="info-item animate__animated animate__fadeIn">
                    <i class="fas fa-microchip"></i>
                    <span>CPU: <?= htmlspecialchars(trim($sysInfo['cpu']['usage'])) ?>%</span>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-cpu" style="width: <?= htmlspecialchars(trim($sysInfo['cpu']['usage'])) ?>%"></div>
                    </div>
                </div>
                <div class="info-item animate__animated animate__fadeIn animate__delay-1s">
                    <i class="fas fa-memory"></i>
                    <span>RAM: <?= round(trim($sysInfo['memory']['percent']), 1) ?>%</span>
                    <div class="progress progress-thin">
                        <div class="progress-bar bg-ram" style="width: <?= round(trim($sysInfo['memory']['percent']), 1) ?>%"></div>
                    </div>
                </div>
                <?php if ($sysInfo['battery']['status'] !== 'Not available'): ?>
                <div class="info-item animate__animated animate__fadeIn animate__delay-2s">
                    <i class="fas fa-battery-three-quarters"></i>
                    <span>Battery: <?= htmlspecialchars($sysInfo['battery']['percentage']) ?>%</span>
                    <div class="progress progress-thin">
                        <div class="progress-bar 
                            <?= $sysInfo['battery']['percentage'] < 20 ? 'bg-danger' : 
                               ($sysInfo['battery']['percentage'] < 50 ? 'bg-warning' : 'bg-success') ?>"
                            style="width: <?= htmlspecialchars($sysInfo['battery']['percentage']) ?>%">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="sidebar-buttons">
                <button data-bs-toggle="modal" data-bs-target="#diskModal" class="animate__animated animate__fadeInLeft">
                    <i class="fas fa-database"></i> Disk Info
                </button>
                <button data-bs-toggle="modal" data-bs-target="#servicesModal" class="animate__animated animate__fadeInLeft animate__delay-1s">
                    <i class="fas fa-cogs"></i> Service Status
                </button>
                <button data-bs-toggle="modal" data-bs-target="#networkModal" class="animate__animated animate__fadeInLeft animate__delay-2s">
                    <i class="fas fa-network-wired"></i> Network Info
                </button>
                <button data-bs-toggle="modal" data-bs-target="#systemModal" class="animate__animated animate__fadeInLeft animate__delay-1s">
                    <i class="fas fa-info-circle"></i> System Details
                </button>
                <button data-bs-toggle="modal" data-bs-target="#processesModal" class="animate__animated animate__fadeInLeft animate__delay-2s">
                    <i class="fas fa-tasks"></i> Running Processes
                </button>
                <button onclick="location.reload()" class="animate__animated animate__fadeInLeft animate__delay-3s refresh-btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                    <span class="spin-icon"><i class="fas fa-spinner"></i></span>
                </button>
            </div>
        </div>
        
        <!-- Floating Theme Selector -->
        <div class="theme-float">
            <div class="theme-radios">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="theme" id="themeDefault" value="default" checked>
                    <label class="form-check-label" for="themeDefault">
                        <span class="theme-preview" style="background: #121212;"></span>
                        <span class="theme-name">Dark</span>
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="theme" id="themeOcean" value="dark-blue">
                    <label class="form-check-label" for="themeOcean">
                        <span class="theme-preview" style="background: #0a192f;"></span>
                        <span class="theme-name">Ocean</span>
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="theme" id="themeForest" value="dark-green">
                    <label class="form-check-label" for="themeForest">
                        <span class="theme-preview" style="background: #0d1f1d;"></span>
                        <span class="theme-name">Forest</span>
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light">
                    <label class="form-check-label" for="themeLight">
                        <span class="theme-preview" style="background: #f5f7fa;"></span>
                        <span class="theme-name">Light</span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Floating Logout Button -->
        <!-- Floating Logout Button -->
<div class="logout-float">
    <a href="logout.php" class="btn btn-transparent animate__animated animate__fadeInUp">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
    <a href="sshterminal.php" target="_blank" class="btn btn-warning animate__animated animate__fadeInUp">
        <i class="fas fa-terminal"></i> Terminal
    </a>
</div>
        <div class="content">
            <div class="dashboard-header animate__animated animate__fadeIn">
                <h1>
                    <span class="typing-text">Automated Panel For </span>
                    <span class="cursor">|</span>
                </h1>
                <p class="animate__animated animate__fadeIn animate__delay-1s">
                    Monitoring <?= htmlspecialchars(trim($sysInfo['hostname'])) ?>
                </p>
            </div>
            
            <div class="system-info">
                <!-- System Card -->
                <div class="info-card animate__animated animate__fadeInUp">
                    <div class="card-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <h5>System</h5>
                    <div class="card-content">
                        <p><span class="label">Hostname:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['hostname'])) ?></span></p>
                        <p><span class="label">Uptime:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['uptime'])) ?></span></p>
                        <p><span class="label">User:</span> <span class="value"><?= htmlspecialchars($_SESSION['ssh_user']) ?></span></p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- Date & Time Card -->
                <div class="info-card animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h5>Date & Time</h5>
                    <div class="card-content">
                        <p><span class="label">Date:</span> <span class="value"><?= $sysInfo['dateTime']['day'] ?>, <?= $sysInfo['dateTime']['month'] ?> <?= date('j') ?>, <?= $sysInfo['dateTime']['year'] ?></span></p>
                        <p><span class="label">Time:</span> <span class="value"><?= date('H:i:s') ?></span></p>
                        <p><span class="label">Timezone:</span> <span class="value"><?= $sysInfo['dateTime']['timezone'] ?></span></p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- CPU Card -->
                <div class="info-card animate__animated animate__fadeInUp animate__delay-2s">
                    <div class="card-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <h5>CPU</h5>
                    <div class="card-content">
                        <p><span class="label">Model:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['cpu']['model'])) ?></span></p>
                        <p><span class="label">Cores:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['cpu']['cores'])) ?></span></p>
                        <p><span class="label">Temp:</span> <span class="value"><?= htmlspecialchars($sysInfo['cpu']['temp']) ?></span></p>
                        <p><span class="label">Usage:</span> 
                            <span class="value"><?= round(trim($sysInfo['cpu']['usage']), 1) ?>%</span>
                            <div class="progress progress-thick">
                                <div class="progress-bar bg-cpu" style="width: <?= trim($sysInfo['cpu']['usage']) ?>%"></div>
                            </div>
                        </p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- Performance Card -->
                <div class="info-card animate__animated animate__fadeInUp">
                    <div class="card-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h5>Performance</h5>
                    <div class="card-content">
                        <p><span class="label">Load Avg:</span> <span class="value"><?= htmlspecialchars(executeSSHCommand('cat /proc/loadavg | awk \'{print $1,$2,$3}\'')) ?></span></p>
                        <p><span class="label">CPU Usage:</span> 
                            <span class="value"><?= round(trim($sysInfo['cpu']['usage']), 1) ?>%</span>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-cpu" style="width: <?= trim($sysInfo['cpu']['usage']) ?>%"></div>
                            </div>
                        </p>
                        <p><span class="label">RAM Usage:</span> 
                            <span class="value"><?= round(trim($sysInfo['memory']['percent']), 1) ?>%</span>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-ram" style="width: <?= round(trim($sysInfo['memory']['percent']), 1) ?>%"></div>
                            </div>
                        </p>
                        <p><span class="label">Disk Usage:</span> 
                            <span class="value"><?= htmlspecialchars($sysInfo['disk']['percent']) ?>%</span>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-disk" style="width: <?= htmlspecialchars($sysInfo['disk']['percent']) ?>%"></div>
                            </div>
                        </p>
                        <?php if ($sysInfo['battery']['status'] !== 'Not available'): ?>
                        <p><span class="label">Battery:</span> 
                            <span class="value">
                                <i class="fas <?= $sysInfo['battery']['levelIcon'] ?>"></i>
                                <?= htmlspecialchars($sysInfo['battery']['percentage']) ?>% (<?= htmlspecialchars($sysInfo['battery']['status']) ?>)
                            </span>
                            <div class="progress progress-thin">
                                <div class="progress-bar 
                                    <?= $sysInfo['battery']['percentage'] < 20 ? 'bg-danger' : 
                                       ($sysInfo['battery']['percentage'] < 50 ? 'bg-warning' : 'bg-success') ?>"
                                    style="width: <?= htmlspecialchars($sysInfo['battery']['percentage']) ?>%">
                                </div>
                            </div>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- Memory Card -->
                <div class="info-card animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="card-icon">
                        <i class="fas fa-memory"></i>
                    </div>
                    <h5>Memory</h5>
                    <div class="card-content">
                        <p><span class="label">Total:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['memory']['total'])) ?> MB</span></p>
                        <p><span class="label">Used:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['memory']['used'])) ?> MB</span></p>
                        <p><span class="label">Free:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['memory']['total']) - trim($sysInfo['memory']['used'])) ?> MB</span></p>
                        <p><span class="label">Usage:</span> 
                            <span class="value"><?= round(trim($sysInfo['memory']['percent']), 1) ?>%</span>
                            <div class="progress progress-thick">
                                <div class="progress-bar bg-ram" style="width: <?= trim($sysInfo['memory']['percent']) ?>%"></div>
                            </div>
                        </p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- Storage Card -->
                <div class="info-card animate__animated animate__fadeInUp animate__delay-2s">
                    <div class="card-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <h5>Storage</h5>
                    <div class="card-content">
                        <p><span class="label">Total:</span> <span class="value"><?= round($sysInfo['disk']['total'] / (1024*1024*1024), 2) ?> GB</span></p>
                        <p><span class="label">Used:</span> <span class="value"><?= round(($sysInfo['disk']['total'] - $sysInfo['disk']['free']) / (1024*1024*1024), 2) ?> GB</span></p>
                        <p><span class="label">Free:</span> <span class="value"><?= round($sysInfo['disk']['free'] / (1024*1024*1024), 2) ?> GB</span></p>
                        <p><span class="label">Usage:</span> 
                            <span class="value"><?= htmlspecialchars($sysInfo['disk']['percent']) ?>%</span>
                            <div class="progress progress-thick">
                                <div class="progress-bar bg-disk" style="width: <?= htmlspecialchars($sysInfo['disk']['percent']) ?>%"></div>
                            </div>
                        </p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- Network Card -->
                <div class="info-card animate__animated animate__fadeInUp">
                    <div class="card-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <h5>Network</h5>
                    <div class="card-content">
                        <p><span class="label">Public IP:</span> <span class="value"><?= htmlspecialchars($sysInfo['publicIp']) ?></span></p>
                        <p><span class="label">Private IP:</span> <span class="value"><?= htmlspecialchars($sysInfo['privateIp']) ?></span></p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- OS & Kernel Card -->
                <div class="info-card animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="card-icon">
                        <i class="fas fa-ubuntu"></i>
                    </div>
                    <h5>OS & Kernel</h5>
                    <div class="card-content">
                        <p><span class="label">OS:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['os'])) ?></span></p>
                        <p><span class="label">Kernel:</span> <span class="value"><?= htmlspecialchars(trim($sysInfo['kernel'])) ?></span></p>
                    </div>
                    <div class="card-wave"></div>
                </div>
                
                <!-- Key Services Card -->
                <div class="info-card animate__animated animate__fadeInUp animate__delay-2s">
                    <div class="card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h5>Key Services</h5>
                    <div class="card-content">
                        <p><span class="label">SSH:</span> <span class="value"><?= checkService("ssh") ?></span></p>
                        <p><span class="label">Web Server:</span> <span class="value"><?= checkService("apache2") ?></span></p>
                        <p><span class="label">Database:</span> <span class="value"><?= checkService("mariadb") ?></span></p>
                        <p><span class="label">Network:</span> <span class="value"><?= checkService("NetworkManager") ?></span></p>
                    </div>
                    <div class="card-wave"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disk Modal -->
    <div class="modal fade" id="diskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-database"></i> Disk Information (df -hT)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['diskInfo']) ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Modal -->
    <div class="modal fade" id="servicesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cogs"></i> Service Status (service --status-all)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['services']['status']) ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Network Modal -->
    <div class="modal fade" id="networkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-network-wired"></i> Network Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>ifconfig</h6>
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['network']['ifconfig']) ?></pre>
                    </div>
                    <h6 class="mt-3">ip a</h6>
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['network']['ip']) ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- System Modal -->
    <div class="modal fade" id="systemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> System Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= 
                            "Hostname: " . htmlspecialchars(trim($sysInfo['hostname'])) . "\n" .
                            "OS: " . htmlspecialchars(trim($sysInfo['os'])) . "\n" .
                            "Kernel: " . htmlspecialchars(trim($sysInfo['kernel'])) . "\n" .
                            "CPU: " . htmlspecialchars(trim($sysInfo['cpu']['model'])) . " (" . htmlspecialchars(trim($sysInfo['cpu']['cores'])) . " cores)\n" .
                            "CPU Temp: " . htmlspecialchars($sysInfo['cpu']['temp']) . "\n" .
                            "CPU Usage: " . round(trim($sysInfo['cpu']['usage']), 1) . "%\n" .
                            "Uptime: " . htmlspecialchars(trim($sysInfo['uptime'])) . "\n" .
                            "Memory: " . round(trim($sysInfo['memory']['percent']), 1) . "% used (" . htmlspecialchars(trim($sysInfo['memory']['used'])) . "MB of " . htmlspecialchars(trim($sysInfo['memory']['total'])) . "MB)\n" .
                            "Disk: " . htmlspecialchars($sysInfo['disk']['percent']) . "% used\n" .
                            "Load Average: " . htmlspecialchars(executeSSHCommand('cat /proc/loadavg | awk \'{print $1,$2,$3}\'')) . "\n" .
                            "Date: " . $sysInfo['dateTime']['day'] . ", " . $sysInfo['dateTime']['month'] . " " . date('j') . ", " . $sysInfo['dateTime']['year'] . "\n" .
                            "Time: " . date('H:i:s') . "\n" .
                            "Timezone: " . $sysInfo['dateTime']['timezone'] . "\n" .
                            "Public IP: " . htmlspecialchars($sysInfo['publicIp']) . "\n" .
                            "Private IP: " . htmlspecialchars($sysInfo['privateIp'])
                        ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Processes Modal -->
    <div class="modal fade" id="processesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tasks"></i> Running Processes (ps aux)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['processes']) ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Services Modal -->
    <div class="modal fade" id="allServicesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-list-check"></i> All Services (systemctl list-unit-files)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['services']['all']) ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Running Services Modal -->
    <div class="modal fade" id="runningServicesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-play-circle"></i> Running Services (systemctl list-units)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-scrollable">
                        <pre class="animate__animated animate__fadeIn"><?= htmlspecialchars($sysInfo['services']['running']) ?></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="themes.js"></script>
    <script src="scripts.js"></script>
    <script>
        // Typing animation for header
        document.addEventListener('DOMContentLoaded', function() {
            const text = "Basic Unix-System Informations (SSH2_based connection)";
            const typingElement = document.querySelector('.typing-text');
            const cursorElement = document.querySelector('.cursor');
            let i = 0;
            
            function typeWriter() {
                if (i < text.length) {
                    typingElement.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 100);
                } else {
                    cursorElement.style.animation = 'blink 1s infinite';
                }
            }
            
            setTimeout(typeWriter, 500);
            
            // Refresh button animation
            const refreshBtn = document.querySelector('.refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const spinIcon = this.querySelector('.spin-icon');
                    spinIcon.classList.add('animate__animated', 'animate__rotateIn');
                    setTimeout(() => {
                        spinIcon.classList.remove('animate__animated', 'animate__rotateIn');
                    }, 1000);
                });
            }
            
            // Animate cards on load
            const cards = document.querySelectorAll('.info-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('animate__animated', 'animate__zoomIn');
                    setTimeout(() => {
                        card.classList.remove('animate__animated', 'animate__zoomIn');
                    }, 1000);
                }, index * 150);
            });
        });
    </script>
</body>
</html>
