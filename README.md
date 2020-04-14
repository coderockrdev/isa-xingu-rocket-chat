#_Rocket.chat_ Module for Drupal 8.x-2.x

CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Requirements
 * Rocketchat instance Requirement
 * (Sub=) Modules
 * Installation
 * Configuration
 * Troubleshooting
 * Maintainers

INTRODUCTION
------------

The Rocket.chat Module enables a drupal site to integrate Rocket.chat.
It consist of several modules, see [(Sub=) Modules](#-sub-modules)
 
Requirements
------------

This module is designed for:
 - [Drupal 8](https://www.drupal.org/project/drupal)
 - [Rocket.chat 1.0.0+](https://rocket.chat/)

It is tested with:
 - Drupal 8.8.5
 - Rocket.chat 3.1.0
 - php 7.4.4
 - node v12.16.1

Rocketchat instance Requirement
-------------------------------

We only support Rocket chat instances that are behind a TLS reverse proxy.

(Sub-) Modules
--------------
- Rocket Chat(`rocket_chat`) the main Rocket.Chat integration module.
  allowing Rocket chat to be integrated with Drupal
- Rocket Chat API(`rocket_chat_api`) API module for Rocketchat. This allows for
  communication with a Rocket chat.
- Rocket Chat Api Test (`rocket_chat_api_test`) a Quick Test page that utilizes
  the Rocket chat API module to do API calls on the configured Rocket chat.
  This facilitates an easy interface to test api calls and see what the return.
- Rocket Chat Group integration(`rocket_chat_group`) Allows you to setup a group
  integration to have channels in the rocketchat for groups where users are
  automatically invited into the channel. (Made in conjuction with
  [OpenSocial](https://www.getopensocial.com/),
  [OpenSocial Project](https://www.drupal.org/project/social)).
- Rocket Chat Livechat Block(`livechat`) Allows controle over placeing the
  livechat widget on your site through th ebolocks interface.

Installation
------------

- Rocket chat Module:
  - Install the module in your modules folder.
  - if you have not done so already, setup Rocket chat.   (out of scope for this
    readme, check out [Rocket.chat](https://rocket.chat) for instructions on how
     to setup
    rocketchat.)
- Livechat Module:
  - install the livechat module and rocket_chat module.
  - Setup the rocket_chat module.
  - Go to `[Structure][Block layout]`. there you can place the livechat block
    using the "Place block" button.
    This works as a normal block we recommend you add it to a footer or alike
    for performance.
    Be aware we currently only suport the newer 2.0 version of the lifechat widget.
- Rocket Chat API Module:
  - This module enables you to utilize the Rocket chat API.
- API Test:
  - You can use this module to test the various aspects of the API without
    having to write all the code to do so.
    After enabling it its available on the `/apitest` path for rocketchat
    admins.
- Group integration:
  - When enabled your group should have A Channel field, this field will inform
    Rocket chat what naem to pick for the channel in Rocket chat.
  - The new Block. `rocket Chat Group Channel` controles where the iframe will
    be rendered to allow instant chat between members.

Configuration
-------------

- Configure your rocketchat url in `[drupal-url]/admin/config/rocket_chat` , you
  will need to have the proper permissions!
 
Troubleshooting
---------------
 
Leave a detailed report of your issue in the
[issue queue](https://www.drupal.org/project/issues/search/2649818) and the
maintainers will add it to the task list.
I am also present in the [Drupalchat.me/Rocket.chat.Module](https://drupalchat.me/channel/rocket.chat.module)
as `@Sysosmaster`.
  
Maintainers / Authors
---------------------
 
 - [Sysosmaster](https://www.drupal.org/u/sysosmaster) (Current maintainer of
   rocketchat module on d.o.).

Previous Authors (Hall of Fame)
----------------
 - [Gabriel Engel](https://www.drupal.org/u/gabriel-engel) (Creator Rocket.chat
   ).
 - [idevit](https://www.drupal.org/u/idevit) (Community Plumbing).
 - [jelhouss](https://www.drupal.org/u/jelhouss) (Initial Module Creator).

Last Updated, 14-April-2020.
