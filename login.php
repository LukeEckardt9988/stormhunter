<?php
require_once 'config.php'; // Lädt Konfiguration und $pdo
$message = '';

// Wenn Benutzer bereits eingeloggt ist, zur Hauptseite weiterleiten
if (isset($_SESSION['user_id'])) {
    header('Location: bibel_lesen.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        $message = '<div class="alert alert-danger">Bitte alle Felder ausfüllen.</div>';
    } else {
        try {
            // $pdo ist jetzt global verfügbar dank config.php
            
            // KORRIGIERTE SQL-Abfrage und execute()-Aufruf
            $sql = "SELECT user_id, username, password_hash FROM users WHERE username = :username_identifier OR email = :email_identifier";
            $stmt = $pdo->prepare($sql);
            
            // Übergebe den Wert für beide Platzhalter
            $stmt->execute([
                ':username_identifier' => $username_or_email,
                ':email_identifier' => $username_or_email 
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                header('Location: bibel_lesen.php');
                exit();
            } else {
                $message = '<div class="alert alert-danger">Benutzername/E-Mail oder Passwort ungültig.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Datenbankfehler beim Login: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bibel-App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                 <div class="text-center mb-4">
                    <h2>Anmelden</h2>
                </div>
                <?php echo $message; ?>
                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label for="username_or_email" class="form-label">Benutzername oder E-Mail</label>
                        <input type="text" class="form-control" id="username_or_email" name="username_or_email" value="<?php echo htmlspecialchars($_POST['username_or_email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Einloggen</button>
                    </div>
                </form>
                <p class="mt-3 text-center">Noch kein Konto? <a href="register.php">Hier registrieren</a></p>
                <p class="mt-1 text-center"><a href="bibel_lesen.php">Zurück zur Bibelauswahl</a></p>
            </div>
        </div>
    </div>
</body>
</html>