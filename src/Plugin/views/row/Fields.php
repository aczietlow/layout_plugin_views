<?php

/**
 * @file
 * Contains \Drupal\panels\Plugin\views\row\Fields.
 */

namespace Drupal\layout_plugin_views\Plugin\views\row;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\layout_plugin\Plugin\Layout\LayoutPluginManagerInterface;
use Drupal\layout_plugin_views\Exceptions\NoMarkupGeneratedException;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The layout_plugin_views 'fields' row plugin
 *
 * This displays fields in a panel.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "layout_plugin_views_fields",
 *   title = @Translation("Layout fields"),
 *   help = @Translation("Displays the fields in a layout rather than using a simple row template."),
 *   theme = "views_view_fields",
 *   display_types = {"normal"}
 * )
 */
class Fields extends \Drupal\views\Plugin\views\row\Fields {
  /**
   * @var array
   */
  private $regionMap;

  /**
   * @var \Drupal\layout_plugin\Plugin\Layout\LayoutPluginManagerInterface
   */
  private $layoutPluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutPluginManagerInterface $layout_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->layoutPluginManager = $layout_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.layout_plugin'));
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['layout']['default'] = '';
    $options['default_region']['default'] = '';

    $options['assigned_regions']['default'] = [];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    try {
      $layout_definition = $this->getSelectedLayoutDefinition();
    }
    catch (\Exception $e) {
      $definitions = $this->layoutPluginManager->getDefinitions();
      $layout_definition = array_shift($definitions);
    }

    if (!empty($layout_definition)) {
      $form['layout'] = [
        '#type' => 'select',
        '#title' => $this->t('Panel layout'),
        '#options' => $this->layoutPluginManager->getLayoutOptions(['group_by_category' => TRUE]),
        '#default_value' => $layout_definition['id'],
      ];

      $form['default_region'] = [
        '#type' => 'select',
        '#title' => $this->t('Default region'),
        '#description' => $this->t('Defines the region in which the fields will be rendered by default.'),
        '#options' => $layout_definition['region_names'],
        '#default_value' => $this->getDefaultRegionMachineName(),
      ];

      $form['assigned_regions'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Assign regions'),
        '#description' => $this->t('You can use the dropdown menus above to select a region for each field to be rendered in.'),
      ];

      foreach ($this->displayHandler->getFieldLabels() as $field_name => $field_label) {
        $form['assigned_regions'][$field_name] = [
          '#type' => 'select',
          '#options' => $layout_definition['region_names'],
          '#title' => $field_label,
          '#default_value' => $this->getAssignedRegionMachineName($field_name),
          '#empty_option' => $this->t('Default region'),
        ];
      }
    }
  }

  /**
   * Retrieves the machine name of the selected layout.
   *
   * @return string
   */
  protected function getSelectedLayoutMachineName() {
    return $this->options['layout'];
  }

  /**
   * Retrieves the definition of the selected layout.
   *
   * @return array
   */
  protected function getSelectedLayoutDefinition() {
    return $this->layoutPluginManager->getDefinition($this->getSelectedLayoutMachineName());
  }

  /**
   * Retrieves the machine name of the region set to be the default region.
   *
   * @return string
   */
  protected function getDefaultRegionMachineName() {
    return $this->options['default_region'];
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
  protected function getAssignedRegionMachineName($field_machine_name) {
    if (isset($this->options['assigned_regions'][$field_machine_name])) {
      return $this->options['assigned_regions'][$field_machine_name];
    }
    else {
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    /** @var \Drupal\views\ResultRow $row */
    $build = $this->renderFieldsIntoRegions($row, $this->getRegionMap());
    return $this->buildLayoutRenderArray($build);
  }

  /**
   * Renders the row's fields into the regions specified in the region map.
   *
   * @param \Drupal\views\ResultRow $row
   * @param array $region_map @see ::getRegionMap
   *
   * @return \Drupal\Component\Render\MarkupInterface[]
   *   An array of MarkupInterface objects keyed by region machine name.
   */
  protected function renderFieldsIntoRegions(ResultRow $row, array $region_map) {
    $build = [];
    foreach ($region_map as $region => $fieldsToRender) {
      if (!empty($fieldsToRender)) {
        try {
          $build[$region]['#markup'] = $this->renderFields($row, $fieldsToRender);
        }
        catch (NoMarkupGeneratedException $e) {
          // We don't want to render empty regions, so we do nothing.
        }
      }
    }

    return $build;
  }

  /**
   * Renders the fields.
   *
   * @param \Drupal\views\ResultRow $row
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase[] $fieldsToRender
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *
   * @throws NoMarkupGeneratedException
   */
  protected function renderFields(ResultRow $row, array $fieldsToRender) {
    // We have to override the available fields for rendering so we create a
    // backup of the original fields.
    $original_fields = $this->getViewFieldDefinitions();
    $this->setViewFieldDefinitions($fieldsToRender);

    // We can not just return a render array with a clone of a filtered view
    // because views assigns the view object just before rendering, which
    // results in all fields being rendered in each region.
    // We therefore have to force rendering outside of the render context of
    // this request.
    $renderer = $this->getRenderer();
    $markup = $renderer->executeInRenderContext(new RenderContext(), function () use ($row, $renderer) {
      // @codeCoverageIgnoreStart
      // We can never reach this code in our unit tests because we mocked out
      // the renderer. These two methods are however defined and tested by core.
      // There is no need for them to be tested by our unit tests.
      $render_array = parent::render($row);
      return $renderer->render($render_array);
      // @codeCoverageIgnoreEnd
    });

    // Restore the original fields.
    $this->setViewFieldDefinitions($original_fields);

    if (empty($markup)) {
      throw new NoMarkupGeneratedException();
    }
    return $markup;
  }

  /**
   * Retrieves the field property of the view.
   *
   * @return \Drupal\views\Plugin\views\field\FieldPluginBase[]
   */
  protected function getViewFieldDefinitions() {
    return $this->view->field;
  }

  /**
   * Sets the field property of the view.
   *
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase[] $fieldDefinitions
   */
  protected function setViewFieldDefinitions(array $fieldDefinitions) {
    $this->view->field = $fieldDefinitions;
  }

  /**
   * Retrieves the region map.
   *
   * @return array
   *  An array with arrays of FieldPluginBase objects keyed by the machine name
   *  of the region they are assigned to.
   */
  protected function getRegionMap() {
    if (empty($this->regionMap)) {
      $this->generateRegionMap();
    }

    return $this->regionMap;
  }

  /**
   * Generates the region map.
   */
  private function generateRegionMap() {
    $this->regionMap = [];
    foreach ($this->getViewFieldDefinitions() as $field_name => $field_definition) {
      $region_machine_name = $this->fieldHasValidAssignment($field_name) ? $this->getAssignedRegionMachineName($field_name) : $this->getDefaultRegionMachineName();
      $this->regionMap[$region_machine_name][$field_name] = $field_definition;
    }
  }

  /**
   * Gets the machine names of all regions in the selected layout.
   *
   * @return string[]
   */
  protected function getRegionNamesForSelectedLayout() {
    $definition = $this->getSelectedLayoutDefinition();
    $available_regions = array_keys($definition['region_names']);
    return $available_regions;
  }

  /**
   * Determines if the given field is assigned to an existing region.
   *
   * @param string $field_name
   *
   * @return bool
   */
  protected function fieldHasValidAssignment($field_name) {
    return $this->selectedLayoutHasRegion($this->getAssignedRegionMachineName($field_name));
  }

  /**
   * Determines if the given machine name is a region in the selected layout.
   *
   * @param string $region_name
   *
   * @return bool
   */
  protected function selectedLayoutHasRegion($region_name) {
    return in_array($region_name, $this->getRegionNamesForSelectedLayout());
  }

  /**
   * Builds a renderable array for the selected layout.
   *
   * @param MarkupInterface[] $rendered_regions
   *  An array of MarkupInterface objects keyed by the machine name of the
   *  region they should be rendered in. @see ::renderFieldsIntoRegions.
   *
   * @return array
   *  Renderable array for the selected layout.
   */
  protected function buildLayoutRenderArray(array $rendered_regions) {
    if (!empty($rendered_regions)) {
      /** @var \Drupal\layout_plugin\Plugin\Layout\LayoutInterface $layout */
      $layout = $this->layoutPluginManager->createInstance($this->getSelectedLayoutMachineName(), []);
      return $layout->build($rendered_regions);
    }
    return $rendered_regions;
  }

}
