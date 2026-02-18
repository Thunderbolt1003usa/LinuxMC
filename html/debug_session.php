<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "Cookie Domain: " . ini_get('session.cookie_domain') . "<br>";
print_r($_COOKIE);
?>
