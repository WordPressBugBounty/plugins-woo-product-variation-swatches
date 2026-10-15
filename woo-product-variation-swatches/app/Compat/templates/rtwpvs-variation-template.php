<?php
/**
 * Empty stand-in for the Pro template `rtwpvs-variation-template.php`.
 *
 * Used by rtwpvs()->get_template_path() when the real Pro templates directory
 * cannot be resolved (e.g. legacy Pro installed under a non-default folder),
 * so Pro's wc_get_template() call includes a harmless file instead of a
 * root-level path. Renders nothing; ProCompatibility reports the problem.
 *
 * @package Rtwpvs
 */

defined( 'ABSPATH' ) || exit;
