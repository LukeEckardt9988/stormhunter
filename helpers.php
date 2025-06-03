<?php
// helpers.php

if (!function_exists('get_article_preview_elements')) {
    function get_article_preview_elements($pdo_conn, $article_id_for_preview, $article_title_for_alt_tag) {
        $stmtBlocks = $pdo_conn->prepare("SELECT block_type, content FROM news_article_blocks WHERE article_id = :aid ORDER BY sort_order ASC");
        $stmtBlocks->execute([':aid' => $article_id_for_preview]);
        $blocks = $stmtBlocks->fetchAll(PDO::FETCH_ASSOC);

        $preview_image_html = '';
        $preview_text_aggregate = '';
        $max_text_length = 180;
        $first_image_found = false;
        $content_definitely_extends_beyond_preview = false;
        $total_text_from_text_blocks = '';

        foreach ($blocks as $block_data) {
            if ($block_data['block_type'] === 'image' && !$first_image_found) {
                $preview_image_html = '<img src="' . htmlspecialchars($block_data['content']) . '" class="card-img-top" alt="' . htmlspecialchars($article_title_for_alt_tag) . '" style="max-height: 220px; object-fit: cover;">';
                $first_image_found = true;
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
            } elseif ($block_data['block_type'] !== 'image') {
                $content_definitely_extends_beyond_preview = true;
            }
        }

        $final_preview_text_html = nl2br(trim($preview_text_aggregate));

        // Entscheidung für "Weiterlesen"-Link
        $show_read_more = false;
        if ($content_definitely_extends_beyond_preview) {
            $show_read_more = true;
        } elseif (count($blocks) > 1) { // Mehr als ein Block insgesamt
            $show_read_more = true;
        } elseif ($first_image_found && empty(trim($final_preview_text_html))) { // Nur Bild, kein Text
             $show_read_more = true;
        } elseif ($first_image_found && !empty(trim($final_preview_text_html))) { // Bild und Text
             $show_read_more = true;
        } else if(count($blocks) == 1 && $blocks[0]['block_type'] == 'text' && str_ends_with(trim($preview_text_aggregate), "...")) {
            // Ein Textblock, der aber gekürzt wurde
            $show_read_more = true;
        }


        // Verhindere "Weiterlesen", wenn der *gesamte* Text des Artikels (aus allen Textblöcken) in die Vorschau gepasst hat
        // UND es keine anderen Blöcke (Bilder etc.) gibt.
        if (!$first_image_found && count($blocks) > 0) {
            $all_text_blocks_only = true;
            foreach($blocks as $b) { if ($b['block_type'] !== 'text') { $all_text_blocks_only = false; break; } }

            if ($all_text_blocks_only && !str_ends_with(trim($preview_text_aggregate), "...")) {
                 // Prüfen, ob der $preview_text_aggregate dem $total_text_from_text_blocks entspricht (gekürzt um Leerzeichen)
                 if(rtrim(htmlspecialchars(trim($total_text_from_text_blocks))) == rtrim($preview_text_aggregate)) {
                    $show_read_more = false;
                 }
            }
        }


        if ($show_read_more) {
            // Link zur neuen Detailseite
            $read_more_link = ' <a href="news_article.php?article_id=' . $article_id_for_preview . '" class="read-more-news stretched-link">Weiterlesen</a>';
            if (empty(trim($final_preview_text_html)) && $first_image_found) {
                $final_preview_text_html = $read_more_link;
            } else {
                $final_preview_text_html .= $read_more_link;
            }
        }

        return [
            'image_html' => $preview_image_html,
            'text_html' => $final_preview_text_html
        ];
    }
}

if (!function_exists('render_article_full_content')) {
    function render_article_full_content($pdo_conn, $article_id_for_full_view) {
        $stmtBlocks = $pdo_conn->prepare("SELECT block_type, content FROM news_article_blocks WHERE article_id = :aid ORDER BY sort_order ASC");
        $stmtBlocks->execute([':aid' => $article_id_for_full_view]);
        $blocks = $stmtBlocks->fetchAll(PDO::FETCH_ASSOC);
        
        $html_output = '';
        if (empty($blocks)) {
            return '<p class="text-muted">Für diesen Artikel wurde kein Inhalt gefunden.</p>';
        }

        foreach ($blocks as $block) {
            $html_output .= '<div class="news-block mb-4">'; // Gemeinsamer Wrapper für jeden Block
            switch ($block['block_type']) {
                case 'text':
                    $html_output .= '<div class="news-block-text">' . nl2br(htmlspecialchars($block['content'])) . '</div>';
                    break;
                case 'image':
                    $html_output .= '<div class="news-block-image text-center"><img src="' . htmlspecialchars($block['content']) . '" class="img-fluid rounded shadow-sm" alt="Artikelbild" style="max-height: 500px; object-fit: contain;"></div>';
                    break;
                case 'audio_mp3':
                    $html_output .= '<div class="news-block-audio text-center">';
                    $html_output .= '<h5>Audiodatei</h5><audio controls src="' . htmlspecialchars($block['content']) . '" class="w-100" style="max-width: 500px;"><a href="'.htmlspecialchars($block['content']).'">Audiodatei herunterladen</a></audio>';
                    $html_output .= '</div>';
                    break;
                case 'video_embed':
                    $html_output .= '<div class="news-block-video-embed ratio ratio-16x9" style="max-width: 720px; margin:auto;"><iframe src="https://www.youtube-nocookie.com/embed/' . htmlspecialchars($block['content']) . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>';
                    break;
                case 'external_link':
                    $html_output .= '<div class="news-block-external-link text-center"><a href="'.htmlspecialchars($block['content']).'" target="_blank" rel="noopener noreferrer" class="btn btn-lg btn-primary"><i class="bi bi-box-arrow-up-right"></i> Externen Inhalt öffnen</a></div>';
                    break;
            }
            $html_output .= '</div>'; 
        }
        return $html_output;
    }
}
?>