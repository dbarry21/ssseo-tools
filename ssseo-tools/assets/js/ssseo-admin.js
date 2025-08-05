jQuery(function ($) {
  console.log("✅ SSSEO Admin JS Loaded");

  const currentTab = new URLSearchParams(window.location.search).get('tab') || '';

  // === Meta Tag History Tab ===
  if (currentTab === 'meta-tag-history') {
    function loadPosts(pt, search = '') {
      $('#ssseo_history_post').html('<option disabled>Loading...</option>');
      $.post(ssseo_admin.ajaxurl, {
        action: 'ssseo_get_posts_by_type',
        post_type: pt,
        search: search
      }, function (response) {
        $('#ssseo_history_post').html(response);
      });
    }

    $('#ssseo_history_pt').on('change', function () {
      loadPosts(this.value, $('#ssseo_history_search').val());
      $('#ssseo_history_output').html('');
      $('#ssseo_export_meta_history').hide();
    });

    $('#ssseo_history_search').on('input', function () {
      const pt = $('#ssseo_history_pt').val();
      loadPosts(pt, this.value);
    });

    $('#ssseo_history_post').on('change', function () {
      const post_id = $(this).val();
      $('#ssseo_history_output').html('<em>Loading history...</em>');
      $('#ssseo_export_meta_history').hide();

      $.post(ssseo_admin.ajaxurl, {
        action: 'ssseo_get_meta_history',
        post_id: post_id,
        _wpnonce: ssseo_admin.nonce
      }, function (response) {
        if (response.success) {
          $('#ssseo_history_output').html(response.data.html || '<p>No history found.</p>');
          if (response.data.csv) {
            $('#ssseo_export_meta_history')
              .show()
              .data('csv', response.data.csv)
              .data('filename', response.data.filename);
          }
        } else {
          $('#ssseo_history_output').html('<p>Error loading history.</p>');
        }
      });
    });

    $('#ssseo_export_meta_history').on('click', function () {
      const csv = $(this).data('csv');
      const filename = $(this).data('filename') || 'meta-history.csv';
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', filename);
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });

    loadPosts($('#ssseo_history_pt').val());
  }

  // === Bulk Operations Tab ===
  if (currentTab === 'bulk') {
    const $select = $('#ssseo_bulk_post_id');
    const $results = $('#ssseo_bulk_result');

    function updateSelectOptions(pt, search = '') {
      const posts = window.ssseoPostsByType?.[pt] || [];
      const matches = posts.filter(p => p.title.toLowerCase().includes(search.toLowerCase()));
      $select.empty();
      if (matches.length) {
        matches.forEach(p => $select.append(`<option value="${p.id}">${p.title} (#${p.id})</option>`));
      } else {
        $select.append('<option disabled>No matching posts found</option>');
      }
    }

    function handleBulkAction(action, message) {
      const selected = $select.val();
      if (!selected?.length) return alert('Please select at least one post.');
      $.post(ssseo_admin.ajaxurl, {
        action,
        post_ids: selected,
        _wpnonce: ssseo_admin.nonce
      }, res => {
        if (res.success) {
          let html = `<strong>${selected.length} ${message}:</strong><ul class='mt-2 ps-3'>`;
          selected.forEach(id => {
            const label = $select.find(`option[value='${id}']`).text();
            html += `<li>${label}</li>`;
          });
          html += '</ul>';
          $results.html(html).show();
        } else {
          alert(res.data || 'An error occurred');
          $results.hide();
        }
      });
    }

    $('#ssseo_bulk_pt_filter').on('change', function () {
      updateSelectOptions(this.value, $('#ssseo_bulk_post_search').val());
    });

    $('#ssseo_bulk_post_search').on('input', function () {
      updateSelectOptions($('#ssseo_bulk_pt_filter').val(), this.value);
    });

    $('#ssseo_bulk_indexfollow').on('click', () => handleBulkAction('ssseo_yoast_set_index_follow', 'post(s) updated'));
    $('#ssseo_bulk_reset_canonical').on('click', () => handleBulkAction('ssseo_yoast_reset_canonical', 'canonical URL(s) reset'));
    $('#ssseo_bulk_clear_canonical').on('click', () => handleBulkAction('ssseo_yoast_clear_canonical', 'canonical URL(s) cleared'));

    if (typeof ssseoPostsByType !== 'undefined') {
      updateSelectOptions($('#ssseo_bulk_pt_filter').val());
    }
  }

  // === Video Blog Tab: Full Import Handler ===
  if (currentTab === 'videoblog') {
    $('.ssseo-full-import-btn').on('click', function (e) {
      e.preventDefault();

      const $btn = $(this);
      const nonce = $btn.data('nonce');
      const $log = $('.ssseo-video-import-log');

      $btn.prop('disabled', true).text('Importing...');
      $log.empty().append('<div>Starting full import...</div>');

      $.post(ssseo_admin.ajaxurl, {
        action: 'ssseo_batch_import_videos',
        nonce: nonce
      }).done(function (response) {
        if (response.success && response.data?.log) {
          response.data.log.forEach(line => {
            $log.append('<div>' + line + '</div>');
          });
          $log.append('<div class="mt-2 text-success fw-bold">✅ ' + response.data.message + '</div>');
        } else {
          $log.append('<div class="text-danger mt-2">❌ ' + (response.data?.error || 'Unknown error') + '</div>');
        }
      }).fail(function () {
        $log.append('<div class="text-danger mt-2">❌ AJAX failed.</div>');
      }).always(function () {
        $btn.prop('disabled', false).text('Fetch All Videos & Create Drafts');
      });
    });
  }
});
