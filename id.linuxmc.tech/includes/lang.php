<?php
// Session checken (falls noch nicht gestartet)
if (session_status() === PHP_SESSION_NONE) {
    // Session Params (Subdomain sharing)
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 Tage
        'path' => '/',
        'domain' => '.linuxmc.tech',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 1. Sprache ermitteln
$lang = 'en'; // Default

if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'])) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + (86400 * 30), "/"); 
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

// Gemeinsame/Globale Übersetzungen (optional)
$common_trans = [
    'de' => ['logout' => 'Abmelden', 'back' => 'Zurück'],
    'en' => ['logout' => 'Logout', 'back' => 'Back']
];
?>