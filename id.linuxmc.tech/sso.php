<?php
require '/var/www/secret/db.php';

// Spracheinbindung
require_once 'includes/lang.php';
require_once 'includes/translations.php';
$txt = $t[$lang];

// Session Einstellungen für Subdomain-übergreifenden Login
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Caching komplett verbieten (WICHTIG für Sicherheits-Tokens!)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Ziel-Service (Whitelist für Sicherheit!)
$allowed_redirects = [
    'https://linuxmc.tech',
    'https://linuxmc.root64.de',
];

$redirect_uri = $_GET['redirect'] ?? 'https://linuxmc.tech';
$base_url = parse_url($redirect_uri, PHP_URL_SCHEME) . '://' . parse_url($redirect_uri, PHP_URL_HOST);

if (!in_array($base_url, $allowed_redirects)) {
    die("Fehler: Ungültige Redirect-URL. Zugriff verweigert.");
}

// Wenn User NICHT eingeloggt ist -> Zeige Login-Maske
if (!isset($_SESSION['user_id'])) {
    
    // Session token generieren falls nicht da (für CSRF beim Login)
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Wir leiten zum normalen Login, aber merken uns, wo wir hinwollten
    // Hier vereinfacht: Wir zeigen ein minimales Login-Formular direkt an
    ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>">
    <head>
        <title><?= $txt['sso_login_title'] ?></title>
        <link rel="stylesheet" href="/styles.css">
        <style>body{display:flex;justify-content:center;align-items:center;min-height:100vh;}</style>
    </head>
    <body style="position:relative;">
        <!-- LANGUAGE SWITCHER -->
        <div style="position:absolute; top:20px; right:20px; z-index:100;">
            <a href="?redirect=<?= urlencode($redirect_uri) ?>&lang=de" style="text-decoration:none; font-size:1.5rem; margin-right:10px; opacity: <?= $lang=='de'?1:0.5 ?>;" title="Deutsch">🇩🇪</a>
            <a href="?redirect=<?= urlencode($redirect_uri) ?>&lang=en" style="text-decoration:none; font-size:1.5rem; opacity: <?= $lang=='en'?1:0.5 ?>;" title="English">🇺🇸</a>
        </div>

        <div class="container" style="max-width:400px; padding:40px;">
            <!-- Top Links (Navigation) -->
            <div style="margin-bottom: 2rem; text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
                <a class="mailto" href="https://linuxmc.tech/index.php?lang=<?= $lang ?>"><?= $txt['home'] ?></a>
                <a class="mailto" href="https://linuxmc.tech/ipinfo.php?lang=<?= $lang ?>"><?= $txt['ipv6'] ?></a>
                <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com"><?= $txt['contact'] ?></a>
                <a class="mailto" href="https://wiki.linuxmc.tech">Wiki</a>
                <a class="mailto" href="https://files.linuxmc.tech">Files</a>
                <a class="mailto" href="https://id.linuxmc.tech">ID</a>
            </div>
            
            <h1>LinuxMC ID</h1>
            <p><?= $txt['sso_continue_to'] ?> <br><strong><?= htmlspecialchars($base_url) ?></strong></p>
            
            <form action="auth.php?action=sso_login&redirect=<?= urlencode($redirect_uri) ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="text" name="username" placeholder="<?= $txt['user'] ?>" required>
                <input type="password" name="password" placeholder="<?= $txt['pass'] ?>" required>
                <button type="submit" class="btn-primary" style="margin-top:10px;"><?= $txt['btn_login'] ?></button>
            </form>
            <p style="font-size:0.8rem; margin-top:20px;">
                <a href="/index.php?view=register&lang=<?= $lang ?>" style="color:#8be9fd; text-decoration:none;"><?= $txt['no_acc'] ?></a><br><br>
                <span style="color: #888;"><?= $txt['forgot_pass'] ?></span> <a href="mailto:ys6v9rqpp@mozmail.com" style="color: #8be9fd;"><?= $txt['support'] ?></a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Wenn User BEREITS eingeloggt ist -> Token generieren und zurückschicken

// 1. ZUERST E-Mail Status prüfen!
$stmt = $pdo->prepare("SELECT email_verified_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$chkUser = $stmt->fetch();

if ($chkUser && empty($chkUser['email_verified_at'])) {
    // Redirect zur Verifizierungs-Seite wenn noch nicht bestätigt
    header("Location: https://id.linuxmc.tech/verify_email_pending.php");
    exit;
}

$token = bin2hex(random_bytes(32));
// Wir nutzen Datenbank-Zeit für Konsistenz (NOW() + 1 Minute)
$stmt = $pdo->prepare("INSERT INTO auth_tokens (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 MINUTE))");
$stmt->execute([$token, $_SESSION['user_id']]);

// WICHTIG: Token an die URL anhängen
$separator = (parse_url($redirect_uri, PHP_URL_QUERY) == NULL) ? '?' : '&';
header("Location: " . $redirect_uri . $separator . "sso_token=" . $token);
exit;
?>
