<?php
require_once 'config.php'; // Lädt Konfiguration, Session und $pdo
$pageTitle = "Aktuelles - Ohael Bibel-App";
$loggedInUserId = $_SESSION['user_id'] ?? 0;
$loggedInUsername = $_SESSION['username'] ?? 'Gast';

// Initial News-Beiträge laden
$posts_per_page = 5; // Anzahl der initial zu ladenden Beiträge
$stmtNews = $pdo->prepare("
    SELECT
        a.article_id, a.title, a.content_type, a.media_url, a.article_text, a.created_at,
        u.username AS author_username
    FROM news_articles a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.is_published = TRUE
    ORDER BY a.created_at DESC
    LIMIT :limit
");
$stmtNews->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
$stmtNews->execute();
$newsArticles = $stmtNews->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4 display-5">Aktuelles & Inspirationen</h1>

    <?php if (empty($newsArticles) && (defined('NEWS_ADMIN_USER_ID') && $loggedInUserId !== NEWS_ADMIN_USER_ID)): ?>
        <div class="alert alert-info text-center">Momentan gibt es keine neuen Beiträge. Schauen Sie bald wieder vorbei!</div>
    <?php elseif (empty($newsArticles) && (defined('NEWS_ADMIN_USER_ID') && $loggedInUserId === NEWS_ADMIN_USER_ID)): ?>
         <div class="alert alert-info text-center">
            Noch keine Beiträge vorhanden. <a href="create_news_post.php" class="alert-link">Jetzt ersten Beitrag erstellen!</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4" id="newsFeedContainer">
            <?php foreach ($newsArticles as $article): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm news-article-card" data-article-id="<?php echo $article['article_id']; ?>">
                        
                        <?php if ($article['content_type'] === 'image' && !empty($article['media_url'])): ?>
                            <img src="<?php echo htmlspecialchars($article['media_url']); ?>" class="card-img-top news-card-image" alt="<?php echo htmlspecialchars($article['title']); ?>">
                        <?php elseif ($article['content_type'] === 'video_embed' && !empty($article['media_url'])): ?>
                            <div class="ratio ratio-16x9 card-img-top-placeholder">
                                <iframe src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($article['media_url']); ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                            <small class="text-muted mb-2">
                                <i class="bi bi-calendar3"></i> <?php echo date("d.m.Y", strtotime($article['created_at'])); ?>
                            </small>
                            <div class="article-text-preview mb-3 flex-grow-1">
                                <?php
                                    $fullTextHtml = nl2br(htmlspecialchars($article['article_text']));
                                    $maxLength = 180; 
                                    if (mb_strlen(strip_tags($article['article_text'])) > $maxLength) {
                                        $previewText = mb_substr(strip_tags($article['article_text']), 0, $maxLength);
                                        $lastSpace = mb_strrpos($previewText, ' ');
                                        if ($lastSpace !== false) {
                                            $previewText = mb_substr($previewText, 0, $lastSpace);
                                        }
                                        echo nl2br(htmlspecialchars($previewText)) . '... <a href="#" class="read-more-news stretched-link" data-bs-toggle="modal" data-bs-target="#newsArticleModal" data-article-id="' . $article['article_id'] . '">Weiterlesen</a>';
                                    } else {
                                        echo $fullTextHtml;
                                    }
                                ?>
                            </div>

                            <?php if (($article['content_type'] === 'external_link' || $article['content_type'] === 'video_link') && !empty($article['media_url'])): ?>
                                <a href="<?php echo htmlspecialchars($article['media_url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm mt-auto mb-2 d-block">
                                    <?php echo ($article['content_type'] === 'video_link' ? '<i class="bi bi-play-btn-fill"></i> Video ansehen' : '<i class="bi bi-box-arrow-up-right"></i> Zum Beitrag'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php /* Like- und Kommentar-Buttons entfernt
                        <div class="card-footer bg-transparent d-flex justify-content-start align-items-center gap-2">
                             <button class="btn btn-sm btn-outline-danger news-like-btn ..." ...> ... </button>
                             <button class="btn btn-sm btn-outline-secondary news-comment-btn ..." ...> ... </button>
                        </div>
                        */ ?>
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

<div class="modal fade" id="newsArticleModal" tabindex="-1" aria-labelledby="newsArticleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newsArticleModalLabel">Titel des Beitrags</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="newsArticleModalBody">
        {/* Inhalt wird per JS geladen */}
      </div>
      <?php /* Footer des Modals entfernt, da keine Aktionen wie Like/Kommentar mehr darin
      <div class="modal-footer justify-content-start" id="newsArticleModalFooter">
      </div>
      */ ?>
    </div>
  </div>
</div>

<?php // Das newsCommentsModal wird nicht mehr benötigt ?>

<?php require_once 'footer.php'; ?>