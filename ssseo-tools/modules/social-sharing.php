<?php
/**
 * Social Sharing Shortcode with Bootstrap Icons + Modal
 * Usage: [social_share]
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    // Load Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');

    // ✅ Load Bootstrap Icons
    wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css');

    // Load Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], null, true);
});

// Register Shortcode
add_shortcode('social_share', 'ssseo_social_sharing_shortcode');

function ssseo_social_sharing_shortcode($atts) {
    ob_start();

    $share_url   = urlencode(get_permalink());
    $share_title = urlencode(get_the_title());

    ?>
<style>
  .modal-dialog {
    margin: 1.75rem auto;
  }

  .modal-content {
    padding: 1rem;
  }

  .btn-outline-* {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
</style>

    <!-- Trigger Button -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#socialShareModal">
        <i class="bi bi-share-fill"></i> Share
    </button>

    <!-- Modal -->
<div class="modal fade" id="socialShareModal" tabindex="-1" aria-labelledby="socialShareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;"> <!-- ✅ limit modal width -->
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="socialShareModalLabel">Share This Page</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body text-center">
        <p class="mb-3">Choose a platform:</p>

        <!-- ✅ Tighter icon layout -->
        <div class="d-flex justify-content-center flex-wrap gap-2">
          <a class="btn btn-outline-primary" href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>" target="_blank" title="Facebook" data-bs-toggle="tooltip">
            <i class="bi bi-facebook fs-4"></i>
          </a>
          <a class="btn btn-outline-info" href="https://twitter.com/intent/tweet?text=<?= $share_title ?>&url=<?= $share_url ?>" target="_blank" title="Twitter/X" data-bs-toggle="tooltip">
            <i class="bi bi-twitter-x fs-4"></i>
          </a>
          <a class="btn btn-outline-success" href="https://api.whatsapp.com/send?text=<?= $share_title ?>%20<?= $share_url ?>" target="_blank" title="WhatsApp" data-bs-toggle="tooltip">
            <i class="bi bi-whatsapp fs-4"></i>
          </a>
          <a class="btn btn-outline-danger" href="mailto:?subject=<?= $share_title ?>&body=<?= $share_url ?>" target="_blank" title="Email" data-bs-toggle="tooltip">
            <i class="bi bi-envelope-fill fs-4"></i>
          </a>
          <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= get_permalink() ?>'); this.innerHTML='<i class=\'bi bi-clipboard-check-fill\'></i>';" title="Copy Link" data-bs-toggle="tooltip">
            <i class="bi bi-clipboard fs-4"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>


    <!-- Tooltip Activation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <?php

    return ob_get_clean();
}

// Register Shortcode
add_shortcode('social_share_icon', 'ssseo_social_sharing_shortcode_icon');

function ssseo_social_sharing_shortcode_icon($atts) {
    ob_start();

    $share_url   = urlencode(get_permalink());
    $share_title = urlencode(get_the_title());

    ?>
<style>
  .modal-dialog {
    margin: 1.75rem auto;
  }

  .modal-content {
    padding: 1rem;
  }

  .btn-outline-* {
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
	button.social-icon-button {border:none;border-radius:0;width:50px;background:var(--e-global-color-primary) !important;color:white !important;}
	button.social-icon-button:hover {background:white !important;color:var(--e-global-color-primary) !important;}
</style>

    <!-- Trigger Button -->
    <button type="button" class="social-icon-button" data-bs-toggle="modal" data-bs-target="#socialShareModal">
        <i class="bi bi-share-fill"></i>
    </button>

    <!-- Modal -->
<div class="modal fade" id="socialShareModal" tabindex="-1" aria-labelledby="socialShareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;"> <!-- ✅ limit modal width -->
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="socialShareModalLabel">Share This Page</h5>
        <button type="button" class="btn btn-primary btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body text-center">
        <p class="mb-3">Choose a platform:</p>

        <!-- ✅ Tighter icon layout -->
        <div class="d-flex justify-content-center flex-wrap gap-2">
          <a class="btn btn-outline-primary" href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>" target="_blank" title="Facebook" data-bs-toggle="tooltip">
            <i class="bi bi-facebook fs-4"></i>
          </a>
          <a class="btn btn-outline-info" href="https://twitter.com/intent/tweet?text=<?= $share_title ?>&url=<?= $share_url ?>" target="_blank" title="Twitter/X" data-bs-toggle="tooltip">
            <i class="bi bi-twitter-x fs-4"></i>
          </a>
          <a class="btn btn-outline-success" href="https://api.whatsapp.com/send?text=<?= $share_title ?>%20<?= $share_url ?>" target="_blank" title="WhatsApp" data-bs-toggle="tooltip">
            <i class="bi bi-whatsapp fs-4"></i>
          </a>
          <a class="btn btn-outline-danger" href="mailto:?subject=<?= $share_title ?>&body=<?= $share_url ?>" target="_blank" title="Email" data-bs-toggle="tooltip">
            <i class="bi bi-envelope-fill fs-4"></i>
          </a>
          <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= get_permalink() ?>'); this.innerHTML='<i class=\'bi bi-clipboard-check-fill\'></i>';" title="Copy Link" data-bs-toggle="tooltip">
            <i class="bi bi-clipboard fs-4"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>


    <!-- Tooltip Activation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <?php

    return ob_get_clean();
}
