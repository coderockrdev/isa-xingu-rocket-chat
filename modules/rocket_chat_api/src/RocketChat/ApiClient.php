<?php

namespace Drupal\rocket_chat_api\RocketChat {

  /*
   * This namespace will hold all of our Rocket Chat specific code.
   * Namespaces are a great way to make sure we do not have collisions of our
   * code with some other part of the system.
   *
   * Created by 040lab b.v. using PhpStorm from Jetbrains.
   * User: Lawri van Buël
   * Date: 07/03/17
   * Time: 11:22
   *
   * We utilize Guzzle for the heavy lifting of the HTTP calls themselves due
   * to
   * speed of operations and easy of use. We could also have used plain cUrl
   * calls or some other technique.
   */

  use \GuzzleHttp\Client;
  use \GuzzleHttp\Exception\ClientException;
  use \Drupal\rocket_chat_api\RocketChat\RocketChatConfigInterface;

  /**
   * Class ApiClient.
   *
   * This class connects the php to the RocketChat Server though its REST-API.
   *
   * @package RocketChat
   */
  class ApiClient {

    /**
     * The HTTP Verb for Getting stuff. WARNING This will be part of the URL!
     */
    const HTTP_GET = 'GET';

    /**
     * The HTTP Verb for Creating. and sometimes Updating.
     */
    const HTTP_POST = 'POST';

    /**
     * CLient Object.
     *
     * @var \GuzzleHttp\Client
     *  This object is a reference to the Guzzle Client.
     */
    private $client;

    /**
     * Config Object.
     *
     * @var \Drupal\rocket_chat_api\RocketChat\RocketChatConfigInterface
     *   RocketChatConfigInterface object.
     */
    private $config;

    private $loggedIn = FALSE;

    /**
     * ApiClient constructor.
     *
     *  Loads in the configuration from the Drupal Variables and preparers the
     *  Client with some defaults and base values for ease of use.
     *
     * @param \Drupal\rocket_chat_api\RocketChat\RocketChatConfigInterface $config
     *   RocketChatConfigInterface that holds the glue between the diffrent implimentations of this Code.
     * @param bool $login
     *   When true the stored login tokens will not be used. when false the
     *   stored login tokens will be used. This is to facilitate login and
     *   non-auth calls. Or in other words, is this a login call.
     */
    public function __construct(RocketChatConfigInterface $config = NULL,
                                $login = FALSE) {
      $this->config = $config;
      if (!empty($config)) {
        $this->client = $this->createClient($login);
        $userToken = $this->config->getElement("rocket_chat_uit");
        if (empty($userToken)) {
          $this->loggedIn = $this->login(
            $this->config->getElement("user"),
            $this->config->getElement("secret")
          );
        }
      }
      else {
        $this->config = NULL;
      }
    }

    /**
     * Create a Guzzle client Object.
     *
     * @param bool $login
     *   Filter out the login credentials.
     *
     * @return \GuzzleHttp\Client
     *   New HTTP Client.
     */
    private function createClient($login = FALSE) {
      $userId = $this->config->getElement("rocket_chat_uid");
      $userToken = $this->config->getElement("rocket_chat_uit");
      $guzzleConfig = [
        'base_uri' => $this->config->getElement('rocket_chat_url', "http://localhost:3000") . '/api/v1/',
        'allow_redirects' => FALSE,
        'timeout' => 60,
        'debug' => $this->config->isDebug(),
        'headers' => [
          'X-Auth-Token' => $userToken,
          'X-User-Id' => $userId,
        ],
      ];
      if ($login) {
        unset($guzzleConfig['headers']);
      }
      $guzzleConfig['headers']['Content-Type'] = 'application/json';
      return new Client($guzzleConfig);
    }

    /**
     * Do a Login on the Rocket Chat REST API.
     *
     * @param string $id
     *   The Username.
     * @param string $token
     *   The Authentication token. aka password.
     *
     * @return bool
     *   Was the login successful or not.
     */
    public function login($id = NULL, $token = NULL) {
      $rocket = $this->config->getElement('rocket_chat_url', "http://localhost:3000");
      $oldClient = $this->client;
      $this->client = $this->createClient(TRUE);
      $params = ['username' => $id, 'password' => $token];
      $result = $this->postToRocketChat('login', ['json' => $params]);
      // $test = self::validateReturn($result);
      $resultString = $result['body'];

      if (!($resultString['status'] == 'success')) {
        $this->config->notify("Login to $rocket was Unsuccessful.", 'error');
        unset($this->client);
        $this->client = $oldClient;
        return FALSE;
      }
      else {
        unset($oldClient);
        $this->config->setElement("rocket_chat_uid", $resultString['data']['userId']);
        unset($resultString['data']['userId']);
        $this->config->setElement("rocket_chat_uit", $resultString['data']['authToken']);
        unset($resultString['data']['authToken']);
        $this->config->notify("Login to $rocket was Successful.", 'status');
        $this->client = $this->createClient(FALSE);
        return TRUE;
      }
    }

    /**
     * Send message to Rocket Chat.
     *
     * @param string $method
     *   The method to call (so the part after '/api/v1/').
     * @param array $options
     *   Optional Data payload. for HTTP_POST calls.
     *
     * @return array
     *   Result array.
     */
    public function postToRocketChat($method = "info", array $options = NULL) {
      return $this->sendToRocketChat(ApiClient::HTTP_POST, $method, $options);
    }

    /**
     * Simple low level helper to GET or POST to the rocketchat.
     *
     * @param string $httpVerb
     *   one of the HTTP_* Verbs to use for this call.
     * @param string $method
     *   The method to call (so the part after '/api/v1/').
     * @param array $options
     *   Optional Data payload. for HTTP_POST calls.
     *
     * @return array
     *   Result array.
     */
    private function sendToRocketChat($httpVerb = ApiClient::HTTP_GET, $method = "info", array $options = []) {
      $result = new \stdClass();
      try {
        switch ($httpVerb) {
          case ApiClient::HTTP_GET:
            $result = $this->client->get($method, $options);
            break;

          case ApiClient::HTTP_POST:
            $result = $this->client->post($method, $options);
            break;

          default:
            throw new ClientException("HTTP Verb is unsupported", NULL, NULL, NULL, NULL);
        }
        $resultString = (string) $result->getBody();

        // HTTP Headers.
        $resultHeader = $result->getHeaders();

        // HTTP Response Code (like 200).
        $resultCode = $result->getStatusCode();

        // HTTP Response String (like OK).
        $resultStatus = $result->getReasonPhrase();
      }
      catch (ClientException $e) {
        $resultStatus = $e->getMessage();
        $resultCode = $e->getCode();
        $resultString = [];
        $resultString['status'] = 'failed';
        $resultString['response'] = $e->getResponse();

        $resultHeader['content-type'][0] = "Error";
      }
      if (isset($resultHeader['content-type']) &&
          !isset($resultHeader['Content-Type'])) {
        // Quick fix to prevent errors due to capitalization of content-type
        // in the header.
        $resultHeader['Content-Type'] = $resultHeader['content-type'];
      }

      if ($resultHeader['Content-Type'][0] == 'application/json') {
        $jsonDecoder = $this->config->getJsonDecoder();
        $resultString = $jsonDecoder($resultString);
      }

      $returnValue = [];
      $returnValue['result'] = $result;
      $returnValue['body'] = $resultString;
      $returnValue['status'] = $resultStatus;
      $returnValue['code'] = $resultCode;

      return $returnValue;
    }

    /**
     * Validate the Return of Rocketchat.
     *
     * @param array $result
     *   Result to check.
     *
     * @return bool
     *   Validation result.
     *
     * @deprecated currently not effective code.
     */
    public static function validateReturn(array $result) {
      // TODO implement a validation for a guzzle return. currently defunct.
      //
      //      $result;
      //      if(is_object($result) &&
      //         $result instanceof \GuzzleHttp\Psr7\Response) {
      //        //Guzzle Response
      //      }
      // TODO Implement Return Validation Checks!
      return TRUE;
    }

    /**
     * Retrieve the information about myself, the 'me' command.
     *
     * @return array
     *   Result array.
     */
    public function whoAmI() {
      return $this->getFromRocketChat('me');
    }

    /**
     * Low Level GET request to the rocketchat.
     *
     * @param string $method
     *   The method to call (so the part after '/api/v1/').
     * @param array $options
     *   Optional Data payload. for HTTP_POST calls.
     *
     * @return array
     *   Result array.
     */
    public function getFromRocketChat($method = "info", array $options = NULL) {
      return $this->sendToRocketChat(ApiClient::HTTP_GET, $method, $options);
    }

    /**
     * Logout a session.
     *
     * @return array
     *   Result array.
     */
    public function logout() {
      return $this->postToRocketChat('logout');
    }

    /* *
     * Execute as different user.
     *
     * @param string $otherUserId
     *   UserID of user to sudo as.
     * @param $functionName
     *   Function Name to call.
     * @param array ...$args
     *   Function Arguments.
     *
     * @return mixed
     *   Result of Call.
     * /
    public function sudo($otherUserId, $functionName, ...$args) {
//      TODO Refactor this to use a special local config for use during sudo call. Disabled until such a time to limit its Security implication!
      throw new \BadFunctionCallException("SUDO is Disabled until a security fix is implemented!", 255);
//
//          // NOTE $args === func_get_args();
//          if($functionName == 'login' || $functionName == 'logout') {
//            throw new \BadFunctionCallException("$functionName must be used directly not through sudo.",502);
//          }
//          $retVal = NULL;
//          $originalConfig = $this->config;
//          $newConfig = clone $this->config;
//          try {
//            $authToken = $this->postToRocketChat('users.createToken',['json' => ['userId' => $otherUserId]]);
//
//            //check     $authToken = $this->postToRocketChat('users.createToken',['json' => ['userId' => $otherUserId]]) yields result;
//
//            $newConfig->setElement('rocket_chat_uid', $authToken['body']['data']['userId']);
//            $newConfig->setElement('rocket_chat_uit',$authToken['body']['data']['authToken']);
//
//            $this->config = $newConfig;
//            $this->client = $this->createClient(false);
//
//            //DO Function call
//            $retVal = $this->$functionName(...$args );
//          } catch (\Exception $e) { //TODO IMPLEMENT!!!
//
//          } finally {
//            $this->config = $originalConfig;
//            $this->client = $this->createClient(false);
//          }
//          return $retVal;
//          //Do Call
//          //TODO Implement me
//            return;
    }
     */

    /**
     * Retrieve User information.
     *
     * @param string $userId
     *   The userId to look up.
     * @param string $userName
     *   The username to look up.
     *
     * @return array
     *   Result array.
     */
    public function usersInfo($userId = NULL, $userName = NULL) {
      $req = [];
      $req['query'] = [];
      if (!empty($userId)) {
        $req['query']['userId'] = $userId;
      }
      if (!empty($userName)) {
        $req['query']['username'] = $userName;
      }
      return $this->getFromRocketChat('users.info', $req);
    }

    /**
     * Logout a session.
     *
     * @return array
     *   Result array
     */
    public function usersList() {
      return $this->getFromRocketChat('users.list');
    }

    /**
     * Create a new Channel.
     *
     * @param string $name
     *   The new channel name.
     * @param array $members
     *   The list of the users of this channel.
     *
     * @return array
     *   Result array.
     */
    public function channelsCreate($name, array $members = NULL) {
      $options["name"] = $name;
      if (!empty($members)) {
        $options['members'] = $members;
      }
      return $this->postToRocketChat('channels.create', ['json' => $options]);
    }

    /**
     * Retrieve User information.
     *
     * @param string $offset
     *   What offset do you want to use, 0-based.
     * @param string $count
     *   How many do you want to retrieve.
     *
     * @return array
     *   Result array.
     */
    public function channelsList($offset = NULL, $count = NULL) {
      $req = [];
      $req['query'] = [];
      if (!empty($offset)) {
        $req['query']['offset'] = $offset;
      }
      if (!empty($count)) {
        $req['query']['count'] = $count;
      }
      if (empty($req)) {
        unset($req);
        $req = NULL;
      }
      return $this->getFromRocketChat('channels.list', $req);
    }

    /**
     * Retrieve User information.
     *
     * @param string $roomId
     *   The roomId to look up.
     * @param string $roomName
     *   The room name to look up.
     *
     * @return array
     *   Result array
     */
    public function channelsInfo($roomId = NULL, $roomName = NULL) {
      $req = [];
      $req['query'] = [];
      if (!empty($roomId)) {
        $req['query']['roomId'] = $roomId;
      }
      if (!empty($roomName)) {
        $req['query']['roomName'] = $roomName;
      }
      return $this->getFromRocketChat('channels.info', $req);
    }

    /**
     * Retrieve the message history information.
     *
     * @param string $roomId
     *   The roomId to look up.
     * @param bool $unreads
     *   Include the amount of unreads.
     * @param bool $inclusive
     *   Are the limits inclusive or exclusive.
     * @param int $count
     *   How many to retrieve max.
     * @param string $latest
     *   Timestring for the jongest message to retrieve.
     * @param string $oldest
     *   Timestring for the max age of a message.
     *
     * @return array
     *   Result Array.
     */
    public function channelsHistory($roomId, $unreads = NULL, $inclusive = NULL, $count = NULL, $latest = NULL, $oldest = NULL) {
      // Time Example = "1970-01-01T01:00:00.00Z".
      $req = [];
      $req['query'] = [];
      $req['query']['roomId'] = $roomId;
      if (!empty($latest)) {
        $req['query']['latest'] = $latest;
      }
      if (!empty($oldest)) {
        $req['query']['oldest'] = $oldest;
      }
      if (isset($inclusive)) {
        $req['query']['inclusive'] = $inclusive;
      }
      if (!empty($count)) {
        $req['query']['count'] = $count;
      }
      if (isset($unreads)) {
        $req['query']['unreads'] = $unreads;
      }
      return $this->getFromRocketChat('channels.history', $req);
    }

    /**
     * Send a Message to the rocketchat.
     *
     * @param string $roomId
     *   Room ID.
     * @param string $channel
     *   Channel Name.
     * @param string $text
     *   Text.
     * @param string $alias
     *   Alias.
     * @param string $emoji
     *   Emoji.
     * @param string $avatar
     *   Avatar link.
     * @param mixed $attachments
     *   Attachments array.
     *
     * @return array
     *   Result array
     */
    public function postMessage($roomId = NULL, $channel = NULL, $text = NULL, $alias = NULL, $emoji = NULL, $avatar = NULL, $attachments = NULL) {
      $params = [];
      if (!empty($roomId)) {
        $params['roomId'] = $roomId;
      }
      if (!empty($channel)) {
        $params['channel'] = $channel;
      }
      if (!empty($text)) {
        $params['text'] = $text;
      }
      if (!empty($alias)) {
        $params['alias'] = $alias;
      }
      if (!empty($emoji)) {
        $params['emoji'] = $emoji;
      }
      if (!empty($avatar)) {
        $params['avatar'] = $avatar;
      }
      if (!empty($attachments)) {
        $params['attachments'] = $attachments;
      }
      return $this->postToRocketChat('chat.postMessage', ['json' => $params]);
    }

  }
}
