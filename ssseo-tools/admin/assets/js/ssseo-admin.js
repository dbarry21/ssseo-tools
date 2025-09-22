jQuery(document).ready(function($){
  function loadPosts(postType){
    $.post(ajaxurl,{action:'ssseo_get_posts_by_type',post_type:postType},function(response){
      $('#ssseo-posts').html(response);
    });
  }

  $('#ssseo-post-type').on('change',function(){
    loadPosts(this.value);
  });

  $('#ssseo-yoast-set-indexfollow').on('click',function(){
    const selected=$('#ssseo-posts').val();
    if(!selected||!selected.length){alert('Select at least one post.');return;}
    $.post(ajaxurl,{action:'ssseo_yoast_set_index_follow',post_ids:selected,_wpnonce:(window.ssseo_admin&&ssseo_admin.nonce)||''},function(response){
      alert((response&&response.data)||'Operation completed');
    });
  });

  // Optional (if you have buttons wired elsewhere in your UI):
  $(document).on('click','#ssseo_bulk_reset_canonical',function(){
    const ids=$('#ssseo-posts').val(); if(!ids||!ids.length){alert('Select at least one post.');return;}
    $.post(ajaxurl,{action:'ssseo_bulk_reset_canonical',post_ids:ids,_wpnonce:(window.ssseo_admin&&ssseo_admin.nonce)||''},function(r){alert((r&&r.data)||'Canonical reset complete');});
  });
  $(document).on('click','#ssseo_bulk_clear_canonical',function(){
    const ids=$('#ssseo-posts').val(); if(!ids||!ids.length){alert('Select at least one post.');return;}
    $.post(ajaxurl,{action:'ssseo_bulk_clear_canonical',post_ids:ids,_wpnonce:(window.ssseo_admin&&ssseo_admin.nonce)||''},function(r){alert((r&&r.data)||'Canonical cleared');});
  });

  loadPosts($('#ssseo-post-type').val());

  // ---- Clone Service Areas to Parents ----

  // Live filter for Source selector
  $(document).on('input','#ssseo-clone-sa-source-filter',function(){
    const q=$(this).val().toLowerCase(); const $sel=$('#ssseo-clone-sa-source');
    $sel.find('option').each(function(){const txt=$(this).text().toLowerCase(); $(this).toggle(txt.indexOf(q)!==-1);});
  });

  // Run clone
  $(document).on('click','#ssseo-clone-sa-run',function(){
    const $btn=$(this),$spinner=$('#ssseo-clone-sa-spinner'),$results=$('#ssseo-clone-sa-results');
    const nonce=$('#ssseo_bulk_clone_sa_nonce').val();
    const sourceId=$('#ssseo-clone-sa-source').val()||'';
    const targetIds=$('#ssseo-clone-sa-targets').val()||[];
    const createDraft=$('#ssseo-clone-sa-draft').is(':checked');
    const skipExisting=$('#ssseo-clone-sa-skip-existing').is(':checked');
    const debug=$('#ssseo-clone-sa-debug').is(':checked');
    const slugRaw=$('#ssseo-clone-sa-slug').val()||'';
    $results.empty().append('<p>Starting…</p>');
    if(!nonce||!sourceId||targetIds.length===0){$results.append('<p class="text-danger">Please choose a source and at least one target parent.</p>');return;}
    $btn.prop('disabled',true); $spinner.show();
    $.ajax({
      url:ajaxurl,method:'POST',dataType:'json',
      data:{action:'ssseo_clone_sa_to_parents',nonce:nonce,source_id:sourceId,target_parent_ids:targetIds,as_draft:createDraft?1:0,skip_existing:skipExisting?1:0,debug:debug?1:0,new_slug:slugRaw}
    })
    .done(function(resp){
      if(!resp||!resp.success){const msg=(resp&&resp.data)?resp.data:'Unknown error';$results.append('<pre class="text-danger">'+$('<div>').text(msg).html()+'</pre>');return;}
      if(resp.data&&resp.data.log){resp.data.log.forEach(function(line){$results.append('<div>'+$('<div>').text(line).html()+'</div>');});}else{$results.append('<p>Done.</p>');}
    })
    .fail(function(){ $results.append('<pre class="text-danger">Request failed.</pre>'); })
    .always(function(){ $btn.prop('disabled',false); $spinner.hide(); });
  });
});
