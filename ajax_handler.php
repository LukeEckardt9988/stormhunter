<?php
require_once 'config.php'; // Lädt Konfiguration, startet Session und stellt $pdo bereit

// Standard-Antwort initialisieren
$response = ['status' => 'error', 'message' => 'Ungültige Aktion oder fehlende Parameter.'];

// Eingehende Parameter sicher abrufen
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);

$globalVerseId = (int)($_POST['global_verse_id'] ?? $_GET['global_verse_id'] ?? 0);
$noteId = (int)($_POST['note_id'] ?? $_GET['note_id'] ?? 0);

// Parameter-Normalisierung
if (in_array($action, ['like_comment', 'update_comment', 'delete_comment']) && $globalVerseId > 0 && $noteId === 0) {
    $noteId = $globalVerseId;
}
if (in_array($action, ['addHighlight', 'removeHighlight', 'addImportant', 'removeImportant']) && $noteId > 0 && $globalVerseId === 0) {
    $globalVerseId = $noteId;
}


if ($action) {
    try {
        // Aktionen, die HTML zurückgeben und mit exit() enden
        if ($action === 'compareVerse' && $globalVerseId > 0) {
            header('Content-Type: text/html; charset=utf-8');
            $sql = "SELECT v.verse_text, ver.version_name 
                    FROM verses v
                    JOIN versions ver ON v.version_id = ver.version_id
                    WHERE v.global_verse_id = :gvid
                    ORDER BY ver.version_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':gvid' => $globalVerseId]);
            $versesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($versesData) {
                $output = '<ul class="list-group list-group-flush">';
                foreach ($versesData as $verseItem) {
                    $output .= '<li class="list-group-item px-0"><small class="text-muted d-block">' . htmlspecialchars($verseItem['version_name']) . '</small><div>' . nl2br(htmlspecialchars($verseItem['verse_text'])) . '</div></li>';
                }
                $output .= '</ul>';
                echo $output;
            } else {
                echo '<p class="text-center">Keine weiteren Übersetzungen gefunden.</p>';
            }
            exit();
        }

        if ($action === 'getComments' && $globalVerseId > 0) { // Für das Modal in bibel_lesen.php
            header('Content-Type: text/html; charset=utf-8');
            $sql = "SELECT un.note_id, un.note_text, un.note_date, u.username AS author_username, un.user_id AS author_id,
                           COALESCE(cl_count.like_count, 0) AS like_count,
                           EXISTS(SELECT 1 FROM comment_likes cl_user WHERE cl_user.note_id = un.note_id AND cl_user.user_id = :current_user_id) as user_liked
                    FROM user_notes un
                    JOIN users u ON un.user_id = u.user_id
                    LEFT JOIN (SELECT note_id, COUNT(like_id) AS like_count FROM comment_likes GROUP BY note_id) cl_count ON un.note_id = cl_count.note_id
                    WHERE un.global_verse_id = :gvid AND un.is_public = 1
                    ORDER BY un.note_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':gvid' => $globalVerseId, ':current_user_id' => $userId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($comments) {
                $output = '<ul class="list-group list-group-flush">';
                foreach ($comments as $comment) {
                    $heartIconModal = $comment['user_liked'] ? 'bi-heart-fill text-danger' : 'bi-heart';
                    $output .= '<li class="list-group-item px-0" id="modal-comment-' . $comment['note_id'] . '">';
                    $output .= '<div>' . nl2br(htmlspecialchars($comment['note_text'])) . '</div>';
                    $output .= '<small class="text-muted">Von: <strong>' . htmlspecialchars($comment['author_username']) . '</strong> am ' . date("d.m.Y H:i", strtotime($comment['note_date'])) . '</small>';
                    $output .= "<div class='mt-1 d-flex align-items-center'>";
                    // Like-Button für Modal - bekommt eine eigene Klasse, um ihn in app.js gezielt anzusprechen
                    $output .= "<button class='btn btn-sm btn-outline-danger like-comment-btn-modal me-2' data-note-id='{$comment['note_id']}' title='Gefällt mir'>";
                    $output .= "<i class='bi {$heartIconModal}'></i> <span class='like-count'>{$comment['like_count']}</span>";
                    $output .= "</button>";
                    // Hier könnten auch Bearbeiten/Löschen-Buttons für den Autor im Modal hinzugefügt werden
                    if (isset($comment['author_id']) && $comment['author_id'] == $userId) {                        // $output .= "<button class='btn btn-sm btn-outline-secondary edit-comment-btn-modal ms-1' data-note-id='{$comment['note_id']}'><i class='bi bi-pencil'></i></button>";
                        // $output .= "<button class='btn btn-sm btn-outline-danger delete-comment-btn-modal ms-1' data-note-id='{$comment['note_id']}'><i class='bi bi-trash'></i></button>";
                    }
                    $output .= "</div></li>";
                }
                $output .= '</ul>';
                echo $output;
            } else {
                echo '<p class="text-center mt-2">Noch keine Kommentare für diesen Vers vorhanden.</p>';
            }
            exit();
        }

        // Ab hier: Aktionen, die JSON zurückgeben
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if ($action === 'get_all_comments') {
            $page = (int)($_GET['page'] ?? 1);
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $searchTerm = trim($_GET['search'] ?? '');
            $filterBookId = (int)($_GET['book_id'] ?? 0);
            $filterUserIdParam = (int)($_GET['filter_user_id'] ?? 0);
            $sortBy = $_GET['sort_by'] ?? 'likes_desc';

            $baseSqlFrom = "FROM user_notes un JOIN users u ON un.user_id = u.user_id JOIN verses v ON un.global_verse_id = v.global_verse_id AND v.version_id = 1 JOIN books b ON v.book_id = b.book_id LEFT JOIN (SELECT note_id, COUNT(like_id) as like_count FROM comment_likes GROUP BY note_id) cl ON un.note_id = cl.note_id";
            $baseSqlWhere = "WHERE un.is_public = 1";

            $whereClauses = [];
            $params = [];

            if (!empty($searchTerm)) {
                $whereClauses[] = "(un.note_text LIKE :search_term OR v.verse_text LIKE :search_term_verse)";
                $params[':search_term'] = "%$searchTerm%";
                $params[':search_term_verse'] = "%$searchTerm%";
            }
            if ($filterBookId > 0) {
                $whereClauses[] = "b.book_id = :book_id";
                $params[':book_id'] = $filterBookId;
            }
            if ($filterUserIdParam > 0) {
                $whereClauses[] = "un.user_id = :user_id_filter";
                $params[':user_id_filter'] = $filterUserIdParam;
            }

            if (!empty($whereClauses)) {
                $baseSqlWhere .= " AND " . implode(" AND ", $whereClauses);
            }

            $countSql = "SELECT COUNT(DISTINCT un.note_id) " . $baseSqlFrom . " " . $baseSqlWhere;
            $dataSql = "SELECT un.note_id, un.note_text, un.note_date, un.updated_at, un.user_id AS author_id, u.username AS author_username, v.verse_text, v.verse_number, v.chapter_number, v.global_verse_id AS verse_global_id, b.book_name_de, b.book_id, COALESCE(cl.like_count, 0) AS likes, EXISTS(SELECT 1 FROM comment_likes cl_user WHERE cl_user.note_id = un.note_id AND cl_user.user_id = :current_logged_in_user_id) as user_liked " . $baseSqlFrom . " " . $baseSqlWhere;

            switch ($sortBy) {
                case 'date_asc':
                    $dataSql .= " ORDER BY un.note_date ASC";
                    break;
                case 'date_desc':
                    $dataSql .= " ORDER BY un.note_date DESC";
                    break;
                case 'likes_desc':
                default:
                    $dataSql .= " ORDER BY likes DESC, un.note_date DESC";
                    break;
            }
            $dataSql .= " LIMIT :limit OFFSET :offset";

            $paramsForData = $params;
            $paramsForData[':current_logged_in_user_id'] = $userId;

            $stmtCount = $pdo->prepare($countSql);
            $stmtCount->execute($params);
            $totalComments = (int)$stmtCount->fetchColumn();
            $totalPages = ceil($totalComments / $limit);

            $stmtData = $pdo->prepare($dataSql);
            foreach ($paramsForData as $key => $value) {
                $stmtData->bindValue($key, $value);
            }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->execute();
            $commentsData = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            $response = ['status' => 'success', 'comments' => $commentsData, 'pagination' => ['currentPage' => $page, 'totalPages' => $totalPages, 'totalComments' => $totalComments]];
        } elseif (($action === 'addHighlight' || $action === 'removeHighlight') && $globalVerseId > 0) {
            // ... (Ihre bestehende Logik für Farbmarkierungen) ...
            $color = $_POST['color'] ?? null;
            if ($userId === 0 && $action === 'addHighlight' && $color !== 'remove_color') {
                $response = ['status' => 'error', 'message' => 'Bitte einloggen, um farblich zu markieren.'];
            } else {
                if ($userId > 0) {
                    $stmtDeleteOld = $pdo->prepare("DELETE FROM user_highlights WHERE user_id = :user_id AND global_verse_id = :gvid");
                    $stmtDeleteOld->execute([':user_id' => $userId, ':gvid' => $globalVerseId]);
                }
                if ($action === 'addHighlight' && $color && $color !== 'remove_color') {
                    if ($userId > 0) {
                        $stmtInsert = $pdo->prepare("INSERT INTO user_highlights (user_id, global_verse_id, highlight_color, highlight_date) VALUES (:user_id, :gvid, :color, NOW())");
                        if ($stmtInsert->execute([':user_id' => $userId, ':gvid' => $globalVerseId, ':color' => $color])) {
                            $response = ['status' => 'success', 'message' => 'Farbliche Markierung gespeichert.'];
                        } else {
                            $response = ['status' => 'error', 'message' => 'Fehler beim Speichern der farblichen Markierung.'];
                        }
                    }
                } elseif ($action === 'removeHighlight' || ($action === 'addHighlight' && $color === 'remove_color')) {
                    $response = ['status' => 'success', 'message' => 'Ihre farbliche Markierung wurde entfernt.'];
                }
            }
        } elseif (($action === 'addImportant' || $action === 'removeImportant') && $globalVerseId > 0) {
            // ... (Ihre bestehende Logik für "Wichtig") ...
            if ($userId === 0) {
                $response = ['status' => 'error', 'message' => 'Bitte einloggen.'];
            } else {
                if ($action === 'addImportant') {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO user_important_verses (user_id, global_verse_id, marked_at) VALUES (:user_id, :gvid, NOW())");
                    if ($stmt->execute([':user_id' => $userId, ':gvid' => $globalVerseId])) {
                        $response = ['status' => 'success', 'message' => 'Vers als "Für mich wichtig" markiert.'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Fehler beim Markieren als wichtig.'];
                    }
                } elseif ($action === 'removeImportant') {
                    $stmt = $pdo->prepare("DELETE FROM user_important_verses WHERE user_id = :user_id AND global_verse_id = :gvid");
                    if ($stmt->execute([':user_id' => $userId, ':gvid' => $globalVerseId])) {
                        $response = ['status' => 'success', 'message' => "Markierung 'Für mich wichtig' entfernt."];
                    } else {
                        $response = ['status' => 'error', 'message' => "Fehler beim Entfernen."];
                    }
                }
            }
        } elseif ($action === 'addComment' && $globalVerseId > 0) {
            // ... (Ihre bestehende Logik für addComment) ...
            $noteText = trim($_POST['note_text'] ?? '');
            if ($userId === 0) {
                $response = ['status' => 'error', 'message' => 'Bitte einloggen.'];
            } elseif (empty($noteText)) {
                $response = ['status' => 'error', 'message' => 'Kommentar darf nicht leer sein.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO user_notes (user_id, global_verse_id, note_text, is_public, note_date) VALUES (:uid, :gvid, :note, 1, NOW())");
                if ($stmt->execute([':uid' => $userId, ':gvid' => $globalVerseId, ':note' => $noteText])) {
                    $response = ['status' => 'success', 'message' => 'Kommentar gespeichert.'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Fehler beim Speichern des Kommentars.'];
                }
            }
        } elseif ($action === 'like_comment' && $noteId > 0) {
            if ($userId === 0) {
                $response = ['status' => 'error', 'message' => 'Bitte einloggen, um zu liken.'];
            } else {
                // Prüfen, ob der Benutzer den Kommentar bereits gelikt hat
                $stmtCheck = $pdo->prepare("SELECT like_id FROM comment_likes WHERE note_id = :note_id AND user_id = :user_id");
                $stmtCheck->execute([':note_id' => $noteId, ':user_id' => $userId]);

                if ($stmtCheck->fetch()) {
                    // Like entfernen (unlike)
                    $stmtUnlike = $pdo->prepare("DELETE FROM comment_likes WHERE note_id = :note_id AND user_id = :user_id");
                    if ($stmtUnlike->execute([':note_id' => $noteId, ':user_id' => $userId])) {
                        $response = ['status' => 'success', 'action_taken' => 'unliked'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Fehler beim Entfernen des Likes.'];
                    }
                } else {
                    // Like hinzufügen
                    $stmtLike = $pdo->prepare("INSERT INTO comment_likes (note_id, user_id, liked_at) VALUES (:note_id, :user_id, NOW())");
                    if ($stmtLike->execute([':note_id' => $noteId, ':user_id' => $userId])) {
                        $response = ['status' => 'success', 'action_taken' => 'liked'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Fehler beim Hinzufügen des Likes.'];
                    }
                }

                // Wenn die Aktion erfolgreich war, die neue Like-Anzahl abrufen
                if (isset($response['status']) && $response['status'] === 'success') {
                    $stmtCountLikes = $pdo->prepare("SELECT COUNT(like_id) as count FROM comment_likes WHERE note_id = :note_id");
                    $stmtCountLikes->execute([':note_id' => $noteId]);
                    $response['like_count'] = (int)$stmtCountLikes->fetchColumn();
                }
            }
        } elseif ($action === 'update_comment' && $noteId > 0) {
            // ... (Ihre bestehende Logik für update_comment) ...
            $newText = trim($_POST['note_text'] ?? '');
            if ($userId === 0) {
                $response = ['status' => 'error', 'message' => 'Bitte einloggen.'];
            } elseif (empty($newText)) {
                $response = ['status' => 'error', 'message' => 'Kommentartext darf nicht leer sein.'];
            } else {
                $stmtCheckAuthor = $pdo->prepare("SELECT user_id FROM user_notes WHERE note_id = :note_id");
                $stmtCheckAuthor->execute([':note_id' => $noteId]);
                $commentAuthor = $stmtCheckAuthor->fetch();
                if ($commentAuthor && $commentAuthor['user_id'] == $userId) {
                    $stmtUpdate = $pdo->prepare("UPDATE user_notes SET note_text = :text, updated_at = NOW() WHERE note_id = :note_id AND user_id = :user_id");
                    if ($stmtUpdate->execute([':text' => $newText, ':note_id' => $noteId, ':user_id' => $userId])) {
                        $response = ['status' => 'success', 'message' => 'Kommentar aktualisiert.'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Fehler beim Aktualisieren.'];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Nicht berechtigt.'];
                }
            }
        } elseif ($action === 'delete_comment' && $noteId > 0) {
            // ... (Ihre bestehende Logik für delete_comment) ...
            if ($userId === 0) {
                $response = ['status' => 'error', 'message' => 'Bitte einloggen.'];
            } else {
                $stmtCheckAuthor = $pdo->prepare("SELECT user_id FROM user_notes WHERE note_id = :note_id");
                $stmtCheckAuthor->execute([':note_id' => $noteId]);
                $commentAuthor = $stmtCheckAuthor->fetch();
                if ($commentAuthor && $commentAuthor['user_id'] == $userId) {
                    $pdo->beginTransaction();
                    try {
                        $stmtDeleteLikes = $pdo->prepare("DELETE FROM comment_likes WHERE note_id = :note_id");
                        $stmtDeleteLikes->execute([':note_id' => $noteId]);
                        $stmtDeleteNote = $pdo->prepare("DELETE FROM user_notes WHERE note_id = :note_id AND user_id = :user_id"); // Sicherstellen, dass nur der eigene Kommentar gelöscht wird
                        if ($stmtDeleteNote->execute([':note_id' => $noteId, ':user_id' => $userId])) {
                            if ($stmtDeleteNote->rowCount() > 0) { // Prüfen, ob wirklich was gelöscht wurde
                                $pdo->commit();
                                $response = ['status' => 'success', 'message' => 'Kommentar gelöscht.'];
                            } else {
                                $pdo->rollBack();
                                $response = ['status' => 'error', 'message' => 'Kommentar nicht gefunden oder nicht berechtigt.'];
                            }
                        } else {
                            $pdo->rollBack();
                            $response = ['status' => 'error', 'message' => 'Fehler beim Löschen des Kommentars.'];
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Nicht berechtigt.'];
                }
            }
        } else {
            // $response bleibt auf Standard-Fehlermeldung
        }
    } catch (PDOException $e) {
        error_log("AJAX PDOException in action '$action': " . $e->getMessage() . " SQL: " . ($stmt ?? null)?->queryString);
        $response = ['status' => 'error', 'message' => 'Datenbankfehler. Details wurden protokolliert.'];
    } catch (Exception $e) {
        error_log("AJAX Exception in action '$action': " . $e->getMessage());
        $response = ['status' => 'error', 'message' => 'Allgemeiner Fehler. Details wurden protokolliert.'];
    }
}

if (!headers_sent()) {
    if (!isset($response['status'])) {
        $response = ['status' => 'error', 'message' => 'Unbehandelte Aktion oder interner Fehler.'];
    }
    @header_remove('Content-Type');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
}
