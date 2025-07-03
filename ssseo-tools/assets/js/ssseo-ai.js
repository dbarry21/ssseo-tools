jQuery(document).ready(function($) {
  const descSelBtn      = $('#ssseo_ai_selected_generate');
  const descTypeBtn     = $('#ssseo_ai_type_generate');
  const titleSelBtn     = $('#ssseo_ai_selected_generate_title');
  const titleTypeBtn    = $('#ssseo_ai_type_generate_title');
  const typeSelect      = $('#ssseo_ai_pt_filter');
  const postSelect      = $('#ssseo_ai_post_id');
  const searchBox       = $('#ssseo_ai_post_search');
  const status          = $('#ssseo_ai_status');
  const results         = $('#ssseo_ai_results');

  // 1) Populate the <select> with options for a given type
  function loadPostsForType( postType ) {
    const arr = ssseoPostsByType[ postType ] || [];
    postSelect.empty();
    arr.forEach(function(item) {
      $('<option>')
        .val(item.id)
        .text(item.title + ' (ID: ' + item.id + ')')
        .appendTo(postSelect);
    });
  }

  // 2) On page load, fill with the default type
  loadPostsForType( ssseoDefaultType );
  typeSelect.val( ssseoDefaultType );

  // 3) When Post Type changes, reload, clear search & logs
  typeSelect.on('change', function() {
    const newType = $(this).val();
    loadPostsForType( newType );
    searchBox.val('');
    results.empty();
    status.text('');
  });

  // 4) Live-filter the <select> options as you type
  searchBox.on('keyup', function() {
    const query = $(this).val().toLowerCase();
    $('#ssseo_ai_post_id option').each(function() {
      const text = $(this).text().toLowerCase();
      $(this).toggle( text.indexOf(query) > -1 );
    });
  });

  // 5) Helper: append a log line and auto-scroll
  function logMessage( msg ) {
    $('<div>').text(msg).appendTo(results);
    results.scrollTop( results[0].scrollHeight );
  }

  // 6) Core loop: generate + save (descriptions or titles). 
  //    `mode` is either 'description' or 'title'
  function processPostIDs( ids, postType, mode ) {
    let index = 0;
    let successCount = 0;

    function processNext() {
      if ( index >= ids.length ) {
        logMessage(`✅ Updated ${ successCount } ${ mode === 'description' ? 'description(s)' : 'title(s)' }.`);
        status.text(`Finished ${ successCount } of ${ ids.length } ${ mode }s.`);
        // Re-enable all buttons
        descSelBtn.prop('disabled', false);
        descTypeBtn.prop('disabled', false);
        titleSelBtn.prop('disabled', false);
        titleTypeBtn.prop('disabled', false);
        return;
      }

      const postID = ids[index];
      const label  = mode === 'description' ? 'Meta' : 'Title';
      status.text(`Generating ${ label } for [${ postType }] ID ${ postID } (${ index + 1 }/${ ids.length })`);
      logMessage(`→ [${ postType }] #${ postID } (${ label }): requesting ChatGPT…`);

      // 6a) Generate via ChatGPT (different action depending on mode)
      const generateAction = mode === 'description'
        ? 'ssseo_ai_generate_meta'
        : 'ssseo_ai_generate_title';

      $.ajax({
        type:     'POST',
        url:      ssseoAI.ajax_url,
        dataType: 'json',
        data: {
          action:  generateAction,
          nonce:   ssseoAI.nonce,
          post_id: postID
        },
        beforeSend() {
          console.log('AJAX generate payload:', {
            action:  generateAction,
            nonce:   ssseoAI.nonce,
            post_id: postID
          });
        }
      })
      .done(function(rsp) {
        console.log('AJAX generate response:', rsp);
        if ( rsp.success && rsp.data.generated ) {
          const text = rsp.data.generated;
          logMessage(`← [${ postType }] #${ postID } (${ label }): Generated → "${ text }"`);

          // 6b) Save into Yoast (meta or title)
          const saveAction = mode === 'description'
            ? 'ssseo_ai_save_meta'
            : 'ssseo_ai_save_title';

          status.text(`Saving ${ label } to [${ postType }] ID ${ postID }…`);

          $.ajax({
            type:     'POST',
            url:      ssseoAI.ajax_url,
            dataType: 'json',
            data: {
              action:  saveAction,
              nonce:   ssseoAI.nonce,
              post_id: postID,
              text:    text
            },
            beforeSend() {
              console.log('AJAX save payload:', {
                action:  saveAction,
                nonce:   ssseoAI.nonce,
                post_id: postID,
                text:    text
              });
            }
          })
          .done(function(saveRsp) {
            console.log('AJAX save response:', saveRsp);
            if ( saveRsp.success ) {
              successCount++;
              logMessage(`✔ [${ postType }] #${ postID } (${ label }): Saved.`);
            } else {
              logMessage(`✘ [${ postType }] #${ postID } (${ label }): Save error: ${ saveRsp.data }`);
            }
          })
          .fail(function(jqXHR, textStatus, errorThrown) {
            const err = `✘ [${ postType }] #${ postID } (${ label }): Save AJAX failure (${ textStatus }: ${ errorThrown })`;
            console.error('Save AJAX error:', jqXHR.responseText || textStatus);
            logMessage(err);
          })
          .always(function() {
            index++;
            processNext();
          });

        } else {
          const msg = rsp.data || 'Unknown error';
          logMessage(`✘ [${ postType }] #${ postID } (${ label }): Generate error: ${ msg }`);
          index++;
          processNext();
        }
      })
      .fail(function(jqXHR, textStatus, errorThrown) {
        const err = `✘ [${ postType }] #${ postID } (${ label }): Generate AJAX failure (${ textStatus }: ${ errorThrown })`;
        console.error('Generate AJAX error:', jqXHR.responseText || textStatus);
        logMessage(err);
        index++;
        processNext();
      });
    }

    processNext();
  }

  // ---------------------------------------------------
  // 7) “Generate & Save Descriptions” for Selected
  // ---------------------------------------------------
  descSelBtn.on('click', function(e) {
    e.preventDefault();
    results.empty();
    status.text('');

    const selectedIDs = postSelect.val() || [];
    if ( selectedIDs.length === 0 ) {
      status.text('Please select at least one post.');
      return;
    }

    // Disable all four buttons
    descSelBtn.prop('disabled', true);
    descTypeBtn.prop('disabled', true);
    titleSelBtn.prop('disabled', true);
    titleTypeBtn.prop('disabled', true);

    const postType = typeSelect.val();
    status.text(`Processing ${ selectedIDs.length } description(s)…`);
    processPostIDs( selectedIDs, postType, 'description' );
  });

  // ---------------------------------------------------
  // 8) “Generate & Save Descriptions” for This Type
  // ---------------------------------------------------
  descTypeBtn.on('click', function(e) {
    e.preventDefault();
    results.empty();
    status.text('');

    const postType = typeSelect.val();
    const allArr   = ssseoPostsByType[ postType ] || [];
    const allIDs   = allArr.map(obj => obj.id);

    if ( allIDs.length === 0 ) {
      status.text(`No published posts found for post type "${ postType }".`);
      return;
    }

    // Disable all four buttons
    descSelBtn.prop('disabled', true);
    descTypeBtn.prop('disabled', true);
    titleSelBtn.prop('disabled', true);
    titleTypeBtn.prop('disabled', true);

    status.text(`Processing ${ allIDs.length } description(s) for type "${ postType }"…`);
    processPostIDs( allIDs, postType, 'description' );
  });

  // ---------------------------------------------------
  // 9) “Generate & Save Titles” for Selected
  // ---------------------------------------------------
  titleSelBtn.on('click', function(e) {
    e.preventDefault();
    results.empty();
    status.text('');

    const selectedIDs = postSelect.val() || [];
    if ( selectedIDs.length === 0 ) {
      status.text('Please select at least one post.');
      return;
    }

    // Disable all four buttons
    descSelBtn.prop('disabled', true);
    descTypeBtn.prop('disabled', true);
    titleSelBtn.prop('disabled', true);
    titleTypeBtn.prop('disabled', true);

    const postType = typeSelect.val();
    status.text(`Processing ${ selectedIDs.length } title(s)…`);
    processPostIDs( selectedIDs, postType, 'title' );
  });

  // ---------------------------------------------------
  // 10) “Generate & Save Titles” for This Type
  // ---------------------------------------------------
  titleTypeBtn.on('click', function(e) {
    e.preventDefault();
    results.empty();
    status.text('');

    const postType = typeSelect.val();
    const allArr   = ssseoPostsByType[ postType ] || [];
    const allIDs   = allArr.map(obj => obj.id);

    if ( allIDs.length === 0 ) {
      status.text(`No published posts found for post type "${ postType }".`);
      return;
    }

    // Disable all four buttons
    descSelBtn.prop('disabled', true);
    descTypeBtn.prop('disabled', true);
    titleSelBtn.prop('disabled', true);
    titleTypeBtn.prop('disabled', true);

    status.text(`Processing ${ allIDs.length } title(s) for type "${ postType }"…`);
    processPostIDs( allIDs, postType, 'title' );
  });

});
