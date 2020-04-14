<?php

namespace Drupal\rocket_chat_api\RocketChat\Element;


use Drupal;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Collection\Users;
use Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface;
use Exception;

class User {

  /**
   * @var array, stores JSON result with details of this channel after retrieval.
   */
  private $User = NULL;

  protected $name = NULL;
  protected $email = NULL;
  protected $username = NULL;

  /**
   * @var \Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface
   */
  protected $state;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected  $Logger;


  /**
   * @return string|null
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @param string|null $name
   *
   * @return User
   */
  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getEmail() {
    return $this->email;
  }

  /**
   * @param string|null $email
   *
   * @return User
   */
  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getUsername() {
    return $this->username;
  }

  /**
   * @param string|null $username
   *
   * @return User
   */
  public function setUsername($username) {
    $this->username = $username;
    return $this;
  }

  /**
   * User constructor.
   *
   * @param \Drupal\rocket_chat_api\RocketChat\RocketChatStateinterface $state
   * @param null|string $username
   * @param null|string $email
   * @param null|string $name
   */
  public function __construct(RocketChatStateinterface $state, $username = NULL,$email = NULL, $name = NULL) {
    $this->state = $state;
    $this->username = $username;
    $this->email = $email;
    $this->name = $name;
    $this->Logger = Drupal::logger("Rocket Chat API: User");
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
    foreach ($list as $user){
      $found= false;
      if(!empty($this->username) && ($user["username"] == $this->username)){
        $found = TRUE;
      }
      if(!empty($this->email)) {
        if(isset($user['emails'])){
          foreach ($user['emails'] as $email){
            if($email['address'] == $this->email){
              $found = TRUE;
              return $user;
            }
          }
        } else {
          if(!(strcmp($user['type'],"bot") === 0)){
            $this->Logger->warning("User is missing the email property |"  . json_encode($user,JSON_PRETTY_PRINT));
          }
        }
      }
      if($found){
        return $user;
      }
    }
    return NULL;
  }

  /**
   * Retrieve the Proxy, create the User if needed.
   *
   * @param \Drupal\rocket_chat_api\RocketChat\ApiClient $apiClient
   *
   * @return array|null
   * @throws Exception
   */
  public function getUserProxy(ApiClient $apiClient){
    if(empty($this->User)) {
      $users = [];

      if ($this->isEmpty()) {
        //TODO report this fail state!
        return NULL;
      }
      $usersState = new Users($this->state, $apiClient);
      $users = $usersState->getCache();
      if (!$this->isInList($users)) {
        $options = [];
        $options['json'] = [];
        $options['json']['email'] = $this->email;
        $options['json']['name'] = $this->name;
        $options['json']['password'] = bin2hex(random_bytes(512));
        $options['json']['username'] = $this->username;
        $options['json']['verified'] = TRUE;

        /**
         * @see https://rocket.chat/docs/developer-guides/rest-api/users/create/
         */
        $ret = $apiClient->postToRocketChat("users.create", $options);
      }
      else {
        $found = 0;
        $userCache = $this->getFromList($users);
        if(empty($userCache)){
          $ret = $apiClient->getFromRocketChat("users.info", ["query" => ["username" => $this->username]]);
        } else {
          $ret = [];
          $ret['body'] = [];
          $ret['body']['user'] = $userCache;
        }

      }
      $this->User = $ret['body']['user'];
      return $this->User;
    } else {
      //Refresh User
      return null;
    }
  }

  public function isEmpty(){
    $empty = TRUE;
    $email = FALSE;
    $username = FALSE;
    $name = FALSE;
    if(!empty($this->email)){
      $email = TRUE;
    }
    if(!empty($this->username)){
      $username = TRUE;
    }
    if(!empty($this->name)){
      $name = TRUE;
    }
    if($email && $username && $name){
      $empty = FALSE;
    }
    return $empty;
  }

  /**
   * @return array
   */
  public function getUser() {
    return $this->User;
  }

}
