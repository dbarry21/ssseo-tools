<?php
// File: admin/tabs/gsc/opportunities.php
if (!defined('ABSPATH')) exit;
$gsc_nonce = wp_create_nonce('ssseo_gsc_ops');
$has_token = (bool) get_option('ssseo_gsc_token');
$property  = trailingslashit( home_url('/') );
?>
<style>
#wpbody-content .ssseo-fullbleed{margin-left:-20px;margin-right:-20px}
@media (max-width:782px){#wpbody-content .ssseo-fullbleed{margin-left:-12px;margin-right:-12px}}
.ssseo-fullbleed .card{border-radius:0;width:100%}
.ssseo-rows{display:grid;gap:16px}
@media(min-width:1000px){.ssseo-rows{grid-template-columns:1fr 1fr}}
.ssseo-table{width:100%;border-collapse:collapse}
.ssseo-table th,.ssseo-table td{padding:8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
.ssseo-table th{white-space:nowrap}
</style>

<div class="ssseo-fullbleed">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="mb-0">Opportunities</h4>
        <input type="url" id="ssseo-prop" class="form-control" style="max-width:420px"
               value="<?php echo esc_attr($property); ?>" placeholder="https://www.example.com/">
      </div>
      <?php if (!$has_token): ?>
        <div class="alert alert-warning mt-3">Not connected. Use <strong>GSC → Connect</strong> first.</div>
      <?php endif; ?>

      <div class="ssseo-rows mt-3">
        <div class="card card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-2">Striking Distance (Positions 8–20)</h5>
            <div>
              <label class="me-1 small">Min Impressions</label>
              <input type="number" id="sd-min-impr" class="form-control d-inline-block" style="width:110px" value="200">
              <button id="run-sd" class="button button-primary ms-2" <?php echo !$has_token ? 'disabled' : ''; ?>>Run</button>
            </div>
          </div>
          <div class="small text-muted mb-2">Last 28 days • type=web • group by query+page</div>
          <div id="sd-out"><div class="text-muted">No data yet.</div></div>
        </div>

        <div class="card card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-2">Decaying Content (Clicks down vs previous period)</h5>
            <div>
              <label class="me-1 small">Min Impressions</label>
              <input type="number" id="dec-min-impr" class="form-control d-inline-block" style="width:110px" value="200">
              <button id="run-dec" class="button button-primary ms-2" <?php echo !$has_token ? 'disabled' : ''; ?>>Run</button>
            </div>
          </div>
          <div class="small text-muted mb-2">Last 28 days vs prior 28 • by page</div>
          <div id="dec-out"><div class="text-muted">No data yet.</div></div>
        </div>

        <div class="card card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-2">New Opportunities (New queries last 7d)</h5>
            <div>
              <label class="me-1 small">Min Impressions</label>
              <input type="number" id="new-min-impr" class="form-control d-inline-block" style="width:110px" value="30">
              <button id="run-new" class="button button-primary ms-2" <?php echo !$has_token ? 'disabled' : ''; ?>>Run</button>
            </div>
          </div>
          <div class="small text-muted mb-2">Last 7 days vs prior 7 • queries new to the site</div>
          <div id="new-out"><div class="text-muted">No data yet.</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
jQuery(function($){
  const URL_AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  function table(rows, headers){
    if (!rows || !rows.length) return '<div class="text-muted">No rows.</div>';
    let th = headers.map(h=>'<th>'+h+'</th>').join('');
    let tr = rows.map(r=>'<tr>'+r.map(c=>'<td>'+c+'</td>').join('')+'</tr>').join('');
    return '<div style="overflow:auto"><table class="ssseo-table"><thead><tr>'+th+'</tr></thead><tbody>'+tr+'</tbody></table></div>';
  }

  $('#run-sd').on('click', function(){
    const minImpr = parseInt($('#sd-min-impr').val()||'200',10);
    const siteUrl = $('#ssseo-prop').val();
    $('#sd-out').html('Running…');
    $.post(URL_AJAX,{
      action:'ssseo_gsc_perf_striking_distance',
      nonce:'<?php echo esc_js($gsc_nonce); ?>',
      site_url:siteUrl, min_impr:minImpr
    }, function(res){
      if(res && res.success){
        const rows = res.data.map(r=>[
          '<a href="'+r.page+'" target="_blank" rel="noopener">'+r.page+'</a>',
          r.query, r.clicks, r.impressions, (r.ctr*100).toFixed(2)+'%', r.position.toFixed(1)
        ]);
        $('#sd-out').html(table(rows,['Page','Query','Clicks','Impr','CTR','Pos']));
      }else $('#sd-out').html('<div class="text-danger">'+(res && res.data ? res.data : 'Error')+'</div>');
    }).fail(()=>$('#sd-out').html('<div class="text-danger">Network error</div>'));
  });

  $('#run-dec').on('click', function(){
    const minImpr = parseInt($('#dec-min-impr').val()||'200',10);
    const siteUrl = $('#ssseo-prop').val();
    $('#dec-out').html('Running…');
    $.post(URL_AJAX,{
      action:'ssseo_gsc_perf_decay',
      nonce:'<?php echo esc_js($gsc_nonce); ?>',
      site_url:siteUrl, min_impr:minImpr
    }, function(res){
      if(res && res.success){
        const rows = res.data.map(r=>[
          '<a href="'+r.page+'" target="_blank" rel="noopener">'+r.page+'</a>',
          r.clicks_prev, r.clicks_now, (r.change_clicks_pct>0?'+':'')+r.change_clicks_pct.toFixed(1)+'%',
          r.impressions_now, (r.ctr_now*100).toFixed(2)+'%', r.position_now.toFixed(1)
        ]);
        $('#dec-out').html(table(rows,['Page','Clicks (prev)','Clicks (now)','Δ Clicks %','Impr (now)','CTR (now)','Pos (now)']));
      }else $('#dec-out').html('<div class="text-danger">'+(res && res.data ? res.data : 'Error')+'</div>');
    }).fail(()=>$('#dec-out').html('<div class="text-danger">Network error</div>'));
  });

  $('#run-new').on('click', function(){
    const minImpr = parseInt($('#new-min-impr').val()||'30',10);
    const siteUrl = $('#ssseo-prop').val();
    $('#new-out').html('Running…');
    $.post(URL_AJAX,{
      action:'ssseo_gsc_perf_new_opps',
      nonce:'<?php echo esc_js($gsc_nonce); ?>',
      site_url:siteUrl, min_impr:minImpr
    }, function(res){
      if(res && res.success){
        const rows = res.data.map(r=>[
          r.query, '<a href="'+r.page+'" target="_blank" rel="noopener">'+r.page+'</a>',
          r.clicks, r.impressions, (r.ctr*100).toFixed(2)+'%', r.position.toFixed(1)
        ]);
        $('#new-out').html(table(rows,['Query','Top Page','Clicks','Impr','CTR','Pos']));
      }else $('#new-out').html('<div class="text-danger">'+(res && res.data ? res.data : 'Error')+'</div>');
    }).fail(()=>$('#new-out').html('<div class="text-danger">Network error</div>'));
  });
});
</script>
