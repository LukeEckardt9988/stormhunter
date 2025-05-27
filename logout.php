<?php
require_once 'config.php'; // Stellt sicher, dass die Session gestartet ist
session_unset();     // Löscht alle Session-Variablen
session_destroy();   // Zerstört die Session
header('Location: login.php'); // Leitet zum Login weiter
exit();
?>