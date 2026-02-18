<?php
// login_callback.php: Hier landet der User nach dem Login auf id.linuxmc.tech

// Session für Subdomain-Sharing konfigurieren
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$token = $_GET['sso_token'] ?? '';

if (!$token) {
    die("SSO Fehler: Kein Token empfangen.");
}

// Token gegen Userdaten tauschen (Server-to-Server Request für Sicherheit)
$apiUrl = 'https://id.linuxmc.tech/api_action.php?action=verify_sso_token&token=' . urlencode($token);

// cURL für den Request nutzen
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("SSO Fehler: Token ungültig oder abgelaufen (Code $httpCode). Bitte erneut versuchen.");
}

$data = json_decode($response, true);

if (isset($data['success']) && $data['success']) {
    // LOGIN SUCCESS!
    // Wir starten jetzt eine lokale Session für die Hauptseite
    $user = $data['user'];
    
    $_SESSION['user_id'] = $user['user_id']; // ID vom Auth-Server
    $_SESSION['username'] = $user['username'];
    $_SESSION['avatar'] = $user['avatar_url'];
    $_SESSION['realname'] = $user['realname'];
    
    // Weiterleiten zur Startseite (Sprache beachten)
    $lang = $_COOKIE['lang'] ?? 'de';
    header("Location: /index.php?lang=$lang");
    exit;
} else {
    // Falls Server einen Fehler sendet
    $errorMsg = $data['error'] ?? 'Unbekannter Fehler'; // Fallback
    $lang = $_COOKIE['lang'] ?? 'de';

    // Fehler auf Englisch anzeigen für bessere Verständlichkeit oder generisch
    if ($lang == 'en') {
        die("SSO Error: " . htmlspecialchars($errorMsg));
    } else {
        die("SSO Fehler: " . htmlspecialchars($errorMsg));
    }
}
?>
