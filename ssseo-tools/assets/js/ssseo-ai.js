jQuery(document).ready(function ($) {
  // --- Safety Check: Only run on AI tab ---
  if (typeof SSSEO_AI === 'undefined') {
    console.warn('⚠️ SSSEO_AI not defined. Skipping AI script.');
    return;
  }

  const requiredSelectors = ['#ssseo_ai_pt_filter', '#ssseo_ai_post_id'];
  const missing = requiredSelectors.some(sel => $(sel).length === 0);
  if (missing) {
    console.warn('⚠️ Required AI tab elements missing. Skipping AI logic.');
    return;
  }

  // --- Constants ---
  const descSelBtn = $('#ssseo_ai_selected_generate');
  const descTypeBtn = $('#ssseo_ai_type_generate');
  const titleSelBtn = $('#ssseo_ai_selected_generate_title');
  const titleTypeBtn = $('#ssseo_ai_type_generate_title');
  const typeSelect = $('#ssseo_ai_pt_filter');
  const postSelect = $('#ssseo_ai_post_id');
  const searchBox = $('#ssseo_ai_post_search');
  const status = $('#ssseo_ai_status');
  const results = $('#ssseo_ai_results');
  const aboutStatus = $('#ssseo-about-area-status');
  const ajaxUrl = SSSEO_AI.ajax_url || window.ajaxurl;
  const nonce = SSSEO_AI.nonce || '';
  const postsByType = SSSEO_AI.posts_by_type || {};
  const defaultType = SSSEO_AI.default_type || 'post';

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
    const arr = postsByType[postType] || [];
    postSelect.empty();
    arr.forEach(item => {
      $('<option>').val(item.id).text(`${item.title} (ID: ${item.id})`).appendTo(postSelect);
    });
  }

  // --- Filter Options ---
  searchBox.on('keyup', function () {
    const q = $(this).val().toLowerCase();
    postSelect.find('option').each(function () {
      $(this).toggle($(this).text().toLowerCase().includes(q));
    });
  });

  // --- Generate Meta/Title ---
  function processPostIDs(ids, postType, mode) {
    let index = 0, successCount = 0;
    const label = mode === 'description' ? 'Meta' : 'Title';
    const generateAction = mode === 'description' ? 'ssseo_ai_generate_meta' : 'ssseo_ai_generate_title';
    const saveAction = mode === 'description' ? 'ssseo_ai_save_meta' : 'ssseo_ai_save_title';

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

      $.post(ajaxUrl, { action: generateAction, nonce, post_id: postID }, function (resp) {
        if (resp.success && resp.data.generated) {
          const text = resp.data.generated;
          logMessage(`← [${postType}] #${postID} (${label}): "${text}"`);

          $.post(ajaxUrl, { action: saveAction, nonce, post_id: postID, text }, function (saveResp) {
            if (saveResp.success) {
              successCount++;
              logMessage(`✔ [${postType}] #${postID} (${label}) saved.`);
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

  // --- About the Area Batch Generator ---
  $('#ssseo-generate-multiple-about').on('click', function (e) {
    e.preventDefault();

    const selected = postSelect.val();
    if (!selected || selected.length === 0) return alert('Please select one or more posts.');

    const $button = $(this);
    const $spinner = $('#ssseo-about-spinner');
    let count = 0;

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
      const title = postSelect.find(`option[value="${postId}"]`).text();

      $.post(ajaxUrl, { action: 'ssseo_get_city_state', post_id: postId, nonce }, function (res1) {
        const areaName = res1.success && res1.data ? res1.data : title;

        $.post(ajaxUrl, {
          action: 'ssseo_ai_generate_about_area',
          post_id: postId,
          area_name: areaName,
          nonce
        }, function (response) {
          count++;

          if (response.success) {
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

  // --- Button Events ---
  descSelBtn.on('click', function (e) {
    e.preventDefault();
    const selected = postSelect.val() || [];
    if (selected.length === 0) return status.text('Please select at least one post.');
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(selected, typeSelect.val(), 'description');
  });

  descTypeBtn.on('click', function (e) {
    e.preventDefault();
    const arr = postsByType[typeSelect.val()] || [];
    const allIDs = arr.map(obj => obj.id);
    if (allIDs.length === 0) return status.text(`No published posts for ${typeSelect.val()}`);
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(allIDs, typeSelect.val(), 'description');
  });

  titleSelBtn.on('click', function (e) {
    e.preventDefault();
    const selected = postSelect.val() || [];
    if (selected.length === 0) return status.text('Please select at least one post.');
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(selected, typeSelect.val(), 'title');
  });

  titleTypeBtn.on('click', function (e) {
    e.preventDefault();
    const arr = postsByType[typeSelect.val()] || [];
    const allIDs = arr.map(obj => obj.id);
    if (allIDs.length === 0) return status.text(`No published posts for ${typeSelect.val()}`);
    disableAllButtons(); results.empty(); status.text('');
    processPostIDs(allIDs, typeSelect.val(), 'title');
  });

  // --- Init ---
  loadPostsForType(defaultType);
  typeSelect.val(defaultType);

  typeSelect.on('change', function () {
    loadPostsForType($(this).val());
    searchBox.val('');
    results.empty();
    status.text('');
  });
});
