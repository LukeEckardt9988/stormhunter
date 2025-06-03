<?php
require_once 'config.php'; // Stellt $pdo bereit
require_once 'helpers.php'; // Stellt render_article_full_content bereit

$article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;
$article_title = "Artikel nicht gefunden";
$article_content_html = "<p>Der angeforderte Artikel konnte nicht gefunden werden.</p>";
$author_username = '';
$created_date = '';

if ($article_id > 0) {
    $stmt = $pdo->prepare("SELECT a.title, a.created_at, u.username 
                           FROM news_articles a
                           JOIN users u ON a.user_id = u.user_id 
                           WHERE a.article_id = :aid AND a.is_published = TRUE");
    $stmt->execute([':aid' => $article_id]);
    $article_meta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($article_meta) {
        $article_title = htmlspecialchars($article_meta['title']);
        $author_username = htmlspecialchars($article_meta['username']);
        $created_date = date("d.m.Y H:i", strtotime($article_meta['created_at']));
        $article_content_html = render_article_full_content($pdo, $article_id);
    } else {
        // Artikel nicht gefunden oder nicht veröffentlicht, Standardmeldung bleibt
        http_response_code(404); // Setze HTTP-Statuscode für "Nicht gefunden"
    }
} else {
    // Keine article_id übergeben
     http_response_code(400); // Bad Request
}

$pageTitle = $article_title . " - Ohael Bibel-App"; // Dynamischer Seitentitel
require_once 'header.php';
?>

<div class="container mt-4 mb-5">
    <article class="news-article-full">
        <header class="mb-4">
            <h1 class="display-5"><?php echo $article_title; ?></h1>
            <?php if ($author_username && $created_date): ?>
                <p class="text-muted">
                    <i class="bi bi-person-fill"></i> Veröffentlicht von: <strong><?php echo $author_username; ?></strong>
                    <span class="mx-2">|</span>
                    <i class="bi bi-calendar3"></i> Am: <?php echo $created_date; ?>
                </p>
            <?php endif; ?>
        </header>
        
        <hr class="my-4">

        <section class="article-content">
            <?php echo $article_content_html; ?>
        </section>
    </article>
    
    <div class="mt-5 text-center">
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle"></i> Zurück zur Übersicht</a>
    </div>
</div>

<?php
require_once 'footer.php';
?>