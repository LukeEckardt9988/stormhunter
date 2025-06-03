<?php
require_once 'config.php'; // Lädt Konfiguration, Session und $pdo
$pageTitle = "Neuen News-Beitrag erstellen/bearbeiten";
$message = '';
$post_creation_allowed = false; // Standardmäßig nicht erlaubt
$current_article_id_for_edit = null;
$article_title_for_edit = '';
$article_blocks_for_edit = [];

// Sicherstellen, dass der User eingeloggt ist und die definierte Admin-ID hat
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== NEWS_ADMIN_USER_ID) {
    header('Location: index.php');
    exit();
}

// Prüfen, ob das Passwort bereits in dieser Session verifiziert wurde
if (isset($_SESSION['admin_post_pw_verified']) && $_SESSION['admin_post_pw_verified'] === true) {
    $post_creation_allowed = true;
}

// Verarbeitung von Formular-Submits
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_admin_password'])) { // Speziell für das Passwort-Verifizierungs-Formular
        $admin_password_attempt = $_POST['admin_password'] ?? '';
        if (defined('ADMIN_POST_PASSWORD_HASH') && password_verify($admin_password_attempt, ADMIN_POST_PASSWORD_HASH)) {
            $_SESSION['admin_post_pw_verified'] = true;
            $post_creation_allowed = true; // Wichtig: Für den aktuellen Request setzen
            $message = '<div class="alert alert-success mt-3">Admin-Passwort korrekt. Sie können jetzt Beiträge erstellen.</div>';
            // Kein Redirect hier, damit die Seite neu rendert und das Hauptformular zeigt
        } else {
            $_SESSION['admin_post_pw_verified'] = false; // Bei Fehler explizit zurücksetzen
            $post_creation_allowed = false;
            $message = '<div class="alert alert-danger mt-3">Falsches Admin-Passwort.</div>';
        }
    } elseif (isset($_POST['save_article'])) { // Für das Speichern des Artikels
        if (!$post_creation_allowed) {
             // Sollte nicht passieren, wenn UI korrekt ist, aber als Sicherheitscheck
            $message = '<div class="alert alert-danger mt-3">Admin-Passwort nicht verifiziert. Bitte zuerst verifizieren.</div>';
        } else {
            // ---- BEGINN ARTIKEL SPEICHERN LOGIK ----
            $title = trim($_POST['title'] ?? '');
            $block_types = $_POST['block_type'] ?? [];
            $block_contents = $_POST['block_content'] ?? [];
            // ... (Restliche Logik zum Speichern des Artikels, wie zuvor)

            if (empty($title)) {
                $message = '<div class="alert alert-danger mt-3">Ein Titel für den Beitrag ist erforderlich.</div>';
            } elseif (empty($block_types)) {
                $message = '<div class="alert alert-danger mt-3">Der Beitrag muss mindestens einen Inhaltsblock haben.</div>';
            } else {
                $pdo->beginTransaction();
                try {
                    $sqlArticle = "INSERT INTO news_articles (user_id, title, is_published, created_at) 
                                VALUES (:user_id, :title, TRUE, NOW())";
                    $stmtArticle = $pdo->prepare($sqlArticle);
                    $stmtArticle->execute([
                        ':user_id' => $_SESSION['user_id'],
                        ':title' => $title
                    ]);
                    $articleId = $pdo->lastInsertId();

                    $uploadDir = 'media/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    $sortOrder = 0;
                    foreach ($block_types as $index => $type) {
                        $content_to_save = null;
                        $current_block_content_text_or_url = $block_contents[$index] ?? '';

                        if ($type === 'text' || $type === 'video_embed' || $type === 'external_link') {
                            $content_to_save = trim($current_block_content_text_or_url);
                            if (empty($content_to_save) && $type !== 'text') { // Leere URL-Felder überspringen, aber nicht leere Textfelder unbedingt
                                 if(empty($content_to_save) && $type === 'text' && !empty(trim($current_block_content_text_or_url))) {
                                     // Erlaube Textfelder, die nur aus Leerzeichen bestehen, wenn das gewollt ist, sonst hier auch `continue`
                                 } else if (empty($content_to_save)) {
                                     continue;
                                 }
                            } else if (empty($content_to_save) && $type === 'text' && trim($current_block_content_text_or_url) === ''){
                                // Wenn ein Textblock explizit leer sein soll, und er nicht nur aus Whitespace besteht
                                // In der Regel wollen wir leere Textblöcke wohl auch nicht speichern.
                                // Hier ggf. Logik anpassen, wenn leere Textblöcke gewünscht sind.
                                // Für jetzt: Leere Textblöcke (auch die nur Whitespace enthalten) werden nicht gespeichert.
                                if(trim($current_block_content_text_or_url) === '') continue;
                            }


                        } elseif ($type === 'image' || $type === 'audio_mp3') {
                            if (isset($_FILES['block_file']['name'][$index]) && $_FILES['block_file']['error'][$index] === UPLOAD_ERR_OK) {
                                $tmpName = $_FILES['block_file']['tmp_name'][$index];
                                $originalName = basename($_FILES['block_file']['name'][$index]);
                                $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                                $safeFileName = uniqid('file_', true) . '.' . $fileExtension;
                                $destination = $uploadDir . $safeFileName;

                                if ($type === 'image' && !in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                    $message .= "<div class='alert alert-warning'>Block ".($sortOrder+1).": Ungültiger Bild-Dateityp ($fileExtension).</div>";
                                    continue;
                                }
                                if ($type === 'audio_mp3' && $fileExtension !== 'mp3') {
                                    $message .= "<div class='alert alert-warning'>Block ".($sortOrder+1).": Ungültiger Audio-Dateityp ($fileExtension).</div>";
                                    continue;
                                }

                                if (move_uploaded_file($tmpName, $destination)) {
                                    $content_to_save = $destination;
                                } else {
                                    $message .= "<div class='alert alert-warning'>Block ".($sortOrder+1).": Fehler beim Hochladen '$originalName'.</div>";
                                    continue;
                                }
                            } else {
                                $uploadErrorCode = $_FILES['block_file']['error'][$index] ?? 'Kein Upload';
                                if ($uploadErrorCode !== UPLOAD_ERR_NO_FILE) { // UPLOAD_ERR_NO_FILE ist okay, wenn nichts ausgewählt wurde
                                     $message .= "<div class='alert alert-info'>Block ".($sortOrder+1).": Fehler beim Upload für Bild/Audio (Code: ".$uploadErrorCode.").</div>";
                                }
                                // Wenn kein Upload, dann Block überspringen, außer es ist ein Edit-Modus mit existierendem Content
                                continue;
                            }
                        }

                        if ($content_to_save !== null || ($type === 'text' && trim($current_block_content_text_or_url) !== '')) {
                             if ($type === 'text' && $content_to_save === null) $content_to_save = trim($current_block_content_text_or_url); // Falls nur Text, der nicht leer ist

                             if ($content_to_save !== null) { // Nur speichern, wenn wirklich Inhalt da ist
                                $sqlBlock = "INSERT INTO news_article_blocks (article_id, block_type, content, sort_order)
                                            VALUES (:article_id, :block_type, :content, :sort_order)";
                                $stmtBlock = $pdo->prepare($sqlBlock);
                                $stmtBlock->execute([
                                    ':article_id' => $articleId,
                                    ':block_type' => $type,
                                    ':content' => $content_to_save,
                                    ':sort_order' => $sortOrder
                                ]);
                                $sortOrder++;
                             }
                        }
                    }

                    if ($sortOrder > 0) { // Nur wenn mindestens ein Block erfolgreich gespeichert wurde
                        $pdo->commit();
                        $message .= '<div class="alert alert-success mt-3">Beitrag erfolgreich gespeichert! <a href="dashboard.php">Zur News-Seite</a></div>';
                        $_POST = [];
                        $article_title_for_edit = '';
                        $article_blocks_for_edit = [];
                    } else {
                        $pdo->rollBack(); // Rollback, wenn keine Blöcke gespeichert wurden (z.B. alle leer oder Upload-Fehler)
                        if (empty($message)) { // Falls keine spezifische Fehlermeldung gesetzt wurde
                           $message = '<div class="alert alert-warning mt-3">Keine Inhaltsblöcke zum Speichern vorhanden oder Fehler bei allen Blöcken. Der Beitrag wurde nicht erstellt.</div>';
                        }
                        // Hier könntest du auch den bereits in news_articles erstellten Eintrag löschen, wenn keine Blöcke da sind.
                        $stmtDeleteEmptyArticle = $pdo->prepare("DELETE FROM news_articles WHERE article_id = ?");
                        $stmtDeleteEmptyArticle->execute([$articleId]);

                    }

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = '<div class="alert alert-danger mt-3">Fehler beim Speichern des Beitrags: ' . $e->getMessage() . '</div>';
                }
            }
            // ---- ENDE ARTIKEL SPEICHERN LOGIK ----
        }
    }
}

require_once 'header.php';
?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4"><?php echo htmlspecialchars($pageTitle); ?></h1>
    <?php echo $message; // Nachrichten (Erfolg/Fehler) anzeigen ?>

    <?php if (!$post_creation_allowed): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Admin-Verifizierung</h5>
                <p>Bitte geben Sie Ihr spezielles Admin-Passwort ein, um Beiträge erstellen zu können.</p>
                <form action="create_news_post.php<?php echo $current_article_id_for_edit ? '?edit_id='.$current_article_id_for_edit : ''; ?>" method="POST">
                    <div class="mb-3">
                        <label for="admin_password" class="form-label">Admin-Passwort für Beiträge:</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                    </div>
                    <button type="submit" name="verify_admin_password" class="btn btn-primary">Passwort verifizieren</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <form action="create_news_post.php<?php echo $current_article_id_for_edit ? '?edit_id='.$current_article_id_for_edit : ''; ?>" method="POST" enctype="multipart/form-data">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Allgemeine Informationen</h5>
                    <div class="mb-3">
                        <label for="title" class="form-label">Titel des Beitrags</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($article_title_for_edit ?: ($_POST['title'] ?? '')); ?>" required>
                    </div>
                </div>
            </div>

            <div id="contentBlocksContainer">
                </div>

            <div class="my-3">
                <button type="button" class="btn btn-outline-secondary me-2 mb-2" onclick="addContentBlock('text')"><i class="bi bi-textarea-t"></i> Textblock</button>
                <button type="button" class="btn btn-outline-secondary me-2 mb-2" onclick="addContentBlock('image')"><i class="bi bi-image"></i> Bildblock</button>
                <button type="button" class="btn btn-outline-secondary me-2 mb-2" onclick="addContentBlock('audio_mp3')"><i class="bi bi-file-earmark-music"></i> Audio (MP3)</button>
                <button type="button" class="btn btn-outline-secondary me-2 mb-2" onclick="addContentBlock('video_embed')"><i class="bi bi-youtube"></i> YouTube Video</button>
                <button type="button" class="btn btn-outline-secondary mb-2" onclick="addContentBlock('external_link')"><i class="bi bi-link-45deg"></i> Externer Link</button>
            </div>

            <button type="submit" name="save_article" class="btn btn-success btn-lg">
                <i class="bi bi-check-circle-fill"></i> Beitrag speichern
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
// JavaScript (addContentBlock, getBlockTypeName, removeContentBlock) bleibt exakt wie in der vorherigen Version
let blockCounter = <?php echo !empty($article_blocks_for_edit) ? count($article_blocks_for_edit) : 0; ?>;

function addContentBlock(type, contentValue = '') {
    const container = document.getElementById('contentBlocksContainer');
    const blockId = `block_${blockCounter}`;
    let blockHtml = `
        <div class="card mb-3 content-block" id="${blockId}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">Block ${blockCounter + 1}: ${getBlockTypeName(type)}</span>
                <input type="hidden" name="block_type[${blockCounter}]" value="${type}">
                <button type="button" class="btn-close" aria-label="Block entfernen" onclick="removeContentBlock('${blockId}')"></button>
            </div>
            <div class="card-body">
    `;

    switch (type) {
        case 'text':
            blockHtml += `<label for="${blockId}_content" class="form-label visually-hidden">Text</label>
                          <textarea class="form-control" id="${blockId}_content" name="block_content[${blockCounter}]" rows="5" placeholder="Schreiben Sie hier Ihren Text...">${contentValue}</textarea>`;
            break;
        case 'image':
            blockHtml += `<label for="${blockId}_file" class="form-label">Bilddatei auswählen (JPG, PNG, GIF, WebP - max. 2MB)</label>
                          <input type="file" class="form-control" id="${blockId}_file" name="block_file[${blockCounter}]" accept="image/jpeg,image/png,image/gif,image/webp">`;
            if (contentValue && (contentValue.startsWith('media/') || contentValue.startsWith('http'))) { // Http für potenzielle externe Bilder im Edit-Modus
                blockHtml += `<input type="hidden" name="block_content[${blockCounter}]" value="${contentValue}">
                              <img src="${contentValue}" alt="Vorschau" class="img-thumbnail mt-2" style="max-height: 150px;">`;
            }
            break;
        case 'audio_mp3':
            blockHtml += `<label for="${blockId}_file" class="form-label">MP3-Audiodatei auswählen</label>
                          <input type="file" class="form-control" id="${blockId}_file" name="block_file[${blockCounter}]" accept=".mp3">`;
            if (contentValue && (contentValue.startsWith('media/') || contentValue.startsWith('http'))) {
                 blockHtml += `<input type="hidden" name="block_content[${blockCounter}]" value="${contentValue}">
                               <p class="mt-2">Aktuelle Datei: <a href="${contentValue}" target="_blank">${contentValue.split('/').pop()}</a></p>`;
            }
            break;
        case 'video_embed':
            blockHtml += `<label for="${blockId}_content" class="form-label">YouTube Video ID</label>
                          <input type="text" class="form-control" id="${blockId}_content" name="block_content[${blockCounter}]" value="${contentValue}" placeholder="z.B. dQw4w9WgXcQ (nur die ID)">`;
            break;
        case 'external_link':
            blockHtml += `<label for="${blockId}_content" class="form-label">URL des externen Links</label>
                          <input type="url" class="form-control" id="${blockId}_content" name="block_content[${blockCounter}]" value="${contentValue}" placeholder="https://beispiel.de/artikel">`;
            break;
    }

    blockHtml += `
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', blockHtml);
    blockCounter++;
}

function getBlockTypeName(type) {
    switch (type) {
        case 'text': return 'Text';
        case 'image': return 'Bild';
        case 'audio_mp3': return 'Audio (MP3)';
        case 'video_embed': return 'YouTube Video';
        case 'external_link': return 'Externer Link';
        default: return 'Unbekannt';
    }
}

function removeContentBlock(blockId) {
    const blockElement = document.getElementById(blockId);
    if (blockElement) {
        blockElement.remove();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    <?php
    // Logik für das Vorbelegen von Blöcken im Bearbeitungsmodus (noch auskommentiert)
    /*
    if (!empty($article_blocks_for_edit)) {
        foreach ($article_blocks_for_edit as $block_to_load) {
            // Escape JavaScript-Strings sicher
            $js_block_type = addslashes($block_to_load['block_type']);
            $js_content_value = addslashes($block_to_load['content']);
            echo "addContentBlock('{$js_block_type}', '{$js_content_value}');\n";
        }
    } else {
        // Wenn keine Blöcke zum Bearbeiten da sind UND keine POST-Daten (z.B. nach fehlgeschlagenem Submit)
        // dann einen initialen Textblock hinzufügen.
        if (empty($_POST['block_type']) && $post_creation_allowed) { // Nur wenn das Hauptformular aktiv ist
             echo "if (document.getElementById('contentBlocksContainer').children.length === 0) { addContentBlock('text'); }\n";
        }
    }
    */
    // Standardmäßig einen Textblock hinzufügen, wenn das Hauptformular angezeigt wird und leer ist.
    // Dies stellt sicher, dass immer mindestens ein Block da ist, wenn der User mit der Erstellung beginnt.
    if ($post_creation_allowed && empty($_POST['block_type']) && empty($article_blocks_for_edit)) {
        echo "if (document.getElementById('contentBlocksContainer').children.length === 0) { addContentBlock('text'); }\n";
    }
    ?>
});

</script>

<?php require_once 'footer.php'; ?>