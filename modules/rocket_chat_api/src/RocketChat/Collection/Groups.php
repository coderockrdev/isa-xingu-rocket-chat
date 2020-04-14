<?php
namespace Drupal\rocket_chat_api\RocketChat\Collection;
use Drupal;
use Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface as StateInterface;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;
use Drupal\serialization\Encoder\JsonEncoder;

class Groups implements CollectionInterface {

  /**
   * @var \Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface
   */
  private $state;

  /**
   * @var \Drupal\rocket_chat_api\RocketChat\ApiClient
   */
  private $apiClient;

  /**
   * Cache Names (or stub name).
   */
  public const LIST   = "rocket.chat.group.list";
  public const UPDATE = "rocket.chat.group.lastUpdate";
  public const GROUP  = "rocket.chat.group.";

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected  $Logger;

  public function __construct(StateInterface $state,ApiClient $apiClient) {
    $this->state = $state;
    $this->apiClient = $apiClient;
    $this->Logger = Drupal::logger("Rocket Chat API: Groups");
  }

  public function getCache($forceReload = FALSE){
    $this->refreshCache(FALSE);
    $idList = $this->state->get(self::LIST,[]);
    foreach ($idList as $index => $id){
      $idList[$index] = self::GROUP.$id;
    }
    $groups = $this->state->getMultiple($idList);
    if(empty($groups)){
      $groups = [];
    }
    return $groups;
  }


  public function refreshCache($forceReload = FALSE){
    $lastUpdate = $this->state->get(self::UPDATE,0);
    $now = time();
    if(($now - $lastUpdate) >= (3600*24*7)){
      $this->Logger->info("Refreshing Groups Cache due to stale Cache  (timeout)");
      $forceReload = TRUE;
    }

    if($forceReload){
      $groups = [];
      $found = Channel::getAllGroupsBatched($this->apiClient, $groups);
      $groupIds = [];
      foreach ($groups as $group){
        $groupIds[] = $group['_id'];
        $this->state->set(self::GROUP . $group['_id'], $group);
      }
      $this->state->set(self::LIST,$groupIds);
      $this->state->set(self::UPDATE,$now);
    }
  }




}
