<?php
/**
 * Subtab: Bulk → Search & Replace (Elementor-aware)
 * File: admin/tabs/bulk/search-replace.php
 *
 * - Recursively replaces strings in _elementor_data and _elementor_page_settings.
 * - Also handles post_title, post_content, post_excerpt.
 * - Dry-run preview before executing.
 */

if (!defined('ABSPATH')) exit;

// Dedicated nonce for this action
$ssseo_sr_nonce = wp_create_nonce('ssseo_bulk_search_replace');

// Public post types to select from (same convention as bulk.php)
$post_types = get_post_types(['public' => true, 'show_ui' => true], 'objects');
?>
<div class="mb-3">
  <p class="text-muted mb-2">
    Search &amp; replace across Titles, Content, Excerpts, and Elementor fields (<code>_elementor_data</code>, <code>_elementor_page_settings</code>).  
    Use <strong>Dry-Run</strong> to preview changes before applying.
  </p>
</div>

<form id="ssseo-sr-form" class="border rounded p-3 bg-light">
  <input type="hidden" id="ssseo_sr_nonce" value="<?php echo esc_attr($ssseo_sr_nonce); ?>">

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Search for <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="ssseo_sr_search" placeholder="Exact text or regex (if enabled)" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Replace with</label>
      <input type="text" class="form-control" id="ssseo_sr_replace" placeholder="Leave blank to remove">
    </div>

    <div class="col-md-6">
      <label class="form-label">Post Types</label>
      <select id="ssseo_sr_types" class="form-select" multiple size="6" aria-describedby="sr-types-help">
        <?php foreach ($post_types as $obj): ?>
          <option value="<?php echo esc_attr($obj->name); ?>">
            <?php echo esc_html($obj->labels->name . ' (' . $obj->name . ')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div id="sr-types-help" class="form-text">Select none to include all public types.</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">Scope</label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_titles" checked>
        <label class="form-check-label" for="ssseo_sr_titles">Titles</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_content" checked>
        <label class="form-check-label" for="ssseo_sr_content">Content</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_excerpt" checked>
        <label class="form-check-label" for="ssseo_sr_excerpt">Excerpts</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_elementor" checked>
        <label class="form-check-label" for="ssseo_sr_elementor">Elementor data</label>
      </div>

      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_case_sensitive">
        <label class="form-check-label" for="ssseo_sr_case_sensitive">Case-sensitive</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_regex">
        <label class="form-check-label" for="ssseo_sr_regex">Use Regular Expression</label>
      </div>

      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="ssseo_sr_dryrun" checked>
        <label class="form-check-label" for="ssseo_sr_dryrun"><strong>Dry-Run (preview only)</strong></label>
      </div>
    </div>

    <div class="col-12">
      <button type="button" id="ssseo_sr_preview" class="btn btn-secondary me-2">Preview (Dry-Run)</button>
      <button type="button" id="ssseo_sr_execute" class="btn btn-primary">Run Replace</button>
      <span id="ssseo_sr_spinner" class="spinner-border spinner-border-sm align-middle ms-3 d-none" role="status"></span>
    </div>
  </div>
</form>

<div class="mt-4">
  <h5>Results</h5>
  <div id="ssseo_sr_log" class="border rounded p-3 bg-white" style="min-height:160px; max-height:420px; overflow:auto;">
    <em>No results yet.</em>
  </div>
</div>

<script>
(function(){
  const $ = (sel) => document.querySelector(sel);
  const logBox  = $("#ssseo_sr_log");
  const spinner = $("#ssseo_sr_spinner");

  function collect() {
    const types = Array.from(document.querySelectorAll("#ssseo_sr_types option:checked")).map(o=>o.value);
    return {
      nonce: $("#ssseo_sr_nonce").value,
      search: $("#ssseo_sr_search").value || "",
      replace: $("#ssseo_sr_replace").value || "",
      post_types: types,
      scope: {
        titles:   $("#ssseo_sr_titles").checked ? 1 : 0,
        content:  $("#ssseo_sr_content").checked ? 1 : 0,
        excerpt:  $("#ssseo_sr_excerpt").checked ? 1 : 0,
        elementor:$("#ssseo_sr_elementor").checked ? 1 : 0
      },
      case_sensitive: $("#ssseo_sr_case_sensitive").checked ? 1 : 0,
      regex:          $("#ssseo_sr_regex").checked ? 1 : 0
    };
  }

  function appendLog(html){
    if (logBox.querySelector('em')) logBox.innerHTML = '';
    const div = document.createElement('div');
    div.className = 'small border-bottom py-2';
    div.innerHTML = html;
    logBox.appendChild(div);
    logBox.scrollTop = logBox.scrollHeight;
  }

  function run(dryRun){
    const payload = collect();
    if (!payload.search) { alert('Please enter a Search value.'); return; }
    payload.dry_run = dryRun ? 1 : 0;

    spinner.classList.remove('d-none');
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'ssseo_bulk_search_replace',
        payload: JSON.stringify(payload)
      })
    })
    .then(r=>r.json())
    .then(data=>{
      spinner.classList.add('d-none');
      if (!data || !data.success) {
        appendLog('<span class="text-danger">Error: '+ (data && data.data ? data.data : 'Unknown') +'</span>');
        return;
      }
      const out = data.data || {};
      if (out.summary) {
        appendLog('<strong>Summary:</strong> scanned '+(out.summary.scanned||0)+' post(s), matched '+(out.summary.matched||0)+' post(s), replacements '+(out.summary.replacements||0)+(dryRun?' <em>(dry-run)</em>':''));
      }
      (out.items || []).forEach(item=>{
        appendLog('<code>#'+item.id+'</code> '+(item.title || '(no title)')+' <span class="text-muted">['+item.type+']</span><br>Changed fields: '+ (item.changed_fields && item.changed_fields.length ? item.changed_fields.join(', ') : '<em>none</em>') + (item.notes ? '<br><span class="text-muted">'+item.notes+'</span>' : ''));
      });
    })
    .catch(e=>{
      spinner.classList.add('d-none');
      appendLog('<span class="text-danger">Fetch error: '+e.message+'</span>');
    });
  }

  $("#ssseo_sr_preview").addEventListener('click', function(){ run(true); });
  $("#ssseo_sr_execute").addEventListener('click', function(){
    if (!confirm('This will modify content. Make sure you have a backup. Continue?')) return;
    run(false);
  });
})();
</script>
<hr class="my-5">
<h5>Restore from Backup</h5>
<p class="text-muted">If a replace went sideways, use this to restore posts that have an <code>_ssseo_sr_backup</code> snapshot.</p>

<form id="ssseo-sr-restore" class="border rounded p-3 bg-light">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Post Types</label>
      <select id="ssseo_sr_restore_types" class="form-select" multiple size="6">
        <?php foreach ($post_types as $obj): ?>
          <option value="<?php echo esc_attr($obj->name); ?>">
            <?php echo esc_html($obj->labels->name . ' (' . $obj->name . ')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Select none to include all public types.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Limit</label>
      <input type="number" class="form-control" id="ssseo_sr_restore_limit" value="200" min="1">
      <div class="form-text">How many posts to attempt per run (safety throttle).</div>
    </div>
    <div class="col-12">
      <button type="button" id="ssseo_sr_restore_btn" class="btn btn-danger">Restore from Backup</button>
      <span id="ssseo_sr_restore_spinner" class="spinner-border spinner-border-sm align-middle ms-3 d-none" role="status"></span>
    </div>
  </div>
</form>

<div class="mt-3">
  <div id="ssseo_sr_restore_log" class="border rounded p-3 bg-white" style="min-height:120px; max-height:420px; overflow:auto;">
    <em>No restore attempts yet.</em>
  </div>
</div>

<script>
(function(){
  const $ = s => document.querySelector(s);
  const log = $("#ssseo_sr_restore_log");
  const spinner = $("#ssseo_sr_restore_spinner");
  function logLine(html){
    if (log.querySelector('em')) log.innerHTML = '';
    const d = document.createElement('div');
    d.className = 'small border-bottom py-2';
    d.innerHTML = html;
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
  }
  $("#ssseo_sr_restore_btn").addEventListener('click', function(){
    if (!confirm('Restore from backup? This will overwrite current titles/content/excerpts and try to recover Elementor JSON from revisions.')) return;

    const types = Array.from(document.querySelectorAll("#ssseo_sr_restore_types option:checked")).map(o=>o.value);
    spinner.classList.remove('d-none');
    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'ssseo_sr_restore_last',
        payload: JSON.stringify({
          nonce: document.querySelector('#ssseo_sr_nonce').value,
          post_types: types,
          limit: parseInt(document.querySelector('#ssseo_sr_restore_limit').value || '200', 10)
        })
      })
    })
    .then(r=>r.json())
    .then(data=>{
      spinner.classList.add('d-none');
      if (!data || !data.success) {
        logLine('<span class="text-danger">Error: '+ (data && data.data ? data.data : 'Unknown') +'</span>');
        return;
      }
      const out = data.data || {};
      logLine('<strong>Restored:</strong> '+(out.restored||0)+' posts, Elementor recovered: '+(out.el_recovered||0)+'; scanned '+(out.scanned||0)+'.');
      (out.items || []).forEach(it=>{
        logLine('<code>#'+it.id+'</code> '+(it.title || '(no title)')+' <span class="text-muted">['+it.type+']</span> – '+it.msg);
      });
    })
    .catch(e=>{
      spinner.classList.add('d-none');
      logLine('<span class="text-danger">Fetch error: '+e.message+'</span>');
    });
  });
})();
</script>
