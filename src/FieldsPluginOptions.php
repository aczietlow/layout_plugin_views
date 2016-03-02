<?php

namespace Drupal\layout_plugin_views;


use Drupal\layout_plugin_views\Plugin\views\row\Fields;

class FieldsPluginOptions {

  /**
   * @var \Drupal\layout_plugin_views\Plugin\views\row\Fields
   */
  private $plugin;

  public static function fromFieldsPlugin(Fields $plugin) {
    return new static($plugin);
  }

  private function __construct(Fields $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * Retrieves the machine name of the selected layout.
   *
   * @return string
   */
  public function getLayout() {
    return $this->plugin->options['layout'];
  }

  /**
   * Retrieves the machine name of the region set to be the default region.
   *
   * @return string
   */
  public function getDefaultRegion() {
    return $this->plugin->options['default_region'];
  }

  /**
   * Retrieves the region machine name that was assigned to the given field.
   *
   * @param string $field_machine_name
   *
   * @return string
   *  The machine name of the region that the given field is assigned to or an
   *  empty string if the field is not assigned to a region.
   */
  public function getAssignedRegion($field_machine_name) {
    if (isset($this->plugin->options['assigned_regions'][$field_machine_name])) {
      return $this->plugin->options['assigned_regions'][$field_machine_name];
    }
    else {
      return '';
    }
  }
}
