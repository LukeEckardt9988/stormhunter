<?php
require_once 'config.php'; // Lädt Konfiguration, Session und $pdo
header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Ungültige Aktion.'];
$userId = (int)($_SESSION['user_id'] ?? 0); // Wird für get_more_news ggf. für user_liked benötigt, falls das später wiederkommt
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action) {
    try {
        if ($action === 'get_news_article_content') {
            $articleId = (int)($_GET['article_id'] ?? 0);
            if ($articleId > 0) {
                $stmtArticle = $pdo->prepare("SELECT title, article_text, content_type, media_url FROM news_articles WHERE article_id = :aid AND is_published = TRUE");
                $stmtArticle->execute([':aid' => $articleId]);
                $articleContent = $stmtArticle->fetch(PDO::FETCH_ASSOC);
                if ($articleContent) {
                    $html_content = '';
                    if ($articleContent['content_type'] === 'image' && !empty($articleContent['media_url'])) {
                        $html_content .= '<img src="'.htmlspecialchars($articleContent['media_url']).'" class="img-fluid mb-3" alt="'.htmlspecialchars($articleContent['title']).'">';
                    } elseif ($articleContent['content_type'] === 'video_embed' && !empty($articleContent['media_url'])) {
                        $html_content .= '<div class="ratio ratio-16x9 mb-3"><iframe src="https://www.youtube-nocookie.com/embed/'.htmlspecialchars($articleContent['media_url']).'" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>';
                    }
                    $html_content .= nl2br(htmlspecialchars($articleContent['article_text']));
                     if (($articleContent['content_type'] === 'external_link' || $articleContent['content_type'] === 'video_link') && !empty($articleContent['media_url'])){
                         $html_content .= '<p class="mt-3"><a href="'.htmlspecialchars($articleContent['media_url']).'" target="_blank" rel="noopener noreferrer" class="btn btn-primary">'.($articleContent['content_type'] === 'video_link' ? 'Video ansehen' : 'Zum Beitrag').' <i class="bi bi-box-arrow-up-right"></i></a></p>';
                     }
                    $response = ['status' => 'success', 'title' => $articleContent['title'], 'html_content' => $html_content];
                } else {
                    $response['message'] = 'Artikel nicht gefunden oder nicht veröffentlicht.';
                }
            } else {
                 $response['message'] = 'Ungültige Artikel-ID für Inhaltsabruf.';
            }
        } elseif ($action === 'get_more_news') {
            $page = (int)($_GET['page'] ?? 1);
            if ($page <= 1) { // page muss größer 1 sein, da erste Seite schon geladen
                 $response = ['status' => 'success', 'articles_html' => '']; // Keine weiteren Artikel für Seite 1
            } else {
                $posts_per_page = 5; // Muss mit dashboard.php übereinstimmen
                $offset = ($page - 1) * $posts_per_page;

                // Da wir keine Likes/Kommentare mehr haben, ist die Abfrage einfacher:
                $stmtNews = $pdo->prepare("
                    SELECT a.*, u.username AS author_username
                    FROM news_articles a
                    JOIN users u ON a.user_id = u.user_id
                    WHERE a.is_published = TRUE
                    ORDER BY a.created_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                // $stmtNews->bindParam(':current_user_id', $userId, PDO::PARAM_INT); // Nicht mehr benötigt ohne Likes/Kommentare
                $stmtNews->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
                $stmtNews->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmtNews->execute();
                $moreArticles = $stmtNews->fetchAll(PDO::FETCH_ASSOC);
                
                $articles_html = '';
                if ($moreArticles) {
                    foreach ($moreArticles as $article) {
                        // HTML für jede Karte generieren (vereinfacht, ohne Like/Kommentar-Buttons)
                        $previewTextFull = nl2br(htmlspecialchars($article['article_text']));
                        $maxLength = 180;
                        $previewTextDisplay = "";
                        if (mb_strlen(strip_tags($article['article_text'])) > $maxLength) {
                            $preview = mb_substr(strip_tags($article['article_text']), 0, $maxLength);
                            $lastSpace = mb_strrpos($preview, ' ');
                            $previewTextDisplay = nl2br(htmlspecialchars(mb_substr($preview, 0, ($lastSpace !== false ? $lastSpace : $maxLength)))) . '... <a href="#" class="read-more-news stretched-link" data-bs-toggle="modal" data-bs-target="#newsArticleModal" data-article-id="' . $article['article_id'] . '">Weiterlesen</a>';
                        } else {
                            $previewTextDisplay = $previewTextFull;
                        }

                        $articles_html .= '<div class="col"><div class="card h-100 shadow-sm news-article-card" data-article-id="'.$article['article_id'].'">';
                        if ($article['content_type'] === 'image' && !empty($article['media_url'])) {
                            $articles_html .= '<img src="'.htmlspecialchars($article['media_url']).'" class="card-img-top news-card-image" alt="'.htmlspecialchars($article['title']).'">';
                        } elseif ($article['content_type'] === 'video_embed' && !empty($article['media_url'])) {
                            $articles_html .= '<div class="ratio ratio-16x9 card-img-top-placeholder"><iframe src="https://www.youtube-nocookie.com/embed/'.htmlspecialchars($article['media_url']).'" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>';
                        }
                        $articles_html .= '<div class="card-body d-flex flex-column"><h5 class="card-title">'.htmlspecialchars($article['title']).'</h5>';
                        $articles_html .= '<small class="text-muted mb-2"><i class="bi bi-calendar3"></i> '.date("d.m.Y", strtotime($article['created_at'])).'</small>';
                        $articles_html .= '<div class="article-text-preview mb-3 flex-grow-1">'.$previewTextDisplay.'</div>';
                        if (($article['content_type'] === 'external_link' || $article['content_type'] === 'video_link') && !empty($article['media_url'])) {
                            $articles_html .= '<a href="'.htmlspecialchars($article['media_url']).'" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm mt-auto mb-2 d-block">'.($article['content_type'] === 'video_link' ? '<i class="bi bi-play-btn-fill"></i> Video ansehen' : '<i class="bi bi-box-arrow-up-right"></i> Zum Beitrag').'</a>';
                        }
                        $articles_html .= '</div>'; // Ende card-body
                        // Card-Footer für Likes/Kommentare wurde entfernt
                        $articles_html .= '</div></div>'; // Ende card und col
                    }
                    $response = ['status' => 'success', 'articles_html' => $articles_html];
                } else {
                    $response = ['status' => 'success', 'articles_html' => '']; // Keine weiteren Artikel
                }
            }
        } else {
            $response['message'] = 'Unbekannte Aktion für News.';
        }
    } catch (PDOException $e) {
        error_log("AJAX News Fehler: " . $e->getMessage() . (isset($stmt) ? " SQL: " . $stmt->queryString : ""));
        $response = ['status' => 'error', 'message' => 'Datenbankfehler. Details wurden protokolliert.'];
    }
}

echo json_encode($response);
exit();
?>