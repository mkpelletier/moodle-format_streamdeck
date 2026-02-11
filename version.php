<?php
/**
 * Streamdeck course format – version info.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026021101;     // Bumped for refactor.
$plugin->requires  = 2024110400;     // Moodle 5.0+.
$plugin->component = 'format_streamdeck';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0-beta5';
$plugin->dependencies = [];