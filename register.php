<?php
require_once 'config.php'; 
$message = '';

// Gemeinden für das Dropdown-Menü laden (nur öffentliche oder alle, je nach Konzept)
// Fürs Erste laden wir alle Gemeinden.
$stmtCommunities = $pdo->prepare("SELECT community_id, community_name FROM communities WHERE access_code IS NULL OR access_code = '' ORDER BY community_name ASC");
$stmtCommunities->execute();
$publicCommunities = $stmtCommunities->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    $join_type = $_POST['join_type'] ?? 'select'; // 'select', 'create', 'code'
    $selected_community_id = (int)($_POST['selected_community_id'] ?? 0);
    $new_community_name = trim($_POST['new_community_name'] ?? '');
    $community_access_code = trim($_POST['community_access_code'] ?? ''); // Für Beitritt per Code

    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $message = '<div class="alert alert-danger">Bitte alle Felder für die Benutzerregistrierung ausfüllen.</div>';
    } elseif ($password !== $password_confirm) {
        $message = '<div class="alert alert-danger">Die Passwörter stimmen nicht überein.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Ungültige E-Mail-Adresse.</div>';
    } else {
        // Benutzerdaten validieren (Existenz)
        $stmtCheckUser = $pdo->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
        $stmtCheckUser->execute([':username' => $username, ':email' => $email]);
        if ($stmtCheckUser->fetch()) {
            $message = '<div class="alert alert-danger">Benutzername oder E-Mail bereits vergeben.</div>';
        } else {
            // Gemeinde-Logik
            $community_id_for_user = null;
            $communityOperationSuccessful = false;

            if ($join_type === 'select' && $selected_community_id > 0) {
                // Ausgewählter Gemeinde beitreten (prüfen, ob sie existiert und öffentlich ist)
                $stmtCheckCommunity = $pdo->prepare("SELECT community_id FROM communities WHERE community_id = :cid AND (access_code IS NULL OR access_code = '')");
                $stmtCheckCommunity->execute([':cid' => $selected_community_id]);
                if ($stmtCheckCommunity->fetch()) {
                    $community_id_for_user = $selected_community_id;
                    $communityOperationSuccessful = true;
                } else {
                    $message = '<div class="alert alert-danger">Ausgewählte Gemeinde nicht gefunden oder nicht öffentlich.</div>';
                }
            } elseif ($join_type === 'code') {
                if (empty($new_community_name) || empty($community_access_code)) { // Hier wird community_name als Suchfeld missbraucht
                     $message = '<div class="alert alert-danger">Bitte Gemeindename und Zugangscode für den Beitritt angeben.</div>';
                } else {
                    $stmtCheckCommunityByCode = $pdo->prepare("SELECT community_id FROM communities WHERE community_name = :name AND access_code = :code");
                    $stmtCheckCommunityByCode->execute([':name' => $new_community_name, ':code' => $community_access_code]);
                    $communityByCode = $stmtCheckCommunityByCode->fetch();
                    if ($communityByCode) {
                        $community_id_for_user = $communityByCode['community_id'];
                        $communityOperationSuccessful = true;
                    } else {
                        $message = '<div class="alert alert-danger">Gemeinde mit diesem Namen und Code nicht gefunden oder Code falsch.</div>';
                    }
                }
            } elseif ($join_type === 'create') {
                if (empty($new_community_name)) {
                    $message = '<div class="alert alert-danger">Bitte geben Sie einen Namen für die neue Gemeinde ein.</div>';
                } else {
                    $stmtCheckExistingCommunity = $pdo->prepare("SELECT community_id FROM communities WHERE community_name = :name");
                    $stmtCheckExistingCommunity->execute([':name' => $new_community_name]);
                    if ($stmtCheckExistingCommunity->fetch()) {
                        $message = '<div class="alert alert-danger">Eine Gemeinde mit diesem Namen existiert bereits. Bitte wählen Sie sie aus oder geben Sie einen anderen Namen ein.</div>';
                    } else {
                        // Hier wird der User erst erstellt, DANN die Gemeinde, und dann der User der Gemeinde zugewiesen.
                        // Das ist etwas umständlich. Besser wäre, der User erstellt die Gemeinde separat NACH der Registrierung,
                        // oder die Gemeindeerstellung hier ist Teil einer Transaktion mit der Usererstellung.
                        // Fürs Erste: Wir merken uns, dass eine Gemeinde erstellt werden soll.
                        // Die eigentliche Erstellung passiert nach erfolgreicher User-Registrierung.
                        $communityOperationSuccessful = true; // Markieren als "bereit für Gemeindeaktion"
                    }
                }
            } else {
                 $message = '<div class="alert alert-danger">Bitte wählen Sie eine Option für die Gemeinde.</div>';
            }

            if ($communityOperationSuccessful) {
                try {
                    $pdo->beginTransaction();
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Benutzer erstellen (noch ohne community_id)
                    $sqlUser = "INSERT INTO users (username, email, password_hash, created_at) VALUES (:username, :email, :password_hash, NOW())";
                    $stmtUser = $pdo->prepare($sqlUser);
                    $stmtUser->execute([
                        ':username' => $username, 
                        ':email' => $email, 
                        ':password_hash' => $password_hash
                    ]);
                    $newUserId = $pdo->lastInsertId();

                    if ($join_type === 'create' && !empty($new_community_name)) {
                        // Neue Gemeinde erstellen und User als Gründer eintragen
                        $sqlNewCommunity = "INSERT INTO communities (community_name, created_by_user_id, created_at) VALUES (:name, :creator_id, NOW())";
                        $stmtNewCommunity = $pdo->prepare($sqlNewCommunity);
                        $stmtNewCommunity->execute([':name' => $new_community_name, ':creator_id' => $newUserId]);
                        $community_id_for_user = $pdo->lastInsertId();
                    }
                    // (Der Fall 'select' und 'code' hat $community_id_for_user schon gesetzt)

                    // Dem Benutzer die community_id zuweisen
                    if ($community_id_for_user) {
                        $stmtUpdateUserCommunity = $pdo->prepare("UPDATE users SET community_id = :cid WHERE user_id = :uid");
                        $stmtUpdateUserCommunity->execute([':cid' => $community_id_for_user, ':uid' => $newUserId]);
                    } else if ($join_type !== 'create') { 
                        // Sollte nicht passieren, wenn Logik oben korrekt ist, aber als Sicherheit
                        throw new Exception("Community ID konnte nicht ermittelt werden.");
                    }
                    
                    $pdo->commit();
                    $_SESSION['user_id_pending_community'] = $newUserId; // Für den Fall, dass man nach dem Login noch was tun muss
                    header('Location: welcome.php?registered=1');
                    exit();

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = '<div class="alert alert-danger">Fehler bei der Registrierung oder Gemeindeerstellung: ' . $e->getMessage() . '</div>';
                }
            }
            // Wenn $communityOperationSuccessful false ist, wurde $message bereits gesetzt.
        }
    }
}

$pageTitle = "Registrieren";
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
        .form-container { max-width: 600px; width: 100%; padding: 2rem; background-color: #fff; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); }
        .community-options { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="text-center mb-4">Neues Konto erstellen</h2>
        <?php echo $message; ?>
        <form action="register.php" method="POST" id="registerForm">
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

            <hr>
            <h5 class="mb-3">Gemeinschaft</h5>
            <div class="mb-3">
                <label class="form-label">Wie möchten Sie einer Gemeinde beitreten/eine erstellen?</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="join_type" id="join_type_select" value="select" checked onchange="toggleCommunityFields()">
                    <label class="form-check-label" for="join_type_select">Einer bestehenden öffentlichen Gemeinde beitreten</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="join_type" id="join_type_code" value="code" onchange="toggleCommunityFields()">
                    <label class="form-check-label" for="join_type_code">Einer Gemeinde mit Name und Zugangscode beitreten</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="join_type" id="join_type_create" value="create" onchange="toggleCommunityFields()">
                    <label class="form-check-label" for="join_type_create">Eine neue Gemeinde gründen</label>
                </div>
            </div>

            <div id="select_community_fields" class="community-options">
                <label for="selected_community_id" class="form-label">Öffentliche Gemeinde auswählen:</label>
                <select class="form-select" id="selected_community_id" name="selected_community_id">
                    <option value="">Bitte wählen...</option>
                    <?php foreach ($publicCommunities as $community): ?>
                        <option value="<?php echo $community['community_id']; ?>" <?php echo (isset($_POST['selected_community_id']) && $_POST['selected_community_id'] == $community['community_id'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($community['community_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="code_community_fields" class="community-options" style="display: none;">
                <div class="mb-3">
                    <label for="find_community_name" class="form-label">Name der Gemeinde:</label>
                    <input type="text" class="form-control" id="find_community_name" name="new_community_name"> </div>
                <div class="mb-3">
                    <label for="community_access_code" class="form-label">Zugangscode der Gemeinde:</label>
                    <input type="text" class="form-control" id="community_access_code" name="community_access_code">
                </div>
            </div>
            
            <div id="create_community_fields" class="community-options" style="display: none;">
                <label for="create_new_community_name" class="form-label">Name der neuen Gemeinde:</label>
                <input type="text" class="form-control" id="create_new_community_name" name="new_community_name"> <div class="form-text">Der Zugangscode für die neue Gemeinde kann später vom Gründer festgelegt werden (optional).</div>
            </div>


            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary">Registrieren</button>
            </div>
        </form>
        <p class="mt-3 text-center">Bereits registriert? <a href="login.php">Hier einloggen</a></p>
        <p class="mt-1 text-center"><a href="welcome.php">Zurück zur Startseite</a></p>
    </div>

<script>
    function toggleCommunityFields() {
        const joinType = document.querySelector('input[name="join_type"]:checked').value;
        document.getElementById('select_community_fields').style.display = (joinType === 'select') ? 'block' : 'none';
        document.getElementById('code_community_fields').style.display = (joinType === 'code') ? 'block' : 'none';
        document.getElementById('create_community_fields').style.display = (joinType === 'create') ? 'block' : 'none';

        // Namen der Input-Felder anpassen, damit PHP das richtige Feld für new_community_name erhält
        if (joinType === 'code') {
            document.getElementById('find_community_name').name = 'new_community_name';
            document.getElementById('create_new_community_name').name = '_new_community_name_disabled'; // Deaktivieren
        } else if (joinType === 'create') {
            document.getElementById('create_new_community_name').name = 'new_community_name';
            document.getElementById('find_community_name').name = '_find_community_name_disabled'; // Deaktivieren
        } else {
            document.getElementById('find_community_name').name = '_find_community_name_disabled';
            document.getElementById('create_new_community_name').name = '_new_community_name_disabled';
        }
    }
    // Initial aufrufen, um den korrekten Zustand beim Laden der Seite herzustellen
    document.addEventListener('DOMContentLoaded', toggleCommunityFields);
</script>
</body>
</html>
