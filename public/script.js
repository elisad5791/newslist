const like = document.querySelector('.js-like');

if (like) {
    like.addEventListener('click', async function(e) {
        const newsId = parseInt(e.target.closest('.js-like').getAttribute('data-item-id'));

        const response = await fetch('/add-like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ news_id: newsId })
        });
        
        const data = await response.json();
        console.log(data);
    });
}