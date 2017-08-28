<?php

namespace Drupal\rocket_chat\Form;

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
 * Contains \Drupal\rocket_chat\Form\RocketChatSettingsForm.
 *
 * The ConfigFormBase required class for module configuration.
 * Any configuration enhancement must be done within.
 */

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rocket_chat\Utility;
use Drupal;

/**
 * Class RocketChatSettingsForm.
 *
 * @package Drupal\rocket_chat\Form
 */
class RocketChatSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rocket_chat.admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rocket_chat.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $moduleHandler = Drupal::service('module_handler');
    $config = $this->config('rocket_chat.settings');
    $server = $config->get('server');

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('The Rocket.chat server address:'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => "https://demo.rocket.chat/",
      ],
    ];
    // Only set the value if there is a value.
    if (!empty($server)) {
      $form['url']['#value'] = $server;
    }

    // Only add the following when the rocket_chat_api module is enabled.
    if ($moduleHandler->moduleExists('rocket_chat_api')) {
      $form['rocketchat_admin'] = [
        '#type' => 'password',
        '#description' => $this->t("Rocket chat Admin login name (for API use)"),
        '#title' => $this->t('Rocketchat Admin User:'),
        '#required' => FALSE,
        '#attributes' => [
          'placeholder' => 'RocketChat Admin User',
        ],
      ];
      $form['rocketchat_key'] = [
        '#type' => 'password',
        '#title' => $this->t('Rocketchat Admin Password:'),
        '#description' => $this->t("Rocket chat Admin login password (for API use)"),
        '#required' => FALSE,
        '#attributes' => [
          'placeholder' => '****************',
        ],
      ];
    }

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // All requirerd fields are submitted.
    if (!empty($form_state->getValue('url'))) {

      // Check if host server is running.
      if (!Utility::serverRun(
        $form_state->getValue('url'))) {
        $form_state->setErrorByName('url', "<div class=\"error rocketchat\">" .
          $this->t('<strong>Server is not working!</strong><br>') .
          $this->t('<em>incorrect address</em>,') . ' ' .
          $this->t('please check your server and your port.') . '</div>');
      }

    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('rocket_chat.settings');

    $oldUrl = $config->get('server');

    $form_url = $form_state->getValue('url');
    $form_user = $form_state->getValue('rocketchat_admin');
    $form_secret = $form_state->getValue('rocketchat_key');

    if (!empty($form_url)) {
      $config
        ->clear('server')
        ->set('server', $form_url)
        ->save();
      drupal_set_message(
        $this->t(
          'Updated the Rocketchat [@oldurl] -> [@url]',
          ['@url' => $form_url, "@oldurl" => (empty($oldUrl) ? $this->t("Not Set") : $oldUrl)]
        )
      );
    }

    if (!empty($form_user)) {
      $config
        ->clear('user')
        ->set('user', $form_user)
        ->save();
      drupal_set_message(
        $this->t('Updated the Rocketchat Admin User')
      );
    }

    if (!empty($form_secret)) {
      $config
        ->clear('secret')
        ->set('secret', $form_secret)
        ->save();
      drupal_set_message(
        $this->t('Updated the Rocketchat Admin Password')
      );
    }
  }

}
