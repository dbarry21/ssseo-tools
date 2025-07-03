/**
 * assets/js/ssseo-video.js
 *
 * Handles AJAX paging for [youtube_channel_list].
 * Listens for clicks on pagination links and fetches the next page via admin-ajax.
 */

jQuery(function($) {
  // Delegate click on any page-link inside .ssseo-pagination
  $('body').on('click', '.ssseo-pagination a.page-link', function(e) {
    e.preventDefault();

    var $link     = $(this);
    var $wrapper  = $link.closest('.ssseo-youtube-wrapper-container');
    var channel   = $wrapper.data('channel');
    var pagesize  = $wrapper.data('pagesize');
    var page      = parseInt( $link.data('page'), 10 );

    $.post(
      ssseoYTAjax.ajax_url,
      {
        action:   'ssseo_youtube_pager',
        nonce:    ssseoYTAjax.nonce,
        channel:  channel,
        pagesize: pagesize,
        page:     page
      },
      function(response) {
        if ( response.success && response.data ) {
          // Replace existing grid & pagination
          $wrapper.find('.ssseo-card-grid').replaceWith( response.data.grid );
          $wrapper.find('.ssseo-pagination').replaceWith( response.data.pagination );
        }
      }
    );
  });
});
