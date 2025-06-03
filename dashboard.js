// dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    const newsFeedContainer = document.getElementById('newsFeedContainer');
    if (newsFeedContainer) {
        initializeNewsFeedLogic();
    }
});

function initializeNewsFeedLogic() {
    const newsFeedContainer = document.getElementById('newsFeedContainer');
    let newsCurrentPage = 1; // Seite 1 wird initial von PHP geladen
    const newsLoadingIndicator = document.getElementById('loadingIndicatorNews');
    let newsIsLoading = false;
    let newsIntersectionObserver = null;

    // Klick-Handler für .read-more-news ist nicht mehr nötig, da es normale Links sind.
    // Das Modal und handleShowFullNewsArticle() werden entfernt.

    // Intersection Observer für "Mehr laden"
    if (newsLoadingIndicator) {
        newsIntersectionObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !newsIsLoading) {
                loadMoreNewsArticles();
            }
        }, { threshold: 0.8 });
        newsIntersectionObserver.observe(newsLoadingIndicator);
    }

    function loadMoreNewsArticles() {
        if (newsIsLoading) return;
        newsIsLoading = true;
        if (newsLoadingIndicator) newsLoadingIndicator.style.display = 'block';
        newsCurrentPage++;

        fetch(`ajax_handler_news.php?action=get_more_news&page=${newsCurrentPage}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.articles_html && data.articles_html.trim() !== "") {
                        newsFeedContainer.insertAdjacentHTML('beforeend', data.articles_html);
                        if (newsLoadingIndicator) newsLoadingIndicator.style.display = 'none';
                    } else {
                        if (newsLoadingIndicator) {
                            newsLoadingIndicator.innerHTML = "Keine weiteren Beiträge.";
                            newsLoadingIndicator.style.display = 'block';
                            if (newsIntersectionObserver) {
                                newsIntersectionObserver.unobserve(newsLoadingIndicator);
                            }
                        }
                    }
                } else {
                     if (newsLoadingIndicator) {
                        newsLoadingIndicator.innerHTML = data.message || "Fehler beim Laden weiterer Beiträge.";
                        newsLoadingIndicator.style.display = 'block';
                     }
                }
                newsIsLoading = false;
            })
            .catch(error => {
                console.error('Fehler beim Laden weiterer Beiträge:', error);
                if (newsLoadingIndicator) {
                    newsLoadingIndicator.innerHTML = 'Ein Fehler ist beim Laden aufgetreten.';
                    newsLoadingIndicator.style.display = 'block';
                }
                newsIsLoading = false;
            });
    }
} // Ende initializeNewsFeedLogic