<?php
if (!defined('ABSPATH')) exit;
?>

<h4 class="mb-3">Fix YouTube Embeds</h4>
<p>This will find <code>&lt;iframe&gt;</code> embeds from YouTube and wrap them in a Bootstrap 5 <code>.ratio-16x9</code> responsive container. It will also remove any fixed width/height attributes.</p>

<form id="ssseo-fix-youtube-form">
  <button type="submit" class="btn btn-danger">Fix YouTube Embeds in All Posts</button>
</form>

<div id="ssseo-fix-youtube-result" class="alert alert-success mt-3 d-none"></div>
