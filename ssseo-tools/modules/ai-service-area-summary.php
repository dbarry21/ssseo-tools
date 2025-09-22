<?php
/**
 * Module: AI HTML Summary for Service Areas (single + helpers)
 * - Model: gpt-4o-mini (change with SSSEO_AI_MODEL)
 * - Saves to ACF "html_excerpt" or post meta "html_excerpt"
 * - Single-post metabox + AJAX (bulk stays in admin/ajax.php to avoid double hooks)
 * - Prompts include: Title, City/State, Yoast Focus Keyword, WP Excerpt, About/Notes, Content Snippet
 * - NEW: Bold key phrases (focus keyword + city_state) before save
 */
if ( ! defined('ABSPATH') ) exit;

/** ---------- Settings ---------- */
if ( ! defined('SSSEO_AI_MODEL') )   define('SSSEO_AI_MODEL', 'gpt-4o-mini');
if ( ! defined('SSSEO_AI_TIMEOUT') ) define('SSSEO_AI_TIMEOUT', 45);

/** =========================
 * DEFAULT PROMPTS (SEO + copywriter)
 * ========================= */
if ( ! defined('SSSEO_AI_SYS_DEFAULT') ) {
  define('SSSEO_AI_SYS_DEFAULT',
    "You are a professional SEO and copywriter.\n".
    "- Write a concise, conversion-focused HTML summary for a local service area page.\n".
    "- Aim for ~{target_words} words.\n".
    "- Use clear paragraphs (<p>) and optionally one short <ul> with up to 3 bullet items.\n".
    "- Naturally incorporate the focus keyword without stuffing; avoid repeating the post title verbatim.\n".
    "- No phone numbers, no contact info, no guarantees.\n".
    "- If linking, use <a rel=\"nofollow noopener\" target=\"_blank\">.\n".
    "- Output ONLY HTML (no markdown, no headings)."
  );
}
if ( ! defined('SSSEO_AI_USER_DEFAULT') ) {
  define('SSSEO_AI_USER_DEFAULT',
    "Post Title: {title}\n".
    "City/State: {city_state}\n".
    "Focus Keyword: {focuskw}\n".
    "Tone: {tone}\n\n".
    "Existing WP Excerpt (if any):\n{excerpt}\n\n".
    "Existing HTML Excerpt (if any):\n{html_excerpt}\n\n".
    "Optional About/Notes:\n{about}\n\n".
    "Content snippet:\n{content_snip}\n\n".
    "Write an HTML summary suitable for the page intro, using the focus keyword naturally."
  );
}

/** Persisted prompt options */
if ( ! defined('SSSEO_AI_SYS_OPTION') )  define('SSSEO_AI_SYS_OPTION',  'ssseo_ai_prompt_sys');
if ( ! defined('SSSEO_AI_USER_OPTION') ) define('SSSEO_AI_USER_OPTION', 'ssseo_ai_prompt_user');

/** API key */
if ( ! function_exists('ssseo_ai_get_api_key') ) {
  function ssseo_ai_get_api_key() {
    return trim( get_option('ssseo_openai_api_key', '') );
  }
}

/** Allowed HTML */
if ( ! function_exists('ssseo_ai_allowed_html') ) {
  function ssseo_ai_allowed_html() {
    return [
      'p'      => [],
      'br'     => [],
      'ul'     => [],
      'ol'     => [],
      'li'     => [],
      'strong' => [],
      'em'     => [],
      'a'      => [
        'href'   => true,
        'title'  => true,
        'target' => true,
        'rel'    => true,
      ],
    ];
  }
}

/** Load/save prompts */
if ( ! function_exists('ssseo_ai_get_prompts') ) {
  function ssseo_ai_get_prompts() {
    $sys  = (string) get_option(SSSEO_AI_SYS_OPTION, '');
    $user = (string) get_option(SSSEO_AI_USER_OPTION, '');
    if ($sys === '')  $sys  = SSSEO_AI_SYS_DEFAULT;
    if ($user === '') $user = SSSEO_AI_USER_DEFAULT;
    return [$sys, $user];
  }
}
if ( ! function_exists('ssseo_ai_save_prompts') ) {
  function ssseo_ai_save_prompts($sys, $user) {
    update_option(SSSEO_AI_SYS_OPTION,  (string) $sys);
    update_option(SSSEO_AI_USER_OPTION, (string) $user);
  }
}

/** Micro-template */
if ( ! function_exists('ssseo_ai_render_template') ) {
  function ssseo_ai_render_template($tpl, array $vars) {
    $rep = [];
    foreach ($vars as $k => $v) $rep['{'.$k.'}'] = (string) $v;
    return strtr((string)$tpl, $rep);
  }
}

/** Try to read Yoast Focus Keyphrase (several legacy keys) */
if ( ! function_exists('ssseo_ai_get_focuskw') ) {
  function ssseo_ai_get_focuskw($post_id) {
    $keys = [
      '_yoast_wpseo_focuskw',
      '_yoast_wpseo_focus_keyphrase',
      'yoast_wpseo_focuskw',
      'rank_math_focus_keyword',
    ];
    foreach ($keys as $k) {
      $v = (string) get_post_meta($post_id, $k, true);
      if ($v !== '') return $v;
    }
    return '';
  }
}

/** ========= NEW: Bold key phrases in safe HTML ========= */
if ( ! function_exists('ssseo_ai_bold_terms_in_html') ) {
  /**
   * Bold a small set of phrases inside HTML safely (outside tags).
   * - Works by splitting into [text|tags] parts so replacements never happen inside tags.
   * - Skips segments that are *directly* inside <strong>…</strong> to prevent double-strong.
   * - Limits replacements per term to avoid over-emphasis.
   */
  function ssseo_ai_bold_terms_in_html( $html, array $terms, $max_per_term = 2 ) {
    $html = (string) $html;
    if ($html === '' || empty($terms)) return $html;

    // Normalize terms (dedupe, trim, longest first)
    $clean = [];
    foreach ($terms as $t) {
      $t = trim((string)$t);
      if ($t !== '') $clean[$t] = mb_strlen($t);
    }
    if (empty($clean)) return $html;
    // Sort by length DESC
    uksort($clean, function($a,$b){ return mb_strlen($b) <=> mb_strlen($a); });

    // Counters
    $left = [];
    foreach (array_keys($clean) as $t) $left[$t] = (int)$max_per_term;

    // Split into tags/text parts
    $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) return $html;

    $out = [];
    $n = count($parts);
    for ($i = 0; $i < $n; $i++) {
      $part = $parts[$i];

      // If tag, pass through
      if (isset($part[0]) && $part[0] === '<') {
        $out[] = $part;
        continue;
      }

      // Determine if this text part is *directly* inside a <strong>…</strong> pair
      $prev = $i > 0 ? $parts[$i-1] : '';
      $next = $i < ($n-1) ? $parts[$i+1] : '';
      $in_strong = (stripos($prev, '<strong') === 0 && stripos($next, '</strong>') === 0);

      $segment = $part;

      if ( ! $in_strong && trim($segment) !== '' ) {
        foreach (array_keys($clean) as $term) {
          if ($left[$term] <= 0) continue;

          $pattern = '~' . preg_quote($term, '~') . '~iu';
          $segment = preg_replace_callback($pattern, function($m) use (&$left, $term) {
            if ($left[$term] <= 0) return $m[0];
            $left[$term]--;
            return '<strong>'.$m[0].'</strong>';
          }, $segment, $left[$term]); // limit per call for tiny speed-up
        }
      }

      $out[] = $segment;
    }

    $result = implode('', $out);

    // Collapse accidental nested strongs: <strong><strong>X</strong></strong> → <strong>X</strong>
    $result = preg_replace('~<strong>\s*<strong>(.*?)</strong>\s*</strong>~isu', '<strong>$1</strong>', $result);

    return $result;
  }
}

/** OpenAI call */
if ( ! function_exists('ssseo_ai_chat_call') ) {
  function ssseo_ai_chat_call($messages, $args = []) {
    $api_key = ssseo_ai_get_api_key();
    if ( ! $api_key ) return new WP_Error('no_key', 'OpenAI API key is missing.');

    $body = wp_json_encode([
      'model'       => $args['model']       ?? SSSEO_AI_MODEL,
      'temperature' => isset($args['temperature']) ? (float)$args['temperature'] : 0.6,
      'max_tokens'  => isset($args['max_tokens'])  ? (int)$args['max_tokens'] : 450,
      'messages'    => $messages,
    ]);

    $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
      'timeout' => SSSEO_AI_TIMEOUT,
      'headers' => [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
      ],
      'body'    => $body,
    ]);
    if ( is_wp_error($resp) ) return $resp;

    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300 || !is_array($json)) {
      return new WP_Error('bad_response', 'OpenAI error', ['status'=>$code, 'body'=>wp_remote_retrieve_body($resp)]);
    }
    $out = $json['choices'][0]['message']['content'] ?? '';
    return $out !== '' ? $out : new WP_Error('empty', 'OpenAI returned no content.');
  }
}

/** Generate one HTML summary (SINGLE helper used by both metabox + bulk AJAX) */
if ( ! function_exists('ssseo_ai_generate_sa_summary') ) {
  function ssseo_ai_generate_sa_summary($post_id, $opts = []) {
    $post = get_post($post_id);
    if ( ! $post || $post->post_type !== 'service_area' ) {
      return new WP_Error('bad_post', 'Invalid service_area post.');
    }

    $tone         = $opts['tone']        ?? 'professional, friendly';
    $target_words = (int)($opts['words'] ?? 160);
    $temperature  = isset($opts['temperature']) ? (float)$opts['temperature'] : 0.6;
    $model        = $opts['model']       ?? SSSEO_AI_MODEL;

    // Prompts: provided → persisted → defaults
    $sys_prompt  = isset($opts['sys_prompt'])  && $opts['sys_prompt']  !== '' ? (string)$opts['sys_prompt']  : null;
    $user_prompt = isset($opts['user_prompt']) && $opts['user_prompt'] !== '' ? (string)$opts['user_prompt'] : null;
    if ($sys_prompt === null || $user_prompt === null) {
      list($sys_opt, $user_opt) = ssseo_ai_get_prompts();
      if ($sys_prompt  === null) $sys_prompt  = $sys_opt;
      if ($user_prompt === null) $user_prompt = $user_opt;
    }

    // Context
    $title = get_the_title($post_id) ?: '';

    $city_state = '';
    if ( function_exists('get_field') ) $city_state = (string) get_field('city_state', $post_id);
    if ($city_state === '') $city_state = (string) get_post_meta($post_id, 'city_state', true);

    $focuskw = ssseo_ai_get_focuskw($post_id);

    $wp_excerpt = get_the_excerpt($post_id);
    if ($wp_excerpt === null) $wp_excerpt = '';

    $html_excerpt = '';
    if ( function_exists('get_field') ) $html_excerpt = (string) get_field('html_excerpt', $post_id);
    if ($html_excerpt === '') $html_excerpt = (string) get_post_meta($post_id, 'html_excerpt', true);

    $about = (string) get_post_meta($post_id, '_about_the_area', true);
    if ( function_exists('get_field') && $about === '' ) $about = (string) get_field('_about_the_area', $post_id);

    $content_raw  = wp_strip_all_tags( get_post_field('post_content', $post_id) );
    $content_snip = mb_substr($content_raw, 0, 2000);

    $sys_content = ssseo_ai_render_template($sys_prompt, [
      'target_words' => $target_words,
    ]);
    $usr_content = ssseo_ai_render_template($user_prompt, [
      'title'        => $title,
      'city_state'   => $city_state,
      'focuskw'      => $focuskw,
      'tone'         => $tone,
      'excerpt'      => $wp_excerpt,
      'html_excerpt' => $html_excerpt,
      'about'        => $about,
      'content_snip' => $content_snip,
      'target_words' => $target_words,
    ]);

    // Allow external code (e.g., admin AJAX logger) to inspect the final prompts
    do_action('ssseo_ai_sa_prompts_built', $post_id, [
      'system'   => $sys_content,
      'user'     => $usr_content,
      'tone'     => $tone,
      'words'    => $target_words,
      'focuskw'  => $focuskw,
      'city'     => $city_state,
      'title'    => $title,
    ]);

    $html = ssseo_ai_chat_call([
      ['role'=>'system','content'=>$sys_content],
      ['role'=>'user',  'content'=>$usr_content],
    ], [
      'model'       => $model,
      'temperature' => $temperature,
      'max_tokens'  => 700,
    ]);
    if ( is_wp_error($html) ) return $html;

    // FIRST: Bold key phrases (focuskw + city_state), THEN sanitize
    $to_bold = array_filter(array_unique([$focuskw, $city_state]));
    if ( ! empty($to_bold) ) {
      $html = ssseo_ai_bold_terms_in_html($html, $to_bold, 2);
    }

    $clean = wp_kses( trim($html), ssseo_ai_allowed_html() );

    if ( function_exists('update_field') ) @update_field('html_excerpt', $clean, $post_id);
    else update_post_meta($post_id, 'html_excerpt', $clean);

    update_post_meta($post_id, '_ssseo_ai_summary_ts', current_time('mysql'));

    return $clean;
  }
}

/** ---------- Single-post metabox (UI) ---------- */
add_action('add_meta_boxes', function(){
  add_meta_box(
    'ssseo_ai_sa_summary',
    __('AI: Generate HTML Summary', 'ssseo'),
    function($post){
      if ($post->post_type !== 'service_area') return;
      $nonce = wp_create_nonce('ssseo_ai_generate');
      $last  = get_post_meta($post->ID, '_ssseo_ai_summary_ts', true);
      ?>
      <p class="description">Click to (re)generate the HTML summary into the <code>html_excerpt</code> field.</p>

      <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
        <label for="ssseo_ai_tone" style="min-width:60px;">Tone</label>
        <select id="ssseo_ai_tone" class="regular-text">
          <option value="professional, friendly">Professional & friendly</option>
          <option value="neutral, informative">Neutral & informative</option>
          <option value="warm, reassuring">Warm & reassuring</option>
          <option value="approachable marketing">Approachable marketing</option>
          <option value="conversational">Conversational</option>
          <option value="technical">Technical</option>
          <option value="seo-optimized, clear">SEO-optimized, clear</option>
          <option value="persuasive but factual">Persuasive but factual</option>
          <option value="formal">Formal</option>
          <option value="casual">Casual</option>
        </select>
      </div>

      <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
        <label for="ssseo_ai_words" style="min-width:60px;">Words</label>
        <input id="ssseo_ai_words" type="number" class="small-text" min="80" max="400" value="160">
      </div>

      <button type="button" class="button button-primary" id="ssseo_ai_gen_btn">Generate Summary</button>
      <span id="ssseo_ai_status" style="margin-left:8px;"></span>

      <?php if ($last): ?>
        <p class="description" style="margin-top:8px;">Last generated: <?php echo esc_html($last); ?></p>
      <?php endif; ?>

      <script>
      (function(){
        const btn = document.getElementById('ssseo_ai_gen_btn');
        const out = document.getElementById('ssseo_ai_status');
        btn && btn.addEventListener('click', function(){
          out.textContent = 'Working…';
          btn.disabled = true;
          const tone  = document.getElementById('ssseo_ai_tone').value;
          const words = document.getElementById('ssseo_ai_words').value;
          fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              action: 'ssseo_ai_generate_sa_summary',
              nonce:  '<?php echo esc_js($nonce); ?>',
              post_id:'<?php echo (int)$post->ID; ?>',
              tone: tone,
              words: words
            })
          }).then(r=>r.json()).then(resp=>{
            btn.disabled = false;
            if (resp && resp.success) out.textContent = 'Done!';
            else out.textContent = 'Error: ' + (resp && resp.data ? resp.data : 'Unknown');
          }).catch(()=>{
            btn.disabled = false;
            out.textContent = 'Request failed';
          });
        });
      })();
      </script>
      <?php
    },
    'service_area','side','high'
  );
});

/** ---------- AJAX: single generation ---------- */
add_action('wp_ajax_ssseo_ai_generate_sa_summary', function(){
  if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ssseo_ai_generate') ) {
    wp_send_json_error('Bad nonce');
  }
  $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
  if ( ! current_user_can('edit_post', $post_id) ) wp_send_json_error('Insufficient permissions');

  $tone  = isset($_POST['tone'])  ? sanitize_text_field(wp_unslash($_POST['tone']))  : 'professional, friendly';
  $words = isset($_POST['words']) ? (int) $_POST['words'] : 160;

  $res = ssseo_ai_generate_sa_summary($post_id, ['tone'=>$tone, 'words'=>$words]);
  if ( is_wp_error($res) ) wp_send_json_error($res->get_error_message());
  wp_send_json_success(['html'=>$res]);
});

/**
 * NOTE:
 * The BULK AJAX handler is intentionally NOT registered here
 * to avoid conflicts with admin/ajax.php (which already registers it).
 * This file exports the reusable generator: ssseo_ai_generate_sa_summary()
 */
