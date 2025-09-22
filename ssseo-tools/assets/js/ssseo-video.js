/* assets/js/ssseo-video.js */
jQuery(function($){
  const sel = '.ssseo-youtube-wrapper-container';
  const ajaxURL = (typeof ssseoYTAjax !== 'undefined' && ssseoYTAjax.ajax_url) ? ssseoYTAjax.ajax_url : (window.ajaxurl || '/wp-admin/admin-ajax.php');
  const nonce   = (typeof ssseoYTAjax !== 'undefined' && ssseoYTAjax.nonce)    ? ssseoYTAjax.nonce    : '';

  function bindPager($wrap){
    $wrap.off('click.ssseo').on('click.ssseo', '.ssseo-pagination a.page-link', function(e){
      e.preventDefault();
      if ($wrap.data('paging') !== true && $wrap.data('paging') !== 'true') return;

      const page = parseInt($(this).data('page') || 1, 10);
      const channel  = $wrap.data('channel')  || (ssseoYTAjax ? ssseoYTAjax.channel : '');
      const pagesize = parseInt($wrap.data('pagesize') || (ssseoYTAjax ? ssseoYTAjax.pagesize : 4), 10);
      const max      = parseInt($wrap.data('max') || (ssseoYTAjax ? ssseoYTAjax.max : 0), 10);

      $wrap.addClass('ssseo-busy');

      $.post(ajaxURL, {
        action: 'ssseo_youtube_pager',
        nonce:  nonce,
        channel: channel,
        page: page,
        pagesize: pagesize,
        max: max
      }).done(function(res){
        if (res && res.success && res.data) {
          $wrap.html((res.data.grid || '') + (res.data.pagination || ''));
        } else {
          $wrap.html('<div class="alert alert-danger">Error loading videos.</div>');
        }
      }).fail(function(){
        $wrap.html('<div class="alert alert-danger">Network error loading videos.</div>');
      }).always(function(){
        $wrap.removeClass('ssseo-busy');
        bindPager($wrap);
      });
    });
  }

  $(document).ready(function(){
    $(sel).each(function(){ bindPager($(this)); });
  });
});
