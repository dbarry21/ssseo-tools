jQuery(document).ready(function($) {
  // --- Constants ---
  const descSelBtn   = $('#ssseo_ai_selected_generate');
  const descTypeBtn  = $('#ssseo_ai_type_generate');
  const titleSelBtn  = $('#ssseo_ai_selected_generate_title');
  const titleTypeBtn = $('#ssseo_ai_type_generate_title');
  const typeSelect   = $('#ssseo_ai_pt_filter');
  const postSelect   = $('#ssseo_ai_post_id');
  const searchBox    = $('#ssseo_ai_post_search');
  const status       = $('#ssseo_ai_status');
  const results      = $('#ssseo_ai_results');
  const aboutStatus  = $('#ssseo-about-area-status');

  const ajaxUrl = typeof SSSEO_AI !== 'undefined' && SSSEO_AI.ajax_url ? SSSEO_AI.ajax_url : window.ajaxurl;
  const nonce   = typeof SSSEO_AI !== 'undefined' && SSSEO_AI.nonce ? SSSEO_AI.nonce : '';

  // --- Utility ---
  function logMessage(msg) {
    $('<div>').text(msg).appendTo(results);
    results.scrollTop(results[0].scrollHeight);
  }

  function disableAllButtons(state = true) {
    [descSelBtn, descTypeBtn, titleSelBtn, titleTypeBtn].forEach(btn => btn.prop('disabled', state));
  }

  // --- Post Loading ---
  function loadPostsForType(postType) {
    const arr = window.ssseoPostsByType?.[postType] || [];
    postSelect.empty();
    arr.forEach(item => {
      $('<option>').val(item.id).text(`${item.title} (ID: ${item.id})`).appendTo(postSelect);
    });
  }

  function loadPostsFromAjax(postType) {
    postSelect.prop('disabled', true).html('<option>Loading…</option>');
    $.post(ajaxUrl, { action: 'ssseo_get_posts_by_type', post_type: postType }, function(res) {
      postSelect.empty();
      if (res.success && Array.isArray(res.data)) {
        res.data.forEach(post => {
          postSelect.append(`<option value="${post.id}">${post.title}</option>`);
        });
      } else {
        postSelect.append('<option disabled>Error loading posts</option>');
      }
      postSelect.prop('disabled', false);
    });
  }

  // --- Filter Options ---
  searchBox.on('keyup', function() {
    const q = $(this).val().toLowerCase();
    $('#ssseo_ai_post_id option').each(function() {
      $(this).toggle($(this).text().toLowerCase().includes(q));
    });
  });

  // --- Meta/Title Generation Loop ---
  function processPostIDs(ids, postType, mode) {
    let index = 0, successCount = 0;
    const label = mode === 'description' ? 'Meta' : 'Title';
    const generateAction = mode === 'description' ? 'ssseo_ai_generate_meta' : 'ssseo_ai_generate_title';
    const saveAction     = mode === 'description' ? 'ssseo_ai_save_meta'     : 'ssseo_ai_save_title';

    function processNext() {
      if (index >= ids.length) {
        logMessage(`✅ Updated ${successCount} ${mode}s.`);
        status.text(`Finished ${successCount} of ${ids.length} ${mode}s.`);
        disableAllButtons(false);
        return;
      }

      const postID = ids[index];
      status.text(`Generating ${label} for [${postType}] ID ${postID} (${index + 1}/${ids.length})`);
      logMessage(`→ [${postType}] #${postID} (${label}): requesting ChatGPT…`);

      $.post(ajaxUrl, { action: generateAction, nonce, post_id: postID }, function(resp) {
        if (resp.success && resp.data.generated) {
          const text = resp.data.generated;
          logMessage(`← [${postType}] #${postID} (${label}): Generated → "${text}"`);

          $.post(ajaxUrl, { action: saveAction, nonce, post_id: postID, text }, function(saveResp) {
            if (saveResp.success) {
              successCount++;
              logMessage(`✔ [${postType}] #${postID} (${label}): Saved.`);
            } else {
              logMessage(`✘ Save error: ${saveResp.data}`);
            }
            index++; processNext();
          }).fail(() => {
            logMessage(`✘ Save AJAX failure for #${postID}`);
            index++; processNext();
          });

        } else {
          logMessage(`✘ Generate error for #${postID}: ${resp.data || 'Unknown error'}`);
          index++; processNext();
        }
      }).fail(() => {
        logMessage(`✘ Generate AJAX failure for #${postID}`);
        index++; processNext();
      });
    }

    processNext();
  }

  // --- About the Area (Batch) ---
$('#ssseo-generate-multiple-about').on('click', function (e) {
  e.preventDefault();

  const selected = postSelect.val();
  if (!selected || selected.length === 0) return alert('Please select one or more posts.');

  const $button = $(this);
  const $spinner = $('#ssseo-about-spinner');
  let count = 0;

  // Show spinner and disable button
  $spinner.removeClass('d-none');
  $button.prop('disabled', true);

  function processNext(index) {
    if (index >= selected.length) {
      $spinner.addClass('d-none');
      $button.prop('disabled', false);
      aboutStatus.prepend(`<div class="alert alert-success">✅ All done. ${count} areas processed.</div>`);
      return;
    }

    const postId = selected[index];

    $.post(ajaxUrl, { action: 'ssseo_get_city_state', post_id: postId, nonce }, function (res1) {
      const areaName = res1.success && res1.data ? res1.data : $(`#ssseo_ai_post_id option[value="${postId}"]`).text();

      $.post(ajaxUrl, {
        action: 'ssseo_ai_generate_about_area',
        post_id: postId,
        area_name: areaName,
        nonce
      }, function (response) {
        count++;

        if (response.success) {
          const title = $(`#ssseo_ai_post_id option[value="${postId}"]`).text();
          aboutStatus.html(`
            <div class="mb-4 p-3 border rounded bg-light">
              <div><strong>Post ID:</strong> ${postId}</div>
              <div><strong>Title:</strong> ${title}</div>
              <div class="mt-2">${response.data.generated}</div>
              <div class="text-muted mt-3">Processed ${count} of ${selected.length}</div>
            </div>
          `);
          processNext(index + 1);
        } else {
          aboutStatus.html(`<div class="alert alert-danger">Error generating for post ID ${postId}: ${response.data}</div>`);
          $spinner.addClass('d-none');
          $button.prop('disabled', false);
        }
      });
    });
  }

  processNext(0);
});


  // --- Button Actions ---
  descSelBtn.on('click', function(e) {
    e.preventDefault();
    const selected = postSelect.val() || [];
    if (selected.length === 0) return status.text('Please select at least one post.');
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(selected, typeSelect.val(), 'description');
  });

  descTypeBtn.on('click', function(e) {
    e.preventDefault();
    const arr = ssseoPostsByType[typeSelect.val()] || [];
    const allIDs = arr.map(obj => obj.id);
    if (allIDs.length === 0) return status.text(`No published posts for ${typeSelect.val()}`);
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(allIDs, typeSelect.val(), 'description');
  });

  titleSelBtn.on('click', function(e) {
    e.preventDefault();
    const selected = postSelect.val() || [];
    if (selected.length === 0) return status.text('Please select at least one post.');
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(selected, typeSelect.val(), 'title');
  });

  titleTypeBtn.on('click', function(e) {
    e.preventDefault();
    const arr = ssseoPostsByType[typeSelect.val()] || [];
    const allIDs = arr.map(obj => obj.id);
    if (allIDs.length === 0) return status.text(`No published posts for ${typeSelect.val()}`);
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(allIDs, typeSelect.val(), 'title');
  });

  // --- Init ---
  const defaultType = window.ssseoDefaultType || 'post';
  loadPostsForType(defaultType);
  typeSelect.val(defaultType);

  typeSelect.on('change', function() {
    loadPostsForType($(this).val());
    searchBox.val('');
    results.empty();
    status.text('');
  });
});
