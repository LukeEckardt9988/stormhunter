<?php
// config.php wird als Erstes geladen
require_once 'config.php'; 

$loggedInUserId = $_SESSION['user_id'] ?? 0; 
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

<h1 class="text-center mb-4"><?php echo htmlspecialchars((string)$currentBookName . ' ' . (string)$currentChapter); ?></h1>

<div class="verse-container fs-5"> 
    <?php if (!empty($versesWithDetails)): ?>
        <?php foreach ($versesWithDetails as $verse): ?>
            <?php 
                // ... (Ihre bestehende Logik zum Setzen von $highlightClass und $importantClass) ...
                $highlightColorValue = $globalHighlights[$verse['global_verse_id']] ?? null; 
                $highlightClass = '';
                if ($highlightColorValue) {
                    switch ($highlightColorValue) {
                        case 'yellow': $highlightClass = 'bg-warning'; break;
                        case 'green':  $highlightClass = 'bg-green-custom'; break;
                        case 'blue':   $highlightClass = 'bg-blue-custom'; break;
                        case 'red':    $highlightClass = 'bg-red-custom'; break;
                        case 'orange': $highlightClass = 'bg-orange-custom'; break;
                        case 'brown':  $highlightClass = 'bg-brown-custom'; break;
                        case 'gray':   $highlightClass = 'bg-gray-custom'; break;
                        case 'parable': $highlightClass = 'bg-parable-custom'; break;
                        case 'rebuke':  $highlightClass = 'bg-rebuke-custom'; break;
                        case 'name':    $highlightClass = 'bg-name-custom'; break;
                        case 'sanctification': $highlightClass = 'bg-sanctification-custom'; break;
                        case 'miracle': $highlightClass = 'bg-miracle-custom'; break;
                        case 'gods_work': $highlightClass = 'bg-gods-work-custom'; break;
                        case 'law':     $highlightClass = 'bg-law-custom'; break;
                        case 'wisdom':  $highlightClass = 'bg-wisdom-custom'; break;
                        case 'worship': $highlightClass = 'bg-worship-custom'; break;
                        case 'covenant':$highlightClass = 'bg-covenant-custom'; break;
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


<?php
require_once 'footer.php'; 
?>
