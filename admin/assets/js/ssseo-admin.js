jQuery(document).ready(function($) {
    function loadPosts(postType) {
        $.post(ajaxurl, {
            action: 'ssseo_get_posts_by_type',
            post_type: postType
        }, function(response) {
            $('#ssseo-posts').html(response);
        });
    }

    $('#ssseo-post-type').on('change', function() {
        loadPosts(this.value);
    });

    $('#ssseo-yoast-set-indexfollow').on('click', function() {
        const selected = $('#ssseo-posts').val();
        if (!selected.length) {
            alert('Select at least one post.');
            return;
        }

        $.post(ajaxurl, {
            action: 'ssseo_yoast_set_index_follow',
            post_ids: selected,
            _wpnonce: ssseo_admin.nonce
        }, function(response) {
            alert(response.data || 'Operation completed');
        });
    });

    loadPosts($('#ssseo-post-type').val()); // Initial load
});
