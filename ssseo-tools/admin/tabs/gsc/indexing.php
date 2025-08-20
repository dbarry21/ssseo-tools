<?php
// File: admin/tabs/gsc/indexing.php
if (!defined('ABSPATH')) exit;

$gsc_nonce = wp_create_nonce('ssseo_gsc_ops');
$has_token = (bool) get_option('ssseo_gsc_token');
$property  = trailingslashit( home_url('/') );
$post_types = get_post_types(['public'=>true],'objects');
?>
<style>
#wpbody-content .ssseo-fullbleed{margin-left:-20px;margin-right:-20px}
@media (max-width:782px){#wpbody-content .ssseo-fullbleed{margin-left:-12px;margin-right:-12px}}
.ssseo-fullbleed .card{border-radius:0;width:100%}
.ssseo-grid{display:grid;gap:16px}
@media(min-width:1100px){.ssseo-grid{grid-template-columns: 320px 1fr}}
.ssseo-table{width:100%;border-collapse:collapse}
.ssseo-table th,.ssseo-table td{padding:8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
.badge{display:inline-block;padding:2px 6px;border-radius:10px;background:#eef;border:1px solid #dde}
</style>

<div class="ssseo-fullbleed">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">Indexing Issues</h4>
      <?php if (!$has_token): ?>
        <div class="alert alert-warning">Not connected. Use <strong>GSC → Connect</strong> first.</div>
      <?php endif; ?>

      <div class="ssseo-grid">
        <div>
          <label class="form-label">Property (siteUrl)</label>
          <input type="url" id="prop" class="form-control" value="<?php echo esc_attr($property); ?>">

          <label class="form-label mt-3">Post Type</label>
          <select id="pt" class="form-select">
            <?php foreach($post_types as $pt){ echo '<option value="'.esc_attr($pt->name).'">'.esc_html($pt->labels->singular_name).'</option>'; } ?>
          </select>

          <label class="form-label mt-3">Filter</label>
          <input type="text" id="flt" class="form-control" placeholder="Filter by title…">

          <label class="form-label mt-3">Select Posts</label>
          <select id="sel" class="form-select" size="12" multiple></select>

          <div class="form-text">Hold Ctrl/Cmd for multi-select.</div>

          <div class="mt-3 d-flex gap-2">
            <button id="run" class="button button-primary" <?php echo !$has_token?'disabled':''; ?>>Inspect Selected</button>
            <button id="clr" class="button">Clear</button>
          </div>
          <div class="small text-muted mt-2" id="msg"></div>
        </div>

        <div>
          <div style="overflow:auto">
            <table class="ssseo-table" id="tbl">
              <thead>
                <tr><th>URL</th><th>Coverage</th><th>Fetch</th><th>Robots</th><th>Google Canonical</th><th>Last Crawl</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script>
jQuery(function($){
  const AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  const $curr = $('<div id="ssseo-gsc-current" class="small text-muted mb-2"></div>').insertBefore($('.ssseo-grid'));

  function renderCurrent(){
    const ctx = (window.SSSEO_GSC && SSSEO_GSC.getCurrentPage()) || null;
    if (ctx && ctx.url) {
      const title = ctx.title ? $('<div/>').text(ctx.title).html() : '';
      $curr.html(
        'Current Page: <a href="'+ ctx.url +'" target="_blank" rel="noopener">' + (title || ctx.url) + '</a> ' +
        '<button type="button" class="button-link delete ssseo-gsc-clear" style="margin-left:6px">clear</button>'
      );
    } else {
      $curr.text('Current Page: none');
    }
  }
  $(document).on('click', '.ssseo-gsc-clear', function(){ SSSEO_GSC && SSSEO_GSC.clearCurrentPage(); renderCurrent(); });
  window.addEventListener('ssseo:gsc:context', renderCurrent);
  renderCurrent();

  const $pt = $('#pt'), $sel = $('#sel'), $flt = $('#flt');

  function loadList(cb){
    const type = $pt.val();
    $sel.prop('disabled',true).empty().append($('<option>').text('Loading…'));
    $.post(AJAX,{action:'ssseo_get_posts_by_type', post_type:type}, function(res){
      $sel.empty();
      if(res && res.success && res.data){ $sel.append(res.data); }
      else { $sel.append($('<option>').text('No posts.')); }
      if (typeof cb === 'function') cb();
    }).always(()=>$('#sel').prop('disabled',false));
  }
  function applyFilter(){
    const n = ($flt.val()||'').toLowerCase();
    $('#sel option').each(function(){ $(this).toggle($(this).text().toLowerCase().indexOf(n)!==-1); });
  }

  // Restore context: set PT and preselect post
  (function restore(){
    const ctx = (window.SSSEO_GSC && SSSEO_GSC.getCurrentPage()) || null;
    if (ctx && ctx.post_type) $pt.val(ctx.post_type);
    loadList(function(){
      if (ctx && ctx.post_id) {
        $sel.val(String(ctx.post_id));
        try { $sel.find('option[value="'+ String(ctx.post_id) +'"]')[0].scrollIntoView({ block: 'nearest' }); } catch(e){}
      }
      applyFilter();
    });
  })();

  $('#pt').on('change', function(){ loadList(applyFilter); });
  $('#flt').on('input', applyFilter);

  $('#run').on('click', async function(){
    const ids = ($sel.val()||[]);
    const siteUrl = $('#prop').val();
    if(!ids.length) { alert('Select at least one post.'); return; }
    const $btn=$(this).prop('disabled',true), $msg=$('#msg').text('Inspecting '+ids.length+' URLs…');
    const $tb=$('#tbl tbody').empty();

    for(const id of ids){
      await new Promise(resolve=>{
        $.post(AJAX,{
          action:'ssseo_gsc_inspect_post',
          nonce:'<?php echo esc_js($gsc_nonce); ?>',
          post_id:id, site_url:siteUrl
        },function(res){
          if(res && res.success){
            const d=res.data;
            const link = d.inspectionUrl ? '<a href="'+d.inspectionUrl+'" target="_blank" rel="noopener">Open</a>' : '';
            const tr = $('<tr/>').append(
              $('<td/>').html(link),
              $('<td/>').text(d.coverage||''),
              $('<td/>').text(d.pageFetchState||''),
              $('<td/>').text(d.robotsTxtState||''),
              $('<td/>').html('<code>'+ (d.googleCanonical||'') +'</code>'),
              $('<td/>').text(d.lastCrawlTime||'')
            );
            if(!d.is_indexed || (d.googleCanonical && d.userCanonical && d.googleCanonical!==d.userCanonical)){
              tr.css('background','#fff8e6');
            }
            $tb.append(tr);
          } else {
            $tb.append($('<tr/>').append($('<td colspan="6" class="text-danger"/>').text((res && res.data) ? res.data : 'Error')));
          }
          resolve();
        }).fail(()=>{ $tb.append($('<tr/>').append($('<td colspan="6" class="text-danger"/>').text('Network error'))); resolve(); });
      });
    }
    $msg.text('Done.');
    $btn.prop('disabled',false);
  });

  $('#clr').on('click', function(){
    $('#tbl tbody').empty();
    $('#msg').empty();
  });
});
</script>
