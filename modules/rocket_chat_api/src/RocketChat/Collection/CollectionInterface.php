<?php


namespace Drupal\rocket_chat_api\RocketChat\Collection;

interface CollectionInterface {
//  public const LIST    = "rocket.chat.room.list";
//  public const UPDATE  = "rocket.chat.room.lastUpdate";
//  public const CHANNEL = "rocket.chat.room.";

  public function getCache($forceReload = FALSE);

  public function refreshCache($forceReload = FALSE);

  }
