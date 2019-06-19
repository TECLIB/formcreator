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
 * @copyright Copyright © 2011 - 2019 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */
namespace tests\units;
use GlpiPlugin\Formcreator\Tests\CommonTestCase;

class PluginFormcreatorSection extends CommonTestCase {
   public function setup() {
      // instanciate classes
      $form           = new \PluginFormcreatorForm;
      $form_section   = new \PluginFormcreatorSection;
      $form_question  = new \PluginFormcreatorQuestion;
      $form_condition = new \PluginFormcreatorQuestion_Condition;
      $form_validator = new \PluginFormcreatorForm_Validator;
      $form_profile   = new \PluginFormcreatorForm_Profile;

      // create objects
      $forms_id = $form->add([
         'name'                => "test clone form",
         'is_active'           => true,
         'validation_required' => \PluginFormcreatorForm_Validator::VALIDATION_USER
      ]);

      $sections_id = $form_section->add([
         'name'                        => "test clone section",
         'plugin_formcreator_forms_id' => $forms_id
      ]);

      $questions_id_1 = $form_question->add([
         'name'                           => "test clone question 1",
         'fieldtype'                      => 'text',
         'plugin_formcreator_sections_id' => $sections_id
      ]);
      $questions_id_2 = $form_question->add([
         'name'                           => "test clone question 2",
         'fieldtype'                      => 'textarea',
         'plugin_formcreator_sections_id' => $sections_id
      ]);
   }

   public function beforeTestMethod($method) {
      parent::beforeTestMethod($method);
      switch ($method) {
         case 'testImport':
            self::login('glpi', 'glpi');
      }
   }

   /**
    * @cover PluginFormcreatorSection::clone
    */
   public function testDuplicate() {
      global $DB;

      // instanciate classes
      $form           = new \PluginFormcreatorForm;
      $section   = new \PluginFormcreatorSection;
      $question  = new \PluginFormcreatorQuestion;

      // create objects
      $forms_id = $form->add(['name'                => "test clone form",
                                   'is_active'           => true,
                                   'validation_required' => \PluginFormcreatorForm_Validator::VALIDATION_USER]);
      $sections_id = $section->add(['name'                        => "test clone section",
                                         'plugin_formcreator_forms_id' => $forms_id]);
      $questions_id_1 = $question->add(['name'                           => "test clone question 1",
                                             'fieldtype'                      => 'text',
                                             'plugin_formcreator_sections_id' => $sections_id]);
      $questions_id_2 = $question->add(['name'                           => "test clone question 2",
                                             'fieldtype'                      => 'textarea',
                                             'plugin_formcreator_sections_id' => $sections_id]);

      //get section
      plugin_formcreator_getFromDBByField($section, 'name', "test clone section");

      //clone it
      $newSection_id = $section->duplicate();
      $this->integer($newSection_id)->isGreaterThan(0);

      //get cloned section
      $new_section = new \PluginFormcreatorSection;
      $new_section->getFromDB($newSection_id);

      // check uuid
      $this->string($new_section->getField('uuid'))->isNotEqualTo($section->getField('uuid'));

      // check questions
      $all_questions = $DB->request([
         'SELECT' => ['uuid'],
         'FROM'   => \PluginFormcreatorQuestion::getTable(),
         'WHERE'  => [
            'plugin_formcreator_sections_id' => $section->getID()
         ]
      ]);
      $all_new_questions = $DB->request([
         'SELECT' => ['uuid'],
         'FROM'   => \PluginFormcreatorQuestion::getTable(),
         'WHERE'  => [
            'plugin_formcreator_sections_id' => $new_section->getID()
         ]
      ]);
      $this->integer(count($all_new_questions))->isEqualTo(count($all_questions));

      // check that all question uuid are new
      $uuids = $new_uuids = [];
      foreach ($all_questions as $question) {
         $uuids[] = $question['uuid'];
      }
      foreach ($all_new_questions as $question) {
         $new_uuids[] = $question['uuid'];
      }
      $this->integer(count(array_diff($new_uuids, $uuids)))->isEqualTo(count($new_uuids));
   }

   public function testExport() {
      $instance = $this->newTestedInstance();

      // Try to export an empty item
      $output = $instance->export();
      $this->boolean($output)->isFalse();

      // Prepare an item to export
      $instance = $this->getSection();
      $instance->getFromDB($instance->getID());

      // Export the item without the ID and with UUID
      $output = $instance->export(false);

      // Test the exported data
      $fieldsWithoutID = [
         'name',
         'order',
      ];
      $extraFields = [
         '_questions',
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
      $formFk = \PluginFormcreatorForm::getForeignKeyField();
      $form = $this->getForm();
      $uuid = plugin_formcreator_getUuid();
      $input = [
         'name' => $this->getUniqueString(),
         'order' => '1',
         'uuid' => $uuid,
      ];

      $linker = new \PluginFormcreatorLinker ();
      $sectionId = \PluginFormcreatorSection::import($linker, $input, $form->getID());
      $this->integer($sectionId)->isGreaterThan(0);

      unset($input['uuid']);

      $this->exception(
         function() use($linker, $input, $form) {
            \PluginFormcreatorSection::import($linker, $input, $form->getID());
         }
      )->isInstanceOf(\GlpiPlugin\Formcreator\Exception\ImportFailureException::class)
      ->hasMessage('UUID or ID is mandatory'); // passes

      $input['id'] = $sectionId;
      $sectionId2 = \PluginFormcreatorSection::import($linker, $input, $form->getID());
      $this->variable($sectionId2)->isNotFalse();
      $this->integer((int) $sectionId)->isNotEqualTo($sectionId2);
   }

   public function testMoveUp() {
      $formFk = \PluginFormcreatorForm::getForeignKeyField();
      $form = $this->getForm();
      $section = $this->getSection(
         [
            $formFk => $form->getID(),
         ]
      );
      $sectionToMove = $this->getSection(
         [
            $formFk => $form->getID(),
         ]
      );

      // Move up the section
      $expectedOrder = $sectionToMove->fields['order'] - 1;
      $sectionToMove->moveUp();

      // Check the order of the section
      $this->integer((int) $sectionToMove->fields['order'])
         ->isEqualTo($expectedOrder);

      // check the order of the other section
      $expectedOrder = $section->fields['order'] + 1;
      $section->getFromDB($section->getID());
      $this->integer((int) $section->fields['order'])
         ->isEqualTo($expectedOrder);
   }

   public function testMoveDown() {
      $formFk = \PluginFormcreatorForm::getForeignKeyField();
      $form = $this->getForm();
      $sectionToMove = $this->getSection(
         [
            $formFk => $form->getID(),
         ]
      );
      $section = $this->getSection(
         [
            $formFk => $form->getID(),
         ]
      );

      // Move down the section
      $expectedOrder = $sectionToMove->fields['order'] + 1;
      $sectionToMove->moveDown();

      // Check the order of the section
      $this->integer((int) $sectionToMove->fields['order'])
         ->isEqualTo($expectedOrder);

      // check the order of the other section
      $expectedOrder = $section->fields['order'] - 1;
      $section->getFromDB($section->getID());
      $this->integer((int) $section->fields['order'])
         ->isEqualTo($expectedOrder);
   }
}
