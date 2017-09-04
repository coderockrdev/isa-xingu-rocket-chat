<?php

namespace Drupal\rocket_chat_api\RocketChat;

/**
 * Created by 040lab b.v. using PhpStorm from Jetbrains.
 * User: Lawri van Buël
 * Date: 20/06/17
 * Time: 16:38
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rocket_chat_api\RocketChat\RocketChatConfigInterface as RocketChatConfig;

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
   * @param \Drupal\Core\State\StateInterface $state
   *   The state interface to manipulate the States.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, StateInterface $state) {
    $this->config = $config_factory->get('rocket_chat.settings');
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
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

  /**
   * Retrieves the Storage from a Drupal 7 Variables container.
   *
   * @param string $elementName
   *   Name of the Variable to get.
   * @param string $default
   *   A Optional default for when none is found.
   *
   * @return mixed
   *   The value stored or the default or NUll.
   */
  public function getElement($elementName, $default = NULL) {
    switch ($elementName) {
      case 'rocket_chat_url':
        // Fallthrough and modify.
        $elementName = "server";
      default:
        $value = $this->config->get($elementName);
        if(empty($value)) {
          $value = $default;
        }
        return $value;
      case 'rocket_chat_uid':
        // Fallthrough.
      case 'rocket_chat_uit':
        // Fallthrough.
        return $this->state->get($elementName, $default);

    }
  }

  /**
   * @param string $elementName
   *   Key value to set in the RocketChatConfigInterface Backend.
   * @param string $newValue
   *   the new Value to store.
   *
   * @return void
   *   The Emptyness of the Digital void is unimaginable.
   */
  public function setElement($elementName, $newValue) {
    $config = $this->config;
    switch ($elementName) {
      case 'rocket_chat_url':
        // Fallthrough and modify.
        $elementName = "url";
      default:
        $config->clear($elementName)->set($elementName, $newValue)->save();
        break;
      case 'rocket_chat_uid':
        // Fallthrough.
      case 'rocket_chat_uit':
        // Fallthrough.
        $this->state->set($elementName, $newValue);
        break;
    }
    return;
  }

  /**
   * is this a Debug / verbose Run.
   *
   * @return boolean
   *   Are we in debug mode?
   */
  public function isDebug() {
    return $this->moduleHandler->moduleExists('devel');
  }

  /**
   * Get a function pointer to the function to use for JsonDecodeing.
   *
   * @return mixed
   */
  public function getJsonDecoder() {
    return '\Drupal\Component\Serialization\Json::decode';
  }

  /**
   * Notify the backend.
   *
   * @param string $message
   *   Message to report back.
   * @param string $type
   *   Type or Level of the Message.
   *
   * @return mixed
   *   Result of notify on backend.
   */
  public function notify($message, $type) {
    return drupal_set_message($message, $type);
  }

}
