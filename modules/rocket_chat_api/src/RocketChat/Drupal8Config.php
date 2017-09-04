<?php
/**
 * Created by 040lab b.v. using PhpStorm from Jetbrains.
 * User: Lawri van Buël
 * Date: 20/06/17
 * Time: 16:38
 */

namespace Drupal\rocket_chat_api\RocketChat;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rocket_chat_api\RocketChat\Config as RocketChatConfig;

/**
 * Class Drupal8Config connects the API with the drupal system.
 *
 * @package RocketChat
 */
class Drupal8Config implements RocketChatConfig, ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  protected $moduleHandler;

  protected $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The ModuleHandler to interact with loaded modules.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, StateInterface $state) {
    $this->config = $config_factory->get('rocket_chat.settings');
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
  }

  /**
   * Retrieves the Storage from a Drupal 7 Variables container
   * @param String $ElementName
   *   Name of the Variable to get.
   * @param String $Default
   *   A Optional default for when none is found.
   *
   * @return mixed
   *   The value stored or the default or NUll.
   */
  function getElement($ElementName, $Default = NULL) {
//    $rUID = $this->state->get('rocket_chat_uid',NULL);
//    $rUIT = $this->state->get('rocket_chat_uit',NULL);
    switch ($ElementName) {
      case 'rocket_chat_url': //fallthrough and modify
        $ElementName = "server";
      default:
        $value = $this->config->get($ElementName);
        if(empty($value)) {
          $value = $Default;
        }
        return $value;
      case 'rocket_chat_uid': //fallthrough
      case 'rocket_chat_uit': //fallthrough
        return $this->state->get($ElementName,$Default);

    }
  }

  /**
   * @param String $ElementName
   *  Key value to set in the Config Backend.
   * @param String $newValue
   *  the new Value to store.
   *
   * @return void
   */
  function setElement($ElementName, $newValue) {
    $config = $this->config;//('rocket_chat.settings');
    switch ($ElementName) {
      case 'rocket_chat_url': //fallthrough and modify
        $ElementName = "url";
      default:
        $config->clear($ElementName)->set($ElementName,$newValue)->save();
        break;
      case 'rocket_chat_uid': //fallthrough
      case 'rocket_chat_uit': //fallthrough
        $this->state->set($ElementName,$newValue);
        break;
    }
    return;


//    return variable_set($ElementName,$newValue);
  }

  /**
   * is this a Debug / verbose Run.
   *
   * @return boolean
   */
  function isDebug() {
    return $this->moduleHandler->moduleExists('devel');
  }

  /**
   * Get a function pointer to teh function to use for JsonDecodeing.
   *
   * @return mixed
   */
  function getJsonDecoder() {
//    return 'JSON::decode';//'drupal_json_decode';
    return '\Drupal\Component\Serialization\Json::decode';
  }



  /**
   * @param String $message
   *   Message to report back.
   * @param String $type
   *   Type or Level of the Message
   *
   * @return mixed
   */
  function notify($message, $type) {
     return drupal_set_message($message,$type);
  }

  /**
   * Instantiates a new instance of this class.
   *
   * This is a factory method that returns a new instance of this class. The
   * factory should pass any needed dependencies into the constructor of this
   * class, but not the container itself. Every call to this method must return
   * a new instance of this class; that is, it may not implement a singleton.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('state')
    );
  }
}