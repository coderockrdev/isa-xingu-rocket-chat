<?php

namespace Drupal\rocket_chat_group\Plugin\Block;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\group\Entity\Group;
use Drupal\group\GroupMembership;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Drupal\rocket_chat_api\RocketChat\Drupal8State;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;
use Drupal\rocket_chat_api\RocketChat\Element\User as RocketchatUser;
use Drupal\rocket_chat_group\RocketChat\moduleHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "rocket_chat_channel_block_example",
 *   admin_label = @Translation("Rocket Chat Group Channel"),
 *   category = @Translation("Rocket Chat Channel Block")
 * )
 */
class RocketChatChannelBlock extends BlockBase implements ContainerFactoryPluginInterface{

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;


  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $Logger;

  /**
   * Constructs a new AjaxFormBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler (what modules are installed).
   * @param \Drupal\Core\State\StateInterface $state
   *   State access.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Manager
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   Current Path handler.
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, StateInterface $state,EntityTypeManagerInterface $entityTypeManager,CurrentPathStack $currentPath, AccountInterface $account, LoggerChannelFactory $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentPath = $currentPath;
    $this->account = $account;
    $this->Logger = $logger->get("Rocket Chat Group Channel Block");

  }

  /**
   * Retrieves the current group based on the loaded path.
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @return \Drupal\group\Entity\Group
   *
   */
  private function retrieveGroupFromPath() {
    $current_path = $this->currentPath->getPath();
    $paths = explode("/", $current_path,4);

    /** @var \Drupal\group\Entity\Group $groupEntity */
    $groupEntity = $this->entityTypeManager->getStorage('group')
      ->load($paths[2]);
    return $groupEntity;
  }

  /**
   * {@inheritdoc}
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws \Exception
   */
  public function build() {
    $apiConfig = new Drupal8Config($this->configFactory, $this->moduleHandler, $this->state, $this->messenger);
    $apiClient = new ApiClient($apiConfig);

    if($apiConfig->isReady() && $apiClient->ping()) {
      $groupEntity = $this->retrieveGroupFromPath();
      $member = FALSE;
      if(!empty($groupEntity)) {
        $newGroup = $groupEntity->isNew();
        $groupOwner = $groupEntity->getOwner();
        $channel = NULL;
        $member = $groupEntity->getMember($this->account);
        $groupMembers = $groupEntity->getMembers();
        $groupUsers = [];
        foreach ($groupMembers as $groupMember) {
          $groupUsers[] = $groupMember->getUser();
        }
      }
      if($member !== FALSE) {
        if (!empty($groupEntity)) {
          $fieldName = moduleHelper::extractChannelMachineName($groupEntity);
          if (!empty($fieldName) && isset($groupEntity->$fieldName)) {
            foreach ($groupEntity->$fieldName->getValue() as $channelDetails) {
              foreach ($channelDetails as $channelName) {
                $channel = new Channel(Channel::READ | Channel::WRITE, $channelName);
                break;
              }
              break;
            }
            if (!empty($channel)) {
              if ($groupEntity->type->getValue()[0]['target_id'] === "closed_group") {
                $channel->setChannelType(Channel::READ | Channel::WRITE | Channel::PRIVATE_CHANNEL);
              }
              else {
                $channel->setChannelType(Channel::READ | Channel::WRITE | Channel::PUBLIC_CHANNEL);
              }
            }
          }
          if (!empty($channel) && $apiClient->ping()) {
            $chatUser = [];
            $chatUserProxy = [];
            foreach ($groupUsers as $groupUser) {
              $rcUser = new RocketchatUser(new Drupal8State($this->state),$groupUser->getAccountName(), $groupUser->getEmail(), $groupUser->getDisplayName());
              $chatUser[] = $rcUser;
              $chatUserProxy[] = $rcUser->getUserProxy($apiClient);
            }

            $chatGroupOwner = new RocketchatUser(new Drupal8State($this->state), $groupOwner->getAccountName(), $groupOwner->getEmail(), $groupOwner->getDisplayName());
            $channelProxy = $channel->getChannelProxy($apiClient);
            if(is_null($channelProxy)){
              $this->Logger->error("Channel/Group Creation / Retrieval Failed");
              return [];
            }
          }
          else {
            if($apiClient->ping()) {
              $this->messenger->addError($this->t("Channel not available., Contact the site admin."), TRUE);
            } else {
              $this->messenger->addError($this->t("Rocket Chat not available., Contact the site admin."), FALSE);
            }
          }
        }

        if (!empty($channel)) {
          $serverUrl = Drupal::configFactory()
            ->get('rocket_chat.settings')
            ->get('server');
          $channelURL = $channel->getChannelURI();
          $targetURL = "$serverUrl$channelURL?layout=embedded";
        }

        $build = [];
        $build['#cache']["max-age"] = 0;
        $build['content']['#host'] = (empty($serverUrl) ? "*" : $serverUrl);
        $build['content']['#app'] = "rocketchat://" . parse_url($serverUrl,PHP_URL_HOST);

        if ($this->canSeeDirectLinkButtons($groupEntity, $groupMember)) {
          $build['content']['#showDirectLink'] = TRUE;
        } else {
          $build['content']['#showDirectLink'] = FALSE;
        }

        if (!empty($targetURL)) {
          $build['content']['#height'] = "750px";
          $build['content']['#width'] = "1334px";
          $build['content']['#url'] = "$targetURL";

          $build['content']['#theme'] = 'rocketChatChannelBlock';
          $build['content']['#markup'] = $this->t('channel');

          return $build;
        }
        else {
          $this->messenger->addWarning($this->t("Unable to locate channel, Contact the administrator"), TRUE);
          return [];
        }
      } else {
        //Not a member.
        if($this->moduleHandler->moduleExists('devel')) {
          $this->messenger->addStatus($this->account->getDisplayName() . " is not a Member.");
        }
        return [];
      }

    } else {
        if($apiClient->ping()){
          $this->messenger->addWarning($this->t("Unable to use Chat"),TRUE);
        } else {
          $this->messenger->addError($this->t("Rocket Chat not available., Contact the site admin."), FALSE);
        }
        return [];
    }
  }

  /**
   * @param \Drupal\group\Entity\Group $groupEntity
   * @param \Drupal\group\GroupMembership $groupMember
   *
   * @return bool canAccess directLinks.
   */
  private function canSeeDirectLinkButtons(Group $groupEntity,GroupMembership $groupMember){
    return FALSE;
    //REMINDER this does not work as intended needs more work before deployement.
    /*
     * if($groupMember->hasPermission('use rocketchat direct links')){
     *   return TRUE;
     * }
     * if($groupEntity->hasPermission('use rocketchat direct links', $this->account)){
     *   return TRUE;
     * }
     * if($this->account->hasPermission('use rocketchat direct links')){
     *   return TRUE;
     * }
     *   return FALSE;
     */
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var ConfigFactoryInterface $configFactory */
    $configFactory = $container->get('config.factory');

    /** @var ModuleHandlerInterface $modulehandler */
    $modulehandler = $container->get('module_handler');

    /** @var StateInterface $stateInterface */
    $stateInterface = $container->get('state');

    /** @var MessengerInterface $messenger */
    $messenger = $container->get('messenger');

    /** @var EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $container->get("entity_type.manager");

    /** @var \Drupal\Core\Path\CurrentPathStack $pathCurrent */
    $pathCurrent = $container->get('path.current');

    /** @var AccountInterface $currentUser */
    $currentUser = $container->get('current_user');

    /** @var LoggerChannelFactory $loggerFactory */
    $loggerFactory = $container->get('logger.factory');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $messenger,
      $configFactory,
      $modulehandler,
      $stateInterface,
      $entityTypeManager,
      $pathCurrent,
      $currentUser,
      $loggerFactory
    );
  }

  /**
   * @inheritDoc
   */
  public function getCacheMaxAge() {
    //This block is always dynamic, do not cache it ever.
    return 0;
  }

}
