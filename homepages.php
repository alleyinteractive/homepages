<?php
/**
 * Plugin Name: Homepages
 * Description: Framework for developers to easily manage Homepages.
 * Author: Alley
 * Version: 0.1.0
 * Author URI: https://alley.co
 *
 * @package Homepages
 */

namespace Homepages;

// Homepages Class.
require_once __DIR__ . '/classes/class-homepages.php';

// Helpers.
require_once __DIR__ . '/inc/helpers.php';

$homepages_class_instance = new Homepages();
$homepages_class_instance->setup();
