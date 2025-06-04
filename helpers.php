<?php
// helpers.php

if (!function_exists('get_article_preview_elements')) {
    function get_article_preview_elements($pdo_conn, $article_id_for_preview, $article_title_for_alt_tag) {
        $stmtBlocks = $pdo_conn->prepare("SELECT block_id, block_type, content FROM news_article_blocks WHERE article_id = :aid ORDER BY sort_order ASC");
        $stmtBlocks->execute([':aid' => $article_id_for_preview]);
        $blocks = $stmtBlocks->fetchAll(PDO::FETCH_ASSOC);

        $preview_image_html = '';
        $preview_video_embed_html = ''; // NEU: Für YouTube-Vorschau
        $preview_text_aggregate = '';
        $max_text_length = 120; // Ggf. etwas kürzer, wenn Video angezeigt wird
        $first_image_found = false;
        $first_video_found = false;
        $content_definitely_extends_beyond_preview = false;
        $total_text_from_text_blocks = '';
        $block_count = count($blocks);

        foreach ($blocks as $block_data) {
            if ($block_data['block_type'] === 'image' && !$first_image_found && !$first_video_found) { // Video hat Vorrang, falls beides früh kommt
                $preview_image_html = '<img src="' . htmlspecialchars($block_data['content']) . '" class="card-img-top" alt="' . htmlspecialchars($article_title_for_alt_tag) . '" style="max-height: 220px; object-fit: cover;">';
                $first_image_found = true;
            } elseif ($block_data['block_type'] === 'video_embed' && !$first_video_found) {
                // Erzeuge HTML für eingebettetes Video als Vorschau
                $preview_video_embed_html = '<div class="card-img-top ratio ratio-16x9"><iframe src="https://www.youtube-nocookie.com/embed/' . htmlspecialchars($block_data['content']) . '" title="' . htmlspecialchars($article_title_for_alt_tag) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>';
                $first_video_found = true;
                $first_image_found = true; // Verhindert, dass danach noch ein Bild als card-img-top genommen wird
            }

            if ($block_data['block_type'] === 'text') {
                $current_text_block_content = strip_tags($block_data['content']);
                $total_text_from_text_blocks .= $current_text_block_content . " ";

                if (mb_strlen($preview_text_aggregate) < $max_text_length) {
                    $needed_chars = $max_text_length - mb_strlen($preview_text_aggregate);
                    if (mb_strlen($current_text_block_content) > $needed_chars) {
                        $substring = mb_substr($current_text_block_content, 0, $needed_chars);
                        $last_space = mb_strrpos($substring, ' ');
                        if ($last_space !== false) {
                            $preview_text_aggregate .= htmlspecialchars(mb_substr($substring, 0, $last_space)) . "...";
                        } else {
                            $preview_text_aggregate .= htmlspecialchars($substring) . "...";
                        }
                        $content_definitely_extends_beyond_preview = true;
                    } else {
                        $preview_text_aggregate .= htmlspecialchars($current_text_block_content) . " ";
                    }
                } else {
                    $content_definitely_extends_beyond_preview = true;
                }
            } elseif ($block_data['block_type'] !== 'image' && $block_data['block_type'] !== 'video_embed') {
                $content_definitely_extends_beyond_preview = true;
            }
        }
        
        $final_preview_text_html = nl2br(trim($preview_text_aggregate));

        $show_read_more = false;
        if ($content_definitely_extends_beyond_preview) {
            $show_read_more = true;
        } elseif ($block_count > 1) {
            $show_read_more = true;
        } elseif (($first_image_found || $first_video_found) && empty(trim($final_preview_text_html)) && $block_count == 1) {
            // Wenn nur ein Bild/Video Block da ist, braucht es kein "Weiterlesen", da es voll angezeigt wird (Video im Dashboard, Bild in Detailansicht)
            // Aber für das Dashboard-Video wollen wir vielleicht doch einen Link zur Detailseite, falls es Text dazu gibt, der nicht im Dashboard gezeigt wird
            // Wenn *nur* ein Video/Bild Block da ist, ist der Link zur Detailseite optional.
            // Wenn aber Text gekürzt wurde ODER es MEHRERE Blöcke gibt, dann "Weiterlesen".
            if ($first_video_found && $block_count == 1) $show_read_more = false; // Video wird direkt im Dashboard angezeigt, kein Weiterlesen nötig wenn es das einzige ist.
            else $show_read_more = true;
        } elseif (($first_image_found || $first_video_found) && !empty(trim($final_preview_text_html))) {
             $show_read_more = true;
        } else if ($block_count == 1 && isset($blocks[0]) && $blocks[0]['block_type'] == 'text' && str_ends_with(trim($preview_text_aggregate), "...")) {
            $show_read_more = true;
        }

        if ($block_count == 1 && isset($blocks[0])) {
             if ($blocks[0]['block_type'] == 'text' && !str_ends_with(trim($preview_text_aggregate), "...")) {
                $show_read_more = false;
             }
             if ($blocks[0]['block_type'] == 'video_embed') { // Ein einzelner Videoblock
                $show_read_more = false; // Das Video wird ja direkt angezeigt
             }
             if ($blocks[0]['block_type'] == 'image') { // Ein einzelner Bildblock
                // Bild wird im Dashboard angezeigt, Link zur Detailseite, um es ggf. größer zu sehen oder falls es mal Kommentare gäbe
                $show_read_more = true;
             }
        }


        $read_more_link_html = '';
        if ($show_read_more) {
            $read_more_link_html = ' <a href="news_article.php?article_id=' . $article_id_for_preview . '" class="read-more-news stretched-link">Weiterlesen</a>';
        }
        
        // Logik für die Anzeige:
        // 1. Wenn ein Video gefunden wurde, zeige das Video und ggf. kurzen Text + Weiterlesen
        // 2. Sonst, wenn ein Bild gefunden wurde, zeige das Bild und ggf. kurzen Text + Weiterlesen
        // 3. Sonst, zeige nur Text + Weiterlesen

        $final_media_html = '';
        if ($first_video_found) {
            $final_media_html = $preview_video_embed_html;
            // Wenn Video angezeigt wird, ist der Text darunter optional, aber der Weiterlesen-Link kann trotzdem sinnvoll sein,
            // falls es noch andere Blöcke in der Vollansicht gibt.
            if (!empty(trim($final_preview_text_html))) {
                 $final_preview_text_html .= $read_more_link_html;
            } elseif ($show_read_more) { // Auch "Weiterlesen" wenn kein Text da ist, aber andere Blöcke existieren
                 $final_preview_text_html = $read_more_link_html;
            }

        } elseif ($first_image_found) {
            $final_media_html = $preview_image_html;
            if (!empty(trim($final_preview_text_html))) {
                $final_preview_text_html .= $read_more_link_html;
            } elseif($show_read_more) {
                $final_preview_text_html = $read_more_link_html;
            }
        } else { // Nur Text
            if (!empty(trim($final_preview_text_html))) {
                 if ($show_read_more && !str_contains($final_preview_text_html, 'read-more-news')) {
                    $final_preview_text_html .= $read_more_link_html;
                 }
            } elseif ($show_read_more) { // Falls gar kein Text, aber andere Blöcke (z.B. Audio, Link)
                 $final_preview_text_html = $read_more_link_html;
            }
        }


        return [
            'media_html' => $final_media_html, // Kann Bild oder Video sein
            'text_html' => $final_preview_text_html
        ];
    }
}

if (!function_exists('render_article_full_content')) {
    /**
     * Rendert alle Inhaltsblöcke eines Artikels als HTML für die Vollansicht.
     *
     * @param PDO $pdo_conn Die PDO-Datenbankverbindung.
     * @param int $article_id_for_full_view Die ID des Artikels, dessen Blöcke gerendert werden sollen.
     * @return string Der gerenderte HTML-Code für alle Blöcke.
     */
    function render_article_full_content($pdo_conn, $article_id_for_full_view) {
        $stmtBlocks = $pdo_conn->prepare("SELECT block_type, content FROM news_article_blocks WHERE article_id = :aid ORDER BY sort_order ASC");
        $stmtBlocks->execute([':aid' => $article_id_for_full_view]);
        $blocks = $stmtBlocks->fetchAll(PDO::FETCH_ASSOC);
        
        $html_output = ''; // Initialisiert den HTML-String

        if (empty($blocks)) {
            return '<p class="text-muted text-center my-5">Für diesen Artikel wurde kein spezifischer Inhalt gefunden.</p>';
        }

        foreach ($blocks as $block) {
            // Gemeinsamer Wrapper für jeden Block mit etwas Abstand
            $html_output .= '<div class="news-block mb-4">'; 
            
            switch ($block['block_type']) {
                case 'text':
                    $html_output .= '<div class="news-block-text">' . nl2br(htmlspecialchars($block['content'])) . '</div>';
                    break;
                case 'image':
                    $html_output .= '<div class="news-block-image text-center">';
                    $html_output .= '<img src="' . htmlspecialchars($block['content']) . '" class="img-fluid rounded shadow-sm" alt="Artikelbild" style="max-height: 500px; object-fit: contain; margin: auto;">';
                    $html_output .= '</div>';
                    break;
                case 'audio_mp3':
                    $html_output .= '<div class="news-block-audio text-center">';
                    $html_output .= '<h5 class="mt-3 mb-2">Audiodatei</h5>';
                    $html_output .= '<audio controls src="' . htmlspecialchars($block['content']) . '" class="w-100" style="max-width: 500px; margin: auto;">';
                    $html_output .= 'Ihr Browser unterstützt das Audio-Element nicht. <a href="'.htmlspecialchars($block['content']).'">Audiodatei herunterladen</a>';
                    $html_output .= '</audio>';
                    $html_output .= '</div>';
                    break;
                case 'video_embed':
                    // Stellt sicher, dass es sich um eine gültige YouTube-Video-ID handelt (einfache Prüfung)
                    $video_id = htmlspecialchars($block['content']);
                    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $video_id)) {
                        $html_output .= '<div class="news-block-video-embed ratio ratio-16x9" style="max-width: 720px; margin:auto; background-color: #000;">';
                        $html_output .= '<iframe src="https://www.youtube-nocookie.com/embed/' . $video_id . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
                        $html_output .= '</div>';
                    } else {
                        $html_output .= '<p class="text-danger text-center">Ungültige YouTube Video ID angegeben.</p>';
                    }
                    break;
                case 'external_link':
                    $link_url = htmlspecialchars($block['content']);
                    // Einfache URL-Validierung
                    if (filter_var($link_url, FILTER_VALIDATE_URL)) {
                        $html_output .= '<div class="news-block-external-link text-center mt-3">';
                        $html_output .= '<a href="'.$link_url.'" target="_blank" rel="noopener noreferrer" class="btn btn-lg btn-primary">';
                        $html_output .= '<i class="bi bi-box-arrow-up-right"></i> Externen Inhalt öffnen';
                        $html_output .= '</a>';
                        $html_output .= '</div>';
                    } else {
                        $html_output .= '<p class="text-danger text-center">Ungültige URL für externen Link angegeben.</p>';
                    }
                    break;
                default:
                    // Unbekannter Blocktyp könnte hier protokolliert oder ignoriert werden
                    $html_output .= '<p class="text-warning text-center">Unbekannter Inhaltsblock.</p>';
                    break;
            }
            $html_output .= '</div>'; // Schließt den .news-block Wrapper
        }
        return $html_output;
    }
}
?>