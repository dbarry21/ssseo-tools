<?php

/**

 * Init file for Video Blog module

 * Loads modular components (e.g., youtube.php)

 */



defined('ABSPATH') || exit;



// Load the YouTube functionality module

require_once plugin_dir_path(__FILE__) . 'youtube.php';

