<?php
require_once 'config.php'; // Lädt Konfiguration, Session und $pdo
$pageTitle = "Neuen News-Beitrag erstellen";
$message = '';
$post_creation_allowed = false;

// Sicherstellen, dass der User eingeloggt ist und die definierte Admin-ID hat
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== NEWS_ADMIN_USER_ID) {
    // Nicht der Admin oder nicht eingeloggt: Umleiten oder Fehlermeldung
    header('Location: index.php'); // Oder dashboard.php, wenn das Ihre Startseite wird
    exit();
}

// Prüfen, ob das zweite Admin-Passwort bereits in dieser Session verifiziert wurde
if (isset($_SESSION['admin_post_pw_verified']) && $_SESSION['admin_post_pw_verified'] === true) {
    $post_creation_allowed = true;
}

// Verarbeitung des Formulars
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_admin_password'])) {
        // Zweites Admin-Passwort verifizieren
        $admin_password_attempt = $_POST['admin_password'] ?? '';
        if (defined('ADMIN_POST_PASSWORD_HASH') && password_verify($admin_password_attempt, ADMIN_POST_PASSWORD_HASH)) {
            $_SESSION['admin_post_pw_verified'] = true;
            $post_creation_allowed = true;
            $message = '<div class="alert alert-success mt-3">Admin-Passwort korrekt. Sie können jetzt Beiträge erstellen.</div>';
        } else {
            $_SESSION['admin_post_pw_verified'] = false; // Explizit zurücksetzen
            $message = '<div class="alert alert-danger mt-3">Falsches Admin-Passwort.</div>';
        }
    } elseif ($post_creation_allowed && isset($_POST['create_post'])) {
        // Neuen Beitrag erstellen und in der Datenbank speichern
        $title = trim($_POST['title'] ?? '');
        $content_type = $_POST['content_type'] ?? '';
        $media_url = trim($_POST['media_url'] ?? '');
        $article_text = trim($_POST['article_text'] ?? '');

        if (empty($title) || empty($content_type) || empty($article_text)) {
            $message = '<div class="alert alert-danger mt-3">Titel, Inhaltstyp und Text sind Pflichtfelder.</div>';
        } elseif ($content_type !== 'text' && empty($media_url) && $content_type !== 'image_upload') { // image_upload braucht anfangs keine URL
            $message = '<div class="alert alert-danger mt-3">Für den gewählten Inhaltstyp wird eine Medien-URL benötigt (außer bei "Nur Text" oder Bild-Upload).</div>';
        } else {
            // Hier würde Ihre Logik zum Speichern des Beitrags in der Tabelle 'news_articles' stehen.
            // Beispiel:
            try {
                $sql = "INSERT INTO news_articles (user_id, title, content_type, media_url, article_text, is_published)
                        VALUES (:user_id, :title, :content_type, :media_url, :article_text, TRUE)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'], // Admin User ID
                    ':title' => $title,
                    ':content_type' => $content_type,
                    ':media_url' => ($content_type === 'text') ? null : $media_url, // Für 'text' keine URL
                    ':article_text' => $article_text
                ]);
                $message = '<div class="alert alert-success mt-3">Beitrag erfolgreich erstellt! <a href="dashboard.php">Zur News-Seite</a></div>';
                // Formularfelder leeren (optional)
                $_POST = []; 
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger mt-3">Fehler beim Speichern des Beitrags: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

require_once 'header.php'; // Lädt den Standard-Header
?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4"><?php echo htmlspecialchars($pageTitle); ?></h1>
    <?php echo $message; ?>

    <?php if (!$post_creation_allowed): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Admin-Verifizierung</h5>
                <p>Bitte geben Sie Ihr spezielles Admin-Passwort ein, um Beiträge erstellen zu können.</p>
                <form action="create_news_post.php" method="POST">
                    <div class="mb-3">
                        <label for="admin_password" class="form-label">Admin-Passwort für Beiträge:</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                    </div>
                    <button type="submit" name="verify_admin_password" class="btn btn-primary">Passwort verifizieren</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Neuen Beitrag verfassen</h5>
                <form action="create_news_post.php" method="POST">
                    <div class="mb-3">
                        <label for="title" class="form-label">Titel des Beitrags</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="content_type" class="form-label">Inhaltstyp</label>
                        <select class="form-select" id="content_type" name="content_type" required>
                            <option value="">Bitte wählen...</option>
                            <option value="text" <?php echo (($_POST['content_type'] ?? '') === 'text' ? 'selected' : ''); ?>>Nur Text</option>
                            <option value="image" <?php echo (($_POST['content_type'] ?? '') === 'image' ? 'selected' : ''); ?>>Bild (URL angeben)</option>
                            <option value="video_embed" <?php echo (($_POST['content_type'] ?? '') === 'video_embed' ? 'selected' : ''); ?>>YouTube Video (nur ID)</option>
                            <option value="video_link" <?php echo (($_POST['content_type'] ?? '') === 'video_link' ? 'selected' : ''); ?>>Video Link (andere Plattform)</option>
                            <option value="external_link" <?php echo (($_POST['content_type'] ?? '') === 'external_link' ? 'selected' : ''); ?>>Externer Link (Artikel, etc.)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="media_url" class="form-label">Medien-URL / YouTube-ID / Link-URL</label>
                        <input type="text" class="form-control" id="media_url" name="media_url" value="<?php echo htmlspecialchars($_POST['media_url'] ?? ''); ?>" placeholder="https://... oder nur die YouTube Video ID">
                        <div class="form-text">Bei "Nur Text" leer lassen. Für YouTube Videos bitte nur die ID nach "v=" (z.B. dQw4w9WgXcQ) eingeben.</div>
                    </div>
                    <div class="mb-3">
                        <label for="article_text" class="form-label">Text / Beschreibung zum Beitrag</label>
                        <textarea class="form-control" id="article_text" name="article_text" rows="10" required><?php echo htmlspecialchars($_POST['article_text'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="create_post" class="btn btn-success">Beitrag veröffentlichen</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>