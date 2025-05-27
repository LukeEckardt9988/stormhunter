<?php
require_once 'config.php'; // Stellt Session und $pdo bereit

$message = '';
$loggedInUserId = $_SESSION['user_id'] ?? 0;

// Wenn ein nicht eingeloggter User versucht, eine Gemeinde zu erstellen,
// könnte man ihn zuerst zur Registrierung/Login leiten.
// Für den Moment erlauben wir es, aber ein User muss existieren, um 'created_by_user_id' zu setzen.
// Besser: User muss eingeloggt sein, um eine Gemeinde zu gründen.
if ($loggedInUserId === 0 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fall: User ist nicht eingeloggt, hat aber das Formular abgeschickt.
    // Dies sollte idealerweise nicht passieren, wenn das Formular nur für eingeloggte User sichtbar ist.
    // Oder man leitet erst zu Login/Register und kommt dann hierher zurück.
    // Hier vereinfacht: Fehlermeldung.
     $message = '<div class="alert alert-warning">Bitte loggen Sie sich zuerst ein oder registrieren Sie sich, um eine Gemeinde zu gründen. <a href="login.php">Login</a> | <a href="register.php">Registrieren</a></div>';
}


if ($loggedInUserId > 0 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $community_name = trim($_POST['community_name'] ?? '');
    $access_code = trim($_POST['access_code'] ?? ''); // Optional

    if (empty($community_name)) {
        $message = '<div class="alert alert-danger">Bitte geben Sie einen Gemeindenamen ein.</div>';
    } else {
        // Prüfen, ob Gemeinde schon existiert
        $stmtCheck = $pdo->prepare("SELECT community_id FROM communities WHERE community_name = :name");
        $stmtCheck->execute([':name' => $community_name]);
        if ($stmtCheck->fetch()) {
            $message = '<div class="alert alert-danger">Eine Gemeinde mit diesem Namen existiert bereits.</div>';
        } else {
            try {
                $pdo->beginTransaction();

                // Gemeinde erstellen
                $sqlCommunity = "INSERT INTO communities (community_name, created_by_user_id, access_code, created_at) 
                                 VALUES (:name, :creator_id, :code, NOW())";
                $stmtCommunity = $pdo->prepare($sqlCommunity);
                $stmtCommunity->execute([
                    ':name' => $community_name,
                    ':creator_id' => $loggedInUserId,
                    ':code' => !empty($access_code) ? $access_code : null
                ]);
                $newCommunityId = $pdo->lastInsertId();

                // Benutzer der neuen Gemeinde zuweisen
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET community_id = :cid WHERE user_id = :uid");
                $stmtUpdateUser->execute([':cid' => $newCommunityId, ':uid' => $loggedInUserId]);
                
                $_SESSION['community_id'] = $newCommunityId; // Community ID in Session speichern

                $pdo->commit();
                // Weiterleitung zur Hauptseite oder einer Bestätigungsseite
                // header('Location: bibel_lesen.php?community_created=1'); // Wenn direkt zur App
                header('Location: welcome.php?community_created=1'); // Zurück zur Welcome-Seite mit Nachricht
                exit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Fehler beim Erstellen der Gemeinde: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

$pageTitle = "Neue Gemeinde gründen";
// Header hier einbinden, wenn er benötigt wird (z.B. für Navbar, falls User schon eingeloggt ist)
// Wenn dies eine "Standalone"-Seite vor dem vollen App-Zugriff ist, könnte der Header anders sein.
// Für Konsistenz, nehmen wir den Standard-Header.
if ($loggedInUserId === 0 && !isset($_GET['notice'])) { // Wenn nicht eingeloggt und keine spezielle Nachricht, zur Welcome-Seite
    // Dies ist ein besserer Flow: User muss sich erst registrieren/einloggen.
    // Das Formular unten wird nur angezeigt, wenn der User eingeloggt ist.
}
if (isset($_GET['notice']) && $_GET['notice'] === 'no_community' && $loggedInUserId > 0) {
    $message = '<div class="alert alert-info">Sie sind noch keiner Gemeinde beigetreten. Bitte gründen Sie eine oder treten Sie einer bestehenden bei (Option im Registrierungsformular).</div>';
}


?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
     <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; }
        .form-container { max-width: 500px; width: 100%; padding: 2rem; background-color: #fff; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="text-center mb-4"><?php echo htmlspecialchars($pageTitle); ?></h2>
        <?php echo $message; ?>

        <?php if ($loggedInUserId > 0): // Formular nur für eingeloggte User anzeigen ?>
            <form action="create_community.php" method="POST">
                <div class="mb-3">
                    <label for="community_name" class="form-label">Name der Gemeinde</label>
                    <input type="text" class="form-control" id="community_name" name="community_name" required value="<?php echo htmlspecialchars($_POST['community_name'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="access_code" class="form-label">Zugangscode (optional, für private Gemeinden)</label>
                    <input type="text" class="form-control" id="access_code" name="access_code" placeholder="Leer lassen für öffentliche Gemeinde">
                    <div class="form-text">Wenn Sie einen Code festlegen, müssen neue Mitglieder diesen Code eingeben, um beizutreten.</div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Gemeinde gründen</button>
                </div>
            </form>
            <p class="mt-3 text-center"><a href="welcome.php">Zurück zur Startseite</a></p>
        <?php else: ?>
            <p class="text-center">Um eine Gemeinde zu gründen, müssen Sie sich zuerst <a href="login.php">einloggen</a> oder <a href="register.php">registrieren</a>.</p>
            <p class="text-center mt-3"><a href="welcome.php" class="btn btn-secondary">Zurück zur Startseite</a></p>
        <?php endif; ?>
    </div>
</body>
</html>

