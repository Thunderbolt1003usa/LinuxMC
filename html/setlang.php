<?php
$lang = $_GET['lang'] ?? 'en';

// Sicherheit: Nur erlaubte Sprachen zulassen
if (!in_array($lang, ['de', 'en'])) {
    $lang = 'en';
}

// Cookie setzen für 30 Tage
setcookie('lang', $lang, time() + (86400 * 30), "/");

// Zur richtigen Sprachversion weiterleiten
header("Location: /" . $lang . "/index.php");
exit;
?>
