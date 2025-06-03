// dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    const newsFeedContainer = document.getElementById('newsFeedContainer');
    if (newsFeedContainer) {
        initializeNewsFeedLogic();
    }
});

function initializeNewsFeedLogic() {
    const newsFeedContainer = document.getElementById('newsFeedContainer');
    let newsCurrentPage = 1; // Seite 1 ist initial geladen, nächste Anfrage ist für Seite 2
    const newsLoadingIndicator = document.getElementById('loadingIndicatorNews');
    let newsIsLoading = false;

    // Event-Delegation für Klicks im NewsFeedContainer (nur noch für "Weiterlesen")
    newsFeedContainer.addEventListener('click', function(event) {
        const readMoreLink = event.target.closest('.read-more-news');
        
        if (readMoreLink) {
            event.preventDefault();
            const articleId = readMoreLink.dataset.articleId;
            handleShowFullNewsArticle(articleId);
        }
    });
    
    if (newsLoadingIndicator) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !newsIsLoading) {
                loadMoreNewsArticles();
            }
        }, { threshold: 0.8 });
        observer.observe(newsLoadingIndicator);
    }

    function handleShowFullNewsArticle(articleId) {
        const modalElement = document.getElementById('newsArticleModal');
        const modalTitleEl = document.getElementById('newsArticleModalLabel');
        const modalBodyEl = document.getElementById('newsArticleModalBody');
        if (!modalElement || !modalTitleEl || !modalBodyEl) return;

        const newsModal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modalTitleEl.textContent = 'Lade Beitrag...';
        modalBodyEl.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Lade...</span></div></div>';
        newsModal.show();

        fetch(`ajax_handler_news.php?action=get_news_article_content&article_id=${articleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    modalTitleEl.textContent = htmlspecialchars(data.title); // htmlspecialchars muss global sein
                    modalBodyEl.innerHTML = data.html_content;
                } else {
                    modalTitleEl.textContent = 'Fehler';
                    modalBodyEl.innerHTML = `<p class="text-danger">${data.message || 'Fehler beim Laden des Beitrags.'}</p>`;
                }
            }).catch(error => {
                console.error('Fehler beim Laden des Artikelinhalts:', error);
                modalTitleEl.textContent = 'Fehler';
                modalBodyEl.innerHTML = '<p class="text-danger">Kommunikationsfehler.</p>';
            });
    }
    
    function loadMoreNewsArticles() {
        if (newsIsLoading) return;
        newsIsLoading = true;
        if(newsLoadingIndicator) newsLoadingIndicator.style.display = 'block';
        newsCurrentPage++; // Nächste Seite anfordern

        fetch(`ajax_handler_news.php?action=get_more_news&page=${newsCurrentPage}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.articles_html && data.articles_html.trim() !== "") {
                        newsFeedContainer.insertAdjacentHTML('beforeend', data.articles_html);
                        if(newsLoadingIndicator) newsLoadingIndicator.style.display = 'none';
                    } else {
                        if(newsLoadingIndicator) {
                            newsLoadingIndicator.innerHTML = "Keine weiteren Beiträge.";
                            newsLoadingIndicator.style.display = 'block'; 
                        }
                        // Intersection Observer stoppen, da es nichts mehr zu laden gibt
                        const observerElement = newsLoadingIndicator; // Das Element, das beobachtet wird
                        if(observerElement && typeof observer !== 'undefined' && observer){ // Prüfen ob observer existiert
                            observer.unobserve(observerElement);
                        }
                    }
                } else {
                     if(newsLoadingIndicator) {
                        newsLoadingIndicator.innerHTML = data.message || "Fehler beim Laden.";
                        newsLoadingIndicator.style.display = 'block';
                     }
                }
                newsIsLoading = false;
            })
            .catch(error => {
                console.error('Fehler beim Laden weiterer Beiträge:', error);
                if(newsLoadingIndicator) {
                    newsLoadingIndicator.innerHTML = 'Fehler beim Laden.';
                    newsLoadingIndicator.style.display = 'block';
                }
                newsIsLoading = false;
            });
    }
} // Ende initializeNewsFeedLogic

// Globale Hilfsfunktionen, falls sie nicht schon in app.js sind
// und app.js nicht VOR dashboard.js geladen wird.
// Sicherstellen, dass diese verfügbar sind:
// function htmlspecialchars(str) { ... }
// function nl2br(str) { ... }