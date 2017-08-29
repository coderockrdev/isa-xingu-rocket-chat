<?php

namespace Drupal\livechat\Controller;

/**
 * Controller routines for block example routes.
 */
class LivechatBlockController {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'livechat';
  }

    /**
   * Generate a render array with our templated content.
   *
   * @return array
   *   A render array.
   */
  public function description() {
    $template_path = $this->getDescriptionTemplatePath();
    $template = file_get_contents($template_path);
    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#context' => $this->getDescriptionVariables(),
      ],
    ];
    return $build;
  }

  /**
   * Get full path to the template.
   *
   * @return string
   *   Path string.
   */
  protected function getDescriptionTemplatePath() {

    return drupal_get_path('module', $this->getModuleName()) . "/templates/description.html.twig";
  }


}
