document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.ssseo-generate-videos-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        const nonce = btn.dataset.nonce;
        const resultBox = document.querySelector('.ssseo-generate-videos-result');
        resultBox.textContent = 'Fetching videos...';

        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'ssseo_generate_videos',
                _ajax_nonce: nonce
            })
        })
        .then(res => res.json())
        .then(data => {
            resultBox.textContent = data.success ? 'Videos imported!' : (data.data || 'Import failed.');
        })
        .catch(() => {
            resultBox.textContent = 'Error during import.';
        });
    });
});
