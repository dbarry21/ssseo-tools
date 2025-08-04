// File: ssseo-admin.js
jQuery(function ($) {
  console.log("✅ SSSEO Admin JS Loaded");

  // Future logic goes here per tab
});

jQuery(document).ready(function($) {

  function loadPosts(pt, search = '') {

    $('#ssseo_history_post').html('<option disabled>Loading...</option>');

    $.post(ssseo_admin.ajaxurl, {

      action: 'ssseo_get_posts_by_type',

      post_type: pt,

      search: search

    }, function(response) {

      $('#ssseo_history_post').html(response);

    });

  }



  $('#ssseo_history_pt').on('change', function() {

    loadPosts(this.value, $('#ssseo_history_search').val());

    $('#ssseo_history_output').html('');

    $('#ssseo_export_meta_history').hide();

  });



  $('#ssseo_history_search').on('input', function() {

    const pt = $('#ssseo_history_pt').val();

    loadPosts(pt, this.value);

  });



  $('#ssseo_history_post').on('change', function() {

    const post_id = $(this).val();

    $('#ssseo_history_output').html('<em>Loading history...</em>');

    $('#ssseo_export_meta_history').hide();

    $.post(ssseo_admin.ajaxurl, {

      action: 'ssseo_get_meta_history',

      post_id: post_id,

      _wpnonce: ssseo_admin.nonce

    }, function(response) {

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



  $('#ssseo_export_meta_history').on('click', function() {

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

});

jQuery(function($) {
  const $select = $('#ssseo_bulk_post_id');
  const $results = $('#ssseo_bulk_result');

  function updateSelectOptions(pt, search = '') {
    const posts = ssseoPostsByType[pt] || [];
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
      action, post_ids: selected, _wpnonce: ssseo_admin.nonce
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

  $('#ssseo_bulk_pt_filter').on('change', function() {
    updateSelectOptions(this.value, $('#ssseo_bulk_post_search').val());
  });

  $('#ssseo_bulk_post_search').on('input', function() {
    updateSelectOptions($('#ssseo_bulk_pt_filter').val(), this.value);
  });

  $('#ssseo_bulk_indexfollow').on('click', () => handleBulkAction('ssseo_yoast_set_index_follow', 'post(s) updated'));
  $('#ssseo_bulk_reset_canonical').on('click', () => handleBulkAction('ssseo_yoast_reset_canonical', 'canonical URL(s) reset'));
  $('#ssseo_bulk_clear_canonical').on('click', () => handleBulkAction('ssseo_yoast_clear_canonical', 'canonical URL(s) cleared'));

  updateSelectOptions(ssseoDefaultType);

  $('#ssseo_clone_services_btn').on('click', function() {
    const serviceIDs = $('#ssseo_service_root_posts').val() || [];
    const areaID = $('#ssseo_target_service_area').val();
    const generateAI = $('#ssseo_generate_ai').is(':checked') ? 1 : 0;

    if (!areaID || !serviceIDs.length) return alert('Select both service posts and a target service area.');

    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_clone_services_to_area',
      services: serviceIDs,
      target_area: areaID,
      enable_ai: generateAI,
      _wpnonce: ssseo_admin.nonce
    }, res => {
      const $out = $('#ssseo_clone_result');
      if (res.success) {
        $out.removeClass('d-none alert-danger').addClass('alert-success').html(res.data);
      } else {
        $out.removeClass('d-none alert-success').addClass('alert-danger').html(res.data || 'Error cloning services.');
      }
    });
  });

  $('#ssseo_fix_youtube_iframes').on('click', function() {
    if (!confirm('Are you sure? This will process all posts and modify any YouTube iframe embed.')) return;

    $.post(ssseo_admin.ajaxurl, {
      action: 'ssseo_fix_youtube_iframes',
      _wpnonce: ssseo_admin.nonce
    }, function(res) {
      const $out = $('#ssseo_youtube_result');
      const $log = $('#ssseo_youtube_log');
      if (res.success) {
        $out.removeClass('d-none alert-danger').addClass('alert-success').html('YouTube iframes updated successfully.');
        $log.removeClass('d-none').empty();
        if (res.data.updated && res.data.updated.length) {
          res.data.updated.forEach(post => {
            $log.append(`<li class="list-group-item">${post.title} (completed)</li>`);
          });
        } else {
          $log.append('<li class="list-group-item">No posts required updates.</li>');
        }
      } else {
        $out.removeClass('d-none alert-success').addClass('alert-danger').html(res.data || 'Error updating posts.');
        $log.addClass('d-none').empty();
      }
    });
  });
});
