<?php
require_once 'config.php'; // Stellt $pdo und die Session bereit (wichtig für $loggedInUserId)
$loggedInUserId = $_SESSION['user_id'] ?? 0;

// Prüfen, ob keine spezifischen Navigationsparameter (Buch, Kapitel, Version) in der URL übergeben wurden.
// Wir leiten nur weiter, wenn ALLE drei fehlen, um Konflikte mit bestehenden Links zu vermeiden,
// die vielleicht nur Buch und Kapitel, aber keine Version angeben (und dann auf Version 1 fallen).
// Wenn Sie möchten, dass schon bei Fehlen von Buch & Kapitel weitergeleitet wird, passen Sie die Bedingung an.
if (!isset($_GET['book_id']) && !isset($_GET['chapter']) && !isset($_GET['version_id'])) {
    if ($loggedInUserId > 0) {
        // Für eingeloggte Benutzer: Versuche, die letzte Position zu laden und weiterzuleiten.
        // Dieser HTML-Block mit JavaScript wird nur ausgegeben, wenn keine Parameter da sind.
        echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Letzte Leseposition wird geladen...</title>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const savedPositionRaw = localStorage.getItem('lastReadPosition_{$loggedInUserId}');
                if (savedPositionRaw) {
                    const savedPosition = JSON.parse(savedPositionRaw);
                    if (savedPosition && savedPosition.book && savedPosition.chapter && savedPosition.version) {
                        // Baue die neue URL zusammen
                        const targetUrl = `bibel_lesen.php?version_id=\${savedPosition.version}&book_id=\${savedPosition.book}&chapter=\${savedPosition.chapter}`;
                        window.location.replace(targetUrl); // .replace() verhindert Back-Button zum Ladebildschirm
                        return; // Verhindere Fallback, falls Weiterleitung initiiert wurde
                    }
                }
                // Fallback, wenn keine gültige Position gespeichert ist
                window.location.replace('bibel_lesen.php?version_id=1&book_id=1&chapter=1');
            } catch (e) {
                console.error('Fehler beim Laden/Weiterleiten von localStorage:', e);
                // Fallback bei JavaScript-Fehler
                window.location.replace('bibel_lesen.php?version_id=1&book_id=1&chapter=1');
            }
        });
    </script>
    <noscript>
        <meta http-equiv="refresh" content="0;url=bibel_lesen.php?version_id=1&book_id=1&chapter=1" />
        <p>JavaScript ist deaktiviert. Sie werden zu 1. Mose 1 weitergeleitet.</p>
    </noscript>
</head>
<body>
    <p style="text-align:center; padding-top:50px;">Letzte Leseposition wird geladen...</p>
</body>
</html>
HTML;
        exit; // Wichtig: Stoppt die weitere Ausführung von bibel_lesen.php für diesen Request.
    } else {
        // Für Gäste oder wenn $loggedInUserId nicht verfügbar ist:
        // Setze Standardwerte, damit die Seite nicht mit Fehlern lädt.
        // Die Seite wird dann mit diesen Standardwerten normal aufgebaut.
        $_GET['version_id'] = 1;
        $_GET['book_id'] = 1;
        $_GET['chapter'] = 1;
    }
}

// --- Ab hier beginnt Ihr normaler Code von bibel_lesen.php ---
// $loggedInUserId = $_SESSION['user_id'] ?? 0; // Ist schon oben definiert
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

$currentVersionId = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 1;
$currentBookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 1; 
$currentChapter = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 1;

// --- Daten für Auswahlmenüs ---
$stmtVersions = $pdo->prepare("SELECT version_id, version_name FROM versions ORDER BY version_name ASC");
$stmtVersions->execute();
$allVersions = $stmtVersions->fetchAll(PDO::FETCH_ASSOC);

$stmtBooks = $pdo->prepare("SELECT book_id, book_name_de, book_order FROM books ORDER BY book_order ASC");
$stmtBooks->execute();
$allBooks = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

// --- Aktuelles Buch und höchste Kapitelnummer ---
$currentBookName = 'Unbekanntes Buch'; 
$maxChapter = 1; 

if ($currentBookId > 0) {
    $stmtCurrentBookInfo = $pdo->prepare("SELECT book_name_de, book_order FROM books WHERE book_id = :book_id");
    $stmtCurrentBookInfo->execute([':book_id' => $currentBookId]);
    $bookInfo = $stmtCurrentBookInfo->fetch(PDO::FETCH_ASSOC);
    if ($bookInfo) {
        $currentBookName = $bookInfo['book_name_de'];
        $currentBookOrder = $bookInfo['book_order']; // Wichtig für Buchnavigation
    } else { 
        $currentBookId = 1; 
        $stmtFallbackBookInfo = $pdo->prepare("SELECT book_name_de, book_order FROM books WHERE book_id = :book_id");
        $stmtFallbackBookInfo->execute([':book_id' => $currentBookId]);
        $bookInfoFallback = $stmtFallbackBookInfo->fetch(PDO::FETCH_ASSOC);
        $currentBookName = $bookInfoFallback ? $bookInfoFallback['book_name_de'] : '1. Mose';
        $currentBookOrder = $bookInfoFallback ? $bookInfoFallback['book_order'] : 1;
    }

    if ($currentVersionId > 0) {
        $stmtMaxChapter = $pdo->prepare("SELECT MAX(chapter_number) as max_chapter FROM verses WHERE book_id = :book_id AND version_id = :version_id");
        $stmtMaxChapter->execute([':book_id' => $currentBookId, ':version_id' => $currentVersionId]);
        $maxChapterResult = $stmtMaxChapter->fetchColumn();
        if ($maxChapterResult) $maxChapter = (int)$maxChapterResult;
    }
}
$currentChapter = max(1, min($currentChapter, $maxChapter));

// --- Logik für vorheriges/nächstes Kapitel/Buch ---
$prevChapterLink = null;
$nextChapterLink = null;
$baseUrl = "bibel_lesen.php?version_id=$currentVersionId";

// Vorheriges Kapitel/Buch
if ($currentChapter > 1) {
    $prevChapter = $currentChapter - 1;
    $prevChapterLink = "$baseUrl&book_id=$currentBookId&chapter=$prevChapter";
} else { // Erstes Kapitel, versuche vorheriges Buch
    $stmtPrevBook = $pdo->prepare("SELECT book_id FROM books WHERE book_order < :current_book_order ORDER BY book_order DESC LIMIT 1");
    $stmtPrevBook->execute([':current_book_order' => $currentBookOrder]);
    $prevBook = $stmtPrevBook->fetch(PDO::FETCH_ASSOC);
    if ($prevBook) {
        // Finde das letzte Kapitel des vorherigen Buches
        $stmtLastChapterPrevBook = $pdo->prepare("SELECT MAX(chapter_number) as max_chap FROM verses WHERE book_id = :book_id AND version_id = :version_id");
        $stmtLastChapterPrevBook->execute([':book_id' => $prevBook['book_id'], ':version_id' => $currentVersionId]);
        $lastChapter = $stmtLastChapterPrevBook->fetchColumn();
        if ($lastChapter) {
            $prevChapterLink = "$baseUrl&book_id={$prevBook['book_id']}&chapter=$lastChapter";
        }
    }
}

// Nächstes Kapitel/Buch
if ($currentChapter < $maxChapter) {
    $nextChapter = $currentChapter + 1;
    $nextChapterLink = "$baseUrl&book_id=$currentBookId&chapter=$nextChapter";
} else { // Letztes Kapitel, versuche nächstes Buch
    $stmtNextBook = $pdo->prepare("SELECT book_id FROM books WHERE book_order > :current_book_order ORDER BY book_order ASC LIMIT 1");
    $stmtNextBook->execute([':current_book_order' => $currentBookOrder]);
    $nextBook = $stmtNextBook->fetch(PDO::FETCH_ASSOC);
    if ($nextBook) {
        $nextChapterLink = "$baseUrl&book_id={$nextBook['book_id']}&chapter=1"; // Starte mit Kapitel 1 des nächsten Buches
    }
}


// --- Verse laden & Informationen zu Markierungen (wie zuvor) ---
$versesWithDetails = []; 
if ($currentBookId > 0 && $currentVersionId > 0) {
    // ... (Ihre bestehende Logik zum Laden von $versesWithDetails bleibt hier) ...
    // Stellen Sie sicher, dass die SQL-Abfrage für $versesWithDetails hier ist.
    // Zum Beispiel die aus bibel_lesen_php_highlight_mode_fix:
     $sqlFetchVerses = "
        SELECT 
            v.verse_number, 
            v.verse_text, 
            v.global_verse_id,
            uiv.user_id IS NOT NULL AS is_important_for_user 
        FROM verses v
        LEFT JOIN user_important_verses uiv ON v.global_verse_id = uiv.global_verse_id AND uiv.user_id = :current_user_id_for_important
        WHERE v.version_id = :version_id AND v.book_id = :book_id AND v.chapter_number = :chapter_num 
        ORDER BY v.verse_number ASC";
    
    $stmtVerses = $pdo->prepare($sqlFetchVerses);
    $stmtVerses->execute([
        ':version_id' => $currentVersionId,
        ':book_id' => $currentBookId,
        ':chapter_num' => $currentChapter,
        ':current_user_id_for_important' => $loggedInUserId 
    ]);
    $versesWithDetails = $stmtVerses->fetchAll(PDO::FETCH_ASSOC);
}


// --- Globale farbliche Markierungen laden (wie zuvor) ---
$globalHighlights = []; 
if (!empty($versesWithDetails)) {
    // ... (Ihre bestehende Logik zum Laden von $globalHighlights bleibt hier) ...
    // Zum Beispiel die aus bibel_lesen_php_highlight_mode_fix:
    $globalVerseIdsForCurrentChapter = array_column($versesWithDetails, 'global_verse_id');
    if (!empty($globalVerseIdsForCurrentChapter)) {
        $placeholders = implode(',', array_fill(0, count($globalVerseIdsForCurrentChapter), '?'));
        $sqlHighlightsQuery = "
            SELECT t1.global_verse_id, t1.highlight_color
            FROM user_highlights t1
            INNER JOIN (
                SELECT global_verse_id, MAX(highlight_date) AS max_date
                FROM user_highlights
                WHERE global_verse_id IN ($placeholders)
                GROUP BY global_verse_id
            ) t2 ON t1.global_verse_id = t2.global_verse_id AND t1.highlight_date = t2.max_date";
        
        $stmtHighlights = $pdo->prepare($sqlHighlightsQuery);
        $stmtHighlights->execute($globalVerseIdsForCurrentChapter);
        while ($row = $stmtHighlights->fetch(PDO::FETCH_ASSOC)) {
            $globalHighlights[$row['global_verse_id']] = $row['highlight_color']; 
        }
    }
}


$pageTitle = "Bibel lesen: " . htmlspecialchars((string)$currentBookName . ' ' . (string)$currentChapter);
require_once 'header.php'; 
?>

<div class="card card-body bg-light mb-4">
    <form method="GET" action="bibel_lesen.php" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="get_version_id" class="form-label">Übersetzung</label>
            <select name="version_id" id="get_version_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($allVersions as $version): ?>
                    <option value="<?php echo $version['version_id']; ?>" <?php echo ($version['version_id'] == $currentVersionId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($version['version_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="get_book_id" class="form-label">Buch</label>
            <select name="book_id" id="get_book_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($allBooks as $book): ?>
                    <option value="<?php echo $book['book_id']; ?>" <?php echo ($book['book_id'] == $currentBookId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($book['book_name_de']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="get_chapter" class="form-label">Kapitel</label>
            <select name="chapter" id="get_chapter" class="form-select" onchange="this.form.submit()">
                <?php if ($maxChapter > 0): ?>
                    <?php for ($i = 1; $i <= $maxChapter; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $currentChapter) ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                <?php else: ?>
                    <option value="1">1</option> 
                <?php endif; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary d-none">Anzeigen</button> </div>
    </form>
</div>

<button id="brush-mode-toggle" class="btn btn-outline-secondary" title="Pinsel-Modus umschalten">
    <i class="bi bi-brush-fill"></i>
</button>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="brushModeToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto"><i class="bi bi-brush-fill"></i> Pinsel-Modus</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      Aktiv! Jeder Klick auf einen Vers färbt ihn ein.
    </div>
  </div>
</div>



<h1 class="text-center mb-4"><?php echo htmlspecialchars((string)$currentBookName . ' ' . (string)$currentChapter); ?></h1>

<div class="verse-container fs-5"> 
    <?php if (!empty($versesWithDetails)): ?>
        <?php foreach ($versesWithDetails as $verse): ?>
            <?php 
               
                $highlightColorValue = $globalHighlights[$verse['global_verse_id']] ?? null; 
                $highlightClass = '';
                if ($highlightColorValue) {
                    // NEU: Angepasste switch-Anweisung
                    switch ($highlightColorValue) {
                        case 'god':               $highlightClass = 'bg-gods-work-custom'; break;
                        case 'christ_prophecy':   $highlightClass = 'bg-warning'; break;
                        case 'christ_teaching':   $highlightClass = 'bg-miracle-custom'; break;
                        case 'sin_repentance':    $highlightClass = 'bg-red-custom'; break;
                        case 'law_covenant':      $highlightClass = 'bg-law-custom'; break;
                        case 'faith_salvation':   $highlightClass = 'bg-sanctification-custom'; break;
                        case 'holy_spirit':       $highlightClass = 'bg-parable-custom'; break;
                        case 'commandments':      $highlightClass = 'bg-blue-custom'; break;
                        case 'hope':              $highlightClass = 'bg-green-custom'; break;
                        case 'growth':            $highlightClass = 'bg-rebuke-custom'; break;
                        case 'worship':           $highlightClass = 'bg-worship-custom'; break;
                        case 'people_church':     $highlightClass = 'bg-name-custom'; break;
                        case 'history':           $highlightClass = 'bg-brown-custom'; break;
                        // Alte Klassen sind nicht mehr nötig nach der Migration
                    }
                }
                $importantClass = $verse['is_important_for_user'] ? 'user-important-verse-text' : '';
                $isImportantDataAttribute = $verse['is_important_for_user'] ? 'true' : 'false';
            ?>
            <span class="verse-num"><?php echo $verse['verse_number']; ?></span><span 
                class="verse <?php echo $highlightClass; ?> <?php echo $importantClass; ?>" 
                data-global-id="<?php echo $verse['global_verse_id']; ?>" 
                data-current-color="<?php echo $highlightColorValue ?? ''; ?>" 
                data-is-important="<?php echo $isImportantDataAttribute; ?>">
                <?php if ($verse['is_important_for_user']): ?>
                    <i class="bi bi-star-fill user-important-star" title="Für mich wichtig"></i>&nbsp;
                <?php endif; ?>
                <?php echo htmlspecialchars($verse['verse_text']); ?>
            </span><?php echo ' '; ?>
        <?php endforeach; ?>
    <?php else: ?>
         <p class="text-center">Keine Verse für diese Auswahl gefunden.</p>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between mt-4 mb-5">
    <?php if ($prevChapterLink): ?>
        <a href="<?php echo $prevChapterLink; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Vorheriges Kapitel
        </a>
    <?php else: ?>
        <span></span> <?php endif; ?>

    <?php if ($nextChapterLink): ?>
        <a href="<?php echo $nextChapterLink; ?>" class="btn btn-outline-secondary">
            Nächstes Kapitel <i class="bi bi-arrow-right-circle"></i>
        </a>
    <?php else: ?>
        <span></span> <?php endif; ?>
</div>


<div class="modal fade" id="compareModal" tabindex="-1" aria-labelledby="compareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="compareModalLabel">Versvergleich</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="compareModalBody"></div>
    </div></div>
</div>
<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="commentsModalLabel">Kommentare für Vers</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="commentsModalBody"></div>
    </div></div>
</div>

<div class="modal fade" id="verseActionModal" tabindex="-1" aria-labelledby="verseActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verseActionModalLabel">Aktionen für...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="verseActionModalBody">
                </div>
        </div>
    </div>
</div>


<?php
// Nur für eingeloggte Benutzer und wenn eine gültige Position angezeigt wird
if ($loggedInUserId > 0 && isset($currentBookId) && $currentBookId > 0 && isset($currentChapter) && $currentChapter > 0 && isset($currentVersionId) && $currentVersionId > 0):
?>
<script>
    try {
        const lastReadPosition = {
            book: <?php echo json_encode($currentBookId); ?>,
            chapter: <?php echo json_encode($currentChapter); ?>,
            version: <?php echo json_encode($currentVersionId); ?>
        };
        // Speichern unter einem Schlüssel, der die User-ID enthält
        localStorage.setItem('lastReadPosition_<?php echo $loggedInUserId; ?>', JSON.stringify(lastReadPosition));
        // console.log('Zuletzt gelesene Position gespeichert:', lastReadPosition);
    } catch (e) {
        console.error('Fehler beim Speichern der letzten Leseposition im localStorage:', e);
    }
</script>
<?php
endif;
require_once 'footer.php'; 
?>




