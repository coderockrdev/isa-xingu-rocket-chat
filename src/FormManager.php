<?php

namespace Drupal\rocket_chat;

/**
 * Copyright (c) 2016.
 *
 * Authors:
 * - Houssam Jelliti <jelitihoussam@gmail.com>.
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
class FormManager {

  /**
   * Check if given value is a port value.
   *
   * @param int $port
   *    Port to check.
   *
   * @return bool
   *    True if in range false if not.
   *
   *    TODO check for integer.
   */
  public static function isPort($port) {
    return ($port > 0 && $port < 65536);
  }

  /**
   * ServerRun.
   *
   * @param string $url
   *    Url to use.
   * @param int $port
   *    Port to use.
   *
   * @return bool
   *    Connection Worked?
   */
  public static function serverRun($url, $port) {
    if ($port === 80 || $port === 443 || $port === 3000) {
      if (strpos($url, 'http://') !== FALSE) {
        $url = str_replace('http://', '', $url);
      }
      elseif (strpos($url, 'https://') !== FALSE) {
        $url = str_replace('https://', '', $url);
      }
      // Server test.
      if ($ping = @fsockopen($url, $port, $errCode, $errStr, 1)) {
        fclose($ping);
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Check for lowercase.
   *
   * @param string $value
   *    Value to check.
   *
   * @return bool
   *    Result.
   */
  public static function isLowerCaseLetters($value) {
    return ctype_lower($value);
  }

}
