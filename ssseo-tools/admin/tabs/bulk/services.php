<?php
if (!defined('ABSPATH')) exit;
?>

<h4 class="mb-3">Clone Service Pages to Service Areas</h4>
<p>This will duplicate selected <code>service</code> pages as <code>service_area</code> drafts and assign a new parent.</p>

<form id="ssseo-clone-services-form">
  <div class="row g-3">

    <div class="col-md-6">
      <label for="ssseo_services_select" class="form-label">Select Services</label>
      <select class="form-select" name="service_ids[]" id="ssseo_services_select" multiple size="8">
        <?php foreach ( $top_services as $service ) : ?>
          <option value="<?= esc_attr($service->ID); ?>"><?= esc_html($service->post_title); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label for="ssseo_service_area_parent" class="form-label">New Parent (Service Area)</label>
      <select class="form-select" name="service_area_parent" id="ssseo_service_area_parent">
        <?php foreach ( $top_service_areas as $area ) : ?>
          <option value="<?= esc_attr($area->ID); ?>"><?= esc_html($area->post_title); ?></option>
        <?php endforeach; ?>
      </select>

      <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" value="1" name="generate_about_the_area" id="generate_about_the_area">
        <label class="form-check-label" for="generate_about_the_area">
          Generate "About the Area" using AI
        </label>
      </div>
    </div>

    <div class="col-12 mt-4">
      <button type="submit" class="btn btn-primary">Clone Selected Services</button>
    </div>

    <div class="col-12 mt-3">
      <div id="ssseo-clone-services-result" class="alert alert-info d-none"></div>
    </div>

  </div>
</form>
