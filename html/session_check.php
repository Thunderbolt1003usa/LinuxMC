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
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => true,
        'username' => $_SESSION['username']
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>
