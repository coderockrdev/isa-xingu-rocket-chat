<?php


namespace Drupal\rocket_chat_group\Plugin\Field\FieldFormatter;


use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Class GroupChannelFormatter
 *
 * @package Drupal\rocket_chat_group\Plugin\Field\FieldFormatter
 * @FieldFormatter(
 *   id = "GroupChannelFormatter",
 *   label = @Translation("Rocket.Chat Channel Formatter"),
 *   field_types={
 *     "channel",
 *   },
 *   description = @Translation("Formatter to handle Rocket.Chat Channel Names.")
 * )
 */
class GroupChannelFormatter extends FormatterBase {

  /**
   * Builds a renderable array for a field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $elements2 = [];

    foreach ($items as $delta => $item) {
      $view_value = $this->viewValue($item);
      $elements2[$delta] = $view_value;
    }

    /** @var \Drupal\rocket_chat_group\Plugin\Field\FieldType\GroupChannel $item */
    foreach($items as $key => $item){
      $channelName = $item->channelName;//$item->value;//$item->getValue['channelName'];
      if(empty($channelName)) {
        $channelName = "";
      } else {
        $channelName = "#" . $channelName;
      }
      $elements[$key]['#type'] = "text_format";
      $elements[$key]['#title'] = $this->t("Channel");
      $elements[$key]['#base_type'] = "text_field";
      $elements[$key]['#format'] = "plain_text";
      $elements[$key]["#context"]['value'] = $channelName;
    }
    return $elements;
  }


  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   The textual output generated as a render array.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return [
      '#type' => 'inline_template',
      '#template' => '{{ value|raw }}',//|nl2br
      '#context' => ['value' => $item->value],
    ];
  }

}
