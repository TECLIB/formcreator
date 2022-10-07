<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2021 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

namespace tests\units\GlpiPlugin\Formcreator\Target;

use Computer;
use Contract;
use Entity;
use Generator;
use GlpiPlugin\Formcreator\Common;
use GlpiPlugin\Formcreator\Condition;
use GlpiPlugin\Formcreator\Fields;
use GlpiPlugin\Formcreator\Form;
use GlpiPlugin\Formcreator\FormAnswer;
use GlpiPlugin\Formcreator\Item_TargetTicket;
use GlpiPlugin\Formcreator\Linker;
use GlpiPlugin\Formcreator\Section;
use GlpiPlugin\Formcreator\Target_Actor;
use GlpiPlugin\Formcreator\Tests\CommonTestCase;
use Group_Ticket;
use Item_Ticket;
use ITILCategory;
use Location;
use Monitor;
use Profile;
use Session;
use Supplier_Ticket;
use TaskCategory;
use Ticket as GlpiTicket;
use Ticket_Ticket;
use Ticket_User;
use TicketTemplate;
use TicketTemplatePredefinedField;
use User;

class Ticket extends CommonTestCase {

   public function beforeTestMethod($method) {
      parent::beforeTestMethod($method);
      switch ($method) {
         case 'testSetTargetEntity':
         case 'testSetTargetCategory':
         case 'testSetTargetLocation':
         case 'testSetTargetContract':
         case 'testSetTargetType':
         case 'testPrepareTemplate':
         case 'testDeleteLinkedTickets':
         case 'testSetTargetAssociatedItem':
         case 'testSetRequestSource':
            $this->boolean($this->login('glpi', 'glpi'))->isTrue();
            break;
      }
   }

   public function providerGetTypeName() {
      return [
         [
            'number' => 0,
            'expected' => 'Target tickets',
         ],
         [
            'number' => 1,
            'expected' => 'Target ticket',
         ],
         [
            'number' => 2,
            'expected' => 'Target tickets',
         ],
      ];
   }

   /**
    * @dataProvider providerGetTypeName
    * @param integer $number
    * @param string $expected
    */
   public function testGetTypeName($number, $expected) {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getTypeName($number);
      $this->string($output)->isEqualTo($expected);
   }

   public function testGetEnumRequestTypeRule(): void {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumRequestTypeRule();
      $this->array($output)->isEqualTo([
         $testedClass::REQUESTTYPE_NONE      => 'Default or from a template',
         $testedClass::REQUESTTYPE_SPECIFIC  => "Specific type",
         $testedClass::REQUESTTYPE_ANSWER    => "Equals to the answer to the question",
      ]);
   }

   public function testGetEnumRequestSourceRule(): void {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumRequestSourceRule();
      $this->array($output)->isEqualTo([
         $testedClass::REQUESTTYPE_NONE      => 'Source from template or user default or GLPI default',
         $testedClass::REQUESTTYPE_SPECIFIC  => "Formcreator",
      ]);
   }

   public function testGetEnumDestinationEntity() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumDestinationEntity();
      $this->array($output)->isEqualTo([
         $testedClass::DESTINATION_ENTITY_CURRENT      => 'Current active entity',
         $testedClass::DESTINATION_ENTITY_REQUESTER  => "Default requester user's entity",
         $testedClass::DESTINATION_ENTITY_REQUESTER_DYN_FIRST    => "First dynamic requester user's entity (alphabetical)",
         $testedClass::DESTINATION_ENTITY_REQUESTER_DYN_LAST      => "Last dynamic requester user's entity (alphabetical)",
         $testedClass::DESTINATION_ENTITY_FORM  => 'The form entity',
         $testedClass::DESTINATION_ENTITY_VALIDATOR    => 'Default entity of the validator',
         $testedClass::DESTINATION_ENTITY_SPECIFIC      => 'Specific entity',
         $testedClass::DESTINATION_ENTITY_USER  => 'Default entity of a user type question answer',
         $testedClass::DESTINATION_ENTITY_ENTITY    => 'From a GLPI object > Entity type question answer',
      ]);
   }

   public function testGetEnumTagType() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumTagType();
      $this->array($output)->isEqualTo([
         $testedClass::TAG_TYPE_NONE                   => __('None'),
         $testedClass::TAG_TYPE_QUESTIONS              => __('Tags from questions', 'formcreator'),
         $testedClass::TAG_TYPE_SPECIFICS              => __('Specific tags', 'formcreator'),
         $testedClass::TAG_TYPE_QUESTIONS_AND_SPECIFIC => __('Tags from questions and specific tags', 'formcreator'),
         $testedClass::TAG_TYPE_QUESTIONS_OR_SPECIFIC  => __('Tags from questions or specific tags', 'formcreator')
      ]);
   }

   public function testGetEnumDueDateType() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumDueDateRule();
      $this->array($output)->isEqualTo([
         $testedClass::DUE_DATE_RULE_NONE   => __('TTR from template or none', 'formcreator'),
         $testedClass::DUE_DATE_RULE_ANSWER => __('equals to the answer to the question', 'formcreator'),
         $testedClass::DUE_DATE_RULE_TICKET => __('calculated from the ticket creation date', 'formcreator'),
         $testedClass::DUE_DATE_RULE_CALC   => __('calculated from the answer to the question', 'formcreator'),
      ]);
   }

   public function testGetEnumLocationType() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumLocationRule();
      $this->array($output)->isEqualTo([
         $testedClass::LOCATION_RULE_NONE        => __('Location from template or none', 'formcreator'),
         $testedClass::LOCATION_RULE_SPECIFIC    => __('Specific location', 'formcreator'),
         $testedClass::LOCATION_RULE_ANSWER      => __('Equals to the answer to the question', 'formcreator'),
         $testedClass::LOCATION_RULE_LAST_ANSWER => __('Last valid answer', 'formcreator'),
      ]);
   }

   public function testGetEnumContractType() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumContractRule();
      $this->array($output)->isEqualTo([
         $testedClass::CONTRACT_RULE_NONE        => __('Contract from template or none', 'formcreator'),
         $testedClass::CONTRACT_RULE_SPECIFIC    => __('Specific contract', 'formcreator'),
         $testedClass::CONTRACT_RULE_ANSWER      => __('Equals to the answer to the question', 'formcreator'),
         $testedClass::CONTRACT_RULE_LAST_ANSWER => __('Last valid answer', 'formcreator'),
      ]);
   }

   public function testGetEnumUrgencyRule() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumUrgencyRule();
      $this->array($output)->isEqualTo([
         $testedClass::URGENCY_RULE_NONE      => 'Urgency from template or Medium',
         $testedClass::URGENCY_RULE_SPECIFIC  => 'Specific urgency',
         $testedClass::URGENCY_RULE_ANSWER    => 'Equals to the answer to the question',
      ]);
   }

   public function testGetEnumAssociateRule() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumAssociateRule();
      $this->array($output)->isEqualTo([
         $testedClass::ASSOCIATE_RULE_NONE         => 'None',
         $testedClass::ASSOCIATE_RULE_SPECIFIC     => 'Specific asset',
         $testedClass::ASSOCIATE_RULE_ANSWER       => 'Equals to the answer to the question',
         $testedClass::ASSOCIATE_RULE_LAST_ANSWER  => 'Last valid answer',
      ]);
   }

   public function testGetEnumCategoryRule() {
      $testedClass = $this->getTestedClassName();
      $output = $testedClass::getEnumCategoryRule();
      $this->array($output)->isEqualTo([
         $testedClass::CATEGORY_RULE_NONE          => 'Category from template or none',
         $testedClass::CATEGORY_RULE_SPECIFIC      => 'Specific category',
         $testedClass::CATEGORY_RULE_ANSWER        => 'Equals to the answer to the question',
         $testedClass::CATEGORY_RULE_LAST_ANSWER   => 'Last valid answer',
      ]);
   }

   public function testGetItem_User() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getItem_User');
      $this->object($output)->isInstanceOf(Ticket_User::class);
      $this->boolean($output->isNewItem())->isTrue();
   }

   public function testGetItem_Group() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getItem_Group');
      $this->object($output)->isInstanceOf(Group_Ticket::class);
      $this->boolean($output->isNewItem())->isTrue();
   }

   public function testGetItem_Supplier() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getItem_Supplier');
      $this->object($output)->isInstanceOf(Supplier_Ticket::class);
      $this->boolean($output->isNewItem())->isTrue();
   }

   public function testGetItem_Item() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getItem_Item');
      $this->object($output)->isInstanceOf(Item_Ticket::class);
      $this->boolean($output->isNewItem())->isTrue();
   }

   public function testGetCategoryFilter() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getCategoryFilter');
      $this->array($output)->isEqualTo([
         'OR' => [
            'is_request'  => 1,
            'is_incident' => 1
         ]
      ]);
   }

   public function testGetTaggableFields() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getTaggableFields');
      $this->array($output)->isEqualTo([
         'target_name',
         'content',
      ]);
   }

   public function testGetTargetItemtypeName() {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'getTargetItemtypeName');
      $this->string($output)->isEqualTo(GlpiTicket::class);
   }

   /**
    * Tests that deleting a target ticket of a form also deletes relations between tickets and generated tickets
    *
    * @covers TargetTicket::pre_deleteItem
    */
   public function testDeleteLinkedTickets() {
      global $CFG_GLPI;

      $testedClass = $this->getTestedClassName();
      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      // setup the test
      $ticket = new GlpiTicket();
      $ticket->add([
         'name'               => 'ticket',
         'content'            => 'help !',
         'users_id_recipient' => '0',
      ]);
      $this->boolean($ticket->isNewItem())->isFalse();

      $formFk = Form::getForeignKeyField();
      $form = $this->getForm(['name' => 'a form']);

      $targetTicket_1 = $this->newTestedInstance();
      $targetTicket_1->add([
         'name'      => 'target 1',
         $formFk     => $form->getID(),
      ]);
      $this->boolean($targetTicket_1->isNewItem())->isFalse();

      $targetTicket_2 = $this->newTestedInstance();
      $targetTicket_2->add([
         'name'      => 'target 2',
         $formFk     => $form->getID(),
      ]);
      $this->boolean($targetTicket_2->isNewItem())->isFalse();

      $targetTicketFk = $testedClass::getForeignKeyField();
      $item_targetticket_1 = new Item_TargetTicket();
      $item_targetticket_1->add([
         $targetTicketFk   => $targetTicket_1->getID(),
         'link'            => Ticket_Ticket::LINK_TO,
         'itemtype'        => GlpiTicket::class,
         'items_id'        => $ticket->getID(),
      ]);
      $this->boolean($item_targetticket_1->isNewItem())->isFalse();

      $item_targetticket_2 = new Item_TargetTicket();
      $item_targetticket_2->add([
         $targetTicketFk   => $targetTicket_1->getID(),
         'link'            => Ticket_Ticket::LINK_TO,
         'itemtype'        => $testedClass,
         'items_id'        => $targetTicket_2->getID(),
      ]);
      $this->boolean($item_targetticket_2->isNewItem())->isFalse();

      // delete the target ticket
      $targetTicket_1->delete(['id' => $targetTicket_1->getID()]);

      // Check the linked ticket or target ticket are deleted
      $this->boolean($item_targetticket_1->getFromDB($item_targetticket_1->getID()))->isFalse();
      $this->boolean($item_targetticket_2->getFromDB($item_targetticket_2->getID()))->isFalse();
   }

   /**
    *
    * @return void
    */
   public function  testSetTargetEntity() {
      global $CFG_GLPI;

      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      $form = $this->getForm();
      $formFk = Form::getForeignKeyField();
      $targetTicket = $this->getTargetTicket([
         $formFk => $form->getID(),
      ]);

      // Use a dummy class to access protected methods
      $instance = $this->newTestedInstance();
      $instance->getFromDB($targetTicket->getID());

      // Test current entity of the requester
      $entity = new Entity();
      $entityId = $entity->import([
         'entities_id' => '0',
         'name' => $this->getUniqueString()
      ]);
      Session::changeActiveEntities($entityId);
      $testedClass = $this->getTestedClassName();
      $targetTicket->update([
         'id' => $targetTicket->getID(),
         'destination_entity' => $testedClass::DESTINATION_ENTITY_CURRENT,
         'destination_entity_value' => '0',
      ]);
      $instance->getFromDB($targetTicket->getID());
      $formAnswer = new FormAnswer();
      $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'entities_id' => $entityId,
      ]);
      $formAnswer->getFromDB($formAnswer->getID());
      $requesterId = \Session::getLoginUserID();
      $output = $this->callPrivateMethod($instance, 'setTargetEntity', [], $formAnswer, $requesterId);
      $this->integer((int) $output['entities_id'])->isEqualTo($entityId);

      // Test requester's entity
      $targetTicket->update([
         'id' => $targetTicket->getID(),
         'destination_entity' => $testedClass::DESTINATION_ENTITY_REQUESTER,
         'destination_entity_value' => '0',
      ]);
      $instance->getFromDB($targetTicket->getID());
      $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'entities_id' => $entityId,
      ]);
      Session::changeActiveEntities($entityId);
      $requesterId = Session::getLoginUserID();
      $output = $this->callPrivateMethod($instance, 'setTargetEntity', [], $formAnswer, $requesterId);
      $this->integer((int) $output['entities_id'])->isEqualTo(0);

      // Test requester's first entity (alphanumeric order)
      $targetTicket->update([
         'id' => $targetTicket->getID(),
         'destination_entity' => $testedClass::DESTINATION_ENTITY_REQUESTER_DYN_FIRST,
         'destination_entity_value' => '0',
      ]);
      $instance->getFromDB($targetTicket->getID());
      $entityId = $entity->import([
         'entities_id' => '0',
         'name' => $this->getUniqueString(),
      ]);
      $user = new User();
      $user->add([
         'name' => $this->getUniqueString(),
         'password' => 'passwd',
         'password2' => 'passwd',
         '_profiles_id' => '3', // Admin
         '_entities_id' => $entityId,
      ]);
      $entity = new \Entity();
      $profileUser = new \Profile_User();
      // A login resyncs a user. Must login nefore adding the dynamic profile
      $this->boolean($this->login($user->fields['name'], 'passwd'))->isTrue();
      $profileUser->add([
         User::getForeignKeyField()    => $user->getID(),
         Profile::getForeignKeyField() => 4, // Super admin
         Entity::getForeignKeyField()  => $entityId,
         'is_dynamic'                   => '1',
      ]);

      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'entities_id' => 0,
      ]);
      $requesterId = \Session::getLoginUserID();
      $output = $this->callPrivateMethod($instance, 'setTargetEntity', [], $formAnswer, $requesterId);
      $this->integer((int) $output['entities_id'])->isEqualTo($entityId);

      // Test requester's last entity (alphanumeric order)
      $targetTicket->update([
         'id' => $targetTicket->getID(),
         'destination_entity' => $testedClass::DESTINATION_ENTITY_REQUESTER_DYN_LAST,
         'destination_entity_value' => '0',
      ]);
      $instance->getFromDB($targetTicket->getID());

      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'entities_id' => $entityId,
      ]);
      $requesterId = Session::getLoginUserID();
      $output = $this->callPrivateMethod($instance, 'setTargetEntity', [], $formAnswer, $requesterId);
      $this->integer((int) $output['entities_id'])->isEqualTo($entityId);

      // Test specific entity
      $this->boolean($this->login('glpi', 'glpi'))->isTrue();
      $entityId = $entity->import([
         'entities_id' => '0',
         'name' => $this->getUniqueString(),
      ]);
      $targetTicket->update([
         'id' => $targetTicket->getID(),
         'destination_entity' => $testedClass::DESTINATION_ENTITY_SPECIFIC,
         '_destination_entity_value_specific' => "$entityId",
      ]);
      $instance->getFromDB($targetTicket->getID());
      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'entities_id' => 0,
      ]);
      $requesterId = Session::getLoginUserID();
      $output = $this->callPrivateMethod($instance, 'setTargetEntity', [], $formAnswer, $requesterId);
      $this->integer((int) $output['entities_id'])->isEqualTo($entityId);

      // Test form's entity
      $entityId = $entity->import([
         'entities_id' => '0',
         'name' => $this->getUniqueString(),
      ]);
      $targetTicket->update([
         'id' => $targetTicket->getID(),
         'destination_entity' => $testedClass::DESTINATION_ENTITY_FORM,
         'destination_entity_value' => '0',
      ]);
      $form->update([
         'id' => $form->getID(),
         'entities_id' => $entityId,
      ]);
      $instance->getFromDB($targetTicket->getID());
      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      $formAnswer = new FormAnswer();
      $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'entities_id' => 0,
      ]);
      $requesterId = \Session::getLoginUserID();
      $output = $this->callPrivateMethod($instance, 'setTargetEntity', [], $formAnswer, $requesterId);
      $this->integer((int) $output['entities_id'])->isEqualTo($entityId);
   }

   public function providerSetTargetType() {
      global $CFG_GLPI;

      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      $question1 = $this->getQuestion([
         'fieldtype' => 'requesttype',
      ]);
      $formFk = Form::getForeignKeyField();
      $form1 = new Form();
      $form1 = Form::getByItem($question1);
      $form1->update([
         'id' => $form1->getID(),
         'validation_required' => Form::VALIDATION_USER,
         '_validator_users' => [2] // Glpi user
      ]);
      $testedClass = $this->getTestedClassName();
      $targetTicket1 = $this->getTargetTicket([
         $formFk     => $form1->getID(),
         'type_rule'     => $testedClass::REQUESTTYPE_SPECIFIC,
         'type_question' => \Ticket::INCIDENT_TYPE,
      ]);

      $question2 = $this->getQuestion([
         'fieldtype' => 'requesttype',
      ]);
      $formFk = Form::getForeignKeyField();
      $form2 = new Form();
      $form2 = Form::getByItem($question2);
      $form2->update([
         'id' => $form2->getID(),
         'validation_required' => Form::VALIDATION_USER,
         '_validator_users' => [2] // Glpi user
      ]);
      $targetTicket2 = $this->getTargetTicket([
         $formFk     => $form2->getID(),
         'type_rule' => $testedClass::REQUESTTYPE_ANSWER,
         'type_question' => $question2->getID(),
      ]);
      return [
         [
            'originalInstance'   => $targetTicket1,
            'formAnswerId' => (new FormAnswer())->add([
               Form::getForeignKeyField() => $form1->getID(),
               'name' => $form1->fields['name'],
               'requester_id' => 2, // glpi user id
               'status' => FormAnswer::STATUS_WAITING,
               'formcreator_validator' => 2, // Glpi user ID
               'formcreator_field_' . $question1->getID() => (string) \Ticket::INCIDENT_TYPE,
            ]),
            'expected'   => GlpiTicket::INCIDENT_TYPE,
         ],
         [
            'originalInstance'   => $targetTicket1,
            'formAnswerId' => (new FormAnswer())->add([
               Form::getForeignKeyField() => $form1->getID(),
               'name' => $form1->fields['name'],
               'requester_id' => 2, // glpi user id
               'status' => FormAnswer::STATUS_WAITING,
               'formcreator_validator' => 2, // Glpi user ID
               'formcreator_field_' . $question1->getID() => (string) \Ticket::DEMAND_TYPE,
            ]),
            'expected'   => \Ticket::INCIDENT_TYPE,
         ],
         [
            'originalInstance'   => $targetTicket2,
            'formAnswerId' => (new FormAnswer())->add([
               Form::getForeignKeyField() => $form2->getID(),
               'name' => $form2->fields['name'],
               'requester_id' => 2, // glpi user id
               'status' => FormAnswer::STATUS_WAITING,
               'formcreator_validator' => 2, // Glpi user ID
               'formcreator_field_' . $question2->getID() => (string) \Ticket::DEMAND_TYPE,
            ]),
            'expected'   => \Ticket::DEMAND_TYPE,
         ],
         [
            'originalInstance'   => $targetTicket2,
            'formAnswerId' => (new FormAnswer())->add([
               Form::getForeignKeyField() => $form2->getID(),
               'name' => $form2->fields['name'],
               'requester_id' => 2, // glpi user id
               'status' => FormAnswer::STATUS_WAITING,
               'formcreator_validator' => 2, // Glpi user ID
               'formcreator_field_' . $question2->getID() => (string) \Ticket::INCIDENT_TYPE,
            ]),
            'expected'   => \Ticket::INCIDENT_TYPE,
         ],
      ];
   }

   /**
    * @dataProvider providerSetTargetType
    */
   public function testSetTargetType($originalInstance, $formAnswerId, $expected) {
      // reload the instance with the helper class
      $instance = $this->newTestedInstance();
      $instance->getFromDB($originalInstance->getID());

      // load the form answer
      $formAnswer = new FormAnswer();
      $formAnswer->getFromDB($formAnswerId);

      $output = $this->callPrivateMethod($instance, 'setTargetType', [], $formAnswer);
      $this->integer((int) $output['type'])->isEqualTo($expected);
   }

   public function providerPrepareTemplate() {
      global $CFG_GLPI;

      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';
      $question = $this->getQuestion([
         'fieldtype' => 'textarea',
         '_parameters' => [
            'textarea' => [
               'range' => [
                  'range_min' => '',
                  'range_max' => '',
               ],
               'regex' => [
                  'regex' => ''
               ],
            ],
         ],
      ]);
      $this->boolean($question->isNewItem())->isFalse();
      $section = new Section();
      $section->getFromDB($question->fields[Section::getForeignKeyField()]);
      $form = new Form();
      $form->getFromDB($section->fields[Form::getForeignKeyField()]);
      $formAnswer = new FormAnswer();
      $formAnswerId = $formAnswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'validation_required' => 0,
         'formcreator_field_' . $question->getID() => 'foo',
      ]);
      $formAnswer->getFromDB($formAnswerId);
      $sectionName = $section->fields['name'];
      $questionTag = '##question_' . $question->getID() . '##';
      $answerTag = '##answer_' . $question->getID() . '##';
      $eolSimple = "\r\n";
      // 2 expected values
      // 0 : Rich text mode disabled
      // 1 : Rich text mode enabled
      return [
         [
            'template' => '##FULLFORM##',
            'formAnswer' => $formAnswer,
            'expected' => [
               0 => 'Form data' . $eolSimple
                  . '=================' . $eolSimple
                  . $eolSimple
                  . $eolSimple . \Toolbox::addslashes_deep($sectionName) . $eolSimple
                  . '---------------------------------' . $eolSimple
                  . '1) ' . $questionTag . ' : ' . $answerTag . $eolSimple . $eolSimple,
               1 => '&#60;h1&#62;Form data&#60;/h1&#62;'
                  . '&#60;h2&#62;' . \Toolbox::addslashes_deep($sectionName) . '&#60;/h2&#62;'
                  . '&#60;div&#62;&#60;b&#62;1) ' . $questionTag . ' : &#60;/b&#62;' . $answerTag . '&#60;/div&#62;',
            ],
         ],
      ];
   }

   /**
    * @dataProvider providerPrepareTemplate
    */
   public function testPrepareTemplate($template, $formAnswer, $expected) {
      $instance = $this->newTestedInstance();
      $output = $this->callPrivateMethod($instance, 'prepareTemplate', $template, $formAnswer);
      $this->string($output)->isEqualTo($expected[0]);

      $output = $this->callPrivateMethod($instance, 'prepareTemplate', $template, $formAnswer, true);
      $this->string($output)->isEqualTo($expected[1]);
   }

   public function testExport() {
      $instance = $this->newTestedInstance();

      // Try to export an empty item
      $this->exception(function () use ($instance) {
         $instance->export();
      })->isInstanceOf(\GlpiPlugin\Formcreator\Exception\ExportFailureException::class);

      // Prepare an item to export
      $instance = $this->getTargetTicket();
      $instance->getFromDB($instance->getID());

      // Export the item without the ID and with UUID
      $output = $instance->export(false);

      // Test the exported data
      $fieldsWithoutID = [
         'name',
         'target_name',
         'source_rule',
         'source_question',
         'type_rule',
         'type_question',
         'content',
         'due_date_rule',
         'due_date_question',
         'due_date_value',
         'due_date_period',
         'urgency_rule',
         'urgency_question',
         'validation_followup',
         'destination_entity',
         'destination_entity_value',
         'tag_type',
         'tag_questions',
         'tag_specifics',
         'category_rule',
         'category_question',
         'associate_rule',
         'associate_question',
         'location_rule',
         'location_question',
         'contract_rule',
         'contract_question',
         'commonitil_validation_rule',
         'commonitil_validation_question',
         'show_rule',
         'sla_rule',
         'sla_question_tto',
         'sla_question_ttr',
         'ola_rule',
         'ola_question_tto',
         'ola_question_ttr',
      ];
      $extraFields = [
         '_tickettemplate',
         '_actors',
         '_ticket_relations',
         'conditions',
      ];

      $this->array($output)
         ->hasKeys($fieldsWithoutID + $extraFields + ['uuid'])
         ->hasSize(1 + count($fieldsWithoutID) + count($extraFields));

      // Export the item without the UUID and with ID
      $output = $instance->export(true);
      $this->array($output)
         ->hasKeys($fieldsWithoutID + $extraFields + ['id'])
         ->hasSize(1 + count($fieldsWithoutID) + count($extraFields));
   }

   public function testImport() {
      $testedClass = $this->getTestedClassName();
      $form = $this->getForm();
      $uuid = plugin_formcreator_getUuid();
      $input = [
         'name' => $this->getUniqueString(),
         'target_name' => $this->getUniqueString(),
         'content' => $this->getUniqueString(),
         'due_date_rule' => $testedClass::DUE_DATE_RULE_NONE,
         'due_date_question' => '0',
         'due_date_value' => null,
         'due_date_period' => '0',
         'urgency_rule' => $testedClass::URGENCY_RULE_NONE,
         'urgency_question' => '0',
         'location_rule' => $testedClass::LOCATION_RULE_NONE,
         'location_question' => '0',
         'contract_rule' => $testedClass::CONTRACT_RULE_NONE,
         'contract_question' => '0',
         'validation_followup' => '1',
         'destination_entity' => '0',
         'destination_entity_value' => '0',
         'tag_type' => $testedClass::TAG_TYPE_NONE,
         'tag_questions' => '0',
         'tag_specifics' => '',
         'category_rule' => $testedClass::CATEGORY_RULE_NONE,
         'category_question' => '0',
         'associate_rule' => $testedClass::ASSOCIATE_RULE_NONE,
         'associate_question' => '0',
         'source_rule' => 0,
         'source_question' => 0,
         'type_rule' => 1,
         'type_question' => 0,
         'uuid' => $uuid,
      ];

      $linker = new Linker();
      $targetTicketId = $testedClass::import($linker, $input, $form->getID());
      $this->integer($targetTicketId)->isGreaterThan(0);

      unset($input['uuid']);

      $this->exception(
         function() use($linker, $input, $form, $testedClass) {
            $testedClass::import($linker, $input, $form->getID());
         }
      )->isInstanceOf(\GlpiPlugin\Formcreator\Exception\ImportFailureException::class)
         ->hasMessage('UUID or ID is mandatory for Target ticket'); // passes

      $input['id'] = $targetTicketId;
      $targetTicketId2 = $testedClass::import($linker, $input, $form->getID());
      $this->integer((int) $targetTicketId)->isNotEqualTo($targetTicketId2);
   }

   public function providerSetTargetCategory_nothing() {
      $testedClass = $this->getTestedClassName();
      $form = $this->getForm();
      $formanswer = new FormAnswer();
      $formanswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer->isNewItem())->isFalse();
      $targetTicket = $this->newTestedInstance();
      $targetTicket->add([
         'name' => 'target ticket',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'category_rule' => $testedClass::CATEGORY_RULE_NONE,
      ]);
      $this->boolean($targetTicket->isNewItem())->isFalse();

      return [
         [
            'instance'   => $targetTicket,
            'formanswer' => $formanswer,
            'expected'   => 0
         ],
      ];
   }

   public function providerSetTargetCategory_noTemplate() {
      $category1 = new ITILCategory();
      $category1Id = $category1->import([
         'name' => 'category 1',
         'entities_id' => 0,
      ]);
      $category2 = new ITILCategory();
      $category2Id = $category2->import([
         'name' => 'category 2',
         'entities_id' => 0,
      ]);

      // Create a task category and ensure its ID is not the
      // same as the ticket categories created above
      $taskCategoryId = 0;
      do {
         $taskCategory = new TaskCategory();
         $taskCategoryId = $taskCategory->import([
            'name' => $this->getUniqueString(),
            'entities_id' => 0,
         ]);
      } while ($taskCategoryId == $category1Id || $taskCategoryId == $category2Id);

      $question1 = $this->getQuestion([
         'name'      => 'request type',
         'fieldtype' => 'requesttype',
      ]);
      $this->boolean($question1->isNewItem())->isFalse();
      $section = new Section();
      $section->getFromDB($question1->fields['plugin_formcreator_sections_id']);
      $this->boolean($section->isNewItem())->isFalse();
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'request category',
         'fieldtype'                      => 'dropdown',
         'itemtype'                       => ITILCategory::class,
         'show_rule'  => Condition::SHOW_RULE_HIDDEN,
         '_conditions'                    => [
            'show_logic' => [Condition::SHOW_LOGIC_AND],
            'plugin_formcreator_questions_id' => [$question1->getID()],
            'show_condition'                  => [Condition::SHOW_CONDITION_EQ],
            'show_value'                      => ['Incident'],
         ]
      ]);
      $question3 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'incident category',
         'fieldtype'                      => 'dropdown',
         'itemtype'                       => ITILCategory::class,
         'show_rule'  => Condition::SHOW_RULE_HIDDEN,
         '_conditions'                    => [
            'show_logic' => [Condition::SHOW_LOGIC_AND],
            'plugin_formcreator_questions_id' => [$question1->getID()],
            'show_condition'                  => [Condition::SHOW_CONDITION_EQ],
            'show_value'                      => ['Request'],
         ]
      ]);
      $question4 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'other category',
         'fieldtype'                      => 'dropdown',
         'itemtype'                       => TaskCategory::class,
         '_conditions'                    => [
            'show_logic' => [],
            'plugin_formcreator_questions_id' => [],
            'show_condition'                  => [],
            'show_value'                      => [],
         ]
      ]);

      $formanswer1 = new FormAnswer();
      $formanswer1->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::INCIDENT_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $category1Id,
         'formcreator_field_' . $question3->getID() => (string) $category2Id,
         'formcreator_field_' . $question4->getID() => (string) $taskCategoryId,
      ]);

      $formanswer2 = new FormAnswer();
      $formanswer2->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::DEMAND_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $category1Id,
         'formcreator_field_' . $question3->getID() => (string) $category2Id,
         'formcreator_field_' . $question4->getID() => (string) $taskCategoryId,
      ]);

      $formanswer3 = new FormAnswer();
      $formanswer3->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::INCIDENT_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $category1Id,
         'formcreator_field_' . $question3->getID() => (string) 0,
         'formcreator_field_' . $question4->getID() => (string) $taskCategoryId,
      ]);

      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'target ticket',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $formanswer1->getForm()->getID(),
         'category_rule' => $testedClass::CATEGORY_RULE_LAST_ANSWER,
      ]);

      return [
         // Check visibility is taken into account
         'visibility taken into account' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer1,
            'expected'   => $category1Id,
         ],
         // Check ticketcategory dropdown is ignored
         '1st ticket category question is ignored' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer2,
            'expected'   => $category2Id,
         ],
         // Check zero value is ignored
         'zero value is ignored' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer3,
            'expected'   => $category1Id,
         ]
      ];
   }

   /**
    * Test if a template with a predefined category is properly applied
    *
    * @return array
    */
   public function providerSetTargetCategory_FromTemplate() {
      // When the target ticket uses a ticket template and does not specify a category
      $category1 = new ITILCategory();
      $category1Id = $category1->import([
         'name' => 'category 1',
         'entities_id' => 0,
      ]);

      $ticketTemplate = $this->getGlpiCoreItem(
         \TicketTemplate::getType(), [
            'name' => 'template with predefined category',
         ]
      );
      $this->getGlpiCoreItem(TicketTemplatePredefinedField::getType(), [
         'tickettemplates_id' => $ticketTemplate->getID(),
         'num'                => 7, // ITIL category
         'value'              => $category1Id
      ]);

      $form = $this->getForm();

      $formanswer1 = new FormAnswer();
      $formanswer1->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer1->isNewItem())->isFalse();

      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'target ticket',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'tickettemplates_id' => $ticketTemplate->getID(),
         'category_rule' => $testedClass::CATEGORY_RULE_NONE,
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formanswer1,
            'expected'   => $category1Id,
         ],
      ];
   }

   public function providerSetTargetCategory() {
      return array_merge(
         $this->providerSetTargetCategory_nothing(),
         $this->providerSetTargetCategory_noTemplate(),
         $this->providerSetTargetCategory_FromTemplate()
      );
   }

   /**
    * @dataProvider providerSetTargetCategory
    */
   public function testSetTargetCategory($instance, $formanswer, $expected) {
      Fields::resetVisibilityCache();
      $data = $this->callPrivateMethod($instance, 'getDefaultData', $formanswer);
      $output = $this->callPrivateMethod($instance, 'setTargetCategory', $data, $formanswer);

      $this->integer((int) $output['itilcategories_id'])->isEqualTo($expected);
   }

   public function providerSetTargetAssociatedItem_1() {
      // Prepare form
      $question = $this->getQuestion([
         'fieldtype' => 'glpiselect',
         'itemtype' => Computer::class,
      ]);
      $form = Form::getByItem($question);

      // Have an item to associate
      $computer = new Computer();
      $computer->add([
         'name' => $this->getUniqueString(),
         'entities_id' => '0',
      ]);
      $this->boolean($computer->isNewItem())->isFalse();

      // Prepare form answer
      $formAnswer = new FormAnswer;
      $formAnswer->add([
         Form::getForeignKeyField() => $form->getID(),
         'name' => $form->fields['name'],
         'requester_d' => 2, // glpi user id
         'status' => FormAnswer::STATUS_WAITING,
         'formcreator_field_' . $question->getID() => (string) $computer->getID(),
      ]);
      $this->boolean($formAnswer->isNewItem())->isFalse();

      // Prepare target ticket
      $instance = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form->getID(),
         'content' => '##FULLFORM',
         'associate_rule' => $testedClass::ASSOCIATE_RULE_ANSWER,
         'associate_question' => $question->getID(),
      ]);
      $this->boolean($instance->isNewItem())->isFalse();

      return [
         [
            'instance' => $instance,
            'formanswer' => $formAnswer,
            'expected' => [
               'Computer' => [
                  $computer->getID() => (string) $computer->getID()
               ]
            ],
         ],
      ];
   }

   public function providerSetTargetAssociatedItem_LastItem() {
      global $CFG_GLPI;

      // Prepare form
      $validItemtype = $CFG_GLPI["asset_types"][0];
      if (array_search(\Computer::getType(), $CFG_GLPI['asset_types']) === false) {
         $CFG_GLPI['asset_types'][] = \Computer::getType();
      }
      $invalidItemtype = \Monitor::getType();

      // Ensure an itemtype is not in the asset types
      $CFG_GLPI['asset_types'] = array_filter($CFG_GLPI['asset_types'], function ($itemtype) use ($invalidItemtype) {
         return ($itemtype != $invalidItemtype);
      });

      $item1 = new $validItemtype();
      $item1->add([
         'name' => $this->getUniqueString(),
         'entities_id' => \Session::getActiveEntity(),
      ]);
      $this->boolean($item1->isNewItem())->isFalse();
      $item2 = new $validItemtype();
      $item2->add([
         'name' => $this->getUniqueString(),
         'entities_id' => \Session::getActiveEntity(),
      ]);
      $this->boolean($item2->isNewItem())->isFalse();

      $question1 = $this->getQuestion([
         'fieldtype' => 'glpiselect',
         'itemtype'  => $validItemtype,
      ]);
      $form1 = Form::getByItem($question1);
      $sectionId = $question1->fields['plugin_formcreator_sections_id'];
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $sectionId,
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => $validItemtype
      ]);
      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form1->getID(),
         'content' => '##FULLFORM',
         'associate_rule' => $testedClass::ASSOCIATE_RULE_LAST_ANSWER,
         'associate_question' => $question2->getID(),
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();
      $formAnswer1 = new FormAnswer();
      $formAnswer1->add([
         'plugin_formcreator_forms_id' => $form1->getID(),
         'formcreator_field_' . $question1->getID() => (string) $item1->getID(),
         'formcreator_field_' . $question2->getID() => (string) $item2->getID(),
      ]);
      $this->boolean($formAnswer1->isNewItem())->isFalse();

      $question3 = $this->getQuestion([
         'fieldtype' => 'glpiselect',
         'itemtype'  => $validItemtype,
      ]);
      $form2 = Form::getByItem($question3);
      $sectionId = $question3->fields['plugin_formcreator_sections_id'];
      $question4 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $sectionId,
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => $invalidItemtype
      ]);

      $instance2 = $this->newTestedInstance();
      $instance2->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form2->getID(),
         'content' => '##FULLFORM',
         'associate_rule' => $testedClass::ASSOCIATE_RULE_LAST_ANSWER,
         'associate_question' => $question3->getID(),
      ]);
      $this->boolean($instance2->isNewItem())->isFalse();
      $monitor = $this->getGlpiCoreItem(\Monitor::getType(), ['name' => $this->getUniqueString()]);
      $this->boolean($monitor->isNewItem())->isFalse();
      $formAnswer2 = new FormAnswer();
      $formAnswer2->add([
         'plugin_formcreator_forms_id' => $form2->getID(),
         'formcreator_field_' . $question3->getID() => (string) $item1->getID(),
         'formcreator_field_' . $question4->getID() => (string) $monitor->getID(),
      ]);
      $this->boolean($formAnswer2->isNewItem())->isFalse();

      $question5 = $this->getQuestion([
         'fieldtype' => 'glpiselect',
         'itemtype'  => $invalidItemtype,
      ]);
      $form3 = Form::getByItem($question5);
      $sectionId = $question5->fields['plugin_formcreator_sections_id'];
      $question6 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $sectionId,
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => $invalidItemtype
      ]);
      $instance3 = $this->newTestedInstance();
      $instance3->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form3->getID(),
         'content' => '##FULLFORM',
         'associate_rule' => $testedClass::ASSOCIATE_RULE_LAST_ANSWER,
         'associate_question' => $question5->getID(),
      ]);
      $this->boolean($instance3->isNewItem())->isFalse();
      $monitor = $this->getGlpiCoreItem(Monitor::getType(), ['name' => $this->getUniqueString()]);
      $this->boolean($monitor->isNewItem())->isFalse();
      $monitor2 = $this->getGlpiCoreItem(Monitor::getType(), ['name' => $this->getUniqueString()]);
      $this->boolean($monitor->isNewItem())->isFalse();
      $formAnswer3 = new FormAnswer();
      $formAnswer3->add([
         'plugin_formcreator_forms_id' => $form3->getID(),
         'formcreator_field_' . $question5->getID() => (string) $monitor->getID(),
         'formcreator_field_' . $question6->getID() => (string) $monitor2->getID(),
      ]);
      $this->boolean($formAnswer3->isNewItem())->isFalse();

      $question7 = $this->getQuestion([
         'fieldtype' => 'glpiselect',
         'itemtype'  => $validItemtype,
      ]);
      $form4 = Form::getByItem($question7);
      $sectionId = $question7->fields['plugin_formcreator_sections_id'];
      $question8 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $sectionId,
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => $validItemtype
      ]);

      $instance4 = $this->newTestedInstance();
      $instance4->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form4->getID(),
         'content' => '##FULLFORM',
         'associate_rule' => $testedClass::ASSOCIATE_RULE_LAST_ANSWER,
         'associate_question' => $question7->getID(),
      ]);
      $this->boolean($instance4->isNewItem())->isFalse();
      $formAnswer4 = new FormAnswer();
      // use non existing items ids and existing itemtypes
      $item7 = new $validItemtype();
      $item7->add([
         'name' => $this->getUniqueString(),
         'entities_id' => Session::getActiveEntity(),
      ]);
      $this->boolean($item7->isNewItem())->isFalse();
      $item8 = new $validItemtype();
      $item8->add([
         'name' => $this->getUniqueString(),
         'entities_id' => Session::getActiveEntity(),
      ]);
      $this->boolean($item8->isNewItem())->isFalse();
      $formAnswer4->add([
         'plugin_formcreator_forms_id' => $form4->getID(),
         'formcreator_field_' . $question7->getID() => (string) $item7->getID(),
         'formcreator_field_' . $question8->getID() => (string) $item8->getID(),
      ]);
      $this->boolean($formAnswer4->isNewItem())->isFalse();
      // Make items non existing for ticket generation
      $item7->delete($item7->fields, 1);
      $item8->delete($item8->fields, 1);

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formAnswer1,
            'expected'   => [
               $validItemtype => [
                  $item2->getID() => (string) $item2->getID()
               ]
            ],
         ],
         [
            'instance'   => $instance2,
            'formanswer' => $formAnswer2,
            'expected'   => [
               $validItemtype => [
                  $item1->getID() => (string) $item1->getID()
               ]
            ],
         ],
         [
            'instance'   => $instance3,
            'formanswer' => $formAnswer3,
            'expected'   => null,
         ],
         [
            'instance'   => $instance4,
            'formanswer' => $formAnswer4,
            'expected'   => null,
         ],
      ];
   }

   public function providerSetTargetAssociatedItem() {
      global $CFG_GLPI;

      // Disable notification to avoid output to console
      $CFG_GLPI['use_notifications'] = '0';

      return array_merge(
         $this->providerSetTargetAssociatedItem_1(),
         $this->providerSetTargetAssociatedItem_LastItem()
      );
   }

   /**
    * @dataProvider providerSetTargetAssociatedItem
    */
   public function testSetTargetAssociatedItem($instance, $formanswer, $expected) {
      $output = $this->callPrivateMethod($instance, 'setTargetAssociatedItem', [], $formanswer);
      if ($expected !== null) {
         $this->array($output['items_id'])->isIdenticalTo($expected);
      } else {
         $this->array($output)->notHasKey('items_id');
      }
   }

   public function testIsEntityAssign() {
      $instance = $this->newTestedInstance();
      $this->boolean($instance->isEntityAssign())->isFalse();
   }

   public function testDeleteObsoleteItems() {
      $form = $this->getForm();
      $targetTicket1 = $this->getTargetTicket([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $targetTicket2 = $this->getTargetTicket([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $instance = $this->newTestedInstance();
      $instance->deleteObsoleteItems($form, [$targetTicket2->getID()]);

      $checkDeleted = $this->newTestedInstance();
      $this->boolean($checkDeleted->getFromDB($targetTicket1->getID()))->isFalse();
      $checkDeleted = $this->newTestedInstance();
      $this->boolean($checkDeleted->getFromDB($targetTicket2->getID()))->isTrue();
   }

   public function providerPrepareInputForAdd() {
      $formFk = Form::getForeignKeyField();
      $form = $this->getForm();
      $name = $this->getUniqueString();
      $testedClass = $this->getTestedClassName();
      $sourceId = Common::getFormcreatorRequestTypeId();
      return [
         'name is mandatory' => [
            'input'    => [
               $formFk => $form->getID(),
            ],
            'expected' => [],
            'message' => 'Name is required.',
         ],
         [
            'input'    => [
               $formFk => $form->getID(),
               'name' => $name,
            ],
            'expected' => [
               $formFk => $form->getID(),
               'name' => $name,
               'target_name' => $name,
               'content' => '##FULLFORM##',
               'type_rule'     => $testedClass::REQUESTTYPE_SPECIFIC,
               'type_question' => GlpiTicket::INCIDENT_TYPE,
               'source_rule'   => $testedClass::REQUESTSOURCE_SPECIFIC,
               'source_question' => $sourceId,
            ],
            'message' => null,
         ],
         [
            'input'    => [
               $formFk => $form->getID(),
               'name' => $name,
               'type_rule'     => $testedClass::REQUESTTYPE_SPECIFIC,
               'type_question' => \Ticket::DEMAND_TYPE,
               'source_rule'   => $testedClass::REQUESTSOURCE_NONE,
            ],
            'expected' => [
               $formFk => $form->getID(),
               'name' => $name,
               'target_name' => $name,
               'content' => '##FULLFORM##',
               'type_rule'     => $testedClass::REQUESTTYPE_SPECIFIC,
               'type_question' => \Ticket::DEMAND_TYPE,
               'source_rule'   => $testedClass::REQUESTSOURCE_NONE,
               'source_question' => 0,
            ],
            'message' => null,
         ],
      ];
   }

   /**
    * @dataProvider providerPrepareInputForAdd
    *
    */
   public function testPrepareInputForAdd($input, $expected, $message) {
      $instance = $this->newTestedInstance();
      $output = $instance->prepareInputForAdd($input);
      if (count($expected) > 0) {
         $this->array($output)->hasKey('uuid');
         unset($output['uuid']);
         $this->array($output)->isEqualTo($expected);
      } else {
         $this->boolean($output)->isFalse();
         $this->sessionHasMessage($message, ERROR);
      }
   }

   public function providerSetTargetLocation_NotSet() {
      // Prepare form

      $form1 = $this->getForm();

      $instance1 = $this->newTestedInstance();
      $instance1->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form1->getID(),
         'content' => '##FULLFORM',
         'location_rule' => $testedClass::LOCATION_RULE_NONE,
         'location_question' => '0',
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();
      $formAnswer1 = new FormAnswer();
      $formAnswer1->add([
         'plugin_formcreator_forms_id' => $form1->getID(),
      ]);
      $this->boolean($formAnswer1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formAnswer1,
            'expected'   => null,
         ],
      ];
   }

   public function providerSetTargetLocation_LastItem() {
      // Prepare form
      $validItemtype = \Location::class;
      $invalidItemtype = \Monitor::getType();

      $item1 = new $validItemtype();
      $item1->add([
         'name' => $this->getUniqueString(),
         'entities_id' => \Session::getActiveEntity(),
      ]);
      $this->boolean($item1->isNewItem())->isFalse();
      $item2 = new $validItemtype();
      $item2->add([
         'name' => $this->getUniqueString(),
         'entities_id' => \Session::getActiveEntity(),
      ]);
      $this->boolean($item2->isNewItem())->isFalse();

      $question1 = $this->getQuestion([
         'fieldtype' => 'dropdown',
         'itemtype'  => $validItemtype,
      ]);
      $form1 = Form::getByItem($question1);
      $sectionId = $question1->fields['plugin_formcreator_sections_id'];
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $sectionId,
         'fieldtype'                      => 'dropdown',
         'itemtype'                       => $validItemtype
      ]);
      $instance1 = $this->newTestedInstance();
      $instance1->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form1->getID(),
         'content' => '##FULLFORM',
         'location_rule' => $testedClass::LOCATION_RULE_LAST_ANSWER,
         'location_question' => '0',
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();
      $formAnswer1 = new FormAnswer();
      $formAnswer1->add([
         'plugin_formcreator_forms_id' => $form1->getID(),
         'formcreator_field_' . $question1->getID() => (string) $item1->getID(),
         'formcreator_field_' . $question2->getID() => (string) $item2->getID(),
      ]);
      $this->boolean($formAnswer1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formAnswer1,
            'expected'   => $item2->getID(),
         ],
      ];
   }

   public function providerSetRequestSource_none(): array {
      $form = $this->getForm();
      $formanswer = new FormAnswer();
      $formanswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer->isNewItem())->isFalse();
      $targetTicket = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $targetTicket->add([
         'name' => 'target ticket',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'source_rule' => $testedClass::REQUESTSOURCE_NONE,
      ]);
      $this->boolean($targetTicket->isNewItem())->isFalse();

      return [
         [
            'instance'   => $targetTicket,
            'formanswer' => $formanswer,
            'expected'   => 0
         ],
      ];
   }

   public function providerSetRequestSource_specific(): array {
      $form = $this->getForm();
      $formanswer = new FormAnswer();
      $formanswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer->isNewItem())->isFalse();
      $targetTicket = $this->newTEstedInstance();
      $testedClass = $this->getTestedClassName();
      $targetTicket->add([
         'name' => 'target ticket',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'source_rule' => $testedClass::REQUESTSOURCE_SPECIFIC,
         'source_question' => Common::getFormcreatorRequestTypeId(),
      ]);
      $this->boolean($targetTicket->isNewItem())->isFalse();

      return [
         [
            'instance'   => $targetTicket,
            'formanswer' => $formanswer,
            'expected'   => 0
         ],
      ];
   }

   public function providerSetRequestSource(): array {
      return array_merge(
         $this->providerSetRequestSource_none(),
         $this->providerSetRequestSource_specific()
      );
   }

   /**
    * @dataProvider providerSetRequestSource
    */
   public function testSetRequestSource($instance, $formanswer, $expected): void {
      $data = $this->callPrivateMethod($instance, 'getDefaultData', $formanswer);
      $output = $this->callPrivateMethod($instance, 'setTargetCategory', $data, $formanswer);
      $this->integer((int) $output['itilcategories_id'])->isEqualTo($expected);
   }

   public function providerSetTargetLocation_nothing() {
      $form = $this->getForm();
      $formanswer = new FormAnswer();
      $formanswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer->isNewItem())->isFalse();
      $targetTicket = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $targetTicket->add([
         'name' => 'target ticket no location',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'location_rule' => $testedClass::LOCATION_RULE_NONE,
      ]);
      $this->boolean($targetTicket->isNewItem())->isFalse();

      return [
         [
            'instance'   => $targetTicket,
            'formanswer' => $formanswer,
            'expected'   => 0
         ],
      ];
   }

   public function providerSetTargetLocation_noTemplate() {
      $location1 = new Location();
      $location1Id = $location1->import([
         'name' => 'location 1',
         'entities_id' => 0,
      ]);
      $location2 = new Location();
      $location2Id = $location2->import([
         'name' => 'location 2',
         'entities_id' => 0,
      ]);

      $question1 = $this->getQuestion([
         'name'      => 'request type',
         'fieldtype' => 'requesttype',
      ]);
      $this->boolean($question1->isNewItem())->isFalse();
      $section = new Section();
      $section->getFromDB($question1->fields['plugin_formcreator_sections_id']);
      $this->boolean($section->isNewItem())->isFalse();
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'location',
         'fieldtype'                      => 'dropdown',
         'itemtype'                       => Location::class,
         'show_rule'  => Condition::SHOW_RULE_HIDDEN,
         '_conditions'                    => [
            'show_logic' => [Condition::SHOW_LOGIC_AND],
            'plugin_formcreator_questions_id' => [$question1->getID()],
            'show_condition'                  => [Condition::SHOW_CONDITION_EQ],
            'show_value'                      => ['Incident'],
         ]
      ]);
      $question3 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'other location',
         'fieldtype'                      => 'dropdown',
         'itemtype'                       => Location::class,
         'show_rule'  => Condition::SHOW_RULE_HIDDEN,
         '_conditions'                    => [
            'show_logic' => [Condition::SHOW_LOGIC_AND],
            'plugin_formcreator_questions_id' => [$question1->getID()],
            'show_condition'                  => [Condition::SHOW_CONDITION_EQ],
            'show_value'                      => ['Request'],
         ]
      ]);

      $formanswer1 = new FormAnswer();
      $formanswer1->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::INCIDENT_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $location1Id,
         'formcreator_field_' . $question3->getID() => (string) $location2Id,
      ]);

      $formanswer2 = new FormAnswer();
      $formanswer2->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::DEMAND_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $location1Id,
         'formcreator_field_' . $question3->getID() => (string) $location2Id,
      ]);

      $formanswer3 = new FormAnswer();
      $formanswer3->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::INCIDENT_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $location1Id,
         'formcreator_field_' . $question3->getID() => (string) 0,
      ]);

      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'target ticket no template',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $formanswer1->getForm()->getID(),
         'location_rule' => $testedClass::LOCATION_RULE_LAST_ANSWER,
      ]);

      return [
         // Check visibility is taken into account
         'visibility taken into account' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer1,
            'expected'   => $location1Id,
         ],
         // Check location dropdown is ignored
         '1st ticket location question is ignored' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer2,
            'expected'   => $location2Id,
         ],
         // Check zero value is ignored
         'zero value is ignored' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer3,
            'expected'   => $location1Id,
         ]
      ];
   }

   public function providerSetTargetLocation_FromTemplate() {
      // When the target ticket uses a ticket template and does not specify a location
      $location1 = new \Location();
      $location1Id = $location1->import([
         'name' => 'location 1',
         'entities_id' => 0,
      ]);

      $ticketTemplate = $this->getGlpiCoreItem(
         \TicketTemplate::getType(), [
            'name' => 'template with predefined location',
         ]
      );
      $this->getGlpiCoreItem(\TicketTemplatePredefinedField::getType(), [
         'tickettemplates_id' => $ticketTemplate->getID(),
         'num'                => 83, // Location
         'value'              => $location1Id
      ]);

      $form = $this->getForm();

      $formanswer1 = new FormAnswer();
      $formanswer1->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer1->isNewItem())->isFalse();

      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'target ticket with template',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'tickettemplates_id' => $ticketTemplate->getID(),
         'location_rule' => $testedClass::LOCATION_RULE_NONE,
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formanswer1,
            'expected'   => $location1Id,
         ],
      ];
   }

   public function providerSetTargetLocation() {
      return array_merge(
         // $this->providerSetTargetLocation_nothing(),
         $this->providerSetTargetLocation_noTemplate(),
         // $this->providerSetTargetLocation_FromTemplate(),
      );
   }

   /**
    * @dataProvider providerSetTargetLocation
    *
    */
   public function testSetTargetLocation($instance, $formanswer, $expected) {
      Fields::resetVisibilityCache();
      $data = $this->callPrivateMethod($instance, 'getDefaultData', $formanswer);
      $output = $this->callPrivateMethod($instance, 'setTargetLocation', $data, $formanswer);

      $this->integer((int) $output['locations_id'])->isEqualTo($expected);
   }

   public function providerSetTargetContract_notSet() {
      // Prepare form

      $form1 = $this->getForm();

      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form1->getID(),
         'content' => '##FULLFORM',
         'contract_rule' => $testedClass::CONTRACT_RULE_NONE,
         'contract_question' => '0',
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();
      $formAnswer1 = new FormAnswer();
      $formAnswer1->add([
         'plugin_formcreator_forms_id' => $form1->getID(),
      ]);
      $this->boolean($formAnswer1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formAnswer1,
            'expected'   => null,
         ],
      ];
   }

   public function providerSetTargetContract_lastItem() {
      // Prepare form
      $validItemtype = Contract::class;

      $item1 = new $validItemtype();
      $item1->add([
         'name' => $this->getUniqueString(),
         'entities_id' => Session::getActiveEntity(),
      ]);
      $this->boolean($item1->isNewItem())->isFalse();
      $item2 = new $validItemtype();
      $item2->add([
         'name' => $this->getUniqueString(),
         'entities_id' => Session::getActiveEntity(),
      ]);
      $this->boolean($item2->isNewItem())->isFalse();

      $question1 = $this->getQuestion([
         'fieldtype' => 'glpiselect',
         'itemtype'  => $validItemtype,
      ]);
      $form1 = Form::getByItem($question1);
      $sectionId = $question1->fields['plugin_formcreator_sections_id'];
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $sectionId,
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => $validItemtype
      ]);
      $instance1 = $this->newTestedInstance();
      $testedClass = $this->getTestedClassName();
      $instance1->add([
         'name' => 'foo',
         'target_name' => '',
         Form::getForeignKeyField() => $form1->getID(),
         'content' => '##FULLFORM',
         'contract_rule' => $testedClass::CONTRACT_RULE_LAST_ANSWER,
         'contract_question' => '0',
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();
      $formAnswer1 = new FormAnswer();
      $formAnswer1->add([
         'plugin_formcreator_forms_id' => $form1->getID(),
         'formcreator_field_' . $question1->getID() => (string) $item1->getID(),
         'formcreator_field_' . $question2->getID() => (string) $item2->getID(),
      ]);
      $this->boolean($formAnswer1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formAnswer1,
            'expected'   => $item2->getID(),
         ],
      ];
   }

   public function providerSetTargetContract_nothing() {
      $form = $this->getForm();
      $formanswer = new FormAnswer();
      $formanswer->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer->isNewItem())->isFalse();
      $testedClass = $this->getTestedClassName();
      $targetTicket = $this->newTestedInstance();
      $targetTicket->add([
         'name' => 'target ticket no contract',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'contract_rule' => $testedClass::CONTRACT_RULE_NONE,
      ]);
      $this->boolean($targetTicket->isNewItem())->isFalse();

      return [
         [
            'instance'   => $targetTicket,
            'formanswer' => $formanswer,
            'expected'   => 0
         ],
      ];
   }

   public function providerSetTargetContract_noTemplate() {
      $contract1 = new Contract();
      $contract1Id = $contract1->add([
         'name' => 'contract 1',
         'entities_id' => 0,
      ]);
      $contract2 = new Contract();
      $contract2Id = $contract2->add([
         'name' => 'contract 2',
         'entities_id' => 0,
      ]);

      $question1 = $this->getQuestion([
         'name'      => 'request type',
         'fieldtype' => 'requesttype',
      ]);
      $this->boolean($question1->isNewItem())->isFalse();
      $section = new Section();
      $section->getFromDB($question1->fields['plugin_formcreator_sections_id']);
      $this->boolean($section->isNewItem())->isFalse();
      $question2 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'contract',
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => \Contract::class,
         'show_rule'  => Condition::SHOW_RULE_HIDDEN,
         '_conditions'                    => [
            'show_logic' => [Condition::SHOW_LOGIC_AND],
            'plugin_formcreator_questions_id' => [$question1->getID()],
            'show_condition'                  => [Condition::SHOW_CONDITION_EQ],
            'show_value'                      => ['Incident'],
         ]
      ]);
      $question3 = $this->getQuestion([
         'plugin_formcreator_sections_id' => $section->getID(),
         'name'                           => 'other contract',
         'fieldtype'                      => 'glpiselect',
         'itemtype'                       => Contract::class,
         'show_rule'  => Condition::SHOW_RULE_HIDDEN,
         '_conditions'                    => [
            'show_logic' => [Condition::SHOW_LOGIC_AND],
            'plugin_formcreator_questions_id' => [$question1->getID()],
            'show_condition'                  => [Condition::SHOW_CONDITION_EQ],
            'show_value'                      => ['Request'],
         ]
      ]);

      $formanswer1 = new FormAnswer();
      $formanswer1->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::INCIDENT_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $contract1Id,
         'formcreator_field_' . $question3->getID() => (string) $contract2Id,
      ]);

      $formanswer2 = new FormAnswer();
      $formanswer2->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::DEMAND_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $contract1Id,
         'formcreator_field_' . $question3->getID() => (string) $contract2Id,
      ]);

      $formanswer3 = new FormAnswer();
      $formanswer3->add([
         'plugin_formcreator_forms_id' => $section->fields['plugin_formcreator_forms_id'],
         'formcreator_field_' . $question1->getID() => (string) GlpiTicket::INCIDENT_TYPE,
         'formcreator_field_' . $question2->getID() => (string) $contract1Id,
         'formcreator_field_' . $question3->getID() => (string) 0,
      ]);

      $testedClass = $this->getTestedClassName();
      $instance1 = $this->newTestedInstance();
      $instance1->add([
         'name' => 'target ticket no template',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $formanswer1->getForm()->getID(),
         'contract_rule' => $testedClass::CONTRACT_RULE_LAST_ANSWER,
      ]);

      return [
         // Check visibility is taken into account
         'visibility taken into account' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer1,
            'expected'   => $contract1Id,
         ],
         // Check contract dropdown is ignored
         '1st ticket contract question is ignored' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer2,
            'expected'   => $contract2Id,
         ],
         // Check zero value is ignored
         'zero value is ignored' => [
            'instance'   => $instance1,
            'formanswer' => $formanswer3,
            'expected'   => $contract1Id,
         ]
      ];
   }

   public function providerSetTargetContract_fromTemplate() {
      // When the target ticket uses a ticket template and does not specify a contract
      $contract1 = new Contract();
      $contract1Id = $contract1->add([
         'name' => 'contract 1',
         'entities_id' => 0,
      ]);

      $ticketTemplate = $this->getGlpiCoreItem(
         TicketTemplate::getType(), [
            'name' => 'template with predefined contract',
         ]
      );
      $this->getGlpiCoreItem(TicketTemplatePredefinedField::getType(), [
         'tickettemplates_id' => $ticketTemplate->getID(),
         'num'                => 193, // Contract
         'value'              => $contract1Id
      ]);

      $form = $this->getForm();

      $formanswer1 = new FormAnswer();
      $formanswer1->add([
         'plugin_formcreator_forms_id' => $form->getID(),
      ]);
      $this->boolean($formanswer1->isNewItem())->isFalse();

      $testedClass = $this->getTestedClassName();
      $instance1 = $this->newTestedInstance();
      $instance1->add([
         'name' => 'target ticket with template',
         'target_name' => 'target ticket',
         'plugin_formcreator_forms_id' => $form->getID(),
         'tickettemplates_id' => $ticketTemplate->getID(),
         'contract_rule' => $testedClass::CONTRACT_RULE_NONE,
      ]);
      $this->boolean($instance1->isNewItem())->isFalse();

      return [
         [
            'instance'   => $instance1,
            'formanswer' => $formanswer1,
            'expected'   => $contract1Id,
         ],
      ];
   }

   public function providerSetTargetContract() {
      return array_merge(
         $this->providerSetTargetContract_notSet(),
         $this->providerSetTargetContract_lastItem(),
         $this->providerSetTargetContract_nothing(),
         $this->providerSetTargetContract_noTemplate(),
         $this->providerSetTargetContract_fromTemplate(),
      );
   }

   /**
    * @dataProvider providerSetTargetContract
    *
    */
   public function testSetTargetContract($instance, $formanswer, $expected) {
      Fields::resetVisibilityCache();
      $data = $this->callPrivateMethod($instance, 'getDefaultData', $formanswer);
      $output = $this->callPrivateMethod($instance, 'setTArgetContract', $data, $formanswer);
      $this->integer((int) $output['_contracts_id'])->isEqualTo($expected);
   }

   public function providerSave(): Generator {
      $targetTicket = $this->getTargetTicket([
         'name' => __FUNCTION__,
      ]);

      $requesterUser = new User();
      $requesterUser->getFromDBbyName('post-only');
      $this->boolean($requesterUser->isNewItem())->isFalse();

      $observerUser = new User();
      $observerUser->getFromDBbyName('normal');
      $this->boolean($observerUser->isNewItem())->isFalse();

      $assignedUser = new User();
      $assignedUser->getFromDBbyName('tech');
      $this->boolean($assignedUser->isNewItem())->isFalse();

      $targetActorRequester = new Target_Actor();
      $targetActorRequester->add([
         'itemtype'                                                => $targetTicket->getType(),
         'items_id'                                                => $targetTicket->getID(),
         'actor_role'                                              => Target_Actor::ACTOR_ROLE_REQUESTER,
         'actor_type'                                              => Target_Actor::ACTOR_TYPE_QUESTION_PERSON,
         'actor_value_' . Target_Actor::ACTOR_TYPE_QUESTION_PERSON => $requesterUser->getID(),
         'use_notification'                                        => 1
      ]);
      $this->boolean($targetActorRequester->isNewItem())->isFalse();

      $observerUser = new Target_Actor();
      $observerUser->add([
         'itemtype'                                                => $targetTicket->getType(),
         'items_id'                                                => $targetTicket->getID(),
         'actor_role'                                              => Target_Actor::ACTOR_ROLE_OBSERVER,
         'actor_type'                                              => Target_Actor::ACTOR_TYPE_QUESTION_PERSON,
         'actor_value_' . Target_Actor::ACTOR_TYPE_QUESTION_PERSON => $requesterUser->getID(),
         'use_notification'                                        => 1
      ]);
      $this->boolean($observerUser->isNewItem())->isFalse();

      $assignedUser = new Target_Actor();
      $assignedUser->add([
         'itemtype'                                                => $targetTicket->getType(),
         'items_id'                                                => $targetTicket->getID(),
         'actor_role'                                              => Target_Actor::ACTOR_ROLE_ASSIGNED,
         'actor_type'                                              => Target_Actor::ACTOR_TYPE_QUESTION_PERSON,
         'actor_value_' . Target_Actor::ACTOR_TYPE_QUESTION_PERSON => $requesterUser->getID(),
         'use_notification'                                        => 1
      ]);
      $this->boolean($assignedUser->isNewItem())->isFalse();

      yield 'chec' => [
         'targetTicket' => $targetTicket,
      ];
   }

   /**
    * @dataProvider providerSave
    */
   public function testSave($targetTicket) {

   }
}
