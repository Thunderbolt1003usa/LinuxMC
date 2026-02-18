<?php
// Sprach-Logik laden
require_once 'includes/lang.php'; 

// DB & User laden (wie bisher in de/index.php)
// Adjust path to db.php.
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
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SEO Meta Tags -->
  <meta name="description" content="<?= strip_tags($t['description']) ?>">
  <meta name="keywords" content="<?= $t['keywords'] ?>">
  <meta name="author" content="Thunderbolt1003USA">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://linuxmc.tech/">
  <meta property="og:title" content="LinuxMC">
  <meta property="og:description" content="<?= strip_tags($t['description']) ?>">
  <meta property="og:image" content="https://linuxmc.tech/tuxrandom64.png">

  <!-- Twitter -->
  <meta property="twitter:card" content="summary">
  <meta property="twitter:url" content="https://linuxmc.tech/">
  <meta property="twitter:title" content="LinuxMC">
  <meta property="twitter:description" content="<?= strip_tags($t['description']) ?>">
  <meta property="twitter:image" content="https://linuxmc.tech/tuxrandom64.png">

  <link rel="icon" type="image/png" href="/tuxrandom64.png">
  <link rel="stylesheet" href="/styles.css">
  <title>LinuxMC</title>

  <script src="/main.js"></script>
</head>

<body>
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

  <main class="container" role="main" aria-labelledby="title">
    <p>
      <a class="mailto" href="/index.php" aria-label="<?= $t['nav']['home'] ?>"><?= $t['nav']['home'] ?></a>
      <a class="mailto" href="/ipinfo.php" aria-label="<?= $t['nav']['ipv6'] ?>"><?= $t['nav']['ipv6'] ?></a> <!-- URL angepasst -->
      <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com" aria-label="<?= $t['nav']['contact'] ?>"><?= $t['nav']['contact'] ?></a>
      <a class="mailto" href="https://wiki.linuxmc.tech" aria-label="<?= $t['nav']['wiki'] ?>"><?= $t['nav']['wiki'] ?></a>
      <a class="mailto" href="https://files.linuxmc.tech" aria-label="<?= $t['nav']['files'] ?>"><?= $t['nav']['files'] ?></a>
      <a class="mailto" href="https://id.linuxmc.tech" aria-label="<?= $t['nav']['id'] ?>"><?= $t['nav']['id'] ?></a>
    </p>
    <h1 id="index-title"><?= $t['title'] ?></h1>
    <p><?= $t['description'] ?></p>
    <strong><?= $t['players_title'] ?></strong>
    <marquee>timelefant, OssiHD, balu27kalle, samibrosami, ...</marquee>
    <img src="//ipv6.he.net/certification/create_badge.php?pass_name=Thunderbolt1003USA&amp;badge=3" style="border: 0; width: 229px; height: 137px" alt="IPv6 Certification Badge for Thunderbolt1003USA"></img>
    <img src="/LinuxMC-Server.jpg" style="border: 0; width: 229px; height: 137px" alt="LinuxMC Server"></img>
    <a href="https://www.abuseipdb.com/user/268216" title="AbuseIPDB is an IP address blacklist for webmasters and sysadmins to report IP addresses engaging in abusive behavior on their networks">
     <img src="https://www.abuseipdb.com/contributor/268216.svg" alt="AbuseIPDB Contributor Badge" style="width: 229px;border-radius: 5px; border-top: 5px solid #058403; border-right: 5px solid #111; border-bottom: 5px solid #111; border-left: 5px solid #058403; padding: 5px;background: #35c246 linear-gradient(rgba(255,255,255,0), rgba(255,255,255,.3) 50%, rgba(0,0,0,.2) 51%, rgba(0,0,0,0)); padding: 5px;box-shadow: 2px 2px 1px 1px rgba(0, 0, 0, .2);">
      </a> <br>
    <strong><?= $t['server_specs'] ?></strong>
    <ul>
      <li>CPU: Intel i5-3470</li>
      <li>RAM: 32GB DDR3</li>
      <li>SSD: 128GB SATA</li>
      <li>HDD: 2TB SATA</li>
      <li>OS: Ubuntu Server 25.10</li>
      <li>NET: VDSL [⬇️50mbit/s] [⬆️10mbit/s]</li>
    </ul>
    <strong><?= $t['support_us'] ?></strong> <br>
    <a class="mailto" href="bitcoin:bc1qn25mdsh9z6gawehzf0jr2wvf6wl8fhd4emgv40">Bitcoin</a>
    <a class="mailto"
      href="monero:497fd55wL3V6DTuTyE9pLmjkrcJcmrbLL3X4F4Y3A1JRgxvCGuXP2sdFrewG8rpsTff1F2bKRFTsJJCGF3n6vV423wdfY2R">Monero</a>
    <footer>"It's perfect!" LinuxMC</footer>
  </main>
</body>
</html>