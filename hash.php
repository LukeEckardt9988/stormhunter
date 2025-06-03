<?php
// hash.php
// Dieses Skript dient nur zum Generieren eines Passwort-Hashes.
// LÖSCHEN SIE DIESE DATEI ODER SCHÜTZEN SIE SIE NACH GEBRAUCH!

$passwordToHash = ''; // Tragen Sie hier das gewünschte Passwort ein
$hashedPassword = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    $passwordToHash = $_POST['password'];
    if (!empty($passwordToHash)) {
        // Generiert einen sicheren Hash mit dem Standard-Algorithmus (aktuell bcrypt)
        $hashedPassword = password_hash($passwordToHash, PASSWORD_DEFAULT);
        $message = "Das gehashte Passwort für \"<strong>" . htmlspecialchars($passwordToHash) . "</strong>\" lautet:";
    } else {
        $message = "Bitte geben Sie ein Passwort ein.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort Hash Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; }
        .container { max-width: 600px; }
        .hash-output { 
            word-break: break-all; 
            background-color: #e9ecef; 
            padding: 10px; 
            border-radius: 5px; 
            font-family: monospace;
            cursor: text; /* Macht es einfacher, den Hash zu markieren und zu kopieren */
        }
    </style>
</head>
<body>
    <div class="container card p-4">
        <h1 class="mb-4 text-center">Passwort Hash Generator</h1>
        <p class="text-center text-danger"><strong>Sicherheitshinweis:</strong> Diese Datei nach Gebrauch unbedingt löschen oder umbenennen!</p>
        
        <form method="POST" action="hash.php">
            <div class="mb-3">
                <label for="password" class="form-label">Zu hashendes Passwort:</label>
                <input type="text" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($passwordToHash); ?>" required autofocus>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Hash generieren</button>
            </div>
        </form>

        <?php if ($message): ?>
            <div class="mt-4">
                <p><?php echo $message; ?></p>
                <?php if ($hashedPassword): ?>
                    <div class="hash-output user-select-all"><?php echo htmlspecialchars($hashedPassword); ?></div>
                    <small class="form-text text-muted">Kopieren Sie diesen Hash und fügen Sie ihn in Ihre `config.php`-Datei für `ADMIN_POST_PASSWORD_HASH` ein.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>