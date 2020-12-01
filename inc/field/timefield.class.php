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
 * @copyright Copyright © 2011 - 2020 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Formcreator\Field;
use DateTime;
use Html;
use PluginFormcreatorAbstractField;
use Session;
use Toolbox;

class TimeField extends PluginFormcreatorAbstractField
{
   const DATE_FORMAT = 'H:i';

   public function isPrerequisites() {
      return true;
   }

   public function getDesignSpecializationField() {
      $rand = mt_rand();

      $label = '';
      $field = '';

      $additions = '<tr class="plugin_formcreator_question_specific">';
      $additions .= '<td>';
      $additions .= '<label for="dropdown_default_values'.$rand.'">';
      $additions .= __('Default values');
      $additions .= '</label>';
      $additions .= '</td>';
      $additions .= '<td>';
      $value = Html::entities_deep($this->question->fields['default_values']);
      $additions .= Html::showTimeField('default_values', [
         'type'    => 'text',
         'id'      => 'default_values',
         'value'   => $value,
         'display' => false,
      ]);
      $additions .= '</td>';
      $additions .= '<td></td>';
      $additions .= '<td></td>';
      $additions .= '</tr>';

      $common = parent::getDesignSpecializationField();
      $additions .= $common['additions'];

      return [
         'label' => $label,
         'field' => $field,
         'additions' => $additions,
         'may_be_empty' => false,
         'may_be_required' => true,
      ];
   }

   public function getRenderedHtml($canEdit = true) {
      if (!$canEdit) {
         return $this->value;
      }

      $html      = '';
      $id        = $this->question->getID();
      $rand      = mt_rand();
      $fieldName = 'formcreator_field_' . $id;
      $html .= Html::showTimeField($fieldName, [
         'value'   => (strtotime($this->value) != '') ? $this->value : '',
         'rand'    => $rand,
         'display' => false,
      ]);
      $html .= Html::scriptBlock("$(function() {
         pluginFormcreatorInitializeTime('$fieldName', '$rand');
      });");

      return $html;
   }

   public function serializeValue() {
      return $this->value;
   }

   public function deserializeValue($value) {
      $this->value = $value;
   }

   public function getValueForDesign() {
      return $this->value;
   }

   public function getValueForTargetText($richText) {
      $date = DateTime::createFromFormat("H:i:s", $this->value);
      if ($date === false) {
         return ' ';
      }
      return Toolbox::addslashes_deep($date->format('H:i'));
   }

   public function moveUploads() {}

   public function getDocumentsForTarget() {
      return [];
   }

   public function isValid() {
      // If the field is required it can't be empty
      if ($this->isRequired() && (strtotime($this->value) === false)) {
         Session::addMessageAfterRedirect(
            __('A required field is empty:', 'formcreator') . ' ' . $this->getLabel(),
            false,
            ERROR);
         return false;
      }

      // All is OK
      return true;
   }

   public function isValidValue($value) {
      return true;
   }

   public static function getName() {
      return __('Time', 'formcreator');
   }

   public function hasInput($input) {
      return isset($input['formcreator_field_' . $this->question->getID()]);
   }

   public static function canRequire() {
      return true;
   }

   public function equals($value) {
      if ($this->value === '') {
         $answer = '00:00';
      } else {
         $answer = $this->value;
      }
      $answerDatetime = DateTime::createFromFormat(self::DATE_FORMAT, $answer);
      $compareDatetime = DateTime::createFromFormat(self::DATE_FORMAT, $value);
      return $answerDatetime == $compareDatetime;
   }

   public function notEquals($value) {
      return !$this->equals($value);
   }

   public function greaterThan($value) {
      if (empty($this->value)) {
         $answer = '00:00';
      } else {
         $answer = $this->value;
      }
      $answerDatetime = DateTime::createFromFormat(self::DATE_FORMAT, $answer);
      $compareDatetime = DateTime::createFromFormat(self::DATE_FORMAT, $value);
      return $answerDatetime > $compareDatetime;
   }

   public function lessThan($value) {
      return !$this->greaterThan($value) && !$this->equals($value);
   }

   public function parseAnswerValues($input, $nonDestructive = false) {

      $key = 'formcreator_field_' . $this->question->getID();
      if (!is_string($input[$key])) {
         return false;
      }

      $this->value = $input[$key];
      return true;
   }

   public function isAnonymousFormCompatible() {
      return true;
   }

   public function getHtmlIcon() {
      return '<i class="fa fa-clock" aria-hidden="true"></i>';
   }

   public function isVisibleField()
   {
      return true;
   }

   public function isEditableField()
   {
      return true;
   }
}
