<?php
class PluginFormcreatorGlpiselectField extends PluginFormcreatorDropdownField
{
   public static function getName() {
      return _n('GLPI object', 'GLPI objects', 1, 'formcreator');
   }

   public function prepareQuestionInputForSave($input) {
      if (isset($input['glpi_objects'])) {
         if (empty($input['glpi_objects'])) {
            Session::addMessageAfterRedirect(
                  __('The field value is required:', 'formcreator') . ' ' . $input['name'],
                  false,
                  ERROR);
            return array();
         }
         $input['values']         = $input['glpi_objects'];
         $input['default_values'] = isset($input['dropdown_default_value']) ? $input['dropdown_default_value'] : '';
      }
      return $input;
   }

   public static function getPrefs() {
      return array(
         'required'       => 1,
         'default_values' => 0,
         'values'         => 0,
         'range'          => 0,
         'show_empty'     => 1,
         'regex'          => 0,
         'show_type'      => 1,
         'dropdown_value' => 0,
         'glpi_objects'   => 1,
         'ldap_values'    => 0,
      );
   }

   public static function getJSFields() {
      $prefs = self::getPrefs();
      return "tab_fields_fields['glpiselect'] = 'showFields(" . implode(', ', $prefs) . ");';";
   }
}
