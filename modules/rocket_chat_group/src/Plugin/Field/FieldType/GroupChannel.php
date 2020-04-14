<?php


namespace Drupal\rocket_chat_group\Plugin\Field\FieldType;


use Drupal;
use Drupal\Component\Utility\Random;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Collection\Channels;
use Drupal\rocket_chat_api\RocketChat\Collection\Groups;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Drupal\rocket_chat_api\RocketChat\Drupal8State;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;

/**
 * Class GroupChannel
 *
 * @package Drupal\rocket_chat_group\Plugin\Field\FieldType
 *
 * @fieldType(
 *   id="channel",
 *   label= @Translation("Channel"),
 *   category = @Translation("Rocket Chat"),
 *   module = "rocket_chat_group",
 *   description = @Translation("Name of this groups Channel in Chat (e.a. #&lt;Channel&gt"),
 *   default_widget = "GroupChannelWidget",
 *   default_formatter = "GroupChannelFormatter"
 * )
 */
class GroupChannel extends FieldItemBase {

  /**
   * Defines field item properties.
   *
   * Properties that are required to constitute a valid, non-empty item should
   * be denoted with \Drupal\Core\TypedData\DataDefinition::setRequired().
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An array of property definitions of contained properties, keyed by
   *   property name.
   *
   * @see \Drupal\Core\Field\BaseFieldDefinition
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['value'] = DataDefinition::create('any')
      ->setLabel(t('Channel name'))
      ->setDescription('Name of the Channel in Chat like #channelName');
    return $properties;
  }

  /**
   * Returns the schema for the field.
   *
   * This method is static because the field schema information is needed on
   * creation of the field. FieldItemInterface objects instantiated at that
   * time are not reliable as field settings might be missing.
   *
   * Computed fields having no schema should return an empty array.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array
   *   An empty array if there is no schema, or an associative array with the
   *   following key/value pairs:
   *   - columns: An array of Schema API column specifications, keyed by column
   *     name. The columns need to be a subset of the properties defined in
   *     propertyDefinitions(). The 'not null' property is ignored if present,
   *     as it is determined automatically by the storage controller depending
   *     on the table layout and the property definitions. It is recommended to
   *     avoid having the column definitions depend on field settings when
   *     possible. No assumptions should be made on how storage engines
   *     internally use the original column name to structure their storage.
   *   - unique keys: (optional) An array of Schema API unique key definitions.
   *     Only columns that appear in the 'columns' array are allowed.
   *   - indexes: (optional) An array of Schema API index definitions. Only
   *     columns that appear in the 'columns' array are allowed. Those indexes
   *     will be used as default indexes. Field definitions can specify
   *     additional indexes or, at their own risk, modify the default indexes
   *     specified by the field-type module. Some storage engines might not
   *     support indexes.
   *   - foreign keys: (optional) An array of Schema API foreign key
   *     definitions. Note, however, that the field data is not necessarily
   *     stored in SQL. Also, the possible usage is limited, as you cannot
   *     specify another field as related, only existing SQL tables,
   *     such as {taxonomy_term_data}.
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar',
          'length' => 2048,
          'description' => 'Name of the Channel in Chat like #channelName',
        ],
      ],
    ];
  }


  /**
   * {@inheritDoc}
   */
  public function isEmpty() {
    try {
      $value = $this->get('value')->getValue();
    } catch (MissingDataException $e) {
      //Data is Mussing. ergo its NULL.
      $value = NULL;
    }
    $test = $this->values ;
    return $value === NULL || $value === '';
  }

  /**
   * @inheritDoc
   * @throws \Exception
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();

    $values['value'] =$random->word(random_int(3, 24));
    return $values;
  }

  /**
   * @inheritDoc
   */
  public function preSave() {
    parent::preSave();
    $entity = $this->getEntity();
    if ($entity->isNew()) {
      $isNew = $entity->isNew();
      $logger = Drupal::logger("Rocket Chat Group Channel");
      $logger->info("Channel Created [" .$this->get('value')->getValue() . "]");

    }
    else {
      $original = $entity->original;
      $fd = $this->getFieldDefinition();
      $newValue = $this->get('value')->getValue();
      $oldValue = $original->get($fd->getName())->getValue()[0]['value'];
      $rcState = new Drupal8State(Drupal::service('state'));
      $apiConfig = new Drupal8Config(
        Drupal::configFactory(),
        Drupal::moduleHandler(),
        Drupal::state(),
        Drupal::messenger()
      );
      $apiClient = new ApiClient($apiConfig);
      $channels = new Channels($rcState, $apiClient);
      $channelsList = $channels->getCache();
      $safeChannelName = Channel::toSafeChannelName($oldValue);
      $channelList = [];
      foreach ($channelsList as $channelList) {
        if (strcmp($channelList['name'], $safeChannelName) === 0) {
          break;
        }
        else {
          $channelList = [];
        }
      }
      if (empty($channelList)) {
        $channels = new Groups($rcState, $apiClient);
        $channelsList = $channels->getCache();
        $channelList = [];
        foreach ($channelsList as $channelList) {
          if (strcmp($channelList['name'], $safeChannelName) === 0) {
            break;
          }
          else {
            $channelList = [];
          }
        }
      }

      $channelType = 0;
      if (isset($channelList['t'])) {
        switch ($channelList['t']) {
          case "c": //channel
            $channelType = $channelType | Channel::PUBLIC_CHANNEL;
            break;
          case "p": //group
            $channelType = $channelType | Channel::PRIVATE_CHANNEL;
            break;
        }
      }
      if (isset($channelList['ro'])) {
        if (!$channelList['ro']) {
          $channelType = $channelType | Channel::WRITE;
        }
        $channelType = $channelType | Channel::READ;
      }
      if ($channelType > 0) {
        $channel = new Channel($channelType, $oldValue);
        $cp = $channel->getChannelProxy($apiClient);
        $channel->changeChannelName($apiClient, $newValue);
        //not renameing non existant channel
      }
    }
  }

}
