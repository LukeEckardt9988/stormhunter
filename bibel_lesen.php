<?php
// config.php wird als Erstes geladen, um die Session zu starten und $pdo bereitzustellen
require_once 'config.php'; // Stellt sicher, dass $pdo global verfügbar ist und die Session läuft

$loggedInUserId = $_SESSION['user_id'] ?? 0; // 0 für Gast oder nicht eingeloggten Benutzer
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

// Standardwerte und GET-Parameter holen (Integer-Cast für Sicherheit)
$currentVersionId = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 1;
$currentBookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 1;
$currentChapter = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 1;

// --- Daten für Auswahlmenüs holen ---
$stmtVersions = $pdo->prepare("SELECT version_id, version_name FROM versions ORDER BY version_name ASC");
$stmtVersions->execute();
$allVersions = $stmtVersions->fetchAll(PDO::FETCH_ASSOC);

$stmtBooks = $pdo->prepare("SELECT book_id, book_name_de, book_order FROM books ORDER BY book_order ASC");
$stmtBooks->execute();
$allBooks = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

// --- Aktuelles Buch und höchste Kapitelnummer für die aktuelle Auswahl ---
$currentBookName = 'Unbekanntes Buch';
if ($currentBookId) {
    $stmtCurrentBookInfo = $pdo->prepare("SELECT book_name_de FROM books WHERE book_id = :book_id");
    $stmtCurrentBookInfo->execute([':book_id' => $currentBookId]);
    $currentBookInfo = $stmtCurrentBookInfo->fetch(PDO::FETCH_ASSOC);
    if ($currentBookInfo) {
        $currentBookName = $currentBookInfo['book_name_de'];
    }
}

$maxChapter = 1; // Standardwert, falls keine Kapitel gefunden werden
if ($currentBookId && $currentVersionId) {
    $stmtMaxChapter = $pdo->prepare("SELECT MAX(chapter_number) as max_chapter FROM verses WHERE book_id = :book_id AND version_id = :version_id");
    $stmtMaxChapter->execute([':book_id' => $currentBookId, ':version_id' => $currentVersionId]);
    $maxChapterResult = $stmtMaxChapter->fetchColumn(); // fetchColumn holt direkt den Wert
    if ($maxChapterResult) {
        $maxChapter = (int)$maxChapterResult;
    }
}
// Sicherstellen, dass das aktuelle Kapitel im gültigen Bereich liegt
$currentChapter = max(1, min($currentChapter, $maxChapter));

// --- Verse für das aktuelle Kapitel und die aktuelle Version laden ---
$verses = [];
if ($currentBookId && $currentVersionId) {
    $stmtVerses = $pdo->prepare(
        "SELECT verse_number, verse_text, global_verse_id FROM verses 
         WHERE version_id = :version_id AND book_id = :book_id AND chapter_number = :chapter_num 
         ORDER BY verse_number ASC"
    );
    $stmtVerses->execute([
        ':version_id' => $currentVersionId,
        ':book_id' => $currentBookId,
        ':chapter_num' => $currentChapter
    ]);
    $verses = $stmtVerses->fetchAll(PDO::FETCH_ASSOC);
}

// --- Markierungen für den aktuellen Benutzer und die angezeigten Verse laden ---
// --- Markierungen für den aktuellen Benutzer und die angezeigten Verse laden ---
$userHighlightsData = []; // Speichert jetzt Farbe statt nur true
if ($loggedInUserId !== 0 && !empty($verses)) {
    $globalVerseIdsForCurrentChapter = array_column($verses, 'global_verse_id');
    if (!empty($globalVerseIdsForCurrentChapter)) {
        $placeholders = implode(',', array_fill(0, count($globalVerseIdsForCurrentChapter), '?'));
        // Lade auch die Farbe
        $sqlHighlights = "SELECT global_verse_id, highlight_color FROM user_highlights 
                          WHERE user_id = ? AND global_verse_id IN (" . $placeholders . ")";
        $stmtHighlights = $pdo->prepare($sqlHighlights);
        $params = array_merge([$loggedInUserId], $globalVerseIdsForCurrentChapter);
        $stmtHighlights->execute($params);
        while ($row = $stmtHighlights->fetch(PDO::FETCH_ASSOC)) {
            $userHighlightsData[$row['global_verse_id']] = $row['highlight_color']; // Speichere die Farbe
        }
    }
}

// Seitentitel für den Header
$pageTitle = "Bibel lesen: " . htmlspecialchars($currentBookName . ' ' . $currentChapter);
require_once 'header.php'; // Lädt den Header, der $pageTitle verwendet
?>

<div class="card card-body bg-light mb-4">
    <form method="GET" action="bibel_lesen.php" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="get_version_id" class="form-label">Übersetzung</label>
            <select name="version_id" id="get_version_id" class="form-select">
                <?php foreach ($allVersions as $version): ?>
                    <option value="<?php echo $version['version_id']; ?>" <?php echo ($version['version_id'] == $currentVersionId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($version['version_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="get_book_id" class="form-label">Buch</label>
            <select name="book_id" id="get_book_id" class="form-select">
                <?php foreach ($allBooks as $book): ?>
                    <option value="<?php echo $book['book_id']; ?>" <?php echo ($book['book_id'] == $currentBookId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($book['book_name_de']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="get_chapter" class="form-label">Kapitel</label>
            <select name="chapter" id="get_chapter" class="form-select">
                <?php if ($maxChapter > 0): ?>
                    <?php for ($i = 1; $i <= $maxChapter; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $currentChapter) ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                <?php else: ?>
                    <option value="1">1</option> <?php endif; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
             <button type="submit" class="btn btn-primary">Anzeigen</button>
        </div>
    </form>
</div>

<h1 class="text-center mb-4"><?php echo htmlspecialchars($currentBookName . ' ' . $currentChapter); ?></h1>

<div class="verse-container fs-5"> 
    <?php if (!empty($verses)): ?>
        <?php foreach ($verses as $verse): ?>
                <?php 
                    $highlightColorValue = $userHighlightsData[$verse['global_verse_id']] ?? null;
                    $highlightClass = '';
                    if ($highlightColorValue) {
                        switch ($highlightColorValue) {
                            case 'yellow': $highlightClass = 'bg-warning'; break;
                            case 'green':  $highlightClass = 'bg-success-subtle'; break;
                            case 'blue':   $highlightClass = 'bg-info-subtle'; break;
                            case 'red':    $highlightClass = 'bg-danger-subtle'; break;
                            case 'orange': $highlightClass = 'bg-orange-subtle'; break;
                            case 'gray':   $highlightClass = 'bg-secondary-subtle'; break;
                        }
                    }
                ?>
                <p>
                    <span class="verse-num"><?php echo $verse['verse_number']; ?></span>
                    <span class="verse <?php echo $highlightClass; ?>" data-global-id="<?php echo $verse['global_verse_id']; ?>" data-current-color="<?php echo $highlightColorValue ?? ''; ?>">
                        <?php echo htmlspecialchars($verse['verse_text']); ?>
                    </span>
                </p>
        <?php endforeach; ?>
    <?php else: ?>
         <p class="text-center">Keine Verse für diese Auswahl gefunden. Bitte überprüfen Sie die Datenbank oder die Auswahl.</p>
    <?php endif; ?>
</div>
<div class="modal fade" id="compareModal" tabindex="-1" aria-labelledby="compareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="compareModalLabel">Versvergleich</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="compareModalBody">
        </div>
    </div>
  </div>
</div>

<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="commentsModalLabel">Kommentare für Vers</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="commentsModalBody">
        </div>
    </div>
  </div>
</div>

<?php
require_once 'footer.php'; // Lädt den Footer (inkl. JavaScript-Dateien)
?>