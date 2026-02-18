<?php
session_start();
session_destroy();
header('Location: /'); // Zurück zur Startseite
?>
