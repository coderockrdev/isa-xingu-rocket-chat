<?php


namespace Drupal\rocket_chat_group\RocketChat;


use Drupal;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Collection\Channels;
use Drupal\rocket_chat_api\RocketChat\Collection\Users;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Drupal\rocket_chat_api\RocketChat\Drupal8State;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;
use Drupal\rocket_chat_api\RocketChat\Element\User as RocketchatUser;

class moduleHelper {

  function test(){

  }

  public static function updateChannelType(string $groupType,Channel &$Channel){
    if($groupType === "closed_group"){
      $Channel->setChannelType(Channel::READ | Channel::WRITE | Channel::PRIVATE_CHANNEL);
    } else {
      $Channel->setChannelType(Channel::READ | Channel::WRITE | Channel::PUBLIC_CHANNEL);
    }
  }

//group_content

  /**
   * @param \Drupal\group\Entity\GroupContent $entity
   * @param string $action
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function ProcessGroupContentUpdate(GroupContent $entity, $action = "Unknown"){

    $groupSelected = $entity->getGroup();
    $groupOwner = $entity->getOwner();

    $targetUserFields  = $entity->getFields(FALSE);
    $targetGroupFields = $groupSelected->getFields(FALSE);

    $userRef = $targetUserFields['entity_id']->getValue();
    $userRefEntity = Drupal::entityTypeManager()->getStorage('user')->load($userRef[0]['target_id']);

    $groupEntity = $entity->getGroup();
    $groupType = $groupEntity->getGroupType();
    $groupEntitytypeId = $groupType->id();

    $channel = new Channel();
    $rcState = new Drupal8State(Drupal::service('state'));
    $chatGroupOwner = new RocketchatUser($rcState,$groupOwner->getAccountName(),$groupOwner->getEmail(),$groupOwner->getDisplayName());

    $channel->setOwner($chatGroupOwner);

    $fieldMachineName = self::extractChannelMachineName($groupSelected);
    $channelName = NULL;

    if(!empty($fieldMachineName) && isset($targetGroupFields[$fieldMachineName])) {
      $test[] = $targetGroupFields[$fieldMachineName]->getValue();
      foreach ($targetGroupFields[$fieldMachineName]->getValue() as $channelDetails){
        foreach ($channelDetails as $channelName){
          $channel->setChannelName($channelName);
          break;
        }
        break;
      }
      self::updateChannelType($groupEntitytypeId, $channel);
    }

    $apiConfig = new Drupal8Config(
      Drupal::configFactory(),
      Drupal::moduleHandler(),
      Drupal::state(),
      Drupal::messenger()
    );
    $apiClient = new ApiClient($apiConfig);


    switch($action){
      default:
        self::addLogEntry("no action taken on [$action] for Group [" . $groupSelected->id() . "]");
        break;
      case "insert":
        // Inserting something into a group content.
        $channel->getChannelProxy($apiClient);
        $channel->getChannelMembers($apiClient);

        /** @var \Drupal\user\Entity\User[] $GroupMembers */
        $GroupMembers = [];
        foreach ($userRef as $target){
          $targetUser = Drupal::entityTypeManager()->getStorage('user')->load($target['target_id']);
          if(!empty($targetUser)){
            $GroupMembers[] = $targetUser;
          }
          unset($targetUser);
        }
        foreach ($GroupMembers as $groupMember){
          $rcUser = new RocketchatUser($rcState,$groupMember->getAccountName(),$groupMember->getEmail(),$groupMember->getDisplayName());
          $rcUser->getUserProxy($apiClient);
          $channel->addMember($apiClient, $rcUser);
        }
        $channels = new Channels($rcState, $apiClient);
        $channels->refreshCache(TRUE);
        return;
        break;
      case "delete":
        // Inserting something into a group content.
        $channel->getChannelProxy($apiClient);
        $channel->getChannelMembers($apiClient);

        /** @var \Drupal\user\Entity\User[] $GroupMembers */
        $GroupMembers = [];
        foreach ($userRef as $target){
          $targetUser = Drupal::entityTypeManager()->getStorage('user')->load($target['target_id']);
          if(!empty($targetUser)){
            $GroupMembers[] = $targetUser;
          }
          unset($targetUser);
        }
        foreach ($GroupMembers as $groupMember){
          $rcUser = new RocketchatUser($rcState,$groupMember->getAccountName(),$groupMember->getEmail(),$groupMember->getDisplayName());
          $rcUser->getUserProxy($apiClient);
//          $channel;
          $removed = $channel->removeMember($apiClient, $rcUser);
          //TODO log result of removal
        }
        $channels = new Channels($rcState, $apiClient);
        $channels->refreshCache(TRUE);
        return;
        break;
    }

    if($apiClient->isLoggedIn()){
      $channelProxy = $channel->getChannelProxy($apiClient);
      $rcState  = new Drupal8State(Drupal::state());
      $user = new RocketchatUser($rcState,$userRefEntity->getAccountName(),$userRefEntity->getEmail(),$userRefEntity->getDisplayName());
      $user->getUserProxy($apiClient);
      $channel->addMember($apiClient,$user);
      $users = [];
      $usersProxy = new Users($rcState, $apiClient);
      $users = $usersProxy->getCache();
      $channel->getChannelType();

    } else {
      Drupal::messenger()->addWarning("Unable to do anything before you login to Rocket.Chat.");
    }
  }

  /**
   * Get the Machine name for the channel in this group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *
   * @return string|null
   */
  public static function extractChannelMachineName(GroupInterface $group) {
    $fieldMachineName = NULL;
    $groupEntityTypeId = $group->getEntityTypeId();
    $groupBundle = $group->bundle();

    $fieldMapping = Drupal::service('entity_field.manager')
      ->getFieldMapByFieldType('channel');
    if (isset($fieldMapping[$groupEntityTypeId])) {
      $channelEntity = $fieldMapping[$groupEntityTypeId];
      foreach ($channelEntity as $fieldName => $fieldSettings) {
        if (isset($fieldSettings['bundles'])) {
          foreach ($fieldSettings['bundles'] as $bundle) {
            if (strcmp($groupBundle, $bundle) === 0) {
              $fieldMachineName = $fieldName;
              break;
            }
          }
        }
      }
    }
    return $fieldMachineName;
  }

  public static function themeRocketChannelBLock($existing, $type, $theme, $path) {
    return [
      'rocketChatChannelBlock' => [
        'variables' => [
          'url' => "https://demo.rocketchat.chat",
          'width' => "0px",
          'height' => "0px",
          'host' => "*",
          'app' => "rocketchat://demo.rocket.chat",
          'showDirectLink' => FALSE
        ]
      ]
    ];
  }

  private static function addLogEntry(string $message, string  $level = "info"){
    $Logger = Drupal::logger("Rocket Chat Group: ModuleHelper");
    $Logger->log($level, $message);
  }
}
