<?php

namespace Drupal\rocket_chat_api\RocketChat {

  /*
   * Created by 040lab b.v. using PhpStorm from Jetbrains.
   * User: Lawri van Buël
   * Date: 20/06/17
   * Time: 16:38
   */

  use Drupal;
  use Drupal\Core\Config\ConfigFactoryInterface;
  use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
  use Drupal\Core\Extension\ModuleHandlerInterface;
  use Drupal\Core\Messenger\MessengerInterface;
  use Drupal\Core\State\StateInterface;
  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
   * Class Drupal8Config connects the API with the drupal system.
   *
   * @package RocketChat
   */
  class Drupal8Config implements RocketChatConfigInterface, ContainerInjectionInterface {

    /**
     * The config factory.
     *
     * Subclasses should use the self::config() method, which may be overridden
     * to address specific needs when loading config, rather than this property
     * directly.
     * See \Drupal\Core\Form\ConfigFormBase::config() for an example of this.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $config;

    /**
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;

    /**
     * The messenger.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected  $Logger;

    /**
     * Constructs a \Drupal\system\ConfigFormBase object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The factory for configuration objects.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The ModuleHandler to interact with loaded modules.
     * @param \Drupal\Core\State\StateInterface $state
     *   The state interface to manipulate the States.
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     */
    public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, StateInterface $state, MessengerInterface $messenger) {
      $this->config = $config_factory->get('rocket_chat.settings');
      $this->moduleHandler = $moduleHandler;
      $this->state = $state;
      $this->messenger = $messenger;
      $this->Logger = Drupal::logger("Rocket Chat API: Config");
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
      /** @var ConfigFactoryInterface $configFactory */
      $configFactory = $container->get('config.factory');

      /** @var ModuleHandlerInterface $modulehandler */
      $modulehandler = $container->get('module_handler');

      /** @var StateInterface $stateInterface */
      $stateInterface = $container->get('state');

      /** @var MessengerInterface $messenger */
      $messenger = $container->get('messenger');

      return new static(
        $configFactory,
        $modulehandler,
        $stateInterface,
        $messenger
      );
    }

    /**
     * {@inheritdoc}
     */
    public function getElement($elementName, $default = NULL) {
      switch ($elementName) {
        /** @noinspection PhpMissingBreakStatementInspection */
        case 'rocket_chat_url':
          // Fallthrough and modify.
          $elementName = "server";
        default:
          $value = $this->config->get($elementName);
          if (empty($value)) {
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
     * {@inheritdoc}
     */
    public function setElement($elementName, $newValue) {
      $config = $this->config;
      switch ($elementName) {
        /** @noinspection PhpMissingBreakStatementInspection */
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
          $this->state->delete($elementName);
          if (!empty($newValue)) {
            $this->state->set($elementName, $newValue);
          }
          break;
      }
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug() {
      return $this->state->get("rocket.chat.debugMode", FALSE);
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonDecoder() {
      return '\Drupal\Component\Serialization\Json::decode';
    }

    /**
     * {@inheritdoc}
     */
    public function notify($message, $type) {
      return $this->messenger->addMessage("$message",$type);
    }

    /**
     * Check if we got everything to connect to a Client.
     *
     * @return bool
     */
    public function isReady() {
      return (
        !empty($this->getElement('rocket_chat_url')) &&
        !empty($this->getElement('rocket_chat_uid')) &&
        !empty($this->getElement('rocket_chat_uit'))
      );
  }

    /**
     * Log a specific action
     *
     * @param $message
     *   Message to log.
     * @param $level
     *   a string value if either "error"|"warning"|"info"|"debug" to indicate the level of this log message.
     *
     * @return void
     */
    public function log($message, $level) {
      $this->Logger->log($level, $message);
    }
  }
}
