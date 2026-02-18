<?php
// Session & Config
$lifetime = 86400 * 30;
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Sprache ermitteln
// Priorität: 1. GET Parameter, 2. Session, 3. Cookie, 4. Browser Header, 5. Default (en)
$lang = 'en'; // Default

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'])) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + (86400 * 30), "/"); // 30 Tage
} elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['de', 'en'])) {
    $lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['de', 'en'])) {
    $lang = $_COOKIE['lang'];
} else {
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
    if ($browserLang === 'de') {
        $lang = 'de';
    }
}

// 2. Übersetzungen laden
$trans = [
    'de' => [
        'title' => 'Willkommen bei der LinuxMC Homepage!',
        'description' => 'LinuxMC ist ein Survival Minecraft Java Server. Wenn du mitspielen willst dann nimm bitte <a href="mailto:ys6v9rqpp@mozmail.com" aria-label="E-Mail">Kontakt</a> auf. Checke unbedingt mal unseren <a href="https://files.linuxmc.tech" aria-label="File Server">File Server</a> und unseren <a href="https://wiki.linuxmc.tech" aria-label="Kiwix Server">Kiwix Server</a> aus!',
        'keywords' => 'Minecraft, Survival, Server, Java, LinuxMC, Multiplayer, Deutsch',
        'players_title' => 'Wir haben schon folgende Mitspieler:',
        'server_specs' => 'Server Specs:',
        'support_us' => 'Willst du UNS supporten?',
        'nav' => [
            'home' => 'Startseite',
            'ipv6' => 'IPv6',
            'contact' => 'Kontakt',
            'wiki' => 'Wiki',
            'files' => 'Files',
            'id' => 'ID'
        ],
        'ipinfo' => [
            'title' => 'IPv6 Infos',
            'your_ip' => 'Deine IP-Adresse:',
            'your_prefix' => 'Dein IP-Präfix (/64):',
            'network_details' => 'Netzwerk-Details:',
            'city' => 'Stadt:',
            'country' => 'Land:',
            'timezone' => 'Zeitzone:',
            'isp' => 'Provider (ISP):',
            'org' => 'Organisation:',
            'asn' => 'AS Nummer:',
            'back' => 'Zurück zur Startseite'
        ],
        'error_404' => [
            'title' => 'Fehler: 404',
            'headline' => 'Seite nicht gefunden!',
            'text' => 'Die angeforderte Seite existiert nicht oder wurde verschoben.'
        ]
    ],
    'en' => [
        'title' => 'Welcome to the LinuxMC Homepage!',
        'description' => 'LinuxMC is a Survival Minecraft Java Server. If you want to join, please contact us. Definitely check out our <a href="https://files.linuxmc.tech" aria-label="File Server">File Server</a> and our <a href="https://wiki.linuxmc.tech" aria-label="Kiwix Server">Kiwix Server</a>!',
        'keywords' => 'Minecraft, Survival, Server, Java, LinuxMC, Multiplayer, English',
        'players_title' => 'We already have the following players:',
        'server_specs' => 'Server Specs:',
        'support_us' => 'Do you want to support US?',
        'nav' => [
            'home' => 'Home',
            'ipv6' => 'IPv6',
            'contact' => 'Contact',
            'wiki' => 'Wiki',
            'files' => 'Files',
            'id' => 'ID'
        ],
        'ipinfo' => [
            'title' => 'IPv6 Information',
            'your_ip' => 'Your IP Address:',
            'your_prefix' => 'Your IP Prefix (/64):',
            'network_details' => 'Network Details:',
            'city' => 'City:',
            'country' => 'Country:',
            'timezone' => 'Timezone:',
            'isp' => 'Provider (ISP):',
            'org' => 'Organization:',
            'asn' => 'AS Number:',
            'back' => 'Back to Home'
        ],
        'error_404' => [
            'title' => 'Error: 404',
            'headline' => 'Page not found!',
            'text' => 'The requested page does not exist or has been moved.'
        ]
    ]
];

$t = $trans[$lang];
?>
