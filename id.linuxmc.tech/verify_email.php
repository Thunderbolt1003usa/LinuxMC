<?php
require_once 'includes/lang.php';
// require_once '../secret/db.php'; // Pfad korrigiert in auth.php war es /var/www/secret/db.php
require_once '/var/www/secret/db.php';

$token = $_GET['token'] ?? '';
$message = '';
$class = '';

if ($token) {
    try {
        // Zuerst prüfen, ob der Token existiert
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Update: Token entfernen und Zeit setzen
            $update = $pdo->prepare("UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE id = ?");
            if ($update->execute([$user['id']])) {
                $message = ($lang == 'de') ? "E-Mail erfolgreich bestätigt!" : "Email successfully verified!";
                $class = 'success';
            } else {
                $message = ($lang == 'de') ? "Fehler beim Speichern." : "Error saving.";
                $class = 'error';
            }
        } else {
            $message = ($lang == 'de') ? "Ungültiger oder abgelaufener Link." : "Invalid or expired link.";
            $class = 'error';
        }
    } catch (PDOException $e) {
        $message = "Database Error.";
        $class = 'error';
    }
} else {
    $message = ($lang == 'de') ? "Kein Token übergeben." : "No token provided.";
    $class = 'error';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinuxMC ID - Verification</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Spezifische Overrides für diese Seite, falls nötig */
        .verification-container {
            max-width: 500px;
            margin: 10vh auto;
            text-align: center;
            padding: 40px;
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(139, 233, 253, 0.2);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
        }
        .status-icon { font-size: 4rem; margin-bottom: 20px; display: block; }
        .success { color: #50fa7b; }
        .error { color: #ff5555; }
        .btn-home {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background: rgba(139, 233, 253, 0.1);
            color: #8be9fd;
            border: 1px solid #8be9fd;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-home:hover {
            background: #8be9fd;
            color: #0f172a;
            box-shadow: 0 0 15px rgba(139, 233, 253, 0.4);
        }
    </style>
</head>
<body>

    <nav class="navbar" style="justify-content: center; position: fixed; top: 0; width: 100%; height: 60px; display: flex; align-items: center; background: rgba(15, 23, 42, 0.8);">
        <a href="index.php" style="font-weight: bold; font-size: 1.5rem; color: #8be9fd; text-decoration: none;">LinuxMC ID</a>
    </nav>

    <div class="verification-container">
        <?php if ($class === 'success'): ?>
            <span class="status-icon success">✓</span>
            <h1 style="color: #50fa7b;"><?= ($lang == 'de') ? 'Geschafft!' : 'Success!' ?></h1>
        <?php else: ?>
            <span class="status-icon error">✗</span>
            <h1 style="color: #ff5555;"><?= ($lang == 'de') ? 'Fehler' : 'Error' ?></h1>
        <?php endif; ?>

        <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 20px;">
            <?= htmlspecialchars($message) ?>
        </p>

        <a href="index.php" class="btn-home"><?= ($lang == 'de') ? 'Zum Login' : 'Go to Login' ?></a>
    </div>

</body>
</html>
