<?php

namespace Drupal\rocket_chat\Form;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\rocket_chat_api\RocketChat\ApiClient;
use Drupal\rocket_chat_api\RocketChat\Collection\Channels;
use Drupal\rocket_chat_api\RocketChat\Collection\Groups;
use Drupal\rocket_chat_api\RocketChat\Collection\Users;
use Drupal\rocket_chat_api\RocketChat\Drupal8Config;
use Drupal\rocket_chat_api\RocketChat\Drupal8State;
use Drupal\rocket_chat_api\RocketChat\Element\Channel;
use Drupal\rocket_chat_api\RocketChat\Element\User;
use Drupal\rocket_chat_group\RocketChat\moduleHelper;
use Exception;

class RocketChatGroupHelper {

  public static function rebuildRocketchatState(array &$form, FormStateInterface $form_state = NULL) {
    $rcState = new Drupal8State(Drupal::service('state'));
    $apiConfig = new Drupal8Config(
      Drupal::configFactory(),
      Drupal::moduleHandler(),
      Drupal::state(),
      Drupal::messenger()
    );
    $apiClient = new ApiClient($apiConfig);
    $Channels = new Channels($rcState, $apiClient);
    $Channels->refreshCache(TRUE);
    $Groups = new Groups($rcState, $apiClient);
    $Groups->refreshCache(TRUE);
    $Users = new Users($rcState, $apiClient);
    $Users->refreshCache(TRUE);

    if ($apiConfig->isReady()) {

      /** @var \Drupal\Core\Entity\EntityFieldManager $entityfieldManager */
      $entityfieldManager = Drupal::service('entity_field.manager');

      /** @var array $fieldMapping */
      $fieldMapping = $entityfieldManager->getFieldMapByFieldType('channel');

      /** @var \Drupal\Core\Entity\Query\QueryInterface $groupseq */
      $groupseq = Drupal::entityQuery("group");
      $machineNames = [];
      foreach ($fieldMapping['group'] as $machinename => $definition) {
        $groupseq->exists($machinename);
        $machineNames[] = $machinename;
      }
      $groups = Group::loadMultiple($groupseq->execute());

      foreach ($groups as $group) {
        /** @var \Drupal\Core\Field\FieldItemListInterface[] $fields */
        $fields = $group->getFields(FALSE);
        $fieldValue = [];
        foreach ($machineNames as $machineName) {
          foreach ($fields[$machineName]->getValue() as $channelValues) {
            $value = $channelValues['value'];
            $fieldValue[] = $value;
          }
        }
        /** @var \Drupal\user\Entity\User $groupOwner */
        $groupOwner = $group->getOwner();
        $owner = new User($rcState, $groupOwner->getAccountName(), $groupOwner->getEmail(), $groupOwner->getDisplayName());
        /** @var \Drupal\group\GroupMembership[] $groupMembers */
        $groupMembers = $group->getMembers();
        $members = [];
        foreach ($groupMembers as $groupMember) {
          $groupMemberUser = $groupMember->getUser();
          $member = new User($rcState, $groupMemberUser->getAccountName(), $groupMemberUser->getEmail(), $groupMemberUser->getDisplayName());
          $members[] = $member;
          try {
            $member->getUserProxy($apiClient);
          }
          catch (Exception $e) {
            //TODO log user proxy failure!.
          }
        }
        $type = $group->bundle();
        $Channels = [];
        foreach ($fieldValue as $channelName) {
          $Channel = new Channel(NULL, $fieldValue[0]);
          $Channels[] = $Channel;
          $Channel->setOwner($owner);
          moduleHelper::updateChannelType($type, $Channel);

          try {
            $Channel->getChannelProxy($apiClient);
            $Channel->addMembers($apiClient, $members);
          }
          catch (Exception $e) {
            //Todo log proxy failure
          }
        }
        Drupal::messenger()
          ->addStatus("Group Channel | " . json_encode($fieldValue) . " | Group Type | " . json_encode($type) . " | Members " . count($members));
      }
    }
    else {
      Drupal::messenger()->addError("Rocket Chat connection Failed");
      if (!empty($form_state)) {
        $form_state->setErrorByName('url', "Rocket Chat connection Failed, is this correct?");
      }
    }
  }

}
