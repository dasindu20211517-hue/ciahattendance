<?php
require_once __DIR__ . '/../auth.php';
if (!isset($pageTitle)) $pageTitle = 'CIAH Attendance';
if (!isset($activePage)) $activePage = '';
requireLoginPage($activePage);
$loggedInUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(COMPANY_NAME); ?></title>
    <meta name="description" content="Attendance, leave, overtime, roster, and reporting system for <?php echo htmlspecialchars(COMPANY_NAME); ?>.">
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="assets/logo.jpg" type="image/jpeg">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CIAH HRIS">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/style.css'); ?>">
</head>
<body>
    <div class="app-layout">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <img src="assets/logo.jpg" alt="Logo" style="width:36px;height:36px;min-width:36px;border-radius:8px;object-fit:contain;background:#fff;padding:2px;">
                    <div style="min-width:0;">
                        <div class="logo-text">HRIS<br><span style="font-size:11px;font-weight:500;letter-spacing:0.5px;">ROWELMARK GROUP</span></div>
                    </div>
                </a>
            </div>
            <nav class="sidebar-nav">
                <?php if (isAdminUser()): ?><div class="nav-section">
                    <div class="nav-section-label">Overview</div>
                    <a href="index.php" class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9632;</span> Dashboard
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Reports</div>
                    <a href="daily.php" class="nav-item <?php echo $activePage === 'daily' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Daily
                    </a>
                    <a href="weekly.php" class="nav-item <?php echo $activePage === 'weekly' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Weekly
                    </a>
                    <a href="monthly.php" class="nav-item <?php echo $activePage === 'monthly' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Monthly
                    </a>
                    <a href="custom.php" class="nav-item <?php echo $activePage === 'custom' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Custom
                    </a>
                </div><?php endif; ?>
                <div class="nav-section">
                    <div class="nav-section-label">Management</div>
                    <a href="roster.php" class="nav-item <?php echo $activePage === 'roster' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Roster
                    </a>
                    <a href="leave.php" class="nav-item <?php echo $activePage === 'leave' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Leave
                    </a>
                    <a href="overtime.php" class="nav-item <?php echo $activePage === 'overtime' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Overtime
                    </a>
                </div>
                <?php if (isAdminUser()): ?><div class="nav-section">
                    <div class="nav-section-label">Settings</div>
                    <a href="shifts.php" class="nav-item <?php echo $activePage === 'shifts' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Shifts
                    </a>
                    <a href="holidays.php" class="nav-item <?php echo $activePage === 'holidays' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Holidays
                    </a>
                    <a href="notifications.php" class="nav-item <?php echo $activePage === 'notifications' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Notifications
                    </a>
                    <a href="import.php" class="nav-item <?php echo $activePage === 'import' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Import DAT
                    </a>
                    <a href="users.php" class="nav-item <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> User Access
                    </a>

                    <a href="employee_contacts.php" class="nav-item <?php echo $activePage === 'contacts' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9679;</span> Employee Contacts
                    </a>
                </div><?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <button class="install-btn" id="installBtn" style="display:none;" onclick="installPWA()">
                    <span class="install-icon">&#8681;</span>
                    <span class="install-label">Install App</span>
                </button>
                <button class="sync-btn" id="syncBtn" onclick="syncDevice()">
                    <span class="sync-icon">&#8635;</span>
                    <span class="spinner"></span>
                    Sync Device
                </button>
                <div class="sync-status" id="syncStatus"></div>
            </div>
        </aside>
        <main class="main-content">
            <div class="topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="menu-toggle" onclick="toggleSidebar()">&#9776;</button>
                    <div class="topbar-title">
                        <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
                        <p><?php echo date('l, F j, Y'); ?> · <?php echo htmlspecialchars($loggedInUser['name']); ?> · <a href="logout.php">Sign out</a></p>
                    </div>
                </div>
            </div>
            <!-- Reports Tab Navigation -->
            <?php if (in_array($activePage, ['daily', 'weekly', 'monthly', 'custom'])): ?>
            <div class="reports-tabs-container">
                <div class="reports-tabs">
                    <a href="daily.php" class="tab-item <?php echo $activePage === 'daily' ? 'active' : ''; ?>">
                        <span class="tab-icon">📅</span>
                        <span class="tab-label">Daily</span>
                    </a>
                    <a href="weekly.php" class="tab-item <?php echo $activePage === 'weekly' ? 'active' : ''; ?>">
                        <span class="tab-icon">📊</span>
                        <span class="tab-label">Weekly</span>
                    </a>
                    <a href="monthly.php" class="tab-item <?php echo $activePage === 'monthly' ? 'active' : ''; ?>">
                        <span class="tab-icon">📈</span>
                        <span class="tab-label">Monthly</span>
                    </a>
                    <a href="custom.php" class="tab-item <?php echo $activePage === 'custom' ? 'active' : ''; ?>">
                        <span class="tab-icon">⚙️</span>
                        <span class="tab-label">Custom</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <div class="page-content">
