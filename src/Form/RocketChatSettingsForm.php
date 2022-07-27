<?php

namespace Drupal\rocket_chat\Form;

/**
 * Copyright (c) 2017.
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

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\rocket_chat\Utility;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Collection\Channels;
use Drupal\rocket_chat_api\RocketChat\Collection\Groups;
use Drupal\rocket_chat_api\RocketChat\Collection\Users;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Drupal\rocket_chat_api\RocketChat\Drupal8State;
use Drupal\rocket_chat_api\RocketChat\InMemoryConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RocketChatSettingsForm.
 *
 * @package Drupal\rocket_chat\Form
 */
class RocketChatSettingsForm extends ConfigFormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The ModuleHandler to interact with loaded modules.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Stateinterface to manipulate the state.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, StateInterface $state, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
    if (!empty($container)) {
      /** @noinspection PhpParamsInspection */
      return new static(
        $container->get("config.factory"),
        $container->get("module_handler"),
        $container->get("state"),
        $container->get('messenger')
      );
    }
    else {
      // Something huge went wrong, we are missing the ContainerInterface.
      throw new ServiceNotFoundException('ContainerInterface');
    }
  }

  /**
   * Alternative submit handler for Button Precces that do not safe.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function altSubmitForm(array &$form, FormStateInterface $form_state) {
    /** @var RocketChatSettingsForm $formObject */
    $formObject = $form_state->getFormObject();
    $apiConfig = new Drupal8Config($formObject->configFactory(), $formObject->moduleHandler, $formObject->state, $formObject->messenger);
    $apiClient = new ApiClient($apiConfig);

    if ($apiConfig->isReady()) {
      $RocketChatState = new Drupal8State($formObject->state);
      $Channels = new Channels($RocketChatState, $apiClient);
      $Channels->refreshCache(TRUE);
      $Groups = new Groups($RocketChatState, $apiClient);
      $Groups->refreshCache(TRUE);
      $Users = new Users($RocketChatState, $apiClient);
      $Users->refreshCache(TRUE);
    }
  }

  /**
   * Alternative submit handler for Button Precces that do not safe.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function groupsSubmitForm(array &$form, FormStateInterface $form_state) {
    RocketChatGroupHelper::rebuildRocketchatState($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rocket_chat.admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {

    $config = $this->config('rocket_chat.settings');
    $server = $config->get('server');
    $apiConfig = new Drupal8Config($this->configFactory(), $this->moduleHandler, $this->state, $this->messenger);

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('The Rocket.chat server address:'),
      '#required' => FALSE,
    ];
    // Only set the value if there is a value.
    if (!empty($server)) {
      $form['url']['#defaultvalue'] = $server;
      $form['url']['#attributes']['placeholder'] = $server;
    }
    else {
      $form['url']['#attributes']['placeholder'] = "https://demo.rocket.chat/";
    }

    // Only add the following when the rocket_chat_api module is enabled.
    if ($this->moduleHandler->moduleExists('rocket_chat_api')) {
      $user = $config->get('user');
      $form['rocketchat_admin'] = [
        '#type' => 'password',
        '#description' => $this->t("Rocket chat Admin user name (for API use)"),
        '#title' => $this->t('Rocketchat Admin User:'),
        '#required' => FALSE,
        '#attributes' => [
          'autocomplete' => "new-password",
          'placeholder' =>
            (empty($user) ? "(Rocket chat Admin user name)" : $user),
        ],
      ];
      if (!empty($user)) {
        $form['rocketchat_admin']['#defaultvalue'] = $user;
      }
      $form['rocketchat_key'] = [
        '#type' => 'password',
        '#title' => $this->t('Rocketchat Admin Password:'),
        '#description' => $this->t("Rocket chat Admin password (for API use)"),
        '#required' => FALSE,
        '#attributes' => [
          'autocomplete' => "new-password",
          'placeholder' => '****************',
        ],
      ];
    }

    $form['rocketchatRefreshCache'] = [
      '#type' => 'button',
      '#value' => $this->t("Rebuild Rocketchat Cache"),
      "#submit" => [
        [
          '\Drupal\rocket_chat\Form\RocketChatSettingsForm',
          'altSubmitForm',
        ],
      ],
      "#limit_validation_errors" => FALSE,
      '#executes_submit_callback' => TRUE,
    ];
    if (!$apiConfig->isReady()) {
      $form['rocketchatRefreshCache']['#disabled'] = TRUE;
    }

    if ($this->moduleHandler->moduleExists("rocket_chat_group")) {
      $form['rocketchatRefreshGroups'] = [
        '#type' => 'button',
        '#value' => $this->t("Rebuild Groups Channels"),
        "#submit" => [
          [
            '\Drupal\rocket_chat\Form\RocketChatSettingsForm',
            'groupsSubmitForm',
          ],
        ],
        "#limit_validation_errors" => FALSE,
        '#executes_submit_callback' => TRUE,
      ];
      if (!$apiConfig->isReady()) {
        $form['rocketchatRefreshGroups']['#disabled'] = TRUE;
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // All required fields are submitted.
    if (!empty($form_state->getValue('url'))) {
      // Check if host server is running.
      $smokeCheck = Utility::serverRun($form_state->getValue('url'));
      $info = [];
      if ($smokeCheck) {
        $apiConfig = new Drupal8Config($this->configFactory(), $this->moduleHandler, $this->state, $this->messenger);
        $empty = "";
        $memConfig = new InMemoryConfig($apiConfig, $empty, $empty);
        $memConfig->setElement('rocket_chat_url', $form_state->getValue('url'));
        $apiClient = new ApiClient($memConfig);

        // Check if the Rocket chat is actually functional with an info call.
        $info = $apiClient->info();
      }
      if (!$smokeCheck || !$info['status'] == "OK") {
        $erred = TRUE;
      }
      else {
        $erred = FALSE;
      }
      if ($erred) {
        $form_state->setErrorByName('url', t('Connecting with the RocketChat service failed. Make sure the URL is correct.'));
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
      $this->messenger->addStatus(
        $this->t(
          'Updated the Rocketchat [@oldurl] -> [@url]',
          [
            '@url' => $form_url,
            "@oldurl" => (empty($oldUrl) ? $this->t("Not Set") : $oldUrl),
          ]
        )
      );
      if (empty($form_user)) {
        $form_user = $config->get('user');
      }
      if (empty($form_secret)) {
        $form_secret = $config->get('secret');
      }
    }

    if (!empty($form_user) || !empty($form_secret)) {
      $apiConfig = new Drupal8Config($this->configFactory(), $this->moduleHandler, $this->state, $this->messenger);

      $user = (empty($form_user) ? $config->get('user') : $form_user);
      $secret = (empty($form_secret) ? $config->get('secret') : $form_secret);

      $memConfig = new InMemoryConfig($apiConfig, $user, $secret);

      $apiClient = new ApiClient($memConfig);

      $loginState = $apiClient->login($user, $secret);

      if ($loginState) {
        $apiConfig->setElement('rocket_chat_uid', $memConfig->getElement('rocket_chat_uid', ""));
        $apiConfig->setElement('rocket_chat_uit', $memConfig->getElement('rocket_chat_uit', ""));
        $user = $apiClient->whoAmI();
        $user['body']['username'];
        $this->messenger->addStatus(
          $this->t(
            'Rocketchat User [@user]',
            ['@user' => $user['body']['username']])
        );
      }
      else {
        // Login failed, unset the credentials.
        $form_user = NULL;
        $form_secret = NULL;
      }

    }

    if (!empty($form_user)) {
      $config
        ->clear('user')
        ->set('user', $form_user)
        ->save();
      $this->messenger->addStatus(
        $this->t('Updated the Rocketchat Admin User')
      );
    }

    if (!empty($form_secret)) {
      $config
        ->clear('secret')
        ->set('secret', $form_secret)
        ->save();
      $this->messenger->addStatus(
        $this->t('Updated the Rocketchat Admin Password')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rocket_chat.settings'];
  }

}
