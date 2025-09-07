<?php
// File: admin/tabs/gsc/page-check.php
if (!defined('ABSPATH')) exit;

// Public post types for dropdown
$post_types = get_post_types(['public' => true], 'objects');
$gsc_nonce  = wp_create_nonce('ssseo_gsc_ops');

$has_token = (bool) get_option('ssseo_gsc_token'); // set after OAuth
$default_property = trailingslashit( home_url('/') ); // must match verified GSC property
?>
<style>
#wpbody-content .ssseo-fullbleed { margin-left:-20px;margin-right:-20px; }
@media (max-width:782px){#wpbody-content .ssseo-fullbleed{margin-left:-12px;margin-right:-12px;}}
.ssseo-fullbleed .card{border-radius:0;width:100%;max-width:none;}
.ssseo-fullbleed .card-body{width:100%;max-width:none;}
.ssseo-form-grid{display:grid;grid-template-columns:repeat(3,minmax(260px,1fr));gap:16px;align-items:end;}
@media (max-width:1200px){.ssseo-form-grid{grid-template-columns:repeat(2,minmax(260px,1fr));}}
@media (max-width:782px){.ssseo-form-grid{grid-template-columns:1fr;}}
.ssseo-results-grid{display:grid;grid-template-columns:1fr;gap:16px;}
@media (min-width:1000px){.ssseo-results-grid{grid-template-columns:1.4fr 1fr;}}
.ssseo-kv{display:grid;grid-template-columns:200px 1fr;gap:8px 12px;align-items:start;}
@media (max-width:782px){.ssseo-kv{grid-template-columns:1fr;}}
.ssseo-kv .k{font-weight:600;color:#333;}
.ssseo-kv .v code{background:#f6f7f7;padding:2px 4px;border-radius:3px;}
#ssseo-gsc-post{width:100%;}
.ssseo-manual-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;}
@media (max-width:782px){.ssseo-manual-row{grid-template-columns:1fr;}}
.badge{display:inline-block;border-radius:6px;font-size:12px;line-height:1;padding:.25em .6em;}
.bg-success{background:#198754;color:#fff;}
.bg-danger{background:#dc3545;color:#fff;}
</style>

<div class="ssseo-fullbleed">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="card-title mb-0">URL Inspection — Page Check</h4>
        <a class="btn btn-outline-secondary"
           href="<?php echo esc_url( admin_url('admin.php?page=ssseo-tools&tab=site-options') ); ?>">
          <i class="bi bi-gear"></i> Site Options
        </a>
      </div>

      <?php if (!$has_token): ?>
        <div class="alert alert-warning mt-3">
          <strong>Not connected to Google Search Console.</strong>
          Use <em>GSC → Connect</em> to authorize, then return here.
        </div>
      <?php endif; ?>

      <div class="ssseo-form-grid mt-3">
        <div>
          <label class="form-label" for="ssseo-gsc-pt">Post Type</label>
          <select id="ssseo-gsc-pt" class="form-select">
            <?php foreach ($post_types as $pt): ?>
              <option value="<?php echo esc_attr($pt->name); ?>">
                <?php echo esc_html($pt->labels->singular_name ?: $pt->name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label" for="ssseo-gsc-search">Filter by title</label>
          <input type="text" id="ssseo-gsc-search" class="form-control" placeholder="Start typing…">
        </div>

        <div>
          <label class="form-label" for="ssseo-gsc-property">GSC Property (siteUrl)</label>
          <input type="url" id="ssseo-gsc-property" class="form-control"
                 value="<?php echo esc_attr($default_property); ?>"
                 placeholder="https://www.example.com/">
          <div class="form-text">Must exactly match a verified property in Search Console (URL-prefix or domain property).</div>
        </div>
      </div>

      <div class="mt-3">
        <label class="form-label" for="ssseo-gsc-post">Select Page/Post</label>
        <select id="ssseo-gsc-post" class="form-select" size="12"></select>
        <div class="form-text">List is limited to published items for speed.</div>
      </div>

      <div class="mt-3">
        <label class="form-label" for="ssseo-gsc-manual-url">Or inspect a specific URL</label>
        <div class="ssseo-manual-row">
          <input type="url" id="ssseo-gsc-manual-url" class="form-control"
                 placeholder="https://yourdomain.com/path-to-check" inputmode="url" spellcheck="false">
          <button type="button" id="ssseo-gsc-inspect-url" class="button"><?php echo esc_html__('Inspect URL', 'ssseo'); ?></button>
        </div>
        <div class="form-text">Useful for non-WP URLs or parameters/canonical variants.</div>
      </div>

      <div class="mt-3 d-flex align-items-center gap-2">
        <button type="button" id="ssseo-gsc-inspect" class="button button-primary" <?php echo !$has_token ? 'disabled' : ''; ?>>
          Inspect Selected Post
        </button>
        <span class="spinner" id="ssseo-gsc-spinner" style="float:none; margin-left:8px; display:none;"></span>
        <div id="ssseo-gsc-msg" class="small text-muted"></div>
      </div>

      <div class="mt-4 ssseo-results-grid">
        <div id="ssseo-gsc-result" class="card card-body" style="min-height:90px; overflow:auto;">
          <div class="text-muted">No results yet. Choose a post or enter a URL and click Inspect.</div>
        </div>
        <div class="card card-body">
          <h6 class="mb-2">Raw response</h6>
          <pre id="ssseo-gsc-raw" style="white-space:pre-wrap;word-break:break-word; min-height:90px;"></pre>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
jQuery(function($){
  if (!window.SSSEO_GSC) {
    window.SSSEO_GSC = (function(){
      let ctx = null;
      return {
        getCurrentPage(){ return ctx; },
        setCurrentPage(v){ ctx = v; window.dispatchEvent(new CustomEvent('ssseo:gsc:context')); },
        clearCurrentPage(){ ctx = null; window.dispatchEvent(new CustomEvent('ssseo:gsc:context')); }
      };
    })();
  }

  const POST_URL = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
  if (typeof window.ajaxurl === 'undefined') window.ajaxurl = POST_URL;

  const $pt = $('#ssseo-gsc-pt'), $list = $('#ssseo-gsc-post'), $filter = $('#ssseo-gsc-search');
  const $curr = $('<div id="ssseo-gsc-current" class="small text-muted mt-2"></div>').insertAfter($('.card-title').closest('.d-flex'));

  function renderCurrent(){
    const ctx = (window.SSSEO_GSC && SSSEO_GSC.getCurrentPage()) || null;
    if (ctx && ctx.url) {
      const title = ctx.title ? $('<div/>').text(ctx.title).html() : '';
      $curr.html('Current Page: <a href="'+ ctx.url +'" target="_blank" rel="noopener">' + (title || ctx.url) + '</a> ' +
                 '<button type="button" class="button-link delete ssseo-gsc-clear" style="margin-left:6px">clear</button>');
    } else {
      $curr.text('Current Page: none');
    }
  }
  $(document).on('click', '.ssseo-gsc-clear', function(){ SSSEO_GSC && SSSEO_GSC.clearCurrentPage(); renderCurrent(); });
  window.addEventListener('ssseo:gsc:context', renderCurrent);

  function loadPostsByType(cb){
    const type = $pt.val();
    $list.prop('disabled', true).empty().append($('<option>').text('Loading…'));
    $.post(POST_URL, { action: 'ssseo_get_posts_by_type', post_type: type }, function(res){
      $list.empty();
      if (res && res.success && res.data) { $list.append(res.data); }
      else { $list.append($('<option>').text('No posts found.')); }
      if (typeof cb === 'function') cb();
    }).fail(function(xhr){
      const msg = 'Network error loading posts' + (xhr && xhr.status ? (' (HTTP '+xhr.status+')'):'') + '.';
      $list.empty().append($('<option>').text(msg));
      if (typeof cb === 'function') cb();
    }).always(function(){ $list.prop('disabled', false); });
  }

  function applyFilter(){
    const needle = ($filter.val() || '').toLowerCase();
    $('#ssseo-gsc-post option').each(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(needle) !== -1);
    });
  }

  $pt.on('change', function(){ loadPostsByType(applyFilter); });
  $filter.on('input', applyFilter);

  (function restoreContext(){
    renderCurrent();
    const ctx = (window.SSSEO_GSC && SSSEO_GSC.getCurrentPage()) || null;
    if (ctx && ctx.post_type) $pt.val(ctx.post_type);
    loadPostsByType(function(){
      if (ctx && ctx.post_id) {
        $list.val(String(ctx.post_id));
        try { $list.find('option[value="'+ String(ctx.post_id) +'"]')[0].scrollIntoView({ block: 'nearest' }); } catch(e){}
      }
      applyFilter();
    });
  })();

  const $spin = $('#ssseo-gsc-spinner'), $msg = $('#ssseo-gsc-msg');
  const $out  = $('#ssseo-gsc-result'),  $raw = $('#ssseo-gsc-raw');

  function beginInspect(message){
    $spin.show(); $msg.text(message || 'Inspecting…');
    $out.html('<div class="text-muted">Requesting URL Inspection…</div>');
    $raw.text('');
  }
  function endInspect(){ $spin.hide(); }

  function renderResultPayload(d){
    const sitemapsHTML = (Array.isArray(d.sitemaps) && d.sitemaps.length)
      ? d.sitemaps.map(function(x){
          try { new URL(x); return '<a href="'+ x +'" target="_blank" rel="noopener">'+ x +'</a>'; }
          catch(e){ return x; }
        }).join('<br>')
      : '';

    const urlEsc  = d.inspectionUrl ? d.inspectionUrl : '';
    const urlLink = urlEsc ? ('<a href="'+ urlEsc +'" target="_blank" rel="noopener">'+ urlEsc +'</a>') : '';

    const openGSC = d.openInGsc
      ? ('<a class="button button-secondary" href="'+ d.openInGsc +'" target="_blank" rel="noopener">Open in Search Console</a>')
      : '';

    const verdictBadge = d.verdict
      ? `<span class="badge ${String(d.verdict).toLowerCase()==='pass'?'bg-success':'bg-danger'}">${d.verdict}</span>`
      : '';

    const html = `
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div><strong>Result</strong> ${verdictBadge}</div>
        <div>${openGSC || ''}</div>
      </div>
      <div class="ssseo-kv">
        <div class="k">URL</div><div class="v">${urlLink || ''}</div>
        <div class="k">Coverage</div><div class="v">${d.coverage || ''}</div>
        <div class="k">Indexing State</div><div class="v">${d.indexingState || ''}</div>
        <div class="k">Last Crawl</div><div class="v">${d.lastCrawlTime || ''}</div>
        <div class="k">Page Fetch</div><div class="v">${d.pageFetchState || ''}</div>
        <div class="k">Robots.txt</div><div class="v">${d.robotsTxtState || ''}</div>
        <div class="k">Google Canonical</div><div class="v"><code>${(d.googleCanonical || '')}</code></div>
        <div class="k">User Canonical</div><div class="v"><code>${(d.userCanonical || '')}</code></div>
        <div class="k">Sitemaps</div><div class="v">${sitemapsHTML || ''}</div>
      </div>
      <hr>
      <div><strong>Indexed?</strong> ${d.is_indexed ? '✅ Yes' : '❌ No'}</div>
    `;
    $out.html(html);
    $raw.text(JSON.stringify(d.raw || {}, null, 2));
    $msg.text('Done.');
  }

  function handleAjaxFail(xhr){
    const detail = (xhr && xhr.responseText) ? (': ' + xhr.responseText) : '';
    $out.html('<div class="text-danger">Network error'+detail+'</div>');
    $msg.text('Failed.');
  }

  // Inspect selected post
  $('#ssseo-gsc-inspect').on('click', function(){
    const postId = $list.val();
    const siteUrl = $('#ssseo-gsc-property').val();
    if (!postId) { alert('Select a post/page to inspect.'); return; }
    if (!siteUrl) { alert('Enter the verified siteUrl property.'); return; }

    const $btn = $(this); $btn.prop('disabled', true); beginInspect();
    $.post(POST_URL, {
      action: 'ssseo_gsc_inspect_post',
      nonce: '<?php echo esc_js($gsc_nonce); ?>',
      post_id: postId,
      site_url: siteUrl
    }, function(res){
      if (res && res.success && res.data) {
        renderResultPayload(res.data);
        const title = ($list.find('option:selected').text() || '').replace(/\s+\(ID\s*\d+\)\s*$/, '');
        if (window.SSSEO_GSC) {
          SSSEO_GSC.setCurrentPage({ post_id: parseInt(postId,10), post_type: $pt.val(), url: res.data.inspectionUrl || '', title: title });
          renderCurrent();
        }
      } else {
        const err = (res && res.data) ? res.data : 'Unknown error';
        $raw.text(typeof err === 'string' ? err : JSON.stringify(err, null, 2));
        $out.html('<div class="text-danger">Error: ' + (typeof err === 'string' ? err : (err.message || 'Request failed')) + '</div>');
        $msg.text('Failed.');
      }
    }).fail(handleAjaxFail).always(function(){ endInspect(); $btn.prop('disabled', false); });
  });

  // Inspect manual URL
  function doManualInspect() {
    const manualUrl = $('#ssseo-gsc-manual-url').val().trim();
    const siteUrl   = $('#ssseo-gsc-property').val();
    if (!manualUrl) { alert('Enter a URL to inspect.'); return; }
    if (!siteUrl)   { alert('Enter the verified siteUrl property.'); return; }

    beginInspect('Inspecting URL…');
    $.post(POST_URL, {
      action: 'ssseo_gsc_inspect_manual',
      nonce: '<?php echo esc_js($gsc_nonce); ?>',
      url: manualUrl,
      site_url: siteUrl
    }, function(res){
      if (res && res.success && res.data) {
        renderResultPayload(res.data);
        if (window.SSSEO_GSC) {
          SSSEO_GSC.setCurrentPage({ post_id: 0, post_type: '', url: res.data.inspectionUrl || manualUrl, title: res.data.inspectionUrl || manualUrl });
          renderCurrent();
        }
      } else {
        const err = (res && res.data) ? res.data : 'Unknown error';
        $raw.text(typeof err === 'string' ? err : JSON.stringify(err, null, 2));
        $out.html('<div class="text-danger">Error: ' + (typeof err === 'string' ? err : (err.message || 'Request failed')) + '</div>');
        $msg.text('Failed.');
      }
    }).fail(handleAjaxFail).always(endInspect);
  }

  $('#ssseo-gsc-inspect-url').on('click', doManualInspect);
  $('#ssseo-gsc-manual-url').on('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); doManualInspect(); }});
});
</script>
