<?php
require_once 'config.php'; // Lädt Konfiguration und $pdo
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $message = '<div class="alert alert-danger">Bitte alle Felder ausfüllen.</div>';
    } elseif ($password !== $password_confirm) {
        $message = '<div class="alert alert-danger">Die Passwörter stimmen nicht überein.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Ungültige E-Mail-Adresse.</div>';
    } else {
        $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
        $stmtCheck->execute([':username' => $username, ':email' => $email]);
        if ($stmtCheck->fetch()) {
            $message = '<div class="alert alert-danger">Benutzername oder E-Mail bereits vergeben.</div>';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // Annahme: Ihre 'users'-Tabelle hat die Spalten user_id, username, email, password_hash, created_at
            $sql = "INSERT INTO users (username, email, password_hash, created_at) VALUES (:username, :email, :password_hash, NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':username' => $username, ':email' => $email, ':password_hash' => $password_hash])) {
                $message = '<div class="alert alert-success">Registrierung erfolgreich! <a href="login.php">Zum Login</a></div>';
            } else {
                $message = '<div class="alert alert-danger">Fehler bei der Registrierung. Bitte versuchen Sie es später erneut.</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung - Bibel-App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="text-center mb-4">
                    <img src="logo.png" alt="Bibel-App Logo" style="width: 100px;"> <h2>Neues Konto erstellen</h2>
                </div>
                <?php echo $message; ?>
                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Benutzername</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-Mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Passwort bestätigen</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Registrieren</button>
                    </div>
                </form>
                <p class="mt-3 text-center">Bereits registriert? <a href="login.php">Hier einloggen</a></p>
                 <p class="mt-1 text-center"><a href="bibel_lesen.php">Zurück zur Bibelauswahl</a></p>
            </div>
        </div>
    </div>
</body>
</html>