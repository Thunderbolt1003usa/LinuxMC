<?php
require_once 'includes/lang.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/tuxrandom64.png">
  <link rel="stylesheet" href="/styles.css">
  <title><?= $t['error_404']['title'] ?></title>
  
  <script defer src="/main.js"></script>
</head>

<body>
  <!-- Top right: only Button/Avatar + Dropdown Menu -->
  <div id="user-widget" class="user-widget" aria-live="polite">
    <!-- Sprachflaggen -->
    <a href="?lang=de" title="Deutsch" style="text-decoration:none; font-size: 1.5rem; margin-right: 5px; opacity: <?= $lang=='de'?1:0.5 ?>;">🇩🇪</a>
    <a href="?lang=en" title="English" style="text-decoration:none; font-size: 1.5rem; margin-right: 15px; opacity: <?= $lang=='en'?1:0.5 ?>;">🇺🇸</a>

    <!-- Rendered by GIS while not logged in -->
    <div id="login-container" class="login-container" aria-label="Login with Google"></div>

    <!-- Avatar button appears after login -->
    <button id="avatar-btn" class="avatar-btn" aria-haspopup="menu" aria-expanded="false" hidden>
      <img id="user-avatar" alt="">
    </button>

    <!-- Dropdown menu with Name/Email/Logout -->
    <div id="user-menu" class="user-menu" role="menu" hidden>
      <div class="menu-header">
        <img id="menu-avatar" class="menu-avatar" alt="Profile Picture">
        <div class="menu-ident">
          <div id="menu-name" class="menu-name"></div>
          <div id="menu-email" class="menu-email"></div>
        </div>
      </div>
      <button id="logout-btn" class="menu-action" type="button" role="menuitem">Logout</button>
    </div>
  </div>

  <main class="container" role="main" aria-labelledby="title">
    <p>
      <a class="mailto" href="/index.php" aria-label="<?= $t['nav']['home'] ?>"><?= $t['nav']['home'] ?></a>
      <a class="mailto" href="/ipinfo.php" aria-label="<?= $t['nav']['ipv6'] ?>"><?= $t['nav']['ipv6'] ?></a>
      <a class="mailto" href="mailto:ys6v9rqpp@mozmail.com" aria-label="<?= $t['nav']['contact'] ?>"><?= $t['nav']['contact'] ?></a>
      <!-- <a class="mailto" href="/datenschutz.html" aria-label="Datenschutzerklärung">Datenschutzerklärung</a> -->
      <a class="mailto" href="https://wiki.linuxmc.tech" aria-label="<?= $t['nav']['wiki'] ?>"><?= $t['nav']['wiki'] ?></a>
      <a class="mailto" href="https://files.linuxmc.tech" aria-label="<?= $t['nav']['files'] ?>"><?= $t['nav']['files'] ?></a>
      <a class="mailto" href="https://id.linuxmc.tech" aria-label="<?= $t['nav']['id'] ?>"><?= $t['nav']['id'] ?></a>
    </p>
    <h1 id="404-title"><?= $t['error_404']['title'] ?></h1>
    <h2><?= $t['error_404']['headline'] ?></h2>
    <p><?= $t['error_404']['text'] ?></p>
    <footer>"It's perfect!" LinuxMC</footer>
  </main>
</body>

</html>
