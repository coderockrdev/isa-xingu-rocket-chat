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

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\State\StateInterface;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rocket_chat\Utility;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Class RocketChatSettingsForm.
 *
 * @package Drupal\rocket_chat\Form
 */
class RocketChatSettingsForm extends ConfigFormBase {

  private $moduleHandler;
  private $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The ModuleHandler to interact with loaded modules.
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, StateInterface $state) {
    parent::__construct($config_factory);
    $this->moduleHandler = $moduleHandler;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var ContainerInterface $container */
    if(!empty($container)) {
      return new static(
        $container->get("config.factory"),
        $container->get("module_handler"),
        $container->get("state")
      );
    }
    else {
      //something huge went wrong, we are missing the ContainerInterface.
      throw new ServiceNotFoundException('ContainerInterface');
    }
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
  protected function getEditableConfigNames() {
    return ['rocket_chat.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {

    $config = $this->config('rocket_chat.settings');
    $server = $config->get('server');

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('The Rocket.chat server address:'),
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => "https://demo.rocket.chat/",
      ],
    ];
    // Only set the value if there is a value.
    if (!empty($server)) {
      $form['url']['#defaultvalue'] = $server;
    }

    // Only add the following when the rocket_chat_api module is enabled.
    if ($this->moduleHandler->moduleExists('rocket_chat_api')) {
      $form['rocketchat_admin'] = [
        '#type' => 'password',
        '#description' => $this->t("Rocket chat Admin login name (for API use)"),
        '#title' => $this->t('Rocketchat Admin User:'),
        '#required' => FALSE,
        '#attributes' => [
          'placeholder' => $config->get('user'),
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
    // All required fields are submitted.
    if (!empty($form_state->getValue('url'))) {

      // Check if host server is running.
      if (!Utility::serverRun(
        $form_state->getValue('url'))) {
        $form_state->setErrorByName('url', "<div class=\"error rocketchat\">" .
          $this->t('<strong>Server is not working!</strong><br>') .
          $this->t('<em>incorrect address</em>,') . ' ' .
          $this->t('please check your supplied URL') . '</div>');
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

    if(!empty($form_user) || !empty($form_secret)) {
      //Logging in with new credentials.
      $this->state->deleteMultiple(['rocket_chat_uid','rocket_chat_uit']);
      $apiConfig = new Drupal8Config($this->configFactory(),$this->moduleHandler,$this->state);
      $Api = new ApiClient($apiConfig);
      if($Api->login($config->get('user'),$config->get('secret'))) {
        $user = $Api->whoami();
        $user['body']['username'];
        drupal_set_message(
          $this->t('Rocketchat User [@user]', ['@user' => $user['body']['username']])
        );
      }
    }
  }

}
