<?php
require '/var/www/secret/db.php';
require_once 'includes/lang.php';
require_once 'includes/translations.php';
$txt = $t[$lang];

// 1. Sicherheits-Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 1.1 CSRF Token initialisieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. User-Daten holen
$stmt = $pdo->prepare("SELECT id, phone, username, realname, email, address, zip, city, country, avatar, bio, api_key, is_2fa_enabled, email_verified_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- SESSION VALIDATION (SECURITY) ---
// Prüfen ob Session in DB existiert (falls Revoked)
try {
    $sessId = session_id();
    $chkSess = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
    $chkSess->execute([$sessId, $user['id']]);
    if (!$chkSess->fetch()) {
        // Session wurde revoked oder existiert nicht -> Logout
        session_destroy();
        header("Location: index.php?msg=session_expired");
        exit;
    }
    // Update Last Active
    $pdo->prepare("UPDATE user_sessions SET last_active = NOW(), ip_address = ?, user_agent = ? WHERE session_id = ?")->execute([$_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', $sessId]);
} catch (Exception $e) { /* Ignore DB Error */ }
// -------------------------------------

// 2.1 E-Mail Verifizierung prüfen
if (empty($user['email_verified_at'])) {
    header("Location: verify_email_pending.php");
    exit;
}

// 3. Avatar Bestimmung (Einfache Logik)
$avatarMap = [
    'default' => '/default.png', // Du brauchst dieses Bild im assets Ordner!
    'tux' => '/default.png',
    'steve' => '/steve.jpg',
    'modem88' => '/modem88.png'
];
// Wenn Avatar in DB nicht bekannt, nimm default. 
// (Tipp: Lade ein Bild namens default.png in deinen assets Ordner!)
$avatarFile = $avatarMap[$user['avatar'] ?? 'default'] ?? '/default.png'; 

// 4. Audit Logs holen
$logStmt = $pdo->prepare("SELECT event_type, details, ip_address, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$logStmt->execute([$_SESSION['user_id']]);
$auditLogs = $logStmt->fetchAll();

// 5. Active Sessions holen
$sessStmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_active DESC");
$sessStmt->execute([$_SESSION['user_id']]);
$activeSessions = $sessStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title><?= $txt['dash_title'] ?></title>
    <link rel="stylesheet" href="/styles.css">
    <script src="/main.js"></script>
    <style>
        /* DASHBOARD SPEZIFISCHES LAYOUT */
        body {
            display: block; 
            padding-top: 80px; 
            background-color: #0f172a;
            min-height: 100vh;
        }

        /* Navbar Anpassung für Dashboard */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 70px;
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(139, 233, 253, 0.1);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 100;
        }
        .nav-logo { font-size: 1.5rem; font-weight: bold; color: #8be9fd; text-decoration: none;}

        /* Grid Layout */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        /* Sidebar */
        .sidebar {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 12px;
            padding: 1rem;
            height: fit-content;
            border: 1px solid rgba(139, 233, 253, 0.1);
        }
        
        /* Updated Sidebar Buttons to use .mailto base style while maintaining layout */
        .sidebar .mailto {
            display: block;
            width: 100%;
            margin-bottom: 5px;
            text-align: left;
            box-sizing: border-box; /* WICHTIG: Damit Padding nicht die Breite sprengt */
        }
        
        /* Ensure distinct active state */
        .sidebar .mailto.active { 
            background: rgba(139, 233, 253, 0.2); 
            color: #fff; 
            border-color: #8be9fd;
            font-weight: bold; 
        }

        /* Content Area */
        .content-card {
            background: rgba(30, 41, 59, 0.8);
            border-radius: 12px;
            border: 1px solid rgba(139, 233, 253, 0.1);
            padding: 2rem;
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .content-card.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        h2 { color: #fff; margin-top: 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 20px;}
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .info-box {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .field-label { color: #94a3b8; font-size: 0.85rem; display: block; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;}
        .field-value { color: #fff; font-size: 1.1rem; font-weight: 500;}

        /* Responsive */
        @media (max-width: 768px) {
            .main-container { grid-template-columns: 1fr; padding: 1rem;}
            .sidebar { display: flex; overflow-x: auto; padding: 10px; gap: 10px; margin-bottom: 20px; background: rgba(15, 23, 42, 0.8); }
            .sidebar .mailto { white-space: nowrap; width: auto; margin-bottom: 0;}
        }
    </style>
</head>
<body>

<!-- NAVBAR MIT USER WIDGET -->
<nav class="navbar">
    <a href="#" class="nav-logo">LinuxMC ID</a>
    
    <div style="display: flex; align-items: center;">
        <!-- LANGUAGE SWITCHER -->
        <div style="margin-right: 20px;">
             <a href="?lang=de" title="Deutsch" style="text-decoration:none; font-size:1.5rem; margin-right:5px; opacity: <?= $lang=='de'?1:0.5 ?>;">🇩🇪</a>
             <a href="?lang=en" title="English" style="text-decoration:none; font-size:1.5rem; opacity: <?= $lang=='en'?1:0.5 ?>;">🇺🇸</a>
        </div>

        <!-- Das User Widget (Kopie vom Hauptdesign) -->
        <div class="user-widget">
            <div class="login-container">
                <button class="avatar-btn" id="avatar-btn" onclick="toggleUserMenu(event)" aria-haspopup="menu" aria-expanded="false">
                    <img src="<?= htmlspecialchars($avatarFile) ?>" id="user-avatar" alt="">
                </button>
                
                <!-- Das Dropdown Menü (Standardmäßig hidden) -->
                <div id="user-menu-dropdown" class="user-menu" hidden>
                    <div class="menu-header">
                        <img src="<?= htmlspecialchars($avatarFile) ?>" class="menu-avatar" alt="Profile Picture">
                        <div class="menu-ident">
                            <span class="menu-name"><?= htmlspecialchars($user['username']) ?></span>
                            <span class="menu-email"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                    </div>
                    <!-- Links im Menü -->
                    <button onclick="window.location.href='logout.php'" class="menu-action"><?= $txt['logout'] ?></button>
                </div>
            </div>
        </div>
    </div>
</nav>
</nav>

<div class="main-container">

    <?php if (empty($user['email_verified_at'])): ?>
    <div style="grid-column: 1 / -1; background: rgba(255, 85, 85, 0.2); border: 1px solid #ff5555; padding: 15px; border-radius: 8px; margin-bottom: 10px; color: #ff5555; text-align: center;">
        <?= $txt['verify_mail_warn'] ?>
        <br>
        <small><?= $txt['verify_mail_sub'] ?></small>
        <br><br>
        <button onclick="resendVerification()" id="resendBtn" class="mailto" style="display:inline-block; width:auto; background:#ff5555; color: white; border:1px solid white; cursor: pointer; font-size: 0.9em;"><?= $txt['resend_mail'] ?></button>
    </div>
    <script>
    async function resendVerification() {
        const btn = document.getElementById('resendBtn');
        btn.disabled = true;
        btn.innerText = "<?= $txt['resend_sending'] ?>";
        
        try {
            const response = await fetch('auth.php?action=resend_verification');
            const result = await response.json();
            
            if (result.success) {
                alert("<?= $txt['resend_success'] ?>");
            } else {
                alert("<?= $txt['resend_error'] ?>" + (result.error || "Unknown Error"));
            }
        } catch (e) {
            alert("Connection Error.");
            console.error(e);
        } finally {
            btn.disabled = false;
            btn.innerText = "<?= $txt['resend_mail'] ?>";
        }
    }
    </script>
    <?php endif; ?>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <button onclick="showTab('overview')" class="mailto active" id="btn-overview"><?= $txt['overview'] ?></button>
        <button onclick="showTab('profile')" class="mailto" id="btn-profile"><?= $txt['profile'] ?></button>
        <button onclick="showTab('security')" class="mailto" id="btn-security"><?= $txt['security'] ?></button>
        <button onclick="showTab('api')" class="mailto" id="btn-api"><?= $txt['api'] ?></button>
        <hr style="border:0; border-top:1px solid rgba(139, 233, 253, 0.1); margin: 15px 0;">
        <a class="mailto" href="https://linuxmc.tech/index.php?lang=<?= $lang ?>" aria-label="<?= $txt['back_home'] ?>"><?= $txt['home'] ?></a>
        <a class="mailto" href="https://linuxmc.tech/ipinfo.php?lang=<?= $lang ?>" aria-label="<?= $txt['ipv6'] ?>"><?= $txt['ipv6'] ?></a>
        <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com" aria-label="E-Mail">Kontakt</a>
        <a class="mailto" href="https://wiki.linuxmc.tech" aria-label="Wiki">Wiki</a>
        <a class="mailto" href="https://files.linuxmc.tech" aria-label="Files">Files</a>
        <a class="mailto" href="https://id.linuxmc.tech" aria-label="ID">ID</a>
    </aside>

    <!-- TAB 1: ÜBERSICHT -->
        <!-- TAB 1: ÜBERSICHT (Verbessert) -->
    <main id="tab-overview" class="content-card active">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 20px;">
            <h2 style="border:none; margin:0; padding:0;"><?= $txt['overview'] ?></h2>
            <span style="background: #8be9fd; color: #0f172a; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 0.8rem;">
                ID: #<?= $user['id'] ?>
            </span>
        </div>

        <p style="color: #ccc; margin-bottom: 30px;">
            Hallo <strong><?= htmlspecialchars($user['realname']) ?></strong>!
        </p>
        
        <!-- Sektion 1: Account -->
        <h3 style="color: #8be9fd; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 15px;"><?= $txt['tab_acc_details'] ?></h3>
        <?php
            // Check auf Failed Logins in den letzten 10 Events
            $hasFailedLogins = false;
            foreach($auditLogs as $l) {
                if(strpos($l['event_type'], 'LOGIN_FAILED') !== false) {
                    $hasFailedLogins = true;
                    break;
                }
            }
            // Definition: Geschützt = 2FA aktiv UND keine Failed Logins
            $isSecure = $user['is_2fa_enabled'] && !$hasFailedLogins;
        ?>
        <div class="info-grid">
            <div class="info-box">
                <span class="field-label"><?= $txt['field_username'] ?></span>
                <div class="field-value"><?= htmlspecialchars($user['username']) ?></div>
            </div>
            <div class="info-box">
                <span class="field-label"><?= $txt['email'] ?></span>
                <div class="field-value"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <div class="info-box">
                <span class="field-label"><?= $txt['field_status'] ?></span>
                <div class="field-value" style="color: #50fa7b;"><?= $txt['status_active'] ?></div>
            </div>
            <div class="info-box">
                <span class="field-label"><?= $txt['status_check'] ?></span>
                <?php if($isSecure): ?>
                    <div class="field-value" style="color: #50fa7b;">
                         <span style="font-size:1.2rem;">✔</span> <?= $txt['status_secure'] ?>
                    </div>
                <?php else: ?>
                    <div class="field-value" style="color: #ff5555;">
                        <span style="font-size:1.2rem;">⚠</span> <?= $txt['status_check'] ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <br><br>

                <!-- Sektion 2: Persönliche Daten -->
        <h3 style="color: #8be9fd; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 15px;"><?= $txt['tab_pers_info'] ?></h3>
        
        <div class="info-grid">
            <div class="info-box">
                <span class="field-label"><?= $txt['field_fullname'] ?></span>
                <div class="field-value"><?= htmlspecialchars($user['realname']) ?></div>
            </div>
            
            <div class="info-box">
                <span class="field-label"><?= $txt['field_phone'] ?></span>
                <div class="field-value">
                    <?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span style="color:#666; font-style:italic;">'.$txt['not_specified'].'</span>' ?>
                </div>
            </div>

            <!-- NEU: Land separat anzeigen -->
            <div class="info-box">
                <span class="field-label"><?= $txt['country'] ?></span>
                <div class="field-value"><?= htmlspecialchars($user['country']) ?></div>
            </div>
            
            <!-- Adresse Block -->
            <div class="info-box">
                <span class="field-label"><?= $txt['field_address'] ?></span>
                <div class="field-value">
                    <?= htmlspecialchars($user['address']) ?><br>
                    <?= htmlspecialchars($user['zip']) ?> <?= htmlspecialchars($user['city']) ?>
                </div>
            </div>

            <!-- NEU: Bio / Über mich (Breite Box) -->
            <div class="info-box" style="grid-column: 1 / -1; margin-top: 10px;">
                <span class="field-label"><?= $txt['field_bio'] ?></span>
                <div class="field-value" style="line-height: 1.5; color: #cbd5e1;">
                    <?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : '<span style="color:#666; font-style:italic;">'.$txt['no_desc'].'</span>' ?>
                </div>
            </div>
        </div>
    </main>
    <!-- WEITERE TABS (Platzhalter) -->
        <!-- TAB 2: PROFIL BEARBEITEN -->
    <main id="tab-profile" class="content-card">
        <h2><?= $txt['profile'] ?></h2>
        <div id="prof-msg" style="display:none; padding:10px; margin-bottom:15px; border-radius:5px;"></div>

        <form onsubmit="updateProfile(event)">
            
            <!-- AVATAR AUSWAHL -->
            <h3 style="color:#8be9fd; font-size:0.9rem;"><?= $txt['choose_avatar'] ?></h3>
            <div class="avatar-grid" style="display:flex; gap:15px; margin-bottom:25px;">
                <!-- Option 1: Tux -->
                <label class="avatar-option">
                    <input type="radio" name="avatar" value="tux" <?= $user['avatar']=='tux'?'checked':'' ?>>
                    <img src="/default.png" alt="Tux">
                </label>
                <!-- Option 2: Steve -->
                <label class="avatar-option">
                    <input type="radio" name="avatar" value="steve" <?= $user['avatar']=='steve'?'checked':'' ?>>
                    <img src="/steve.jpg" alt="Steve">
                </label>
                <!-- Option 3: Default -->
                 <label class="avatar-option">
                    <input type="radio" name="avatar" value="modem88" <?= $user['avatar']=='modem88'?'checked':'' ?>>
                    <img src="/modem88.png" alt="Modem88">
                </label>
                
            </div>

            <!-- PERSÖNLICHE DATEN -->
            <div style="margin-bottom:15px;">
                <span class="field-label"><?= $txt['email'] ?></span>
                <input type="email" id="p_email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <span class="field-label"><?= $txt['field_fullname'] ?></span>
                    <input type="text" id="p_realname" value="<?= htmlspecialchars($user['realname']) ?>" required>
                </div>
                <div>
                    <span class="field-label"><?= $txt['field_phone'] ?></span>
                    <input type="text" id="p_phone" value="<?= htmlspecialchars($user['phone']) ?>">
                </div>
            </div>

            <!-- ADRESSE -->
            <div style="margin-bottom:15px;">
                <span class="field-label"><?= $txt['field_address'] ?></span>
                <input type="text" id="p_address" value="<?= htmlspecialchars($user['address']) ?>" required>
            </div>

            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <span class="field-label"><?= $txt['zip'] ?></span>
                    <input type="text" id="p_zip" value="<?= htmlspecialchars($user['zip']) ?>" required>
                </div>
                <div>
                    <span class="field-label"><?= $txt['city'] ?></span>
                    <input type="text" id="p_city" value="<?= htmlspecialchars($user['city']) ?>" required>
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <span class="field-label"><?= $txt['country'] ?></span>
                <select id="p_country" required>
                    <?php 
                    $countries = ['Deutschland', 'Österreich', 'Schweiz', 'USA', 'UK', 'Other'];
                    foreach($countries as $c) {
                        $sel = ($user['country'] === $c) ? 'selected' : '';
                        echo "<option value='$c' $sel>$c</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- BIO -->
            <div style="margin-bottom:20px;">
                <span class="field-label"><?= $txt['field_bio'] ?></span>
                <textarea id="p_bio" rows="4"><?= htmlspecialchars($user['bio']) ?></textarea>
            </div>

            <button type="submit" class="mailto"><?= $txt['save_btn'] ?></button>
        </form>
    </main>
    <main id="tab-security" class="content-card">
        <h2><?= $txt['security'] ?></h2>
        
        <!-- Passwort ändern Box -->
        <h3 style="color: #8be9fd; font-size: 0.9rem; text-transform: uppercase;"><?= $txt['change_pw_title'] ?></h3>
        <p style="color: #ccc; margin-bottom: 20px;"><?= $txt['change_pw_desc'] ?></p>

        <form onsubmit="changePassword(event)">
            <div>
                <span class="field-label"><?= $txt['curr_pw'] ?></span>
                <input type="password" id="sec_old_pw" required placeholder="••••••••">
            </div>
            
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                <div>
                    <span class="field-label"><?= $txt['new_pw'] ?></span>
                    <input type="password" id="sec_new_pw" required placeholder="<?= $txt['new_pw'] ?>">
                </div>
                <div>
                    <span class="field-label"><?= $txt['repeat_pw'] ?></span>
                    <input type="password" id="sec_new_pw2" required placeholder="<?= $txt['repeat_pw'] ?>">
                </div>
            </div>

            <div id="sec-msg" style="margin: 10px 0; padding:10px; border-radius:5px; display:none;"></div>

            <button type="submit" class="mailto" style="margin-top: 10px;"><?= $txt['change_pw_title'] ?></button>
        </form>

        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin: 30px 0;">

        <!-- ACTIVE SESSIONS -->
        <h3 style="color: #8be9fd; font-size: 0.9rem; text-transform: uppercase; display:flex; justify-content:space-between; align-items:center;">
            <?= $txt['active_sessions_title'] ?>
            <?php if(count($activeSessions) > 1): ?>
                <button onclick="revokeAllSessions()" class="mailto" style="width:auto; padding:5px 10px; font-size:0.8rem; border-color:#ff5555; color:#ff5555; margin:0;"><?= $txt['revoke_all_btn'] ?></button>
            <?php endif; ?>
        </h3>
        <script>
        async function revokeSession(id) {
            if(!confirm("<?= $txt['revoke_confirm'] ?>")) return;
            try {
                const res = await fetch('api_action.php?action=revoke_session&id='+id);
                const data = await res.json();
                if(data.success) location.reload();
                else alert(data.error);
            } catch(e) { alert('Error'); }
        }
        async function revokeAllSessions() {
            if(!confirm("<?= $txt['revoke_all_confirm'] ?>")) return;
            try {
                const res = await fetch('api_action.php?action=revoke_all_sessions');
                const data = await res.json();
                if(data.success) location.reload();
                else alert(data.error);
            } catch(e) { alert('Error'); }
        }
        </script>
        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); overflow:hidden; margin-bottom:15px;">
            <table style="width:100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: rgba(139, 233, 253, 0.1); color: #8be9fd; text-align:left;">
                        <th style="padding: 10px;"><?= $txt['device'] ?></th>
                        <th style="padding: 10px;"><?= $txt['location'] ?></th>
                        <th style="padding: 10px;"><?= $txt['last_active'] ?></th>
                        <th style="padding: 10px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($activeSessions as $s): 
                        $isCurrent = ($s['session_id'] === session_id());
                        // User Agent Parsing (Simple)
                        $browser = "Unknown Device";
                        if (strpos($s['user_agent'], 'Firefox') !== false) $browser = "Firefox";
                        if (strpos($s['user_agent'], 'Chrome') !== false) $browser = "Chrome";
                        if (strpos($s['user_agent'], 'Safari') !== false && strpos($s['user_agent'], 'Chrome') === false) $browser = "Safari";
                        if (strpos($s['user_agent'], 'Edge') !== false) $browser = "Edge";
                        if (strpos($s['user_agent'], 'iPhone') !== false) $browser .= " on iPhone";
                        if (strpos($s['user_agent'], 'Android') !== false) $browser .= " on Android";
                        if (strpos($s['user_agent'], 'Windows') !== false) $browser .= " (Windows)";
                        if (strpos($s['user_agent'], 'Macintosh') !== false) $browser .= " (Mac)";
                        if (strpos($s['user_agent'], 'Linux') !== false) $browser .= " (Linux)";
                    ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding: 10px; color:#fff;">
                            <div style="font-weight:bold;"><?= htmlspecialchars($browser) ?></div>
                            <?php if($isCurrent): ?>
                                <span style="font-size:0.75rem; color:#50fa7b; border:1px solid #50fa7b; padding:2px 4px; border-radius:4px;"><?= $txt['current_session'] ?></span>
                            <?php endif; ?>
                            <div style="font-size: 0.8em; color: #888; font-family: monospace;" title="<?= htmlspecialchars($s['user_agent']) ?>"><?= htmlspecialchars($s['ip_address']) ?></div>
                        </td>
                        <td style="padding: 10px; font-family:monospace; color:#ccc;">
                             <!-- Location Container -->
                             <span id="loc-<?= $s['id'] ?>" class="location-load" data-ip="<?= $s['ip_address'] ?>">
                                <span style="color:#666;">Loading...</span>
                             </span>
                        </td>
                        <td style="padding: 10px; color:#aaa;"><?= $s['last_active'] ?></td>
                        <td style="padding: 10px; text-align:right;">
                            <?php if(!$isCurrent): ?>
                                <button onclick="revokeSession('<?= $s['session_id'] ?>')" style="background:none; border:none; color:#ff5555; cursor:pointer; font-size:1.2rem;" title="<?= $txt['revoke_btn'] ?>">×</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            // Async Location Loader
            document.addEventListener("DOMContentLoaded", function() {
                const locs = document.querySelectorAll('.location-load');
                locs.forEach(async el => {
                    const ip = el.getAttribute('data-ip');
                    if(ip === '127.0.0.1' || ip === '::1') {
                        el.innerText = 'Localhost'; 
                        return;
                    }
                    try {
                        const res = await fetch('api_action.php?action=geoip&ip=' + ip);
                        const data = await res.json();
                        if(data.city) {
                            el.innerHTML = `<span style="color:#8be9fd;">${data.city}, ${data.country}</span>`;
                        } else {
                            el.innerText = 'Unknown';
                        }
                    } catch(e) { el.innerText = '-'; }
                });
            });
            </script>
        </div>

        <!-- 2FA SECTION -->
        <h3 style="color: #8be9fd; font-size: 0.9rem; text-transform: uppercase;"><?= $txt['2fa_title'] ?></h3>
        
        <?php if($user['is_2fa_enabled']): ?>
            <!-- 2FA IST AKTIV -->
            <div class="info-box" style="border: 1px solid #00c853; background: rgba(0, 200, 83, 0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="color: #00c853;"><?= $txt['2fa_active'] ?></strong> <br>
                        <span style="font-size: 0.85rem; color: #ccc;"><?= $txt['2fa_protected'] ?></span>
                    </div>
                </div>
            </div>
            
            <p style="margin-top:10px; font-size:0.8rem; color:#888;"><?= $txt['2fa_disable_desc'] ?></p>
            <div style="display:flex; gap:10px; max-width:400px;">
                <input type="password" id="disable_2fa_pw" placeholder="<?= $txt['pass'] ?>" class="modal-input" style="margin:0;">
                <button onclick="disable2FA()" class="mailto" style="width:auto; white-space:nowrap; border-color:#ff5252; color:#ff5252;"><?= $txt['2fa_remove_btn'] ?></button>
            </div>

        <?php else: ?>
            <!-- 2FA IST AUS -->
            <p style="color: #ccc; margin-bottom: 20px;">
                <?= $txt['2fa_inactive_desc'] ?>
            </p>
            
            <div id="2fa-intro">
                <button onclick="start2FASetup()" class="mailto" style="background: var(--accent); color: var(--bg1);"><?= $txt['2fa_setup_btn'] ?></button>
            </div>

            <!-- SETUP AREA (Initial versteckt) -->
            <div id="2fa-setup" style="display:none; margin-top:20px; background: rgba(15, 23, 42, 0.5); padding: 20px; border-radius: 12px; border: 1px solid rgba(139, 233, 253, 0.2);">
                <h4 style="margin-top:0; color:#fff;"><?= $txt['2fa_step1'] ?></h4>
                <div id="qrcode" style="background:white; padding:10px; width:fit-content; border-radius:8px; margin-bottom:20px;"></div>
                
                <p style="font-size:0.9rem; color:#aaa;">Secret Key: <code id="manual-secret" style="background:#000; padding:4px; font-family:monospace; color:#8be9fd;"></code></p>

                <h4 style="color:#fff; margin-top:20px;"><?= $txt['2fa_step2'] ?></h4>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="2fa_code" placeholder="123456" maxlength="6" style="width: 150px; text-align: center; font-size: 1.5rem; letter-spacing: 5px; background: #000; border:1px solid #444; color:#fff; padding: 5px; border-radius: 5px;">
                    <button onclick="confirm2FA()" class="mailto" style="width: auto;"><?= $txt['2fa_activate_btn'] ?></button>
                </div>
                <div id="2fa-msg" style="margin-top:10px;"></div>
            </div>
        <?php endif; ?>

        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin: 30px 0;">

        <!-- AUDIT LOG SECTION -->
        <h3 style="color: #8be9fd; font-size: 0.9rem; text-transform: uppercase;"><?= $txt['activity_title'] ?></h3>
        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); overflow:hidden;">
            <?php if(count($auditLogs) > 0): ?>
                <table style="width:100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: rgba(139, 233, 253, 0.1); color: #8be9fd; text-align:left;">
                            <th style="padding: 10px;"><?= $txt['col_date'] ?></th>
                            <th style="padding: 10px;"><?= $txt['col_event'] ?></th>
                            <th style="padding: 10px;"><?= $txt['col_details'] ?></th>
                            <th style="padding: 10px;">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($auditLogs as $log): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 10px; color:#aaa;"><?= $log['created_at'] ?></td>
                            <td style="padding: 10px; color:#fff;"><?= htmlspecialchars($log['event']) ?></td>
                            <td style="padding: 10px; color:#ccc;"><?= htmlspecialchars($log['details']) ?></td>
                            <td style="padding: 10px; font-family:monospace; color:#8be9fd;"><?= htmlspecialchars($log['ip_address']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding:20px; color:#888; text-align:center;"><?= $txt['no_activity'] ?></div>
            <?php endif; ?>
        </div>

        <!-- DANGER ZONE -->
        <hr style="border:0; border-top:1px solid rgba(255,85,85,0.3); margin: 40px 0;">
        
        <div style="border: 1px solid #ff5555; background: rgba(255, 85, 85, 0.05); padding: 20px; border-radius: 8px;">
            <h3 style="color: #ff5555; margin-top: 0; text-transform: uppercase; font-size: 0.9rem;"><?= $txt['danger_zone'] ?></h3>
            <p style="color: #ccc; font-size: 0.9rem; margin-bottom: 15px;">
                <?= $txt['delete_warning_text'] ?>
            </p>
            <button onclick="document.getElementById('delete-modal').style.display='flex'" class="mailto" style="background: transparent; border: 1px solid #ff5555; color: #ff5555; margin: 0;">
                <?= $txt['delete_btn'] ?>
            </button>
        </div>
    </main>
        <!-- TAB 4: API / ENTWICKLER -->
    <main id="tab-api" class="content-card">
        <h2><?= $txt['api_title'] ?></h2>
        <p style="color: #ccc; margin-bottom: 20px;">
            <?= $txt['api_desc'] ?>
            <br><span style="color: #ff5252;"><?= $txt['api_warning'] ?></span>
        </p>
        
        <div class="info-box" style="margin-bottom: 20px;">
            <span class="field-label"><?= $txt['api_key_label'] ?></span>
            <div style="display: flex; gap: 10px; margin-top: 5px;">
                <input type="text" id="api-key-display" 
                       value="<?= $user['api_key'] ? htmlspecialchars($user['api_key']) : $txt['api_no_key'] ?>" 
                       readonly 
                       class="api-key-input">
                
                <button onclick="copyApiKey()" class="mailto"><?= $txt['api_copy'] ?></button>
            </div>
        </div>

        <button onclick="generateApiKey()" class="mailto">
            <?= $user['api_key'] ? $txt['api_regenerate'] : $txt['api_generate'] ?>
        </button>
        
        <div id="api-msg" style="margin-top: 15px; display: none;"></div>
    </main>   

</div>

<!-- DELETE CONFIRM MODAL -->
<div id="delete-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); align-items:center; justify-content:center; z-index:9999;">
    <div style="background:#1e293b; padding:30px; border-radius:12px; border:1px solid #ff5555; max-width:400px; width:90%; box-shadow: 0 0 50px rgba(255, 85, 85, 0.2);">
        <h3 style="color:#ff5555; margin-top:0;"><?= $txt['modal_title'] ?></h3>
        <p style="color:#ccc; line-height:1.5;">
            <?= $txt['modal_text'] ?>
        </p>
        <p style="color:#fff;"><?= $txt['modal_pw_label'] ?></p>
        <input type="password" id="del-pw" placeholder="<?= $txt['modal_placeholder'] ?>" class="modal-input" style="width:100%; margin-bottom:15px; padding:10px; background:#0f172a; border:1px solid #333; color:#fff; border-radius:5px;">
        
        <div style="display:flex; justify-content:space-between; gap:10px;">
            <button onclick="document.getElementById('delete-modal').style.display='none'" style="flex:1; padding:10px; background:transparent; border:1px solid #555; color:#ccc; border-radius:5px; cursor:pointer;"><?= $txt['modal_cancel'] ?></button>
            <button onclick="deleteAccount()" style="flex:1; padding:10px; background:#ff5555; border:none; color:#fff; border-radius:5px; font-weight:bold; cursor:pointer;"><?= $txt['modal_confirm'] ?></button>
        </div>
    </div>
</div>

<script>
async function deleteAccount() {
    const pw = document.getElementById('del-pw').value;
    if(!pw) { alert("<?= $txt['alert_pw_missing'] ?>"); return; }

    try {
        const res = await apiFetch('delete_account', {
            method: 'POST',
            body: JSON.stringify({ password: pw })
        });

        // Robustes Handling: Erst Text holen, dann parsen
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error("Server Response was not JSON:", text);
            alert("Server Error:\n" + text.substring(0, 200));
            return; 
        }

        if(data.success) {
            alert("<?= $txt['alert_deleted'] ?>");
            window.location.href = 'index.php';
        } else {
            alert("<?= $txt['alert_error'] ?>" + (data.error || "Unknown Error"));
        }
    } catch(e) {
        console.error(e);
        alert("<?= $txt['alert_connection'] ?> " + e.message);
    }
}
</script>

</body>
</html>
