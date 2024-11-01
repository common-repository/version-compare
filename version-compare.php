<?php

/**
 * Plugin Name: Plugin Version Compare
 * Plugin URI: https://github.com/bake/versioncompare/
 * Description: Show version informations in the plugin overview.
 * Version: 1.0.4
 * Author: bakemon
 * Author URI: https://github.com/bake/
 * License: GPLv2 or later
 * Text Domain: versioncompare
 */

namespace VersionCompare;

function format(
  string $plugin_name,
  string $plugin_version,
  string $wordpress_version
): string {
  $title = vsprintf(__('%s %s is compatible with %s %s.', 'versioncompare'), [
    $plugin_name,
    $plugin_version,
    'WordPress',
    $wordpress_version,
  ]);
  return "
    <span title=\"{$title}\">
      {$wordpress_version}
    </span>
  ";
}

add_filter('plugin_row_meta', function (
  array $plugin_meta,
  string $plugin_file,
  array $plugin_data,
  string $status
): array {
  /**
   * Filter if the version of a given plugin should be printed.
   *
   * @param bool $show If the versions should be shown.
   * @param string $plugin_file The plugins relative path.
   */
  $show = apply_filters('versioncompare_show', true, $plugin_file);
  if (!$show) {
    return $plugin_meta;
  }

  /**
   * Filter the index at which the versions should be printed.
   *
   * @param int $index The position, zero based.
   */
  $index = apply_filters('versioncompare_index', 1);
  // Add an empty string to the meta array. This is why every return filters
  // empty elements.
  array_splice($plugin_meta, $index, 0, '');

  $headers = ['Tested' => 'Tested up to'];

  // Query data from the plugins local readme.
  $readme = WP_PLUGIN_DIR . '/' . dirname($plugin_file) . '/readme.txt';
  $installed = @get_file_data($readme, $headers, 'plugin');
  if ($installed['Tested'] === '') {
    return array_filter($plugin_meta);
  }
  $plugin_meta[$index] .=
    format($plugin_data['Name'], $plugin_data['Version'], $installed['Tested']);

  // There is no update available.
  if (!isset($plugin_data['new_version'])) {
    return array_filter($plugin_meta);
  }

  // Get meta data of the current release from WordPress' public SVN server.
  $new_transient =
    "versioncompare_{$plugin_file}_{$plugin_data['new_version']}";
  $new = get_transient($new_transient);
  if ($new === false) {
    // We could use plugin_api() to request meta data about a plugin and get the
    // bonus of being able to call is_wp_error() of the response, but using
    // get_file_data() again way we're not introducing a new function to the
    // plugin.
    $readme = "https://plugins.svn.wordpress.org/{$plugin_data['slug']}"
      . "/tags/{$plugin_data['new_version']}/readme.txt";
    $new = @get_file_data($readme, $headers, 'plugin');
    /**
     * Filter for the time in seconds the fetched version should be cached in
     * the database.
     *
     * @param int $expiration Duration in seconds.
     */
    $expiration = apply_filters('versioncompare_expiration', HOUR_IN_SECONDS);
    set_transient($new_transient, $new, $expiration);
  }
  if ($new['Tested'] === '') {
    return array_filter($plugin_meta);
  }
  // The local string might be less accurate and may not include the bugfix
  // version. Thus it should be enough to test if the remote version starts with
  // the installed one.
  if (strpos($new['Tested'] . '.', $installed['Tested'] . '.') === 0) {
    return array_filter($plugin_meta);
  }
  $plugin_meta[$index] .= ' / '
    . format($plugin_data['Name'], $plugin_data['new_version'], $new['Tested']);

  return array_filter($plugin_meta);
}, 10, 4);
