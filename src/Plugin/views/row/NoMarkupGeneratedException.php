<?php
/**
 * @file
 * Contains \Drupal\panels\Plugin\views\row\NoMarkupGeneratedException.
 */

namespace Drupal\layout_plugin_views\Plugin\views\row;

/**
 * Exception which can be thrown if a render function did not generate markup.
 */
class NoMarkupGeneratedException extends \RuntimeException {}
