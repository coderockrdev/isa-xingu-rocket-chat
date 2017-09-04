<?php
/**
 * Created by 040lab b.v. using PhpStorm from Jetbrains.
 * User: Lawri van Buël
 * Date: 07/03/17
 * Time: 11:22
 */


/**
 * This name space will hold all of our Rocket Chat specific code.
 * Namespaces are a great way to make sure we do not have collisions of our code
 * with some other part of the system.
 */
namespace Drupal\rocket_chat_api\RocketChat {

  /**
   * We utilize Guzzle for the heavy lifting of the HTTP calls themselves due to
   * speed of operations and easy of use. We could also have used plain cUrl calls
   * or some other technique.
   */
  use \GuzzleHttp\Client;
  use \GuzzleHttp\Exception\ClientException;

  /**
   * Class ApiClient
   *  This  class connects the php to the RocketChat Server though its REST-API.
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
     * @var Client Object
     *  This object is a reference to the Guzzle Client.
     */
    private $client;

    private $config;

    private $loggedIn = FALSE;

    /**
     * ApiClient constructor.
     *  Loads in the configuration from the Drupal Variables and preparers the
     *  Client with some defaults and base values for ease of use.
     * @param bool $login
     *  When true the stored login tokens will not be used. when false the stored
     *  login tokens will be useed. This is to facilitate logins and non-auth
     *  calls. Or in other words, is this a login call.
     * @param $config \RocketChat\Config
     */
    public function __construct($config = NULL, $login = FALSE) {
      $this->config = $config;
      if(!empty($config)) {
        $this->client = $this->createClient($login);
        if(empty($userToken = $this->config->getElement("rocket_chat_uit"))) {
          $this->loggedIn = $this->login($this->config->getElement("user"), $this->config->getElement("secret"));
        }
      } else {
        $this->config = NULL;
      }
    }

    /**
     *  create a Guzzle client Object
     * @param $login
     *   filter out the login credentials.
     * @return \GuzzleHttp\Client
     *
     * @todo reimplemnt this to utilize N client such as the Drupal::HTTPclient.
     */
    private function createClient($login = FALSE){
      $userId = $this->config->getElement("rocket_chat_uid");
      $userToken = $this->config->getElement("rocket_chat_uit");
      $GuzzleConfig = array(
        'base_uri' => $this->config->getElement('rocket_chat_url', "http://localhost:3000") . '/api/v1/',
        'allow_redirects' => FALSE,
        'timeout' => 60,
        'debug' => $this->config->isDebug(),
        'headers' => array(
          'X-Auth-Token' => $userToken,
          'X-User-Id' => $userId,
        )
      );
      if ($login) {
        unset($GuzzleConfig['headers']);
      }
      $GuzzleConfig['headers']['Content-Type'] = 'application/json';
      return new Client($GuzzleConfig);
    }

    /**
     * Do a Login on the Rocket Chat REST API.
     *
     * @param $id
     *   the Username.
     * @param $token
     *   The Authentication token. aka password.
     *
     * @return bool
     *  Was the login successful or not.
     */
    public function login($id = NULL, $token = NULL) {
      $rocket = $this->config->getElement('rocket_chat_url',"http://localhost:3000");
      $oldCLient = $this->client;
      $this->client = $this->createClient(TRUE);
      //TODO CHECK!
      $params = ['username' => $id, 'password' => $token];
      $result = $this->postToRocketChat('login',['json' => $params]);
      $test = self::validateReturn($result);
      $resultString = $result['body'];

      if(!($resultString['status']=='success')){
        $this->config->notify("Login to $rocket was Unsuccessful.",'error');
        unset($this->client);
        $this->client = $oldCLient;
        return false;
      } else {
        unset($oldCLient);
        $this->config->setElement("rocket_chat_uid",$resultString['data']['userId']);
        unset($resultString['data']['userId']);
        $this->config->setElement("rocket_chat_uit",$resultString['data']['authToken']);
        unset($resultString['data']['authToken']);
        $this->config->notify("Login to $rocket was Succesfull.",'status');
        $this->client = $this->createClient(FALSE);
        return true;
      }
    }

    /**
     * Retrieve the information about myself, the 'me' command.
     * @param $config \RocketChat\Config
     * @return array
     */
    function whoami(){
      return $this->getFromRocketChat('me');
    }

    /**
     * Logout a session.
     * @param $config \RocketChat\Config
     * @return array
     */
    function logout(){
      //    return $this->getFromRocketChat('logout');
      return $this->postToRocketChat('logout');
    }

    /**
     * @param $otherUserId
     * @param $functionName
     * @param array ...$args
     */
    function sudo($otherUserId,$functionName,...$args){
      //TODO Refactor this to use a special local config for use during sudo call. Disabled untill such a time to limit its Security implication!
      throw new \BadFunctionCallException("SUDO is Disabled untill a security fix is implemented!",255);
      //
      //    // NOTE $args === func_get_args();
      //    if($functionName == 'login' || $functionName == 'logout') {
      //      throw new \BadFunctionCallException("$functionName must be used directly not through sudo.",502);
      //    }
      //    $retVal = NULL;
      //    $originalConfig = $this->config;
      //    $newConfig = clone $this->config;
      //    try {
      //      $authToken = $this->postToRocketChat('users.createToken',['json' => ['userId' => $otherUserId]]);
      //
      //      //check     $authToken = $this->postToRocketChat('users.createToken',['json' => ['userId' => $otherUserId]]) yield sresult;
      //
      //      $newConfig->setElement('rocket_chat_uid', $authToken['body']['data']['userId']);
      //      $newConfig->setElement('rocket_chat_uit',$authToken['body']['data']['authToken']);
      //
      //      $this->config = $newConfig;
      //      $this->client = $this->createClient(false);
      //
      //      //DO Function call
      //      $retVal = $this->$functionName(...$args );
      //    } catch (\Exception $e) { //TODO IMPLEMENT!!!
      //
      //    } finally {
      //      $this->config = $originalConfig;
      //      $this->client = $this->createClient(false);
      //    }
      //    return $retVal;
      //    //Do Call
      //    //TODO Implement me
      return;
    }

    /**
     * Retrieve User information.
     * @param $config \RocketChat\Config
     * @param $userId string
     *   The userId to look up.
     * @param $userName string
     *   The username to look up.
     * @return array
     */
    function users_info($userId =  NULL, $userName = NULL){
      $req = array();
      $req['query'] = array();
      if(!empty($userId)){
        $req['query']['userId'] = $userId;
      }
      if(!empty($userName)){
        $req['query']['username'] = $userName;
      }
      return $this->getFromRocketChat('users.info',$req);
    }

    /**
     * Logout a session.
     * @param $config \RocketChat\Config
     * @return array
     */
    function users_list(){
      return $this->getFromRocketChat('users.list');
    }

    /**
     * Create a new Channel
     * @param $name
     *   The new channel name
     * @param array $members
     *   The list of the users of this channel.
     */
    function channels_create($name,$members = NULL){
      $options["name"] = $name;
      if(!empty($members)) {
        $options['members'] = $members;
      }
      return $this->postToRocketChat('channels.create',['json' => $options]);
    }


    /**
     * Retrieve User information.
     * @param $config \RocketChat\Config
     * @param string $offset
     *   What offset do you want to use, 0-based.
     * @param string $count
     *   How many do you want to retrieve.
     * @return array
     */
    function channels_list($offset =  NULL, $count = NULL){
      $req = array();
      $req['query'] = array();
      if(!empty($offset)){
        $req['query']['offset'] = $offset;
      }
      if(!empty($userName)){
        $req['query']['count'] = $count;
      }
      if(empty($req)) {
        unset($req);
        $req = NULL;
      }
      return $this->getFromRocketChat('channels.list',$req);
    }

    /**
     * Retrieve User information.
     * @param $config \RocketChat\Config
     * @param string $roomId
     *   the roomId to look up
     * @param string $roomName
     *   the room name to look up.
     *
     * @return array
     */
    function channels_info($roomId =  NULL, $roomName = NULL){
      $req = array();
      $req['query'] = array();
      if(!empty($roomId)){
        $req['query']['roomId'] = $roomId;
      }
      if(!empty($roomName)){
        $req['query']['roomName'] = $roomName;
      }
      return $this->getFromRocketChat('channels.info',$req);
    }

    /**
     * Retrieve the message history information.
     * @param $config \RocketChat\Config
     * @param string $roomId
     *   the roomId to look up
     * @param string $roomName
     *   the room name to look up.
     *
     * @return array
     */
    function channels_history($roomId, $unreads = NULL, $inclusive = NULL, $count = NULL, $latest = NULL, $oldest = NULL){// = "1970-01-01T01:00:00.00Z"
      $req = array();
      $req['query'] = array();
      $req['query']['roomId'] = $roomId;
      if(!empty($latest)){
        $req['query']['latest'] = $latest;
      }
      if(!empty($oldest)){
        $req['query']['oldest'] = $oldest;
      }
      if(isset($inclusive)){
        $req['query']['inclusive'] = $inclusive;
      }
      if(!empty($count)){
        $req['query']['count'] = $count;
      }
      if(isset($unreads)){
        $req['query']['unreads'] = $unreads;
      }

      return $this->getFromRocketChat('channels.history',$req);
    }

    /**
     * @param $config
     * @param $roomId
     * @param $channel
     * @param $text
     * @param $alias
     * @param $emoji
     * @param $avatar
     * @param $attachements
     * @return array
     */
    function postMessage($roomId = NULL, $channel = NULL, $text = NULL, $alias = NULL, $emoji = NULL, $avatar = NULL, $attachements = NULL) {
      $params = [];
      if(!empty($roomId)){
        $params['roomId'] = $roomId;
      }
      if(!empty($channel)){
        $params['channel'] = $channel;
      }
      if(!empty($text)){
        $params['text'] = $text;
      }
      if(!empty($alias)){
        $params['alias'] = $alias;
      }
      if(!empty($emoji)){
        $params['emoji'] = $emoji;
      }
      if(!empty($avatar)){
        $params['avatar'] = $avatar;
      }
      if(!empty($attachements)){
        $params['attachements'] = $attachements;
      }
      return $this->postToRocketChat('chat.postMessage',['json' => $params]);
    }

    /**
     * @param $config \RocketChat\Config
     *   config store that holds the config that is retrieveable using a key-value
     *   architecture. and has possble defaults.
     * @param string $methode
     *   The methode to call (so the part after '/api/v1/').
     * @param array $Options
     *   Optional Data payload. for HTTP_POST calls.
     */
    function postToRocketChat($methode = "info", $Options = NULL) {
      return $this->sendToRocketChat(ApiClient::HTTP_POST,$methode,$Options);
    }

    /**
     * @param $config \RocketChat\Config
     *   config store that holds the config that is retrieveable using a key-value
     *   architecture. and has possble defaults.
     * @param string $methode
     *   The methode to call (so the part after '/api/v1/').
     * @param array $Options
     *   Optional Data payload. for HTTP_POST calls.
     */
    function getFromRocketChat($methode = "info", $Options = NULL) {
//      $class = get_class($this);
      return $this->sendToRocketChat( ApiClient::HTTP_GET,$methode,$Options);//\Drupal\rocket_chat_api\RocketChat\
    }

    /**
     * @param $config \RocketChat\Config
     *   config store that holds the config that is retrieveable using a key-value
     *   architecture. and has possble defaults.
     * @param $Verb ApiClient::HTTP_GET | ApiClient::HTTP_POST
     *   one of the HTTP_* Verbs to use for this call.
     * @param string $methode
     *   The methode to call (so the part after '/api/v1/').
     * @param array $Options
     *   Optional Data payload. for HTTP_POST calls.
     */
    private function sendToRocketChat($Verb = ApiClient::HTTP_GET, $methode = "info", $Options = array()) {
      //    $api = new Apiclient($config, ($methode == "login" ? TRUE : FALSE) );
      $result = new \stdClass();
      try {
        switch ($Verb) {
          case ApiClient::HTTP_GET:
            $result = $this->client->get($methode,$Options);
            break;
          case ApiClient::HTTP_POST: $result = $this->client->post($methode, $Options);
            break;
          default:        throw new ClientException("HTTP Verb is unsupported",null,null,null,null);
        }
        $resultString = (string) $result->getBody();
        $resultHeader = $result->getHeaders();     //HTTP Headers
        $resultCode = $result->getStatusCode();    //HTTP Response Code (like 200)
        $resultStatus = $result->getReasonPhrase();//HTTP Response String (like OK)
      } catch (ClientException $e) {
        $resultStatus = $e->getMessage();
        $resultCode = $e->getCode();
        $resultString = array();
        $resultString['status'] = 'failed';
        $resultString['response'] = $e->getResponse();

        $resultHeader['content-type'][0] = "Error";
        //      $resultString['body'] = (string)$result->getBody(); //This can hold Private Information. Only use for Debugging and disable in Production!
      }
      if (isset($resultHeader['content-type']) && !isset($resultHeader['Content-Type'])) {
        //Quick fix to prevent errors due to capitalization of content-type in the header.
        $resultHeader['Content-Type'] = $resultHeader['content-type'];
      }


      if ($resultHeader['Content-Type'][0] == 'application/json') {
        $jsonDecoder = $this->config->getJsonDecoder();
        $resultString = $jsonDecoder($resultString);
      }

      $Ret = array();
      $Ret['result'] = $result;
      $Ret['body'] = $resultString;
      $Ret['status'] = $resultStatus;
      $Ret['code'] = $resultCode;

      return $Ret;
    }


    public static function validateReturn($result){
      //TODO implement a validation for a guzzle return. currently this code is defunct.

      $result;
      if(is_object($result) && $result instanceof \GuzzleHttp\Psr7\Response) {
        //Guzzle Response

      }

      return true; //TODO Implement Return Validation Checks!
    }

  }
}
