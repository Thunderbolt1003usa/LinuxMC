<?php
require_once 'includes/lang.php';
// Datenbank für User-Widget laden
require_once '/var/www/secret/db.php'; 

$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, email, realname, avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $found = $stmt->fetch();
    if ($found) {
        $user = $found;
    }
}

// Avatar Logic
$avatarMap = [
    'default' => '/default.png', 
    'tux' => '/default.png',
    'steve' => '/steve.jpg',
    'modem88' => '/modem88.png'
];
$userAvatar = $user['avatar'] ?? 'default';
$avatarFile = $avatarMap[$userAvatar] ?? '/default.png';

header('Content-Type: text/html; charset=utf-8');

function client_ip() {
    $keys = ['HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $ip);
                return trim($parts[0]);
            }
            return $ip;
        }
    }
    return 'unbekannt';
}

$ip = client_ip();
$safe_ip = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');

$details = null;
if ($ip !== 'unbekannt' && $ip !== '::1') {
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    // Wir holen uns ISP, ASN und organisatorische Daten
    $api_url = "http://ip-api.com/json/" . $ip . "?fields=status,country,city,as,isp,org,timezone";
    $response = @file_get_contents($api_url, false, $ctx);
    if ($response) {
        $details = json_decode($response, true);
    }
}

// Extrahiere das /64 Präfix (die ersten 4 Blöcke einer IPv6)

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link rel="icon" type="image/png" href="/tuxrandom64.png">
    <link rel="stylesheet" href="/styles.css">
    <title><?= $t['ipinfo']['title'] ?></title>
    <script src="/main.js"></script>
</head>
<body style="position:relative;">

  <!-- Rechts oben: nur Button/Avatar + Dropdown-Menü -->
  <div id="user-widget" class="user-widget" aria-live="polite">
    <!-- Sprachflaggen -->
    <a href="?lang=de" title="Deutsch" style="text-decoration:none; font-size: 1.5rem; margin-right: 5px; opacity: <?= $lang=='de'?1:0.5 ?>;">🇩🇪</a>
    <a href="?lang=en" title="English" style="text-decoration:none; font-size: 1.5rem; margin-right: 15px; opacity: <?= $lang=='en'?1:0.5 ?>;">🇺🇸</a>
    
    <?php if ($user): ?>
        <!-- Eingeloggt -->
        <div class="login-container">
            <button class="avatar-btn" id="avatar-btn" onclick="toggleUserMenu(event)" aria-haspopup="menu" aria-expanded="false">
                <img src="<?= htmlspecialchars($avatarFile) ?>" id="user-avatar" alt="">
            </button>
            
            <!-- Das Dropdown Menü -->
            <div id="user-menu-dropdown" class="user-menu" hidden>
                <div class="menu-header">
                    <img src="<?= htmlspecialchars($avatarFile) ?>" class="menu-avatar" alt="Profile Picture">
                    <div class="menu-ident">
                        <span class="menu-name"><?= htmlspecialchars($user['username']) ?></span>
                        <span class="menu-email"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                </div>
                <!-- Links im Menü -->
                <button onclick="window.location.href='https://id.linuxmc.tech/logout.php'" class="menu-action">Logout</button>
            </div>
        </div>
    <?php else: ?>
        <!-- Nicht eingeloggt -->
        <button id="local-login-btn" class="login-trigger" onclick="window.location.href='https://id.linuxmc.tech/sso.php?redirect=' + encodeURIComponent(window.location.href)" aria-label="LinuxMC ID Login">
            LinuxMC ID Login
        </button>
    <?php endif; ?>

  </div>

    <main class="container">
      <p>
        <a class="mailto" href="/index.php" aria-label="<?= $t['nav']['home'] ?>"><?= $t['nav']['home'] ?></a>
        <a class="mailto" href="/ipinfo.php" aria-label="<?= $t['nav']['ipv6'] ?>"><?= $t['nav']['ipv6'] ?></a>
        <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com" aria-label="<?= $t['nav']['contact'] ?>"><?= $t['nav']['contact'] ?></a>
        <a class="mailto" href="https://wiki.linuxmc.tech" aria-label="<?= $t['nav']['wiki'] ?>"><?= $t['nav']['wiki'] ?></a>
        <a class="mailto" href="https://files.linuxmc.tech" aria-label="<?= $t['nav']['files'] ?>"><?= $t['nav']['files'] ?></a>
        <a class="mailto" href="https://id.linuxmc.tech" aria-label="<?= $t['nav']['id'] ?>"><?= $t['nav']['id'] ?></a>
      </p>

      <h1><?= $t['ipinfo']['title'] ?></h1>
      
      <strong><?= $t['ipinfo']['your_ip'] ?></strong>
      <pre><?= $safe_ip ?></pre>

      <?php if ($details && isset($details['status']) && $details['status'] === 'success'): ?>
         <hr>
         <h2><?= $t['ipinfo']['network_details'] ?></h2>
         <table class="info-table">
           <tr><td><?= $t['ipinfo']['isp'] ?></td><td><?= htmlspecialchars($details['isp']) ?></td></tr>
           <tr><td><?= $t['ipinfo']['org'] ?></td><td><?= htmlspecialchars($details['org']) ?></td></tr>
           <tr><td><?= $t['ipinfo']['asn'] ?></td><td><?= htmlspecialchars($details['as']) ?></td></tr>
           <tr><td><?= $t['ipinfo']['country'] ?></td><td><?= htmlspecialchars($details['country']) ?></td></tr>
           <tr><td><?= $t['ipinfo']['city'] ?></td><td><?= htmlspecialchars($details['city']) ?></td></tr>
           <tr><td><?= $t['ipinfo']['timezone'] ?></td><td><?= htmlspecialchars($details['timezone']) ?></td></tr>
         </table>
      <?php endif; ?>
    </main>
</body>
</html>
