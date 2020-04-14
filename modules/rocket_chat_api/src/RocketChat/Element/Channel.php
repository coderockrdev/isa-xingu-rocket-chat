<?php


namespace Drupal\rocket_chat_api\RocketChat\Element;


use Drupal;
use Drupal\rocket_chat_api\RocketChat\Collection\Channels;
use Drupal\rocket_chat_api\RocketChat\Collection\Groups;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Drupal\rocket_chat_api\RocketChat\Drupal8State;
use \Error as ImplementationError;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Exception;

class Channel {

  const PRIVATE_CHANNEL = 0b00001;
  const PUBLIC_CHANNEL  = 0b00010;
  const READ            = 0b00100;
  const WRITE           = 0b01000;
  const BROADCAST       = 0b10000;

  //Encodes like this: [Broadcast, Writeable Readable, public Channel, privateGroup]
  //                             BWRCG
  const DEFAULT_CHANNEL_TYPE = 0b01110;

  /**
   * @var int Masked value indicating what type of Channel this uses;
   */
  protected $ChannelType = NULL;
  protected $ChannelName = NULL;

  private $ChannelMembers = [];

  /**
   * @var \Drupal\rocket_chat_api\RocketChat\Element\User Room Owner.
   */
  private $owner = NULL;

  /**
   * @var array, stores JSON result with details of this channel after retrieval.
   */
  private $Channel = NULL;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected  $Logger;

  /**
   * @return int
   */
  public function getChannelType() {
    return $this->ChannelType;
  }

  /**
   * @param int $ChannelType
   *
   * @return Channel
   */
  public function setChannelType($ChannelType) {
    $this->ChannelType = $ChannelType;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getChannelName() {
    return $this->ChannelName;
  }

  /**
   * @param string|null $ChannelName
   *
   * @return Channel
   */
  public function setChannelName($ChannelName) {
    $this->ChannelName = $ChannelName;
    return $this;
  }

  /**
   * @return \Drupal\rocket_chat_api\RocketChat\Element\User
   */
  public function getOwner() {
    return $this->owner;
  }

  /**
   * @param \Drupal\rocket_chat_api\RocketChat\Element\User $owner
   *
   * @return Channel
   */
  public function setOwner($owner) {
    $this->owner = $owner;
    return $this;
  }

  /**
   * Channel constructor.
   *
   * @param int|NULL $typeMask
   * @param string|NULL $ChannelName
   */
  public function __construct($typeMask = null, $ChannelName = NULL) {
    if(is_null($typeMask)){
      $typeMask = self::DEFAULT_CHANNEL_TYPE;
    }
    if(is_null($ChannelName)){
      $ChannelName = "";
    } else {
      $rcState = new Drupal8State(Drupal::service('state'));
      $apiConfig = new Drupal8Config(
        Drupal::configFactory(),
        Drupal::moduleHandler(),
        Drupal::state(),
        Drupal::messenger()
      );
      $apiClient = new ApiClient($apiConfig);
      $channelsState = new Channels($rcState, $apiClient);
      $channelsState->getCache();
    }
    $this->ChannelType = $typeMask;
    $this->ChannelName = $ChannelName;
    $this->Logger = Drupal::logger('Rocket Chat API: Channel');
  }

  public function __toString() {
    $ret = "#". $this->getChannelName();
    if($this->hasType(self::PRIVATE_CHANNEL)){
     $ret .= " [Private Channel]";
    }
    if($this->hasType(self::PUBLIC_CHANNEL)){
     $ret .= " [Public Channel]";
    }

    $perms = "";
    if($this->hasType(self::READ)){
      $perms .= "READABLE,";
    }
    if($this->hasType(self::WRITE)){
      $perms .= "WRITABLE,";
    }
    if($this->hasType(self::BROADCAST)){
      $perms .= "BROADCAST,";
    }
    $perms = rtrim($perms,",");

    $ret.=" ($perms)";
    return $ret;
  }

  public function __get($name) {
    switch($name){
      default:
        if (isset($this->$name)) {
          return $this->$name;
        } else {
          return null;
        }
      break;
      case "Channel":
        return $this->Channel;
    }


  }

  /**
   * Check if this Channel is of type $test.
   * @param int $test
   * @return bool|int
   */
  public function hasType($test){
    return ($this->getChannelType() & $test) > 0;
  }

  /**
   * Retrieve Chat Private Groups list. (in batch size)
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $api
   * @param array $groups
   * @param int $offset
   * @param int $size
   * @return int  Total number of Groups found.
   * @todo needs better Error checking / missing detection.
   */
  public static function getAllGroupsBatched(
    ApiClient &$api,
    array &$groups,
    $offset=0,
    $size=500
  ) {
    return self::getAllRoomsBatched($api,$groups,"groups.listAll",$offset,$size);
  }

  /**
   * Retrieve Chat Public Channels list. (in batch size).
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $api
   * @param array $channels
   * @param int $offset
   * @param int $size
   * @return int  Total number of Channels found.
   * @todo needs better Error checking / missing detection.
   */
  public static function getAllChannelsBatched(
    ApiClient &$api,
    array &$channels,
    $offset=0,
    $size=500
  ) {
    return self::getAllRoomsBatched($api,$channels,"channels.list",$offset,$size);
  }

  /**
   * Retrieve Chat <Method> Channels list. (in batch size).
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $api
   * @param array $rooms
   * @param string $method (channels | groups)
   * @param int $offset
   * @param int $size
   * @return int Total number of Rooms of this <Method> found.
   * @todo needs better Error checking / missing detection.
   */
  private static function getAllRoomsBatched(
    ApiClient &$api,
    array &$rooms,
    $method,
    $offset,
    $size
  ) {
    $ret = $api->getFromRocketChat(
      "$method",
      [
        "query" => [
          "offset" => $offset,
          "count" => $size
        ]
      ]
    );

    $methodParts = explode(".",$method,2);
    foreach ($ret['body'][$methodParts[0]] as $index => $room){
      $rooms[]  = $room;
    }
    $total = $ret['body']['total'];
    $count = $ret['body']['count'];
    $retOffset= $ret['body']['offset'];
    $subTotal = $count * (1 + $retOffset);
    $roomsLeft = $total - $subTotal;
    if($roomsLeft > 0 ){
      self::getAllRoomsBatched($api,$rooms,$method, ++$offset, $size);
    }
    return $total;
  }

  /**
   * Check if this Channel is in the List provided.
   * @param $list
   * @return bool
   */
  private function isInList($list) {
    return !is_null($this->getFromList($list));
  }

  private function getFromList($list){
    foreach ($list as $channel){
      if($channel["name"] == $this->getChannelName()){
        return $channel;
      }
      if($channel["name"] == $this->getSafeChannelName()){
        return $channel;
      }
    }
    return NULL;
  }

  /**
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $apiClient
   *
   * @throws \Exception
   */
  public function getChannelMembers(ApiClient $apiClient){

    $this->ChannelMembers = [];
  }

  /**
   * Retrieve Chat Private Groups list. (in batch size)
   *
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $api
   * @param array $members
   * @param int $offset
   * @param int $size
   * @param int $level
   *   for entry level. anything else is unsuported.
   *
   * @return int  Total number of Groups found.
   *
   * @throws \Exception
   * @todo needs better Error checking / missing detection.
   */
  public function getAllChannelMembersBatched(
    ApiClient &$api,
    array &$members,
    $offset=0,
    $size=500,
    $level = 0
  ) {
    if(empty($this->Channel)){
      $this->getChannelProxy($api);
    }
    $method = $this->getChannelTypeName();
    $retOptions = [
      "query" => [
        "offset" => $offset,
        "count" => $size,
        "roomId" => $this->Channel['_id'],
      ]
    ];

    $ret = $api->getFromRocketChat("$method" . ".members",$retOptions);
    foreach ($ret['body']['members'] as $index => $member){
      $members[]  = $member;
    }
    $total = $ret['body']['total'];
    $count = $ret['body']['count'];
    $retOffset= $ret['body']['offset'];
    $subTotal = $count * (1 + $retOffset);
    $roomsLeft = $total - $subTotal;
    if($roomsLeft > 0 ){
      self::getAllChannelMembersBatched($api,$members,++$offset,$size,++$level);
    }
    if($level === 0){
      $this->ChannelMembers = $members;
    }
    return $total;
  }

  /**
   * Retrieve the Proxy, create the Channel / Group if needed.
   *
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $apiClient
   *
   * @return array|null
   * @throws \Exception
   */
  public function getChannelProxy(ApiClient $apiClient){
    if(!$this->isEmpty()) {
      $Channels = [];
      $readChannel = $this->hasType(Channel::READ);
      $writeChannel = $this->hasType(Channel::WRITE);
      $broadcastChannel = $this->hasType(Channel::BROADCAST);

      if (!$readChannel) {
        //Nothing to do when we can't read a channel.
        //TODO need own error to throw.
        throw new ImplementationError("Trying to do something with an unreadable channel??");
      }
      if ($broadcastChannel) {
        throw new ImplementationError("Broadcast is not implemented yet, why use it??");
      }
      $methodBase = $this->getChannelTypeName();
      $state = Drupal::service('state');
      $drupal8State = new Drupal8State($state);
      switch($methodBase){
        case "channels":
          $ChannelList = new Channels($drupal8State, $apiClient);
          //TODO needs to replaced by state cached version.
          $Channels = $ChannelList->getCache();
          break;
        case "groups":
          $GroupList = new Groups($drupal8State,$apiClient);
          $Channels = $GroupList->getCache();
          if(is_null($Channels) || empty($Channels)){
            $Channels = [];
            $ChannelsB = Channel::getAllGroupsBatched($apiClient, $Channels);
          }
          break;
        default:
          //TODO report error!
          break;
      }
      ///TODO check if group exists?
      $members = [];
      $ret = $this->fetchChannel($Channels, $apiClient, $writeChannel, $methodBase);
      if(is_null($ret)){
        $this->Logger->error("No Channel/Group found and creation failed.");
        return NULL;
      }
      if(!isset($ret['body'][rtrim($methodBase,"s")]) || empty($ret['body'][rtrim($methodBase,"s")])){
        $this->Logger->error("Error Retrieving Details |" . json_encode($ret));
        return NULL;
      }
      $this->Channel = $ret['body'][rtrim($methodBase, "s")];
      $members = [];
      $this->getAllChannelMembersBatched($apiClient,$members);
      return $this->Channel;
    } else {
      return null;
    }
  }

  /**
   * Get Channel Type (channels|groups) or (public | private type.
   * @return string
   * @throws \Exception
   * @throws \Exception
   */
  public function getChannelTypeName(){
    if ($this->hasType(Channel::PUBLIC_CHANNEL)) {
      $methodBase = "channels";
      //Public Channel
    }
    elseif ($this->hasType(Channel::PRIVATE_CHANNEL)) {
      $methodBase = "groups";
      //Private Group
    }
    else {
      //TODO report this fial state better!
      throw new Exception("ERROR!");
    }
    return $methodBase;
  }

private function fetchChannel(array &$Channels,ApiClient &$apiClient,bool $writeChannel,string $methodBase){
  if (!$this->isInList($Channels)) {
    if(!empty($this->owner)){
      $this->owner->getUserProxy($apiClient);
      $members[] = $this->owner->getUsername();
    }
    $options = [];
    $options['json'] = [];
    $options['json']['name'] = $this->getSafeChannelName();
    $options['json']['readOnly'] = !$writeChannel;
    $myProxy =$apiClient->whoAmI();

    $foundMemberInList = FALSE;
    if(!empty($this->owner)){
      foreach ($members as $member){
        if(strcmp($member, $this->owner->getUsername()) === 0){
          $foundMemberInList = TRUE;
          break;
        }
      }
    } else {
      foreach ($members as $member){
        if(strcmp($member, $myProxy['body']['username']) === 0){
          $foundMemberInList = TRUE;
          break;
        }
      }
    }
    if(!$foundMemberInList){
      if(!empty($this->owner)){
        $members[] = $this->owner->getUsername();
      } else {
        $members[] = $myProxy['body']['username'];
      }
    }
    $options['json']['members'] = $members; //Member names to add...

    $ret = $apiClient->postToRocketChat("$methodBase.create", $options);
    //todo implement error check
    if($ret['body']['status'] === "failed"){
      $this->Logger->error($ret['status']);
      return NULL;
      //FAILED!
    }
    $myId = $myProxy['body']['_id'];
    if(!empty($this->owner)) {
      if (strcmp($myId, $this->owner->getUser()['_id']) !== 0) {
        $ownerJson = [];
        $ownerJson["json"] = [];
        $ownerJson['json']['roomId'] = $ret['body'][rtrim($methodBase, "s")]['_id'];
        $ownerJson['json']["userId"] = $this->owner->getUser()['_id'];

        $ownerJson['json']["userId"] = $this->owner->getUser()['_id']; //Group Owner
        $own = $apiClient->postToRocketChat("$methodBase.addOwner", $ownerJson);
        $ownerJson['json']["userId"] = $myId; //Current User
        $remOwn = $apiClient->postToRocketChat("$methodBase.removeOwner", $ownerJson);

        //todo implement better error check
      }
    } else {
      $logger = drupal::logger("Rocket Chat API");
      $logger->warning("Can not set a channle owner that we do not know.");
      //Can not set Owner if we do not know owner.
    }
  }
  else {
    $ret = [];
    $ret['body'] = [];
    $ret['body'][rtrim($methodBase,"s")] = $this->getFromList($Channels);
    $ret2 = $apiClient->getFromRocketChat("$methodBase.info", ["query" => ["roomName" => $this->getSafeChannelName()]]);
    $ret3 = $ret;
  }
  return $ret;
}

  /**
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $apiClient
   * @param \Drupal\rocket_chat_api\RocketChat\Element\User[] $users
   *
   * @return array
   * @return array
   * @throws \Exception
   */
  public function addMembers(ApiClient $apiClient, $users) {
    $ret = [];
    foreach ($users as $user){
      $ret[] = $this->addMember($apiClient,$user);
    }
    return $ret;
  }

  /**
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $apiClient
   * @param \Drupal\rocket_chat_api\RocketChat\Element\User $user
   *
   * @return bool|mixed
   * @return bool|mixed
   * @throws \Exception
   */
  public function addMember(ApiClient $apiClient, User $user){
    $members = [];
    if(empty($this->ChannelMembers)){
      $this->getAllChannelMembersBatched($apiClient,$members);
    }
    $found = FALSE;
    foreach ($this->ChannelMembers as $member){
      if(strcmp($user->getName(), $member['name']) === 0){
        if(strcmp($user->getUsername(), $member['username']) === 0){
          $found = TRUE;
          break;
        }
      }
    }
    if(!$found){
      $user->getUserProxy($apiClient);
      $method = $methodBase = $this->getChannelTypeName(); 
      $membersJson = [];
      $membersJson["json"] = [];
      $membersJson["json"]['roomId'] = $this->Channel['_id'];
      $membersJson["json"]['userId'] = $user->getUser()['_id'];
      $ret = $apiClient->postToRocketChat($method . ".invite",$membersJson);
      $this->getAllChannelMembersBatched($apiClient,$members);

      return $ret['body']['status'];
    }
    return false;
  }

  public function removeMember(ApiClient $apiClient, User $user){
    $members = [];
    if(empty($this->ChannelMembers)){
      $this->getAllChannelMembersBatched($apiClient,$members);
    }
    $found = FALSE;
    foreach ($this->ChannelMembers as $member){
      if(strcmp($user->getName(), $member['name']) === 0){
        if(strcmp($user->getUsername(), $member['username']) === 0){
          $found = TRUE;
          break;
        }
      }
    }
    if($found){
      $user->getUserProxy($apiClient);
      $method = $methodBase = $this->getChannelTypeName();
      $membersJson = [];
      $membersJson["json"] = [];
      $membersJson["json"]['roomId'] = $this->Channel['_id'];
      $membersJson["json"]['userId'] = $user->getUser()['_id'];
      $ret = $apiClient->postToRocketChat($method . ".kick",$membersJson);
      $this->getAllChannelMembersBatched($apiClient,$members);

      return $ret['body']['status'];
    }
    return false;
  }


  /**
   * @return bool isEmpty?
   */
  public function isEmpty() {
    if(empty($this->ChannelName)){
      return TRUE;
    }
    return FALSE;
  }

  public function getSafeChannelName(){
    return self::toSafeChannelName($this->getChannelName());
  }

  /**
   * @param string|null $channelname
   * @return string
   */
  public static function toSafeChannelName( $channelname = ""){
    return rawurlencode(str_replace(" ","_",$channelname));
  }

  public function getChannelURI(){
    if($this->hasType(self::PRIVATE_CHANNEL)){
      return "/group/" . $this->getSafeChannelName();
    } else {
      return "/channel/" . $this->getSafeChannelName();
    }
  }

  public function changeChannelName(ApiClient $apiClient, string $newName){
    $channelProxy = $this->getChannelProxy($apiClient);
    if(strcmp($this->getSafeChannelName(), self::toSafeChannelName($newName) !== 0)){
      $methodBase = $this->getChannelTypeName();
      $rename = [];
      $rename['json'] = [];
      $rename['json']['roomId'] = $this->Channel['_id'];
      $rename['json']['name'] = self::toSafeChannelName($newName);
      $ret = $apiClient->postToRocketChat($methodBase . ".rename",$rename);
      //TODO implement better Check.
      $this->Channel = $ret['body'][rtrim($methodBase, "s")];
      $state = Drupal::service('state');
      $ChannelList = new Channels(new Drupal8State($state), $apiClient);
      $ChannelList->refreshCache(TRUE);
    }
  }
}
