<?php
// ============================================================
// layout.php — Shared shell: <head>, sidebar, topbar
// ============================================================
$user        = currentUser();
$role        = $user['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — LQMS</title>

    <!-- BASE_URL injected here so app.js can read it from a <meta> tag
         instead of a fragile data attribute on an arbitrary div. -->
    <meta name="base-url" content="<?= BASE_URL ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= BASE_URL ?>/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/components.css">
    <?= $extraStyles ?? '' ?>
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                <line x1="12" y1="19" x2="12" y2="23"/>
                <line x1="8"  y1="23" x2="16" y2="23"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">LibraryQuiet</div>
            <div class="brand-sub">Noise Monitoring · NBSC</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section">Main</span>

        <a href="<?= BASE_URL ?>/dashboard.php"
           class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
            Dashboard
        </a>

        <a href="<?= BASE_URL ?>/zones.php"
           class="nav-item <?= $currentPage === 'zones' ? 'active' : '' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Zones
        </a>

        <a href="<?= BASE_URL ?>/alerts.php"
           class="nav-item <?= $currentPage === 'alerts' ? 'active' : '' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            Alerts
            <span class="nav-badge" id="alertBadge" style="display:none"></span>
        </a>

        <?php if (hasRole('Administrator', 'Library Manager')): ?>
        <a href="<?= BASE_URL ?>/reports.php"
           class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Reports
        </a>
        <?php endif; ?>

        <?php if (hasRole('Administrator')): ?>
        <span class="nav-section" style="margin-top:8px">Admin</span>

        <a href="<?= BASE_URL ?>/users.php"
           class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                <path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            Users
        </a>

        <a href="<?= BASE_URL ?>/activity_log.php"
           class="nav-item <?= $currentPage === 'activity_log' ? 'active' : '' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Activity Log
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-pill">
            <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                <div class="user-role"><?= htmlspecialchars($role) ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/php/logout.php" class="logout-btn" title="Sign out">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </a>
    </div>
</aside>

<!-- ── Main wrapper ──────────────────────────────────────────── -->
<div class="main-wrap">

    <header class="topbar">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="6"  x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="topbar-left">
            <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
            <?php if (!empty($pageSubtitle)): ?>
            <div class="topbar-subtitle"><?= htmlspecialchars($pageSubtitle) ?></div>
            <?php endif; ?>
        </div>

        <div class="topbar-right">
            <div class="live-indicator" id="livePulse" title="Sensors active">
                <span class="pulse-dot"></span>
                <span>LIVE</span>
            </div>
            <div class="topbar-clock" id="liveClock"></div>
        </div>
    </header>

    <main class="page-content">
