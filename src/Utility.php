<?php

namespace Drupal\rocket_chat;

use Drupal;
use Exception;
use HttpUrlException;

/**
 * Copyright (c) 2016.
 *
 * Authors:
 * - Lawri van Buël <sysosmaster@2588960.no-reply.drupal.org>.
 *
 * This file is part of (rocket_chat) a Drupal 8 Module for Rocket.Chat
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * @file
 * Contains \Drupal\rocket_chat\FormManager.
 */

/**
 * Check the form values.
 */
class Utility {

  /**
   * Will test the host for connectivity (ony t* transports are used).
   *
   * @param string $host
   * @param int $port
   * @param string $path
   *
   * @return string
   */
  private static function transportTestUrl($host = "localhost", $port = 80, $path = "") {
    $transports = stream_get_transports();
    foreach ($transports as $index => $transport) {
      if (strtolower(substr($transport, 0, 1)) !== strtolower("t")) {
        unset($transports[$index]);
      }
    }
    $connections = [];
    $results = [];
    $meta = [];
    $working = [];
    $processed = [];
    $returnCode = [];
    $errCode = [];
    $errStr = [];
    foreach ($transports as $index => $transport) {
      $conUrl = $transport . "://" . $host;// . $path . "/api/info";
      try {
        $connections[$index] = fsockopen($conUrl, $port, $errCode[$index], $errStr[$index], 15);

        if ($connections[$index]) {
          $state = stream_set_blocking($connections[$index], 1);
          $bits = fwrite($connections[$index], "GET $path/api/info HTTP/1.1\r\nHost: $host\r\n\r\n");
          $meta[$index] = stream_get_meta_data($connections[$index]);
          $results[$index] = "";
          while (!$oef = feof($connections[$index])) {
            $meta[$index] = stream_get_meta_data($connections[$index]);
            $buf = fread($connections[$index], 100);
            if ($buf !== FALSE) {
              $results[$index] = $results[$index] . $buf;
            }
            break;
          }
          $meta[$index] = stream_get_meta_data($connections[$index]);
          $sections = explode("\r\n\r\n", $results[$index]);
          foreach ($sections as $subIndex => $section) {
            $processed[$index][$subIndex] = explode("\r\n", rtrim($section));
          }
          $returnCode[$index] = explode(" ", $processed[$index][0][0])[1];
          $meta[$index] = stream_get_meta_data($connections[$index]);
        }
      }
      catch (Exception $exception) {
        $connections[$index] = FALSE;
        //Error?
      }
      if ($connections[$index]) {
        $working[] = $transport;
      }
      if ($connections[$index] !== FALSE) {
        fclose($connections[$index]);
      }
    }
    $selected = "";
    foreach ($returnCode as $offset => $code) {
      if ($code == 200) {
        $selected = $transports[$offset];
      }
    }
    return $selected;
  }

  /**
   * ServerRun.
   *
   * @param string $url
   *   Url to use.
   *
   * @return bool
   *   Connection Worked?
   */
  public static function serverRun($url) {
    try {
      $urlSplit = Utility::parseUrl($url);
      $ConnectionType = self::transportTestUrl($urlSplit['host'], $urlSplit['port'], $urlSplit['path']);
      if (!empty($ConnectionType)) {
        Drupal::messenger()
          ->addStatus(t("Connected to RocketChat through [@transport]", ["@transport" => $ConnectionType]));
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    catch (Exception $exception) {
      error_log("serverRun encountered and exception, check [$url] for valid URL" . $exception->getMessage());
      return FALSE;
    }
  }

  /**
   * Helper function to split an URL into its base components.
   *
   * @param string $url
   *   Url to parse.
   *
   * @return array
   *   Url in its separated Parts.
   *
   * @throws \HttpUrlException
   *   Throws when scheme is missing.
   */
  public static function parseUrl($url) {
    $returnValue = parse_url($url);
    if (!isset($returnValue['scheme'])) {
      throw new HttpUrlException("Missing Scheme.", 404);
    }
    if (!isset($returnValue['host'])) {
      $returnValue['hosts'] = 'localhost';
    }
    if (!isset($returnValue['path'])) {
      $returnValue['path'] = "";
    }
    if (!isset($returnValue['port'])) {
      switch ($returnValue['scheme']) {
        case "http":
          $returnValue['port'] = 80;
          break;

        case "https":
          $returnValue['port'] = 443;
          break;

      }
    }
    $returnValue['baseUrl'] = $returnValue['host'] . $returnValue['path'];
    $returnValue['orgScheme'] = $returnValue['scheme'];
    switch ($returnValue['scheme']) {
      default:
        $returnValue['url'] = "tcp://" . $returnValue['baseUrl'];
        break;

      case "https":
        $returnValue['url'] = "tls://" . $returnValue['baseUrl'];
        break;

    }
    return $returnValue;
  }

}
