<?php
// File: admin/tabs/gsc/discover.php
if (!defined('ABSPATH')) exit;

$gsc_nonce = wp_create_nonce('ssseo_gsc_ops');
$has_token = (bool) get_option('ssseo_gsc_token');
$property  = trailingslashit( home_url('/') );
?>
<style>
#wpbody-content .ssseo-fullbleed{margin-left:-20px;margin-right:-20px}
@media (max-width:782px){#wpbody-content .ssseo-fullbleed{margin-left:-12px;margin-right:-12px}}
.ssseo-fullbleed .card{border-radius:0;width:100%}
.ssseo-table{width:100%;border-collapse:collapse}
.ssseo-table th,.ssseo-table td{padding:8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
</style>

<div class="ssseo-fullbleed">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="mb-0">Discover Performance</h4>
        <div>
          <input type="url" id="prop" class="form-control d-inline-block" style="width:420px" value="<?php echo esc_attr($property); ?>">
          <button id="run" class="button button-primary ms-2" <?php echo !$has_token?'disabled':''; ?>>Run</button>
        </div>
      </div>
      <div class="small text-muted mt-2">Last 28 days • type=discover • by page</div>

      <div class="mt-3" id="out"><div class="text-muted">No data yet.</div></div>
    </div>
  </div>
</div>

<script>
jQuery(function($){
  const AJAX = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
  function table(rows){
    if(!rows || !rows.length) return '<div class="text-muted">No rows.</div>';
    let tr = rows.map(r=>'<tr><td><a href="'+r.page+'" target="_blank" rel="noopener">'+r.page+'</a></td><td>'+r.clicks+'</td><td>'+r.impressions+'</td><td>'+ (r.ctr*100).toFixed(2)+'%</td></tr>').join('');
    return '<div style="overflow:auto"><table class="ssseo-table"><thead><tr><th>Page</th><th>Clicks</th><th>Impr</th><th>CTR</th></tr></thead><tbody>'+tr+'</tbody></table></div>';
  }
  $('#run').on('click', function(){
    const siteUrl = $('#prop').val();
    $('#out').html('Running…');
    $.post(AJAX,{action:'ssseo_gsc_discover_pages', nonce:'<?php echo esc_js($gsc_nonce); ?>', site_url:siteUrl}, function(res){
      if(res && res.success){ $('#out').html(table(res.data)); }
      else $('#out').html('<div class="text-danger">'+(res && res.data ? res.data : 'Error')+'</div>');
    }).fail(()=>$('#out').html('<div class="text-danger">Network error</div>'));
  });
});
</script>
