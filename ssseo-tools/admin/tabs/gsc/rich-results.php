<?php
// File: admin/tabs/gsc/rich-results.php
if (!defined('ABSPATH')) exit;

$gsc_nonce   = wp_create_nonce('ssseo_gsc_ops');
$has_token   = (bool) get_option('ssseo_gsc_token');
$property    = trailingslashit( home_url('/') );
$post_types  = get_post_types(['public'=>true],'objects');
?>
<style>
/* Full-bleed is handled by the parent gsc.php; keep this lean */
.ssseo-rr-grid{display:grid;gap:16px}
@media(min-width:1100px){.ssseo-rr-grid{grid-template-columns: 330px 1fr}}

.ssseo-table{width:100%;border-collapse:collapse}
.ssseo-table th,.ssseo-table td{padding:8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
.ssseo-table th{white-space:nowrap}
.badge{display:inline-block;padding:2px 8px;border-radius:12px;background:#eef;border:1px solid #dde;margin:0 6px 6px 0;font-size:12px}
.ssseo-muted{color:#6c757d}
.ssseo-row-actions{white-space:nowrap}
details.ssseo-raw{margin-top:6px}
details.ssseo-raw > pre{white-space:pre-wrap;word-break:break-word;background:#fafafa;border:1px solid #eee;padding:8px;border-radius:4px}
</style>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h4 class="mb-0">Rich Results</h4>
      <div class="d-flex align-items-center gap-2">
        <input type="url" id="rr-prop" class="form-control" style="width:420px"
               value="<?php echo esc_attr($property); ?>" placeholder="https://www.example.com/">
      </div>
    </div>

    <?php if (!$has_token): ?>
      <div class="alert alert-warning mt-3">
        <strong>Not connected to Google Search Console.</strong>
        Use <em>GSC → Connect</em> to authorize, then return here.
      </div>
    <?php endif; ?>

    <!-- Current page context banner -->
    <div id="rr-current" class="small ssseo-muted mt-2"></div>

    <div class="ssseo-rr-grid mt-3">
      <!-- Left: selectors -->
      <div>
        <label class="form-label">Post Type</label>
        <select id="rr-pt" class="form-select">
          <?php foreach($post_types as $pt){ ?>
            <option value="<?php echo esc_attr($pt->name); ?>">
              <?php echo esc_html($pt->labels->singular_name ?: $pt->name); ?>
            </option>
          <?php } ?>
        </select>

        <label class="form-label mt-3">Filter by title</label>
        <input type="text" id="rr-flt" class="form-control" placeholder="Start typing…">

        <label class="form-label mt-3">Select Posts</label>
        <select id="rr-sel" class="form-select" size="12" multiple></select>
        <div class="form-text">Hold Ctrl/Cmd to multi-select.</div>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <button id="rr-use-current" class="button">Use Current</button>
          <button id="rr-run" class="button button-primary" <?php echo !$has_token?'disabled':''; ?>>Check</button>
          <button id="rr-clear" class="button">Clear</button>
        </div>
        <div id="rr-msg" class="small ssseo-muted mt-2"></div>
      </div>

      <!-- Right: results -->
      <div>
        <div style="overflow:auto">
          <table class="ssseo-table" id="rr-table">
            <thead>
              <tr>
                <th>URL</th>
                <th>Detected Types</th>
                <th>Issues</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="3" class="ssseo-muted">No data yet. Select posts and click <em>Check</em>.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
jQuery(function($){
  const AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  const NONCE = '<?php echo esc_js($gsc_nonce); ?>';

  const $pt  = $('#rr-pt'), $sel = $('#rr-sel'), $flt = $('#rr-flt');
  const $tbl = $('#rr-table tbody'), $msg = $('#rr-msg');
  const $cur = $('#rr-current');

  // ----- Current page banner -----
  function renderCurrent(){
    const ctx = (window.SSSEO_GSC && SSSEO_GSC.getCurrentPage()) || null;
    if (ctx && ctx.url) {
      const title = ctx.title ? $('<div/>').text(ctx.title).html() : '';
      $cur.html(
        'Current Page: <a href="'+ ctx.url +'" target="_blank" rel="noopener">' + (title || ctx.url) + '</a>' +
        ' <button type="button" class="button-link delete rr-clear-context" style="margin-left:6px">clear</button>'
      );
    } else {
      $cur.text('Current Page: none');
    }
  }
  $(document).on('click', '.rr-clear-context', function(){ SSSEO_GSC && SSSEO_GSC.clearCurrentPage(); renderCurrent(); });
  window.addEventListener('ssseo:gsc:context', renderCurrent);
  renderCurrent();

  // ----- Load posts by type -----
  function loadList(cb){
    const type = $pt.val();
    $sel.prop('disabled',true).empty().append($('<option>').text('Loading…'));
    $.post(AJAX,{action:'ssseo_get_posts_by_type', post_type:type}, function(res){
      $sel.empty();
      if(res && res.success && res.data){ $sel.append(res.data); }
      else { $sel.append($('<option>').text('No posts.')); }
      if (typeof cb === 'function') cb();
    }).always(()=>{$sel.prop('disabled',false);});
  }
  function applyFilter(){
    const n = ($flt.val()||'').toLowerCase();
    $('#rr-sel option').each(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(n)!==-1);
    });
  }
  $pt.on('change', function(){ loadList(applyFilter); });
  $flt.on('input', applyFilter);

  // Restore context on load: set PT and preselect post if we have one
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

  // Shortcut: Use Current (replace selection with the sticky post)
  $('#rr-use-current').on('click', function(){
    const ctx = (window.SSSEO_GSC && SSSEO_GSC.getCurrentPage()) || null;
    if (!ctx || !ctx.post_id) { alert('No current page saved yet. Inspect one in Page Check first.'); return; }
    if (ctx.post_type) $pt.val(ctx.post_type);
    loadList(function(){
      $sel.val(String(ctx.post_id));
      try { $sel.find('option[value="'+ String(ctx.post_id) +'"]')[0].scrollIntoView({ block: 'nearest' }); } catch(e){}
      applyFilter();
    });
  });

  $('#rr-clear').on('click', function(){
    $tbl.empty().append('<tr><td colspan="3" class="ssseo-muted">Cleared.</td></tr>');
    $msg.empty();
  });

  // ---- Robust rich-results parser for URL Inspection API ----
  function parseRich(rawObj){
    const out = { types: [], issues: [], raw: rawObj || {} };
    const root = rawObj && (rawObj.inspectionResult || rawObj);
    if (!root) return out;

    const rr = root.richResultsResult || root.richResults || {};
    let detected = rr.detectedItems;
    if (!detected) detected = rr.items || rr.richResults || rr;

    const labelMap = {
      'LOCAL_BUSINESS':'LocalBusiness','PRODUCT':'Product','RECIPE':'Recipe','FAQ':'FAQ',
      'HOW_TO':'HowTo','QAPAGE':'QAPage','EVENT':'Event','ARTICLE':'Article',
      'JOB_POSTING':'JobPosting','VIDEO':'Video','ORGANIZATION':'Organization'
    };
    const label = (t)=> labelMap[String(t||'').toUpperCase()] || t || 'unknown';

    const pushItem = (typeGuess, item)=>{
      const t = label(item?.richResultType || item?.type || typeGuess || item?.resultType);
      if (t && !out.types.includes(t)) out.types.push(t);

      const iss = item?.issues || item?.itemIssues || item?.warnings || [];
      (Array.isArray(iss) ? iss : []).forEach(is=>{
        const sev = is?.severity || is?.level || '';
        const msg = is?.issueMessage || is?.message || is?.description || '';
        if (msg) out.issues.push((t+': '+(sev?sev+' ':'')+msg).trim());
      });
    };

    if (Array.isArray(detected)) {
      detected.forEach(block=>{
        const t = block?.richResultType || block?.type || block?.resultType;
        if (Array.isArray(block?.items) && block.items.length) {
          block.items.forEach(it=>pushItem(t, it));
        } else {
          pushItem(t, block);
        }
      });
    } else if (detected && typeof detected === 'object') {
      Object.keys(detected).forEach(key=>{
        const b = detected[key];
        if (Array.isArray(b?.items)) b.items.forEach(it=>pushItem(key, it));
        else pushItem(key, b);
      });
    }
    return out;
  }

  // ----- Run inspection on selected posts -----
  $('#rr-run').on('click', async function(){
    const ids = ($sel.val()||[]);
    const siteUrl = $('#rr-prop').val();
    if(!ids.length) { alert('Select at least one post.'); return; }
    if(!siteUrl) { alert('Enter the verified siteUrl property.'); return; }

    const $btn=$(this).prop('disabled',true);
    $msg.text('Inspecting '+ids.length+' URL(s)…');
    $tbl.empty();

    for (const id of ids) {
      await new Promise(resolve=>{
        $.post(AJAX, {
          action:'ssseo_gsc_inspect_post',
          nonce: NONCE,
          post_id: id,
          site_url: siteUrl
        }, function(res){
          if (res && res.success) {
            const raw = res.data.raw || {};
            const url = res.data.inspectionUrl || '';
            const link = url ? '<a href="'+url+'" target="_blank" rel="noopener">'+url+'</a>' : '';

            const parsed = parseRich(raw);
            const tdTypes = parsed.types.length
              ? parsed.types.map(x=>'<span class="badge">'+x+'</span>').join(' ')
              : '<span class="ssseo-muted">None</span>';
            const tdIssues = parsed.issues.length
              ? parsed.issues.map(x=>'<div>'+x+'</div>').join('')
              : '<span class="ssseo-muted">None</span>';

            const $tr = $('<tr/>').append(
              $('<td/>').html(
                link +
                '<div class="ssseo-row-actions">' +
                  '<details class="ssseo-raw"><summary>raw JSON</summary><pre>'+ $('<div/>').text(JSON.stringify(parsed.raw, null, 2)).html() +'</pre></details>' +
                '</div>'
              ),
              $('<td/>').html(tdTypes),
              $('<td/>').html(tdIssues)
            );

            // subtle highlight if no types detected (schema present may still be ineligible)
            if (!parsed.types.length) $tr.css('background','#fff8f3');

            $tbl.append($tr);
          } else {
            $tbl.append($('<tr/>').append(
              $('<td colspan="3" class="text-danger"/>').text((res && res.data) ? res.data : 'Error')
            ));
          }
          resolve();
        }).fail(function(){
          $tbl.append($('<tr/>').append(
            $('<td colspan="3" class="text-danger"/>').text('Network error')
          ));
          resolve();
        });
      });
    }

    $msg.text('Done.');
    $btn.prop('disabled',false);
  });
});
</script>
