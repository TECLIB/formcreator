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

class PluginFormcreatorSelectField extends CommonTestCase {

   public function provider() {

      $dataset = [
         [
            'fields'          => [
                  'fieldtype'       => 'select',
                  'name'            => 'question',
                  'required'        => '0',
                  'show_empty'      => '0',
                  'default_values'  => '',
                  'values'          => "1\r\n2\r\n3\r\n4\r\n5\r\n6",
                  'order'           => '1',
                  'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
                  'fieldtype'       => 'select',
                  'name'            => 'question',
                  'required'        => '0',
                  'show_empty'      => '1',
                  'default_values'  => '',
                  'values'          => "1\r\n2\r\n3\r\n4\r\n5\r\n6",
                  'order'           => '1',
                  'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS
            ],
            'data'            => null,
            'expectedValue'   => '',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
                  'fieldtype'       => 'select',
                  'name'            => 'question',
                  'required'        => '0',
                  'show_empty'      => '0',
                  'default_values'  => '3',
                  'values'          => "1\r\n2\r\n3\r\n4\r\n5\r\n6",
                  'order'           => '1',
                  'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS
            ],
            'data'            => null,
            'expectedValue'   => '3',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
                  'fieldtype'       => 'select',
                  'name'            => 'question',
                  'required'        => '1',
                  'show_empty'      => '0',
                  'default_values'  => '',
                  'values'          => "1\r\n2\r\n3\r\n4\r\n5\r\n6",
                  'order'           => '1',
                  'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS
            ],
            'data'            => null,
            'expectedValue'   => '1',
            'expectedIsValid' => true
         ],
         [
            'fields'          => [
                  'fieldtype'       => 'select',
                  'name'            => 'question',
                  'required'        => '1',
                  'show_empty'      => '1',
                  'default_values'  => '',
                  'values'          => "1\r\n2\r\n3\r\n4\r\n5\r\n6",
                  'order'           => '1',
                  'show_rule'       =>\PluginFormcreatorQuestion::SHOW_RULE_ALWAYS
            ],
            'data'            => null,
            'expectedValue'   => '',
            'expectedIsValid' => true
         ],
      ];

      return $dataset;
   }

   /**
    * @dataProvider provider
    */
   public function testFieldAvailableValue($fields, $data, $expectedValue, $expectedValidity) {
      $fieldInstance = new \PluginFormcreatorSelectField($fields, $data);

      $availableValues = $fieldInstance->getAvailableValues();
      $expectedAvaliableValues = explode("\r\n", $fields['values']);

      $this->integer(count($availableValues))->isEqualTo(count($expectedAvaliableValues));

      foreach ($expectedAvaliableValues as $expectedValue) {
         $this->array($availableValues)->contains($expectedValue);
      }
   }

   /**
    * @dataProvider provider
    */
   public function testFieldIsValid($fields, $data, $expectedValue, $expectedValidity) {
      $instance = new \PluginFormcreatorSelectField($fields, $data);
      $instance->deserializeValue($fields['default_values']);

      $isValid = $instance->isValid();
      $this->boolean((boolean) $isValid)->isEqualTo($expectedValidity);
   }

   public function testGetName() {
      $output = \PluginFormcreatorSelectField::getName();
      $this->string($output)->isEqualTo('Select');
   }

   public function testIsAnonymousFormCompatible() {
      $instance = new \PluginFormcreatorSelectField([]);
      $output = $instance->isAnonymousFormCompatible();
      $this->boolean($output)->isTrue();
   }

   public function testIsPrerequisites() {
      $instance = $this->newTestedInstance([]);
      $output = $instance->isPrerequisites();
      $this->boolean($output)->isEqualTo(true);
   }

   public function testCanRequire() {
      $instance = new \PluginFormcreatorSelectField([
         'id' => '1',
      ]);
      $output = $instance->canRequire();
      $this->boolean($output)->isTrue();
   }
   
   public function testGetDocumentsForTarget() {
      $instance = $this->newTestedInstance([]);
      $this->array($instance->getDocumentsForTarget())->hasSize(0);
   }

   public function testGetEmptyParameters() {
      $instance = $this->newTestedInstance([]);
      $output = $instance->getEmptyParameters();
      $this->array($output)
         ->isIdenticalTo([]);
   }

   public function providerSerializeValue() {
      return [
         [
            'value' => '',
            'expected' => '',
         ],
         [
            'value' => "foo",
            'expected' => "foo",
         ],
         [
            'value'     => 'test d\'apostrophe',
            'expected'  => "test d\'apostrophe",
         ],
      ];
   }

   /**
    * @dataProvider providerSerializeValue
    */
   public function testSerializeValue($value, $expected) {
      $instance = new \PluginFormcreatorSelectField([]);
      $instance->prepareQuestionInputForSave([
         'default_values' => $value,
      ]);
      $output = $instance->serializeValue();
      $this->string($output)->isEqualTo($expected);
   }

   public function providerDeserializeValue() {
      return [
         [
            'value'     => '',
            'expected'  => '',
         ],
         [
            'value'     => 'foo',
            'expected'  => 'foo' ,
         ],
         [
            'value'     => 'test d\'apostrophe',
            'expected'  => 'test d\'apostrophe',
         ],
      ];
   }

   /**
    * @dataProvider providerDeserializeValue
    */
   public function testDeserializeValue($value, $expected) {
      $instance = new \PluginFormcreatorSelectField([]);
      $instance->deserializeValue($value);
      $output = $instance->getValueForTargetText(false);
      $this->string($output)->isEqualTo($expected);
   }
   
   public function providerparseAnswerValues() {
      return [
         [
            'id' => '1',
            'input' => [
               'formcreator_field_1' => ''
            ],
            'expected' => true,
            'expectedValue' => '',
         ],
         [
            'id' => '1',
            'input' => [
               'formcreator_field_1' => 'test d\'apostrophe',
            ],
            'expected' => true,
            'expectedValue' => "test d'apostrophe",
         ],
      ];
   }

   /**
    * @dataProvider providerparseAnswerValues
    */
   public function testParseAnswerValues($id, $input, $expected, $expectedValue) {
      $instance = $this->newTestedInstance(['id' => $id]);
      $output = $instance->parseAnswerValues($input);
      $this->boolean($output)->isEqualTo($expected);

      $outputValue = $instance->getValueForTargetText(false);
      if ($expected === false) {
         $this->variable($outputValue)->isNull();
      } else {
         $this->string($outputValue)
            ->isEqualTo($expectedValue);
      }
   }

   public function providerGetValueForDesign() {
      return [
         [
            'value' => null,
            'expected' => '',
         ],
         [
            'value' => 'foo',
            'expected' => 'foo',
         ],
      ];
   }

   /**
    * @dataProvider providerGetValueForDesign
    */
   public function testGetValueForDesign($value, $expected) {
      $instance = new \PluginFormcreatorSelectField([]);
      $instance->deserializeValue($value);
      $output = $instance->getValueForDesign();
      $this->string($output)->isEqualTo($expected);
   }
}
