<?php
/**
 * Created by 040lab b.v. using PhpStorm from Jetbrains.
 * User: Lawri van Buël
 * Date: 20/06/17
 * Time: 16:33
 */

namespace Drupal\rocket_chat_api\RocketChat {

  /**
   * Interface Config
   *  interface to make an Arbitrary storeage bbackend for the config elements.
   * @package RocketChat
   */
  interface Config {
    /**
     * @param String $elementName
     *  Key value to retrieve from the Config Backend.
     * @param String $Default
     *  A possible Default to use when no config is found in the backend.
     *
     * @return mixed
     *  The retrieved config value.
     */
    public function getElement($elementName, $Default = NULL);

    /**
     * @param String $elementName
     *  Key value to set in the Config Backend.
     * @param String $newValue
     *  the new Value to store.
     *
     * @return void
     */
    public function setElement($elementName, $newValue);

    /**
     * is this a Debug / verbose Run.
     *
     * @return boolean
     */
    public function isDebug();

    /**
     * Get a function pointer to the function to use for JsonDecodeing.
     * @return mixed
     */
    public function getJsonDecoder();

    /**
     * @param String $message
     *   Message to report back.
     * @param String $type
     *   Type or Level of the Message
     *
     * @return mixed
     */
    public function notify($message, $type);

  }
}