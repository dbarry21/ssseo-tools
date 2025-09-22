jQuery(function($){
  const $out     = $('#ssseo-youtube-output');
  const $spin    = $('#ssseo-youtube-spinner');
  const $log     = $('#ssseo-youtube-log');
  const $testOut = $('#ssseo-youtube-test-output');

  function showMsg($el, type, msg) {
    const color = type === 'ok' ? '#155724' : '#721c24';
    const bg    = type === 'ok' ? '#d4edda' : '#f8d7da';
    $el.html('<div style="padding:8px;border-radius:4px;background:'+bg+';color:'+color+';">'+msg+'</div>');
  }

  function ajaxPost(data, onDone) {
    $.ajax({
      url:  SSSEO_YT_Admin.ajax_url,
      type: 'POST',
      data: data,
      dataType: 'json'
    })
    .done(function(resp){
      if (resp && resp.success) onDone(true, resp.data);
      else onDone(false, resp && resp.data ? resp.data : { message: 'Unknown error' });
    })
    .fail(function(xhr){
      let msg = 'AJAX failed';
      if (xhr && xhr.responseText) {
        msg += ': ' + xhr.responseText.substring(0, 200);
      }
      onDone(false, { message: msg, status: xhr.status });
    });
  }

  // Bulk create drafts
  $('#ssseo-fetch-create-drafts').on('click', function(e){
    e.preventDefault();
    $spin.show(); $out.empty();

    ajaxPost({
      action:  'ssseo_generate_videos',
      nonce:   SSSEO_YT_Admin.nonce,
	  security: SSSEO_YT_Admin.nonce,
      channel: SSSEO_YT_Admin.channel
    }, function(ok, data){
      $spin.hide();
      if (ok) {
        const errs = (data.errors && data.errors.length) ? '<br><small>'+data.errors.join('<br>')+'</small>' : '';
        showMsg($out, 'ok', 'Created: <strong>'+data.new_posts+'</strong> — Skipped (exists): <strong>'+data.existing_posts+'</strong>'+errs);
        refreshLog();
      } else {
        showMsg($out, 'err', data.message || 'Error');
        refreshLog();
      }
    });
  });

  // Single test draft
  $('#ssseo-youtube-test-btn').on('click', function(e){
    e.preventDefault();
    const value = $('#ssseo-youtube-test-value').val().trim();
    if (!value) { showMsg($testOut, 'err', 'Please paste a URL or 11-char ID.'); return; }
    $testOut.empty();

    ajaxPost({
      action:  'ssseo_youtube_create_single',
      nonce:   SSSEO_YT_Admin.nonce,
      security: SSSEO_YT_Admin.nonce,
      value:   value
    }, function(ok, data){
      if (ok) {
        if (data.created) {
          showMsg($testOut, 'ok', 'Draft created (ID '+data.post_id+') — slug: <code>'+data.slug+'</code>');
        } else {
          showMsg($testOut, 'ok', 'Skipped: already exists — slug: <code>'+data.slug+'</code>');
        }
        refreshLog();
      } else {
        showMsg($testOut, 'err', data.message || 'Error');
        refreshLog();
      }
    });
  });

  // Debug toggle
  $('#ssseo-youtube-debug').on('change', function(){
    ajaxPost({
      action:  'ssseo_youtube_toggle_debug',
      nonce:   SSSEO_YT_Admin.nonce,
      security: SSSEO_YT_Admin.nonce,
      enabled: $(this).is(':checked') ? 1 : 0
    }, function(ok, data){
      if (!ok) alert(data.message || 'Could not toggle debug.');
      refreshLog();
    });
  });

  // Clear / Refresh log
  $('#ssseo-youtube-clear-log').on('click', function(e){
    e.preventDefault();
    ajaxPost({ action: 'ssseo_youtube_clear_log' }, function(){ refreshLog(); });
  });
  $('#ssseo-youtube-refresh-log').on('click', function(e){
    e.preventDefault();
    refreshLog();
  });

  function refreshLog(){
    ajaxPost({ action: 'ssseo_youtube_get_log' }, function(ok, data){
      if (ok) {
        const lines = (data.log || []).slice(-500);
        $log.text(lines.join('\n'));
      } else {
        $log.text(data.message || 'Log unavailable');
      }
    });
  }

  // Auto-load log on open
  refreshLog();
});
