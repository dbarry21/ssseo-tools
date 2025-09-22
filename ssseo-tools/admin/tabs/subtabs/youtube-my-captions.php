<?php
if (!current_user_can('manage_options')) wp_die('Unauthorized');
?>

<h4 class="mb-3">My YouTube Captions (via OAuth)</h4>

<div class="mb-3 row">
  <label class="col-sm-3 col-form-label">YouTube Video ID</label>
  <div class="col-sm-9">
    <input type="text" id="ssseo_video_id_for_captions" class="form-control" placeholder="e.g., abc123xyz">
  </div>
</div>

<button class="btn btn-primary mb-3" id="ssseo-list-captions">List Caption Tracks</button>

<div id="ssseo-captions-list" class="mb-4 border p-3 bg-light rounded" style="min-height:150px;">
  <em>Caption track info will appear here...</em>
</div>

<div id="ssseo-caption-text" class="border p-3 bg-light rounded" style="white-space: pre-wrap;"></div>

<script>
jQuery(document).ready(function($) {
  $('#ssseo-list-captions').on('click', function(e) {
    e.preventDefault();

    const videoId = $('#ssseo_video_id_for_captions').val().trim();
    const $list = $('#ssseo-captions-list');
    const $text = $('#ssseo-caption-text');

    if (!videoId) return alert('Please enter a video ID.');

    $list.html('<p><em>Fetching caption tracks...</em></p>');
    $text.empty();

    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_youtube_list_captions',
      _wpnonce: ssseo_admin.nonce,
      video_id: videoId
    }, function(res) {
      if (res.success && res.data.length > 0) {
        let html = '<strong>Available Caption Tracks:</strong><ul>';
        res.data.forEach(track => {
          html += `<li>
            [${track.language}] ${track.name} (${track.kind})
            <button class="btn btn-sm btn-outline-primary ms-2 ssseo-download-caption" data-id="${track.id}">Download</button>
          </li>`;
        });
        html += '</ul>';
        $list.html(html);
      } else {
        $list.html('<div class="text-danger">❌ No captions found or error occurred.</div>');
      }
    });
  });

  $(document).on('click', '.ssseo-download-caption', function(e) {
    e.preventDefault();
    const captionId = $(this).data('id');
    const $text = $('#ssseo-caption-text');
    $text.html('<p><em>Downloading caption text...</em></p>');

    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_youtube_download_caption',
      _wpnonce: ssseo_admin.nonce,
      caption_id: captionId
    }, function(res) {
      if (res.success) {
        $text.html(`<pre>${res.data}</pre>`);
      } else {
        $text.html('<div class="text-danger">❌ Error: ' + (res.data || 'Failed to retrieve caption text.') + '</div>');
      }
    });
  });
});
</script>
