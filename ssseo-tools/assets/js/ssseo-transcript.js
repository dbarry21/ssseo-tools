jQuery(document).ready(function ($) {
    // Generate Transcript
    $('#ssseo-generate-transcript').on('click', function () {
        const postId = $(this).data('post-id');
        const $result = $('#ssseo-transcript-result');
        $result.html('<p><em>Generating transcript...</em></p>');

        $.ajax({
            url: SSSEO_Transcript.ajax_url,
            method: 'POST',
            data: {
                action: 'ssseo_generate_transcript',
                post_id: postId,
                nonce: SSSEO_Transcript.nonce,
            },
            success: function (res) {
                if (res.success) {
                    $result.html(
                        '<strong>Transcript Preview:</strong><textarea readonly style="width:100%;height:200px;">' +
                        res.data +
                        '</textarea>'
                    );
                } else {
                    $result.html('<p><strong>Error:</strong> ' + res.data + '</p>');
                }
            },
            error: function () {
                $result.html('<p><strong>Error:</strong> AJAX failed.</p>');
            }
        });
    });

    // Fetch Captions
    $('#ssseo-fetch-captions').on('click', function () {
        const postId = $(this).data('post-id');
        const $result = $('#ssseo-captions-result');
        $result.html('<p><em>Fetching captions…</em></p>');

        $.ajax({
            url: SSSEO_Transcript.ajax_url,
            method: 'POST',
            data: {
                action: 'ssseo_fetch_captions',
                post_id: postId,
                nonce: SSSEO_Transcript.nonce,
            },
            success: function (res) {
                if (res.success) {
                    $result.html(
                        '<strong>Captions:</strong><textarea readonly style="width:100%;height:150px;">' +
                        res.data +
                        '</textarea>'
                    );
                } else {
                    $result.html('<p><strong>Error:</strong> ' + res.data + '</p>');
                }
            },
            error: function () {
                $result.html('<p><strong>Error:</strong> AJAX failed.</p>');
            }
        });
    });

    // Fetch AI Captions
    $('#ssseo-fetch-ai-captions').on('click', function () {
        const postId = $(this).data('post-id');
        const $result = $('#ssseo-captions-result');
        $result.html('<p><em>Generating AI captions...</em></p>');

        $.ajax({
            url: SSSEO_Transcript.ajax_url,
            method: 'POST',
            data: {
                action: 'ssseo_fetch_ai_captions',
                post_id: postId,
                nonce: SSSEO_Transcript.nonce,
            },
            success: function (res) {
                if (res.success) {
                    $result.html(
                        '<strong>Captions:</strong><textarea readonly style="width:100%;height:150px;">' +
                        res.data +
                        '</textarea>'
                    );
                } else {
                    $result.html('<p><strong>Error:</strong> ' + res.data + '</p>');
                }
            },
            error: function () {
                $result.html('<p><strong>Error:</strong> AJAX failed.</p>');
            }
        });
    });
});
