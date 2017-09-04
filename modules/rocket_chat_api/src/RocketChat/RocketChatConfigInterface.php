<?php
/**
 * Created by 040lab b.v. using PhpStorm from Jetbrains.
 * User: Lawri van Buël
 * Date: 20/06/17
 * Time: 16:33
 */

namespace Drupal\rocket_chat_api\RocketChat {

  /**
   * Interface RocketChatConfigInterface
   *  interface to make an Arbitrary storage backend for the config elements.
   * @package RocketChat
   */
  interface RocketChatConfigInterface {

    /**
     * Get a RocketChatConfigInterface Element.
     *
     * @param string $elementName
     *  Key value to retrieve from the RocketChatConfigInterface Backend.
     * @param string $Default
     *  A possible Default to use when no config is found in the backend.
     *
     * @return mixed
     *  The retrieved config value.
     */
    public function getElement($elementName, $Default = NULL);

    /**
     * Set an Element in the RocketChatConfigInterface.
     *
     * @param string $elementName
     *   Key value to set in the RocketChatConfigInterface Backend.
     * @param string $newValue
     *   the new Value to store.
     *
     * @return void
     *   The Emptyness of the Digital void is unimaginable.
     */
    public function setElement($elementName, $newValue);

    /**
     * is this a Debug / verbose Run.
     *
     * @return boolean
     *   Are we in debug mode?
     */
    public function isDebug();

    /**
     * Get a function pointer to the function to use for JsonDecodeing.
     *
     * @return mixed
     *   Result array
     */
    public function getJsonDecoder();

    /**
     * Notify the backend.
     *
     * @param string $message
     *   Message to report back.
     * @param string $type
     *   Type or Level of the Message.
     *
     * @return mixed
     *   Result of notify on backend.
     */
    public function notify($message, $type);

  }
}
