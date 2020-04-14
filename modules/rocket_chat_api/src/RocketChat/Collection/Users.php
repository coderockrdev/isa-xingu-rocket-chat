<?php
namespace Drupal\rocket_chat_api\RocketChat\Collection;

use Drupal;
use Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface as StateInterface;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;
use Drupal\rocket_chat_api\RocketChat\Element\User;
use Drupal\serialization\Encoder\JsonEncoder;

class Users implements CollectionInterface {

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
  public const LIST   = "rocket.chat.user.list";
  public const UPDATE = "rocket.chat.user.lastUpdate";
  public const USER   = "rocket.chat.user.";

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected  $Logger;

  public function __construct(StateInterface $state,ApiClient $apiClient) {
    $this->state = $state;
    $this->apiClient = $apiClient;
    $this->Logger = Drupal::logger("Rocket Chat API: Users");
    //Todo Decouple this

  }

  /**
   * @param bool $forceReload
   * @return array
   */
  public function getCache($forceReload = FALSE){
    $this->refreshCache(FALSE);
    $idList = $this->state->get(self::LIST,[]);
    foreach ($idList as $index => $id){
      $idList[$index] = self::USER.$id;
    }
    $users = $this->state->getMultiple($idList);
    if(empty($users)){
      $users = [];
    }
    return $users;
  }

  public function refreshCache($forceReload = FALSE){
    $lastUpdate = $this->state->get(self::UPDATE,0);
    $now = time();
    if(($now - $lastUpdate) >= (3600*24*7)){
      $this->Logger->info("Refreshing Users Cache due to stale Cache  (timeout)");
      $forceReload = TRUE;
    }

    if($forceReload){
      $users = [];
      $found = self::getAllUsersBatched($this->apiClient, $users);
      $userIds = [];
      foreach ($users as $user){
        $userIds[] = $user['_id'];
        $this->state->set(self::USER . $user['_id'], $user);
      }
      $this->state->set(self::LIST,$userIds);
      $this->state->set(self::UPDATE,$now);
    }
  }

  /**
   * Retrieve Chat User List. (in batch size)
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $api
   * @param array $users
   * @param int $offset
   * @param int $size
   * @return float|int
   * @return float|int
   * @todo needs better Error checking / missing detection.
   */
  public static function getAllUsersBatched(ApiClient &$api, array &$users, $offset=0, $size=500){
    $ret = $api->getFromRocketChat("users.list",["query" => ["offset" => $offset,"count" => $size]]);
    foreach ($ret['body']['users'] as $index => $user){
      $users[]  = $user;
    }
    $total = $ret['body']['total'];
    $count = $ret['body']['count'];
    $retOffset= $ret['body']['offset'];
    $subTotal = $count * (1 + $retOffset);
    $usersLeft = $total - $subTotal;
    if($usersLeft > 0 ){
      self::getAllUsersBatched($api, $users, ++$offset, $size);
    }
    return $subTotal;
  }



}
