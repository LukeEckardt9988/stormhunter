<?php
// config.php

// Fehlermeldungen aktivieren (für die Entwicklung sehr nützlich)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session starten (wichtig für Login-Status)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Datenbank-Zugangsdaten
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'bibelapp_db'); // Name Ihrer Datenbank

// Globale PDO-Verbindungsvariable
$pdo = null;

// Funktion, um eine PDO-Verbindung zu erhalten und zu speichern
function get_db_connection() {
    global $pdo;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            die("FEHLER: Konnte keine Verbindung zur Datenbank herstellen. " . $e->getMessage());
        }
    }
    return $pdo;
}

// Stellt sicher, dass die Verbindung einmalig hergestellt wird, wenn diese Datei geladen wird.
// Das $pdo-Objekt ist danach global verfügbar.
$pdo = get_db_connection();
?>