<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can('manage_options') ) {
  wp_die('Unauthorized');
}
?>

<h4 class="mb-3">Fetch Captions via YouTube</h4>

<div class="mb-3 row">
  <label class="col-sm-3 col-form-label">YouTube Video ID</label>
  <div class="col-sm-9">
    <input type="text" id="ssseo_caption_video_id" class="form-control" placeholder="e.g., dQw4w9WgXcQ">
  </div>
</div>

<button class="btn btn-primary mb-3" id="ssseo-fetch-captions">Fetch Captions</button>

<div id="ssseo-caption-result" class="border p-3 bg-light rounded" style="min-height:200px;">
  <em>Results will appear here...</em>
</div>

<script>
jQuery(document).ready(function ($) {
  $('#ssseo-fetch-captions').on('click', function (e) {
    e.preventDefault();

    const videoId = $('#ssseo_caption_video_id').val().trim();
    const $output = $('#ssseo-caption-result');

    if (!videoId) {
      $output.html('<div class="text-danger">Please enter a valid YouTube video ID.</div>');
      return;
    }

    $output.html('<p><em>Fetching captions…</em></p>');

    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_get_youtube_captions',
      video_id: videoId,
      _wpnonce: ssseo_admin.nonce
    }, function (res) {
      if (res.success) {
        $output.html('<pre style="white-space:pre-wrap;">' + res.data + '</pre>');
      } else {
        $output.html('<div class="text-danger">❌ ' + (res.data || 'Unable to fetch captions.') + '</div>');
      }
    }).fail(function () {
      $output.html('<div class="text-danger">❌ AJAX request failed.</div>');
    });
  });
});
</script>
