<?php
namespace Drupal\rocket_chat_api\RocketChat\Collection;

use Drupal;
use Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface as StateInterface;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;
use Drupal\serialization\Encoder\JsonEncoder;

class Channels implements CollectionInterface {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected  $Logger;

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
  public const LIST    = "rocket.chat.channel.list";
  public const UPDATE  = "rocket.chat.channel.lastUpdate";
  public const CHANNEL = "rocket.chat.channel.";

  public function __construct(StateInterface $state,ApiClient $apiClient) {
    $this->state = $state;
    $this->apiClient = $apiClient;
    $this->Logger = Drupal::logger("Rocket Chat API: Channels");

  }

  /**
   * @param bool $forceReload
   *
   * @return array
   */
  public function getCache($forceReload = FALSE){
    $this->refreshCache(FALSE);
    $idList = $this->state->get(self::LIST,[]);
    foreach ($idList as $index => $id){
      $idList[$index] = self::CHANNEL.$id;
    }
    $channels = $this->state->getMultiple($idList);
    if(empty($channels)){
      $channels = [];
    }
    return $channels;
  }

  public function refreshCache($forceReload = FALSE){
    $lastUpdate = $this->state->get(self::UPDATE,0);
    $now = time();
    if(($now - $lastUpdate) >= (3600*24*7)){
      $this->Logger->info("Refreshing Channels Cache due to stale Cache  (timeout)");
      $forceReload = TRUE;
    }

    if($forceReload){
      $channels = [];
      $found = Channel::getAllChannelsBatched($this->apiClient, $channels);
      $channelIds = [];
      foreach ($channels as $channel){
        $this->state->set(self::CHANNEL . $channel['_id'], $channel);
      }
      $this->state->set(self::LIST,$channelIds);
      $this->state->set(self::UPDATE,$now);
    }
  }




}
