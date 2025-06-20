/**
 * ssseo-generate-videos.js
 *
 * Binds a click handler directly to the “Generate Video Posts” button in the Customizer.
 * Sends an AJAX request to wp_ajax_ssseo_generate_videos.
 */
jQuery(function($) {
  // Whenever a button with our class is clicked…
  $(document).on('click', '.ssseo-generate-videos-btn', function(e) {
    e.preventDefault();

    var $button = $(this);
    var nonce   = $button.data('nonce');
    var $result = $button.siblings('.ssseo-generate-videos-result');

    // Disable the button so we don’t double-fire
    $button.attr('disabled', true).text('Generating…');
    $result.empty();

    // Grab the current channel ID from the Customizer setting
    var channelID = wp.customize.control('ssseo_youtube_channel_id').setting.get();

    // Kick off our AJAX call
    $.post(
      ssseoGenerateAjax.ajax_url,
      {
        action:  'ssseo_generate_videos',
        nonce:   nonce,
        channel: channelID
      }
    )
    .done(function(response) {
      if (response.success && response.data) {
        var data = response.data;
        var html = '<p>Created <strong>' + data.new_posts + '</strong> new draft posts. ' +
                   '<strong>' + data.existing_posts + '</strong> already existed.</p>';
        if (data.errors.length) {
          html += '<p><strong>Errors:</strong><br>' + data.errors.join('<br>') + '</p>';
        }
        $result.html(html);
      } else {
        $result.html('<span style="color:red;">Error: ' + (response.data.message || 'Unknown') + '</span>');
      }
    })
    .fail(function(jqXHR, textStatus) {
      $result.html('<span style="color:red;">AJAX failed: ' + textStatus + '</span>');
    })
    .always(function() {
      // Re-enable the button
      $button.removeAttr('disabled').text('Fetch & Create Drafts');
    });
  });
});
