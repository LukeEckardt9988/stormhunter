<?php
require_once 'config.php';
require_once 'helpers.php'; // NEU: Hilfsfunktionen einbinden

$pageTitle = "Aktuelles - Ohael Bibel-App";
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

$posts_per_page = 5;
$stmtNewsInitial = $pdo->prepare("
    SELECT
        a.article_id, a.title, a.created_at,
        u.username AS author_username
    FROM news_articles a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.is_published = TRUE
    ORDER BY a.created_at DESC
    LIMIT :limit
");
$stmtNewsInitial->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
$stmtNewsInitial->execute();
$newsArticlesInitial = $stmtNewsInitial->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4 display-5">Aktuelles & Inspirationen</h1>

    <?php if (empty($newsArticlesInitial) && (!defined('NEWS_ADMIN_USER_ID') || $loggedInUserId !== NEWS_ADMIN_USER_ID)): ?>
        <div class="alert alert-info text-center">Momentan gibt es keine neuen Beiträge. Schauen Sie bald wieder vorbei!</div>
    <?php elseif (empty($newsArticlesInitial) && (defined('NEWS_ADMIN_USER_ID') && $loggedInUserId === NEWS_ADMIN_USER_ID)): ?>
         <div class="alert alert-info text-center">
            Noch keine Beiträge vorhanden. <a href="create_news_post.php" class="alert-link">Jetzt ersten Beitrag erstellen!</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4" id="newsFeedContainer">
            <?php foreach ($newsArticlesInitial as $article): ?>
                <?php
                    // Verwende die Hilfsfunktion für die Vorschau-Teile
                    $preview_elements = get_article_preview_elements($pdo, $article['article_id'], $article['title']);
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm news-article-card"> 
                        <?php echo $preview_elements['image_html']; // Gibt das Bild aus, wenn vorhanden ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                            <small class="text-muted mb-2">
                                <i class="bi bi-calendar3"></i> <?php echo date("d.m.Y", strtotime($article['created_at'])); ?>
                                von <?php echo htmlspecialchars($article['author_username']); ?>
                            </small>
                            <div class="article-text-preview mb-3 flex-grow-1">
                                <?php echo $preview_elements['text_html']; // Gibt den Vorschautext mit korrektem "Weiterlesen"-Link aus ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="loadingIndicatorNews" class="text-center my-4" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Lade mehr Beiträge...</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php // Das Modal #newsArticleModal wird nicht mehr benötigt und kann aus dieser Datei entfernt werden. ?>

<?php require_once 'footer.php'; ?>