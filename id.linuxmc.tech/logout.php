<?php
require '/var/www/secret/db.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.linuxmc.tech',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Delete Session from DB
if(isset($_SESSION['user_id'])) {
    $sid = session_id();
    $dstmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
    $dstmt->execute([$sid]);
}

session_destroy();
header("Location: index.php");
exit;
?>
