<?php
/**
 * This file contains helper functions.
 *
 * @package Homepages
 */

namespace Homepages;

/**
 * Get the latest homepage ID.
 *
 * @return int $homepage_id The latest homepage ID.
 */
function get_latest_homepage_id() {
	return ( new Homepages() )->get_latest_homepage_id();
}
