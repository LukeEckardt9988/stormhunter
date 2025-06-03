<?php
require_once 'config.php';
require_once 'helpers.php'; // NEU: Hilfsfunktionen einbinden

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Ungültige Aktion.'];
$userId = (int)($_SESSION['user_id'] ?? 0); // Bleibt für ggf. spätere personalisierte Features
$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Die Funktion render_article_full_content() ist jetzt in helpers.php,
// daher hier nicht mehr nötig, es sei denn, sie wurde nicht aus helpers.php geladen.

if ($action) {
    try {
        // Die Aktion 'get_news_article_content' für das Modal ist nicht mehr nötig.
        // if ($action === 'get_news_article_content') { ... }

        if ($action === 'get_more_news') {
            $page = (int)($_GET['page'] ?? 1);
            if ($page <= 1) { // Sollte nicht passieren, da Seite 1 initial geladen wird
                 $response = ['status' => 'success', 'articles_html' => ''];
            } else {
                $posts_per_page = 5;
                $offset = ($page - 1) * $posts_per_page;

                $stmtNews = $pdo->prepare("
                    SELECT a.article_id, a.title, a.created_at, u.username AS author_username
                    FROM news_articles a
                    JOIN users u ON a.user_id = u.user_id
                    WHERE a.is_published = TRUE
                    ORDER BY a.created_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                $stmtNews->bindParam(':limit', $posts_per_page, PDO::PARAM_INT);
                $stmtNews->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmtNews->execute();
                $moreArticles = $stmtNews->fetchAll(PDO::FETCH_ASSOC);
                
                $articles_html = '';
                if ($moreArticles) {
                    foreach ($moreArticles as $article) {
                        // Verwende die Hilfsfunktion aus helpers.php
                        $preview_elements = get_article_preview_elements($pdo, $article['article_id'], $article['title']);

                        $articles_html .= '<div class="col"><div class="card h-100 shadow-sm news-article-card">';
                        $articles_html .= $preview_elements['image_html']; // Fügt das Bild ein, wenn vorhanden
                        $articles_html .= '<div class="card-body d-flex flex-column">';
                        $articles_html .= '<h5 class="card-title">'.htmlspecialchars($article['title']).'</h5>';
                        $articles_html .= '<small class="text-muted mb-2"><i class="bi bi-calendar3"></i> '.date("d.m.Y", strtotime($article['created_at'])).' von '.htmlspecialchars($article['author_username']).'</small>';
                        $articles_html .= '<div class="article-text-preview mb-3 flex-grow-1">'.$preview_elements['text_html'].'</div>';
                        $articles_html .= '</div></div></div>';
                    }
                    $response = ['status' => 'success', 'articles_html' => $articles_html];
                } else {
                    $response = ['status' => 'success', 'articles_html' => '']; 
                }
            }
        } else {
            $response['message'] = 'Unbekannte Aktion für News oder Aktion nicht mehr unterstützt.';
        }
    } catch (PDOException $e) {
        error_log("AJAX News Fehler: " . $e->getMessage() . (isset($stmt) ? " SQL: " . $stmt->queryString : ""));
        $response = ['status' => 'error', 'message' => 'Datenbankfehler: ' . $e->getMessage()];
    }
}

echo json_encode($response);
exit();
?>