<?php
if (!defined('ABSPATH')) exit;

/**
 * AI Summaries — Bulk (Service Areas)
 * - Self-contained defaults so prompts always appear even if module not loaded
 * - Reads saved prompts from options if present
 */

# ---- Hard defaults (match module’s defaults; safe even if module isn’t loaded) ----
$SSSEO_AI_SYS_DEFAULT  =
"You are a professional SEO and copywriter.
- Write a concise, conversion-focused HTML summary for a local service area page.
- Aim for ~{target_words} words.
- Use clear paragraphs (<p>) and optionally one short <ul> with up to 3 bullet items.
- Bold 2–4 important phrases with <strong>…</strong> (including the focus keyword at least once) — do not overuse.
- Naturally incorporate the focus keyword without stuffing; avoid repeating the post title verbatim.
- No phone numbers, no contact info, no guarantees.
- If linking, use <a rel=\"nofollow noopener\" target=\"_blank\">.
- Output ONLY HTML (no markdown, no headings).";

$SSSEO_AI_USER_DEFAULT =
"Post Title: {title}
City/State: {city_state}
Focus Keyword: {focuskw}
Tone: {tone}

Existing WP Excerpt (if any):
{excerpt}

Existing HTML Excerpt (if any):
{html_excerpt}

Optional About/Notes:
{about}

Content snippet:
{content_snip}

Write an HTML summary suitable for the page intro. 
Use <strong>…</strong> to highlight the focus keyword and 1–3 other key phrases (sparingly).";

# ---- Load current prompts: function -> options -> hard defaults ----
$sys_prompt  = '';
$user_prompt = '';

// If the module is loaded, use its helper (includes any saved options + module defaults)
if (function_exists('ssseo_ai_get_prompts')) {
  list($sys_prompt, $user_prompt) = ssseo_ai_get_prompts();
}

// If still empty, try options directly (in case module not loaded yet)
if (trim($sys_prompt) === '')  $sys_prompt  = (string) get_option('ssseo_ai_prompt_sys',  '');
if (trim($user_prompt) === '') $user_prompt = (string) get_option('ssseo_ai_prompt_user', '');

// Final fallback to hard defaults
if (trim($sys_prompt) === '')  $sys_prompt  = $SSSEO_AI_SYS_DEFAULT;
if (trim($user_prompt) === '') $user_prompt = $SSSEO_AI_USER_DEFAULT;

// Expose defaults for “Reset to Defaults” and auto-prefill in JS (UTF-8 safe JSON)
$DEFAULT_SYS_JS = wp_json_encode($SSSEO_AI_SYS_DEFAULT, JSON_UNESCAPED_UNICODE);
$DEFAULT_USR_JS = wp_json_encode($SSSEO_AI_USER_DEFAULT, JSON_UNESCAPED_UNICODE);
?>

<h4 class="mb-3">AI Summaries — Generate HTML for Service Areas</h4>
<p class="text-muted">Creates an HTML summary in the <code>html_excerpt</code> field for selected <strong>Service Area</strong> posts using your OpenAI key.</p>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <label for="ssseo_ai_post_list" class="form-label">Select Service Areas</label>
    <select class="form-select" id="ssseo_ai_post_list" multiple size="14"></select>
    <div class="form-text">Tip: Type to jump; hold Ctrl/Cmd to multi-select.</div>
    <div class="mt-2">
      <button id="ssseo_ai_select_all" type="button" class="btn btn-link p-0 me-3">Select all</button>
      <button id="ssseo_ai_clear" type="button" class="btn btn-link p-0">Clear</button>
      <span id="ssseo_ai_count" class="ms-2 text-muted"></span>
    </div>
  </div>

  <div class="col-md-6">
    <label class="form-label">Summary Options</label>
    <div class="row g-2">
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="ssseo_ai_force" value="1">
          <label class="form-check-label" for="ssseo_ai_force">Overwrite existing <code>html_excerpt</code> if present</label>
        </div>
      </div>

      <div class="col-sm-6">
        <label class="form-label" for="ssseo_ai_tone">Tone</label>
        <select id="ssseo_ai_tone" class="form-select">
          <option value="professional, friendly" selected>Professional &amp; friendly</option>
          <option value="neutral, informative">Neutral &amp; informative</option>
          <option value="warm, reassuring">Warm &amp; reassuring</option>
          <option value="approachable marketing">Approachable marketing</option>
          <option value="conversational">Conversational</option>
          <option value="technical">Technical</option>
          <option value="seo-optimized, clear">SEO-optimized, clear</option>
          <option value="persuasive but factual">Persuasive but factual</option>
          <option value="formal">Formal</option>
          <option value="casual">Casual</option>
          <option value="custom">Custom…</option>
        </select>
      </div>
      <div class="col-sm-6" id="ssseo_ai_tone_custom_wrap" style="display:none;">
        <label class="form-label" for="ssseo_ai_tone_custom">Custom tone</label>
        <input id="ssseo_ai_tone_custom" type="text" class="form-control" placeholder="e.g., calm, authoritative, concise">
      </div>

      <div class="col-sm-4">
        <label class="form-label" for="ssseo_ai_words">Words</label>
        <input id="ssseo_ai_words" type="number" class="form-control" min="80" max="400" value="160">
      </div>
      <div class="col-sm-4">
        <label class="form-label" for="ssseo_ai_temp">Temperature</label>
        <input id="ssseo_ai_temp" type="number" step="0.1" min="0" max="1" value="0.6" class="form-control">
      </div>
      <div class="col-sm-4 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="ssseo_ai_remember" value="1" checked>
          <label class="form-check-label" for="ssseo_ai_remember">Remember prompts</label>
        </div>
      </div>

      <!-- DEBUG CONTROLS -->
      <div class="col-12 mt-2">
        <label class="form-label">Debug</label>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="ssseo_ai_dbg" value="1">
          <label class="form-check-label" for="ssseo_ai_dbg">Show final prompt in results log</label>
        </div>
        <div class="form-check mt-1 ms-3">
          <input class="form-check-input" type="checkbox" id="ssseo_ai_dbg_sys" value="1">
          <label class="form-check-label" for="ssseo_ai_dbg_sys">Include system prompt</label>
        </div>
        <div class="mt-1 ms-3" style="max-width:180px;">
          <label class="form-label mb-0" for="ssseo_ai_dbg_len">Preview length</label>
          <input id="ssseo_ai_dbg_len" type="number" class="form-control form-control-sm" min="200" max="4000" value="1200">
        </div>
      </div>
    </div>

    <!-- Advanced Prompt: OPEN by default, always prefilled -->
    <details class="mt-3" open>
      <summary><strong>Advanced Prompt</strong> (System + User Template)</summary>
      <div class="mt-3">
        <label class="form-label" for="ssseo_ai_sys_prompt">System Prompt</label>
        <textarea id="ssseo_ai_sys_prompt" class="form-control" rows="6"><?php echo esc_textarea($sys_prompt); ?></textarea>

        <label class="form-label mt-3" for="ssseo_ai_user_prompt">User Prompt Template</label>
        <textarea id="ssseo_ai_user_prompt" class="form-control" rows="10"><?php echo esc_textarea($user_prompt); ?></textarea>

        <div class="d-flex align-items-center gap-2 mt-2">
          <button id="ssseo_ai_reset_prompts" type="button" class="btn btn-outline-secondary btn-sm">Reset to Defaults</button>
          <small class="text-muted">Placeholders: <code>{title}</code>, <code>{city_state}</code>, <code>{focuskw}</code>, <code>{tone}</code>, <code>{excerpt}</code>, <code>{html_excerpt}</code>, <code>{about}</code>, <code>{content_snip}</code>, <code>{target_words}</code></small>
        </div>
      </div>
    </details>

    <div class="mt-3 d-flex align-items-center gap-2">
      <button id="ssseo_ai_run" class="btn btn-primary">Generate AI Summaries</button>
      <span id="ssseo_ai_status" class="text-muted"></span>
    </div>

    <div class="mt-3" id="ssseo_ai_result" style="display:none;">
      <div class="alert alert-info" id="ssseo_ai_summary" role="status"></div>
      <pre class="border p-2 bg-light" id="ssseo_ai_log" style="max-height:260px; overflow:auto;"></pre>
    </div>
  </div>
</div>

<script>
jQuery(function($){
  const POST_URL = (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
  const DEFAULT_SYS = <?php echo $DEFAULT_SYS_JS; ?>;
  const DEFAULT_USR = <?php echo $DEFAULT_USR_JS; ?>;

  function getBulkNonce() {
    if (window.SSSEO && SSSEO.bulkNonce) return SSSEO.bulkNonce;
    const el = document.getElementById('ssseo_bulk_nonce');
    return el && el.value ? el.value : '<?php echo esc_js( wp_create_nonce('ssseo_bulk_ops') ); ?>';
  }
  const nonce = getBulkNonce();

  const $list   = $('#ssseo_ai_post_list');
  const $run    = $('#ssseo_ai_run');
  const $force  = $('#ssseo_ai_force');
  const $tone   = $('#ssseo_ai_tone');
  const $toneC  = $('#ssseo_ai_tone_custom');
  const $toneCW = $('#ssseo_ai_tone_custom_wrap');
  const $words  = $('#ssseo_ai_words');
  const $temp   = $('#ssseo_ai_temp');
  const $status = $('#ssseo_ai_status');
  const $wrap   = $('#ssseo_ai_result');
  const $sum    = $('#ssseo_ai_summary');
  const $log    = $('#ssseo_ai_log');
  const $selAll = $('#ssseo_ai_select_all');
  const $clear  = $('#ssseo_ai_clear');
  const $count  = $('#ssseo_ai_count');

  const $sysP   = $('#ssseo_ai_sys_prompt');
  const $usrP   = $('#ssseo_ai_user_prompt');
  const $remember = $('#ssseo_ai_remember');

  // Ensure textareas are never empty in UI (first load & after cache)
  if (!$sysP.val().trim()) $sysP.val(DEFAULT_SYS);
  if (!$usrP.val().trim()) $usrP.val(DEFAULT_USR);

  // Reset button
  $('#ssseo_ai_reset_prompts').on('click', function(e){
    e.preventDefault();
    $sysP.val(DEFAULT_SYS);
    $usrP.val(DEFAULT_USR);
  });

  // UTF-8 → HEX (strict WAF-safe)
  function strToHex(str){
    const enc = new TextEncoder().encode(str || '');
    let out = '';
    for (const b of enc) out += b.toString(16).padStart(2,'0');
    return out;
  }

  // Load list
  $list.empty().append($('<option>').text('Loading…'));
  $.post(POST_URL, { action:'ssseo_sa_all_published', nonce: nonce })
    .done(resp => {
      if (resp && resp.success && resp.data && resp.data.items) {
        $list.empty();
        resp.data.items.forEach(row => $('<option>').val(row.id).text(row.title || ('(no title) #' + row.id)).appendTo($list));
        updateCount();
      } else {
        $list.empty().append($('<option>').text('No items found'));
        updateCount();
      }
    })
    .fail(xhr => {
      $list.empty().append($('<option>').text(xhr && xhr.status === 403 ? 'Failed to load (forbidden – check nonce)' : 'Failed to load'));
    });

  function updateCount(){ const n = ($list.val() || []).length; $count.text(n ? (n + ' selected') : ''); }
  $selAll.on('click', e => { e.preventDefault(); $('#ssseo_ai_post_list option').prop('selected', true); $list.trigger('change'); updateCount(); });
  $clear .on('click', e => { e.preventDefault(); $('#ssseo_ai_post_list option').prop('selected', false); $list.trigger('change'); updateCount(); });
  $list.on('change', updateCount);

  // Tone custom UI
  function refreshToneUI(){
    if ($tone.val() === 'custom') { $toneCW.show(); $toneC.attr('required','required'); }
    else { $toneCW.hide(); $toneC.removeAttr('required'); }
  }
  $tone.on('change', refreshToneUI);
  refreshToneUI();

  // --------- Chunked run (WAF-friendly) ----------
  const BATCH_SIZE = 1;   // start safe; you can raise to 2–3 after confirming
  const PAUSE_MS   = 250;

  $run.on('click', function(){
    const allIds = ($list.val() || []).map(v => parseInt(v,10)||0).filter(Boolean);
    if (!allIds.length) { alert('Select at least one Service Area.'); return; }

    const toneValue = ($tone.val() === 'custom' ? ($toneC.val() || '').trim() : $tone.val());
    if (!toneValue) { alert('Please provide a custom tone.'); return; }

    // Build a tiny config (neutral keys), optionally include prompts if you want to save them
    const wantSave = $remember.is(':checked');
    const cfgBase = {
      n: nonce,
      f: $('#ssseo_ai_force').is(':checked') ? 1 : 0,
      t: toneValue,
      w: parseInt($words.val(),10) || 160,
      x: parseFloat($temp.val() || '0.6')
    };

    // >>> NEW: attach debug flags so server logs the exact prompt <<<
    cfgBase.dg  = $('#ssseo_ai_dbg').is(':checked') ? 1 : 0;      // show user prompt in log
    cfgBase.dgs = $('#ssseo_ai_dbg_sys').is(':checked') ? 1 : 0;  // include system prompt
    cfgBase.dgl = parseInt($('#ssseo_ai_dbg_len').val(), 10) || 1200; // preview length

    $run.prop('disabled', true).addClass('disabled');
    $wrap.hide(); $sum.text(''); $log.text(''); $status.text('Starting…');

    let total = allIds.length, idx = 0, ok = 0, err = 0;

    function step(){
      if (idx >= total) {
        $status.text('');
        $sum.text('Done: ' + ok + ' succeeded, ' + err + ' failed.');
        $wrap.show();
        $run.prop('disabled', false).removeClass('disabled');
        return;
      }
      const slice = allIds.slice(idx, idx + BATCH_SIZE);
      $status.text('Working… (' + Math.min(idx + BATCH_SIZE, total) + '/' + total + ')');

      const cfg = Object.assign({}, cfgBase, { ids: slice });
      if (wantSave) {
        cfg.ps = $sysP.val();   // system
        cfg.pu = $usrP.val();   // user template
        cfg.re = 1;             // remember
      }

      $.post(POST_URL, {
        action: 'ssseo_bulk_ai_generate_summaries',
        cfg_hex: strToHex(JSON.stringify(cfg))
      })
      .done(resp => {
        if (resp && resp.success && resp.data) {
          ok  += (resp.data.ok  || 0);
          err += (resp.data.err || 0);
          const lines = (resp.data.log || []);
          if (lines.length) $log.text($log.text() + ( $log.text() ? '\n' : '' ) + lines.join('\n'));
        } else {
          err += slice.length;
          $log.text($log.text() + '\nChunk failed: ' + JSON.stringify(resp||{}, null, 2));
        }
      })
      .fail(xhr => {
        err += slice.length;
        const body = (xhr && xhr.responseText) ? (' → ' + xhr.responseText.substring(0, 200)) : '';
        $log.text($log.text() + '\nChunk failed (HTTP ' + (xhr.status||'') + ') for IDs: ' + slice.join(', ') + body);
      })
      .always(() => {
        idx += BATCH_SIZE;
        setTimeout(step, PAUSE_MS);
      });
    }
    step();
  });
});
</script>
