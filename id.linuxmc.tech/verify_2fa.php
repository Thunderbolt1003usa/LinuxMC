<?php
require '/var/www/secret/db.php';
require '/var/www/id.linuxmc.tech/audit_logger.php'; // Audit Logger laden
require '/var/www/id.linuxmc.tech/totp.php'; // TOTP Helper

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once 'includes/lang.php';
require_once 'includes/translations.php';
$txt = $t[$lang];

if (empty($_SESSION['2fa_pending_user_id'])) {
    header("Location: index.php");
    exit;
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF Token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Ungültige Anfrage (CSRF Token mismatch). Bitte Seite neu laden.");
    }

    $code = trim($_POST['code'] ?? '');
    
    // Hole Secret aus DB
    $stmt = $pdo->prepare("SELECT totp_secret, id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['2fa_pending_user_id']]);
    $dbUser = $stmt->fetch();
    
    if ($dbUser && TOTP::verifyCode($dbUser['totp_secret'], $code)) {
        // Authentifizierung erfolgreich!
        $_SESSION['user_id'] = $dbUser['id'];
        $_SESSION['username'] = $_SESSION['2fa_pending_username'];
        $_SESSION['avatar'] = $_SESSION['2fa_pending_avatar'] ?? 'default';
        
        log_audit($dbUser['id'], 'LOGIN_SUCCESS', '2FA Verified');
        
        $redirect = $_SESSION['2fa_redirect_after'] ?? 'https://linuxmc.tech';
        
        // Aufräumen
        unset($_SESSION['2fa_pending_user_id']);
        unset($_SESSION['2fa_pending_username']);
        unset($_SESSION['2fa_pending_avatar']);
        unset($_SESSION['2fa_redirect_after']);
        
        header("Location: sso.php?redirect=" . urlencode($redirect));
        exit;
    } else {
        $error = ($lang == 'de') ? "Code ungültig oder abgelaufen." : "Invalid code or expired.";
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $txt['verify_2fa_title'] ?> - LinuxMC ID</title>
    <link rel="stylesheet" href="styles.css">
      <script defer src="/main.js"></script>
  </head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; position:relative;">

  <!-- LANGUAGE SWITCHER -->
  <div style="position:absolute; top:20px; right:20px; z-index:100;">
      <a href="?lang=de" title="Deutsch" style="text-decoration:none; font-size:1.5rem; margin-right:5px; opacity: <?= $lang=='de'?1:0.5 ?>;">🇩🇪</a>
      <a href="?lang=en" title="English" style="text-decoration:none; font-size:1.5rem; opacity: <?= $lang=='en'?1:0.5 ?>;">🇺🇸</a>
  </div>

    <!-- Rechts oben: nur Button/Avatar + Dropdown-Menü -->
  <div id="user-widget" class="user-widget" aria-live="polite">
    <!-- Wird von GIS gerendert, solange nicht eingeloggt -->
    
    <!-- Avatar-Button erscheint nach Login -->
    <button id="avatar-btn" class="avatar-btn" aria-haspopup="menu" aria-expanded="false" hidden>
      <img id="user-avatar" alt="">
    </button>
 </div>

    <div class="content-card" style="width: 100%; max-width: 400px; text-align:center;">
        
        <!-- Top Links (Navigation) -->
        <div style="margin-bottom: 2rem; text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
            <a class="mailto" href="https://linuxmc.tech/index.php?lang=<?= $lang ?>"><?= $txt['home'] ?></a>
            <a class="mailto" href="https://linuxmc.tech/ipinfo.php?lang=<?= $lang ?>"><?= $txt['ipv6'] ?></a>
            <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com"><?= $txt['contact'] ?></a>
            <a class="mailto" href="https://wiki.linuxmc.tech">Wiki</a>
            <a class="mailto" href="https://files.linuxmc.tech">Files</a>
            <a class="mailto" href="https://id.linuxmc.tech">ID</a>
        </div>
        
        <h2 style="color: #8be9fd;"><?= $txt['verify_2fa_title'] ?></h2>
        <p style="color: #ccc; margin-bottom: 20px;">
            <?= $txt['verify_2fa_desc'] ?>
        </p>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="text" name="code" 
                   class="api-key-input" 
                   style="font-size: 2rem; letter-spacing: 5px; text-align:center; width: 100%; padding: 10px; margin-bottom: 20px; background: rgba(0,0,0,0.3); border: 1px solid #444;" 
                   placeholder="000000" maxlength="6" autofocus required autocomplete="one-time-code">
            
            <?php if($error): ?>
                <div style="background: rgba(255, 82, 82, 0.1); color: #ff5252; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-primary"><?= $txt['verify_2fa_btn'] ?></button>
        </form>
        
        <p style="margin-top: 20px; font-size: 0.8rem;">
            <a href="index.php" style="color: #666;"><?= $txt['cancel'] ?></a>
        </p>

        <p style="margin-top: 10px; font-size: 0.8rem; color: #666;">
            <?= $txt['lost_device'] ?> <a href="mailto:ys6v9rqpp@mozmail.com" style="color: #8be9fd;"><?= $txt['support'] ?></a>
        </p>

    </div>
</body>
</html>
