<?php
/**
 * LICENSE
 *
 * Copyright © 2011-2018 Teclib'
 *
 * This file is part of Formcreator Plugin for GLPI.
 *
 * Formcreator is a plugin that allow creation of custom, easy to access forms
 * for users when they want to create one or more GLPI tickets.
 *
 * Formcreator Plugin for GLPI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator Plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * If not, see http://www.gnu.org/licenses/.
 * ------------------------------------------------------------------------------
 * @author    Thierry Bugier
 * @author    Jérémy Moreau
 * @copyright Copyright © 2018 Teclib
 * @license   GPLv2 https://www.gnu.org/licenses/gpl2.txt
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ------------------------------------------------------------------------------
 */
class PluginFormcreatorTextareaField extends PluginFormcreatorTextField
{
   public function displayField($canEdit = true) {
      global $CFG_GLPI;

      if ($canEdit) {
         $required = $this->fields['required'] ? ' required' : '';

         echo '<textarea class="form-control"
                  rows="5"
                  name="formcreator_field_'.$this->fields['id'].'"
                  id="formcreator_field_'.$this->fields['id'].'"
                  onchange="formcreatorChangeValueOf('.$this->fields['id'].', this.value);">'
                 .str_replace('\r\n', PHP_EOL, $this->getValue()).'</textarea>';
         if ($CFG_GLPI["use_rich_text"]) {
            Html::initEditorSystem('formcreator_field_'.$this->fields['id']);
         }
      } else {
         if ($CFG_GLPI["use_rich_text"]) {
            echo plugin_formcreator_decode($this->getAnswer());
         } else {
            echo nl2br($this->getAnswer());
         }
      }
   }

   public static function getName() {
      return __('Textarea', 'formcreator');
   }

   public function prepareQuestionInputForTarget($input) {
      $input = str_replace("\r\n", '\r\n', addslashes($input));
      return $input;
   }

   public static function getJSFields() {
      $prefs = self::getPrefs();
      return "tab_fields_fields['textarea'] = 'showFields(".implode(', ', $prefs).");';";
   }
}
