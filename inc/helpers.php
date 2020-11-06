<?php
/**
 * This file contains helper functions.
 *
 * @package Homepages
 */

/**
 * Get the latest homepage ID.
 *
 * @return int $homepage_id The latest homepage ID.
 */
function get_latest_homepage_id() {
	return \Homepages::instance()->get_latest_homepage_id();
}
