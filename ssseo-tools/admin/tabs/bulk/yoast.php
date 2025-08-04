<?php
if (!defined('ABSPATH')) exit;
?>

<h4 class="mb-3">Yoast Meta Tag Updates</h4>
<p>Update the Yoast SEO <strong>meta robots</strong> tag for selected posts.</p>

<form id="ssseo-yoast-bulk-form" class="mb-4">
  <div class="row g-3">
    <div class="col-md-6">
      <label for="ssseo_post_type_select" class="form-label">Post Type</label>
      <select class="form-select" id="ssseo_post_type_select" name="post_type">
        <!-- Populated by JS -->
      </select>
    </div>

    <div class="col-md-6">
      <label for="ssseo_post_list" class="form-label">Select Posts</label>
      <select class="form-select" id="ssseo_post_list" name="post_ids[]" multiple size="6">
        <!-- Populated by JS -->
      </select>
    </div>

    <div class="col-md-12">
      <label class="form-label">Yoast Meta Robots</label>
      <select class="form-select" name="yoast_value" id="yoast_value">
        <option value="index,follow">index,follow</option>
        <option value="noindex,follow">noindex,follow</option>
        <option value="index,nofollow">index,nofollow</option>
        <option value="noindex,nofollow">noindex,nofollow</option>
      </select>
    </div>
  </div>

  <div class="mt-4">
    <button type="submit" class="btn btn-primary">Update Meta Robots</button>
    <div id="ssseo-yoast-result" class="mt-3 text-success fw-semibold"></div>
  </div>
</form>
