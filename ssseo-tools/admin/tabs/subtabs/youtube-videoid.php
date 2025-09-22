<?php
// File: admin/tabs/subtabs/youtube-videoid.php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this tool.' );
}
?>

<h4 class="mb-4">Fetch Transcript for a YouTube Video (via OpenAI)</h4>

<div class="row mb-3">
    <label for="ssseo_video_id_input" class="col-sm-3 col-form-label">YouTube Video ID</label>
    <div class="col-sm-9">
        <input type="text" id="ssseo_video_id_input" class="form-control" placeholder="e.g., dQw4w9WgXcQ">
    </div>
</div>

<button class="btn btn-primary mb-4" id="ssseo-fetch-video-tools">Fetch Transcript</button>

<div id="ssseo-video-tools-result" class="border p-4 bg-light rounded" style="min-height:200px;">
    <em>Transcript and prompt will appear here…</em>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ssseo-fetch-video-tools').on('click', function(e) {
        e.preventDefault();
        const videoId = $('#ssseo_video_id_input').val().trim();
        const $result = $('#ssseo-video-tools-result');

        if (!videoId) {
            $result.html('<div class="text-danger">Please enter a valid YouTube Video ID.</div>');
            return;
        }

        $result.html('<p><em>Fetching transcript...</em></p>');

        $.post(ajaxurl, {
            action: 'ssseo_fetch_video_transcript',
            nonce: SSSEO_Transcript.nonce,
            video_id: videoId
        }, function(res) {
            if (res.success) {
                $result.html(
                    '<label><strong>Generated Transcript:</strong></label>' +
                    '<textarea readonly class="form-control mb-3" style="height: 220px;">' + res.data.output + '</textarea>' +
                    '<label><strong>Prompt Used:</strong></label>' +
                    '<textarea readonly class="form-control bg-light" style="height: 200px;">' + res.data.prompt + '</textarea>'
                );
            } else {
                $result.html('<div class="text-danger"><strong>Error:</strong> ' + (res.data || 'Unknown error') + '</div>');
            }
        }).fail(function() {
            $result.html('<div class="text-danger"><strong>Error:</strong> AJAX request failed.</div>');
        });
    });
});
</script>
