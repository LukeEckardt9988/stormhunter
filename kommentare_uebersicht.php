<?php
require_once 'config.php'; 

$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';
$pageTitle = "Alle Kommentare";

require_once 'header.php'; 

// Standard-Filter/Sortierwerte
$searchTerm = trim($_GET['search'] ?? '');
$filterBookId = (int)($_GET['book_id'] ?? 0);
$filterUserId = (isset($_GET['my_comments']) && $loggedInUserId > 0) ? $loggedInUserId : 0; // 0 für alle User
$sortBy = $_GET['sort_by'] ?? 'likes_desc'; // likes_desc, date_desc, date_asc

// Hier würde die Logik zum Laden der Bücher für den Filter-Dropdown stehen
$stmtBooks = $pdo->prepare("SELECT book_id, book_name_de FROM books ORDER BY book_order ASC");
$stmtBooks->execute();
$allBooks = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container mt-4">
    <h1 class="mb-4"><?php echo $pageTitle; ?></h1>

    <div class="card card-body bg-light mb-4">
        <form method="GET" action="kommentare_uebersicht.php" id="filterCommentsForm">
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="search" class="form-label">Suche in Kommentaren/Versen</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Stichwort eingeben...">
                </div>
                <div class="col-md-3">
                    <label for="book_id" class="form-label">Buch filtern</label>
                    <select class="form-select" id="book_id" name="book_id">
                        <option value="">Alle Bücher</option>
                        <?php foreach ($allBooks as $book): ?>
                            <option value="<?php echo $book['book_id']; ?>" <?php echo ($filterBookId == $book['book_id'] ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($book['book_name_de']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort_by" class="form-label">Sortieren nach</label>
                    <select class="form-select" id="sort_by" name="sort_by">
                        <option value="likes_desc" <?php echo ($sortBy === 'likes_desc' ? 'selected' : ''); ?>>Meiste Likes</option>
                        <option value="date_desc" <?php echo ($sortBy === 'date_desc' ? 'selected' : ''); ?>>Neueste zuerst</option>
                        <option value="date_asc" <?php echo ($sortBy === 'date_asc' ? 'selected' : ''); ?>>Älteste zuerst</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtern</button>
                </div>
            </div>
            <?php if ($loggedInUserId > 0): ?>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="my_comments" id="my_comments" value="1" <?php echo ($filterUserId === $loggedInUserId ? 'checked' : ''); ?> onchange="this.form.submit()">
                        <label class="form-check-label" for="my_comments">
                            Nur meine Kommentare anzeigen
                        </label>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div id="commentListContainer">
        <p class="text-center">Lade Kommentare...</p>
    </div>

    <nav aria-label="Comment navigation" class="mt-4">
        <ul class="pagination justify-content-center" id="commentPagination">
           
        </ul>
    </nav>

    <div class="modal fade" id="editCommentModal" tabindex="-1" aria-labelledby="editCommentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCommentModalLabel">Kommentar bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editCommentId">
                    <textarea class="form-control" id="editCommentText" rows="5"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" id="saveCommentChanges">Änderungen speichern</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php'; 
?>
