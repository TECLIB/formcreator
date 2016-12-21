<?php
class PluginFormcreatorQuestion extends CommonDBChild
{
   static public $itemtype = "PluginFormcreatorSection";
   static public $items_id = "plugin_formcreator_sections_id";

   /**
    * Check if current user have the right to create and modify requests
    *
    * @return boolean True if he can create and modify requests
    */
   public static function canCreate()
   {
      return true;
   }

   /**
    * Check if current user have the right to read requests
    *
    * @return boolean True if he can read requests
    */
   public static function canView()
   {
      return true;
   }

   /**
    * Returns the type name with consideration of plural
    *
    * @param number $nb Number of item(s)
    * @return string Itemtype name
    */
   public static function getTypeName($nb = 0)
   {
      return _n('Question', 'Questions', $nb, 'formcreator');
   }


   function addMessageOnAddAction() {}
   function addMessageOnUpdateAction() {}
   function addMessageOnDeleteAction() {}
   function addMessageOnPurgeAction() {}

   /**
    * Return the name of the tab for item including forms like the config page
    *
    * @param  CommonGLPI $item         Instance of a CommonGLPI Item (The Config Item)
    * @param  integer    $withtemplate
    *
    * @return String                   Name to be displayed
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate=0)
   {
      switch ($item->getType()) {
         case "PluginFormcreatorForm":
            $number      = 0;
            $section     = new PluginFormcreatorSection();
            $found     = $section->find('plugin_formcreator_forms_id = ' . $item->getID());
            $tab_section = array();
            foreach($found as $section_item) {
               $tab_section[] = $section_item['id'];
            }

            if(!empty($tab_section)) {
               $object  = new self;
               $found = $object->find('plugin_formcreator_sections_id IN (' . implode(', ', $tab_section) . ')');
               $number  = count($found);
            }
            return self::createTabEntry(self::getTypeName($number), $number);
      }
      return '';
   }

   /**
    * Display a list of all form sections and questions
    *
    * @param  CommonGLPI $item         Instance of a CommonGLPI Item (The Form Item)
    * @param  integer    $tabnum       Number of the current tab
    * @param  integer    $withtemplate
    *
    * @see CommonDBTM::displayTabContentForItem
    *
    * @return null                     Nothing, just display the list
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0)
   {
      global $CFG_GLPI;

      echo '<table class="tab_cadre_fixe">';

      // Get sections
      $section          = new PluginFormcreatorSection();
      $found_sections = $section->find('plugin_formcreator_forms_id = ' . (int) $item->getId(), '`order`');
      $section_number   = count($found_sections);
      $tab_sections     = array();
      $tab_questions    = array();
      $token            = Session::getNewCSRFToken();
      foreach ($found_sections as $section) {
         $tab_sections[] = $section['id'];
         echo '<tr class="section_row" id="section_row_' . $section['id'] . '">';
         echo '<th onclick="editSection(' . $item->getId() . ', \'' . $token . '\', ' . $section['id'] . ')">';
         echo "<a href='#'>";
         echo $section['name'];
         echo '</a>';
         echo '</th>';

         echo '<th align="center">';

         echo "<span class='form_control pointer'>";
         echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/delete.png"
                  title="' . __('Delete', 'formcreator') . '"
                  onclick="deleteSection(' . $item->getId() . ', \'' . $token . '\', ' . $section['id'] . ')"> ';
         echo "</span>";

         echo "<span class='form_control pointer'>";
         echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/clone.png"
                  title="' . _sx('button', "Duplicate") . '"
                  onclick="duplicateSection(' . $item->getId() . ', \'' . $token . '\', ' . $section['id'] . ')"> ';
         echo "</span>";

         echo "<span class='form_control pointer'>";
         if($section['order'] != $section_number) {
            echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/down.png"
                     title="' . __('Bring down') . '"
                     onclick="moveSection(\'' . $token . '\', ' . $section['id'] . ', \'down\');" >';
         }
         echo "</span>";

         echo "<span class='form_control pointer'>";
         if($section['order'] != 1) {
            echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/up.png"
                     title="' . __('Bring up') . '"
                     onclick="moveSection(\'' . $token . '\', ' . $section['id'] . ', \'up\');"> ';
         }
         echo "</span>";

         echo '</th>';
         echo '</tr>';


         // Get questions
         $question          = new PluginFormcreatorQuestion();
         $found_questions = $question->find('plugin_formcreator_sections_id = ' . (int) $section['id'], '`order`');
         $question_number   = count($found_questions);
         $i = 0;
         foreach ($found_questions as $question) {
            $i++;
            $tab_questions[] = $question['id'];
            echo '<tr class="line' . ($i % 2) . '" id="question_row_' . $question['id'] . '">';
            echo '<td onclick="editQuestion(' . $item->getId() . ', \'' . $token . '\', ' . $question['id'] . ', ' . $section['id'] . ')">';
            echo "<a href='#'>";
            echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/ui-' . $question['fieldtype'] . '-field.png" title="" /> ';
            echo $question['name'];
            echo "<a>";
            echo '</td>';

            echo '<td align="center">';

            $question_type = $question['fieldtype'] . 'Field';


            $question_types = PluginFormcreatorFields::getTypes();
            $classname = 'PluginFormcreator' . ucfirst($question['fieldtype']) . 'Field';
            $fields = $classname::getPrefs();

            // avoid quote js error
            $question['name'] = htmlspecialchars_decode($question['name'], ENT_QUOTES);

            echo "<span class='form_control pointer'>";
            echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/delete.png"
                     title="' . __('Delete', 'formcreator') . '"
                     onclick="deleteQuestion(' . $item->getId() . ', \'' . $token . '\', ' . $question['id'] . ')"> ';
            echo "</span>";

            echo "<span class='form_control pointer'>";
            echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/clone.png"
                     title="' . _sx('button', "Duplicate") . '"
                     onclick="cloneQuestion(' . $item->getId() . ', \'' . $token . '\', ' . $question['id'] . ')"> ';
            echo "</span>";

            if ($fields['required'] != 0) {
               $required_pic = ($question['required'] ? "required": "not-required");
               echo "<span class='form_control pointer'>";
               echo "<img src='" . $CFG_GLPI['root_doc'] . "/plugins/formcreator/pics/$required_pic.png'
                        title='" . __('Required', 'formcreator') . "'
                        onclick='setRequired(\"".$token."\", ".$question['id'].", ".($question['required']?0:1).")' > ";
               echo "</span>";
            }

            echo "<span class='form_control pointer'>";
            if($question['order'] != 1) {
               echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/up.png"
                        title="' . __('Bring up') . '"
                        onclick="moveQuestion(\'' . $token . '\', ' . $question['id'] . ', \'up\');" align="absmiddle"> ';
            }
            echo "</span>";

            echo "<span class='form_control pointer'>";
            if($question['order'] != $question_number) {
               echo '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/down.png"
                        title="' . __('Bring down') . '"
                        onclick="moveQuestion(\'' . $token . '\', ' . $question['id'] . ', \'down\');"> ';
            }
            echo "</span>";

            echo '</td>';
            echo '</tr>';
         }


         echo '<tr class="line' . (($i + 1) % 2) . '">';
         echo '<td colspan="6" id="add_question_td_' . $section['id'] . '" class="add_question_tds">';
         echo '<a href="javascript:addQuestion(' . $item->getId() . ', \'' . $token . '\', ' . $section['id'] . ');">
                   <img src="'.$CFG_GLPI['root_doc'].'/pics/menu_add.png" alt="+"/>
                   '.__('Add a question', 'formcreator').'
               </a>';
         echo '</td>';
         echo '</tr>';
      }

      echo '<tr class="line1 section_row">';
      echo '<th id="add_section_th">';
      echo '<a href="javascript:addSection(' . $item->getId() . ', \'' . $token . '\');">
                <img src="'.$CFG_GLPI['root_doc'].'/pics/menu_add.png" alt="+">
                '.__('Add a section', 'formcreator').'
            </a>';
      echo '</th>';
      echo '<th></th>';
      echo '</tr>';

      echo "</table>";

      $js_tab_sections   = "";
      $js_tab_questions  = "";
      $js_line_questions = "";
      foreach ($tab_sections as $key) {
         $js_tab_sections  .= "tab_sections[$key] = document.getElementById('section_row_$key').innerHTML;".PHP_EOL;
         $js_tab_questions .= "tab_questions[$key] = document.getElementById('add_question_td_$key').innerHTML;".PHP_EOL;
      }
      foreach ($tab_questions as $key) {
         $js_line_questions .= "line_questions[$key] = document.getElementById('question_row_$key').innerHTML;".PHP_EOL;
      }
   }

   /**
    * Validate form fields before add or update a question
    *
    * @param  Array $input Datas used to add the item
    *
    * @return Array        The modified $input array
    *
    * @param  [type] $input [description]
    * @return [type]        [description]
    */
   private function checkBeforeSave($input)
   {
      // Control fields values :
      // - name is required
      if (isset($input['name'])
          && empty($input['name'])) {
         Session::addMessageAfterRedirect(__('The title is required', 'formcreator'), false, ERROR);
         return array();
      }

      // - field type is required
      if (isset($input['fieldtype'])
          && empty($input['fieldtype'])) {
         Session::addMessageAfterRedirect(__('The field type is required', 'formcreator'), false, ERROR);
         return array();
      }

      // - section is required
      if (isset($input['plugin_formcreator_sections_id'])
          && empty($input['plugin_formcreator_sections_id'])) {
         Session::addMessageAfterRedirect(__('The section is required', 'formcreator'), false, ERROR);
         return array();
      }

      // Values are required for GLPI dropdowns, dropdowns, multiple dropdowns, checkboxes, radios, LDAP
      $itemtypes = array('select', 'multiselect', 'checkboxes', 'radios', 'ldap');
      if (isset($input['values'])
          && empty($input['values']) && in_array($input['fieldtype'], $itemtypes)) {
         Session::addMessageAfterRedirect(
            __('The field value is required:', 'formcreator') . ' ' . $input['name'],
            false,
            ERROR);
         return array();
      }

      // Fields are differents for dropdown lists, so we need to replace these values into the good ones
      if (isset($input['fieldtype'])) {
         if ($input['fieldtype'] == 'dropdown'
             && isset($input['dropdown_values'])) {
            if (empty($input['dropdown_values'])) {
               Session::addMessageAfterRedirect(
                  __('The field value is required:', 'formcreator') . ' ' . $input['name'],
                  false,
                  ERROR);
               return array();
            }
            $input['values']         = $input['dropdown_values'];
            $input['default_values'] = isset($input['dropdown_default_value']) ? $input['dropdown_default_value'] : '';
         }

         // Fields are differents for GLPI object lists, so we need to replace these values into the good ones
         if ($input['fieldtype'] == 'glpiselect'
             && isset($input['glpi_objects'])) {
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

         // A description field should have a description
         if ($input['fieldtype'] == 'description'
             && isset($input['description'])
             && empty($input['description'])) {
               Session::addMessageAfterRedirect(
                  __('A description field should have a description:', 'formcreator') . ' ' . $input['name'],
                  false,
                  ERROR);
               return array();
         }

         // format values for numbers
         if (isset($input['range_min'])
             && isset($input['range_max'])
             && isset($input['default_values'])
             && ($input['fieldtype'] == 'integer') || ($input['fieldtype'] == 'float')) {
            $input['default_values'] = !empty($input['default_values'])
                                          ? (float) str_replace(',', '.', $input['default_values'])
                                          : null;
            $input['range_min']      = !empty($input['range_min'])
                                          ? (float) str_replace(',', '.', $input['range_min'])
                                          : null;
            $input['range_max']      = !empty($input['range_max'])
                                          ? (float) str_replace(',', '.', $input['range_max'])
                                          : null;
         }

         // LDAP fields validation
         if ($input['fieldtype'] == 'ldapselect') {
            // Fields are differents for dropdown lists, so we need to replace these values into the good ones
            if(isset($input['ldap_auth'])
               && !empty($input['ldap_auth'])) {

               $config_ldap = new AuthLDAP();
               $config_ldap->getFromDB($input['ldap_auth']);

               if (!empty($input['ldap_attribute'])) {
                  $ldap_dropdown = new RuleRightParameter();
                  $ldap_dropdown->getFromDB($input['ldap_attribute']);
                  $attribute     = array($ldap_dropdown->fields['value']);
               } else {
                  $attribute     = array();
               }

               // Set specific error handler to catch LDAP errors
               if (!function_exists('warning_handler')) {
                  function warning_handler($errno, $errstr, $errfile, $errline, array $errcontext) {
                     if (0 === error_reporting()) return false;
                     throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
                  }
               }
               set_error_handler("warning_handler", E_WARNING);

               try {
                  $ds            = $config_ldap->connect();
                  ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                  ldap_control_paged_result($ds, 1);
                  $sn            = ldap_search($ds, $config_ldap->fields['basedn'], $input['ldap_filter'], $attribute);
                  $entries       = ldap_get_entries($ds, $sn);
               } catch(Exception $e) {
                  Session::addMessageAfterRedirect(__('Cannot recover LDAP informations!', 'formcreator'), false, ERROR);
               }

               restore_error_handler();

               $input['values'] = json_encode(array(
                  'ldap_auth'      => $input['ldap_auth'],
                  'ldap_filter'    => $input['ldap_filter'],
                  'ldap_attribute' => strtolower($input['ldap_attribute']),
               ));
            }
         }
      }

      // Add leading and trailing regex marker automaticaly
      if (isset($input['regex'])
          && !empty($input['regex'])) {
         if (substr($input['regex'], 0, 1)  != '/')
            if (substr($input['regex'], 0, 1)  != '^')   $input['regex'] = '/^' . $input['regex'];
            else                                         $input['regex'] = '/' . $input['regex'];
         if (substr($input['regex'], -1, 1) != '/')
            if (substr($input['regex'], -1, 1)  != '$')  $input['regex'] = $input['regex'] . '$/';
            else                                         $input['regex'] = $input['regex'] . '/';
      }

      return $input;
   }

   /**
    * Prepare input datas for adding the question
    * Check fields values and get the order for the new question
    *
    * @param $input datas used to add the item
    *
    * @return the modified $input array
   **/
   public function prepareInputForAdd($input)
   {
      global $DB;

      $input = $this->checkBeforeSave($input);
      if (count($input) == 0) {
         return array();
      }

      // Decode (if already encoded) and encode strings to avoid problems with quotes
      foreach ($input as $key => $value) {
         $input[$key] = plugin_formcreator_encode($value);
      }

      // generate a uniq id
      if (!isset($input['uuid'])
          || empty($input['uuid'])) {
         $input['uuid'] = plugin_formcreator_getUuid();
      }

      if (!empty($input)) {
         // Get next order
         $table = self::getTable();
         $sectionId = $input['plugin_formcreator_sections_id'];
         $query  = "SELECT MAX(`order`) AS `order`
                    FROM `$table`
                    WHERE `plugin_formcreator_sections_id` = '$sectionId'";
         $result = $DB->query($query);
         $line   = $DB->fetch_array($result);
         $input['order'] = $line['order'] + 1;

         $input = $this->serializeDefaultValue($input);
      }
      return $input;
   }

   /**
    * Prepare input datas for adding the question
    * Check fields values and get the order for the new question
    *
    * @param $input datas used to add the item
    *
    * @return the modified $input array
   **/
   public function prepareInputForUpdate($input)
   {
      global $DB;

      if (!isset($input['_skip_checks'])
          || !$input['_skip_checks']) {
         $input = $this->checkBeforeSave($input);
      }

      // generate a uniq id
      if (!isset($input['uuid'])
          || empty($input['uuid'])) {
         $input['uuid'] = plugin_formcreator_getUuid();
      }

      // Decode (if already encoded) and encode strings to avoid problems with quotes
      foreach ($input as $key => $value) {
         $input[$key] = plugin_formcreator_encode($value);
      }

      if (!empty($input)
          && isset($input['plugin_formcreator_sections_id'])) {
         // If change section, reorder questions
         if($input['plugin_formcreator_sections_id'] != $this->fields['plugin_formcreator_sections_id']) {
            $oldId = $this->fields['plugin_formcreator_sections_id'];
            $newId = $input['plugin_formcreator_sections_id'];
            $order = $this->fields['order'];
            // Reorder other questions from the old section
            $table = self::getTable();
            $query = "UPDATE `$table` SET
                `order` = `order` - 1
                WHERE `order` > '$order'
                AND plugin_formcreator_sections_id = '$oldId'";
            $DB->query($query);

            // Get the order for the new section
            $query  = "SELECT MAX(`order`) AS `order`
                       FROM `$table`
                       WHERE `plugin_formcreator_sections_id` = '$newId'";
            $result = $DB->query($query);
            $line   = $DB->fetch_array($result);
            $input['order'] = $line['order'] + 1;
         }

         $input = $this->serializeDefaultValue($input);
      }

      return $input;
   }

   protected function serializeDefaultValue($input) {
      // Load field types
      PluginFormcreatorFields::getTypes();

      // actor field only
      // TODO : generalize to all other field types
      if ($input['fieldtype'] == 'actor') {
         $actorField = new ActorField($input, $input['default_values']);
         $input['default_values'] = $actorField->serializeValue($input['default_values']);
      }

      return $input;
   }

   protected function deserializeDefaultValue($input) {
      // Load field types
      PluginFormcreatorFields::getTypes();

      // Actor field only
      if ($input['fieldtype'] == 'actor') {
         $actorField = new ActorField($input, $input['default_values']);
         $input['default_values'] = $actorField->deserializeValue($input['default_values']);
      }

      return $input;
   }

   public function moveUp() {
      $order         = $this->fields['order'];
      $sectionId     = $this->fields['plugin_formcreator_sections_id'];
      $otherItem = new static();
      $otherItem->getFromDBByQuery("WHERE `plugin_formcreator_sections_id` = '$sectionId'
                                        AND `order` < '$order'
                                        ORDER BY `order` DESC LIMIT 1");
      if (!$otherItem->isNewItem()) {
         $this->update(array(
               'id'     => $this->getID(),
               'order'  => $otherItem->getField('order'),
         ));
         $otherItem->update(array(
               'id'     => $otherItem->getID(),
               'order'  => $order,
         ));
      }
   }

   public function moveDown() {
      $order         = $this->fields['order'];
      $sectionId     = $this->fields['plugin_formcreator_sections_id'];
      $otherItem = new static();
      $otherItem->getFromDBByQuery("WHERE `plugin_formcreator_sections_id` = '$sectionId'
            AND `order` > '$order'
            ORDER BY `order` ASC LIMIT 1");
      if (!$otherItem->isNewItem()) {
         $this->update(array(
               'id'     => $this->getID(),
               'order'  => $otherItem->getField('order'),
         ));
         $otherItem->update(array(
               'id'     => $otherItem->getID(),
               'order'  => $order,
         ));
      }
   }

   public function updateConditions($input) {
      global $DB;

      $question_condition = new PluginFormcreatorQuestion_Condition();
      $question_condition->deleteByCriteria(array('plugin_formcreator_questions_id' => $input['id']));

      if ($input['show_rule'] != 'always') {
         // ===============================================================
         // TODO : Mettre en place l'interface multi-conditions
         // Ci-dessous une solution temporaire qui affiche uniquement la 1ere condition
         $value      = plugin_formcreator_encode($input['show_value']);
         $show_field = empty($input['show_field']) ? 'NULL' : (int) $input['show_field'];
         $show_condition = plugin_formcreator_decode($input['show_condition']);
         $question_condition = new PluginFormcreatorQuestion_Condition();
         $question_condition->add([
               'plugin_formcreator_questions_id'   => $input['id'],
               'show_field'                        => $show_field,
               'show_condition'                    => $show_condition,
               'show_value'                        => $value,
         ]);
         // ===============================================================
      }
   }

   /**
    * Actions done after the PURGE of the item in the database
    * Reorder other questions
    *
    * @return nothing
   **/
   public function post_purgeItem()
   {
      global $DB;

      $table = self::getTable();
      $question_condition_table = PluginFormcreatorQuestion_Condition::getTable();

      $order = $this->fields['order'];
      $query = "UPDATE `$table` SET
                `order` = `order` - 1
                WHERE `order` > '$order'
                AND plugin_formcreator_sections_id = {$this->fields['plugin_formcreator_sections_id']}";
      $DB->query($query);

      $questionId = $this->fields['id'];
      $query = "UPDATE `$table` SET `show_rule`='always'
            WHERE `id` IN (
                  SELECT `plugin_formcreator_questions_id` FROM `$question_condition_table`
                  WHERE `show_field` = '$questionId'
            )";
      $DB->query($query);

      $query = "DELETE FROM `$question_condition_table`
            WHERE `plugin_formcreator_questions_id` = '$questionId'
            OR `show_field` = '$questionId'";
      $DB->query($query);
   }

   /**
    * Database table installation for the item type
    *
    * @param Migration $migration
    * @return boolean True on success
    */
   public static function install(Migration $migration)
   {
      global $DB;

      $table = self::getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         // Create questions table
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `plugin_formcreator_sections_id` int(11) NOT NULL,
                     `fieldtype` varchar(30) NOT NULL DEFAULT 'text',
                     `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                     `required` boolean NOT NULL DEFAULT FALSE,
                     `show_empty` boolean NOT NULL DEFAULT FALSE,
                     `default_values` text NULL,
                     `values` text NULL,
                     `range_min` varchar(10) NULL DEFAULT NULL,
                     `range_max` varchar(10) NULL DEFAULT NULL,
                     `description` text NOT NULL,
                     `regex` varchar(255) NULL DEFAULT NULL,
                     `order` int(11) NOT NULL DEFAULT '0',
                     `show_rule` enum('always','hidden','shown') NOT NULL DEFAULT 'always',
                     `uuid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                     PRIMARY KEY (`id`),
                     FULLTEXT INDEX `Search` (`description`, `name`)
                  )
                  ENGINE = MyISAM
                  DEFAULT CHARACTER SET = utf8
                  COLLATE = utf8_unicode_ci";
         $DB->query($query) or die ($DB->error());

         // Create questions conditions table (since 0.85-1.1)
         $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_formcreator_questions_conditions` (
                    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `plugin_formcreator_questions_id` int(11) NOT NULL,
                    `show_field` int(11) DEFAULT NULL,
                    `show_condition` enum('==','!=','<','>','<=','>=') DEFAULT NULL,
                    `show_value` varchar(255) DEFAULT NULL,
                    `show_logic` enum('AND','OR','XOR') DEFAULT NULL,
                    `uuid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
                  )
                  ENGINE = MyISAM
                  DEFAULT CHARACTER SET = utf8
                  COLLATE = utf8_unicode_ci";
         $DB->query($query) or die ($DB->error());

      } else {
         // Migration 0.83-1.0 => 0.85-1.0
         if(!FieldExists($table, 'fieldtype', false)) {
            // Migration from previous version
            $query = "ALTER TABLE `$table`
                      ADD `fieldtype` varchar(30) NOT NULL DEFAULT 'text',
                      ADD `show_type` enum ('show', 'hide') NOT NULL DEFAULT 'show',
                      ADD `show_field` int(11) DEFAULT NULL,
                      ADD `show_condition` enum('equal','notequal','lower','greater') COLLATE utf8_unicode_ci DEFAULT NULL,
                      ADD `show_value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                      ADD `required` tinyint(1) NOT NULL DEFAULT '0',
                      ADD `show_empty` tinyint(1) NOT NULL DEFAULT '0',
                      ADD `default_values` text COLLATE utf8_unicode_ci,
                      ADD `values` text COLLATE utf8_unicode_ci,
                      ADD `range_min` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
                      ADD `range_max` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
                      ADD `regex` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                      CHANGE `content` `description` text COLLATE utf8_unicode_ci NOT NULL,
                      CHANGE `position` `order` int(11) NOT NULL DEFAULT '0';";
            $DB->query($query) or die ($DB->error());

            // order start from 1 instead of 0
            $DB->query("UPDATE `$table` SET `order` = `order` + 1;") or die ($DB->error());

            // Match new type
            $query  = "SELECT `id`, `type`, `data`, `option`
                       FROM $table";
            $result = $DB->query($query);
            while ($line = $DB->fetch_array($result)) {
               $datas    = json_decode($line['data']);
               $options  = json_decode($line['option']);

               $fieldtype = 'text';
               $values    = '';
               $default   = '';
               $regex     = '';
               $required  = 0;

               if (isset($datas->value) && !empty($datas->value)) {
                  if(is_object($datas->value)) {
                     foreach($datas->value as $value) {
                        if (!empty($value)) $values .= urldecode($value) . "\r\n";
                     }
                  } else {
                     $values .= urldecode($datas->value);
                  }
               }

               switch ($line['type']) {
                  case '1':
                     $fieldtype = 'text';

                     if (isset($options->type)) {
                        switch ($options->type) {
                           case '2':
                              $required  = 1;
                              break;
                           case '3':
                              $regex = '[[:alpha:]]';
                              break;
                           case '4':
                              $fieldtype = 'float';
                              break;
                           case '5':
                              $regex = urldecode($options->value);
                              // Add leading and trailing regex marker (automaticaly added in V1)
                              if (substr($regex, 0, 1)  != '/') $regex = '/' . $regex;
                              if (substr($regex, -1, 1) != '/') $regex = $regex . '/';
                              break;
                           case '6':
                              $fieldtype = 'email';
                              break;
                           case '7':
                              $fieldtype = 'date';
                              break;
                        }
                     }
                     $default_values = $values;
                     $values = '';
                     break;

                  case '2':
                     $fieldtype = 'select';
                     break;

                  case '3':
                     $fieldtype = 'checkboxes';
                     break;

                  case '4':
                     $fieldtype = 'textarea';
                     if (isset($options->type) && ($options->type == 2)) {
                        $required = 1;
                     }
                     $default_values = $values;
                     $values = '';
                     break;

                  case '5':
                     $fieldtype = 'file';
                     break;

                  case '8':
                     $fieldtype = 'select';
                     break;

                  case '9':
                     $fieldtype = 'select';
                     break;

                  case '10':
                     $fieldtype = 'dropdown';
                     break;

                  default :
                     $data = null;
                     break;
               }

               $query_udate = "UPDATE `$table` SET
                                  `fieldtype`      = '" . $fieldtype . "',
                                  `values`         = '" . htmlspecialchars($values) . "',
                                  `default_values` = '" . htmlspecialchars($default) . "',
                                  `regex`          = '" . $regex . "',
                                  `required`       = " . (int) $required . "
                               WHERE `id` = " . $line['id'];
               $DB->query($query_udate) or die ($DB->error());
            }

            $query = "ALTER TABLE `$table`
                      DROP `type`,
                      DROP `data`,
                      DROP `option`,
                      DROP `plugin_formcreator_forms_id`;";
            $DB->query($query) or die ($DB->error());
         }

         // Migration 0.85-1.0 => 0.85-1.1
         if (FieldExists($table, 'show_type', false)) {

            // Fix type of section ID
            if (!FieldExists('glpi_plugin_formcreator_questions', 'show_rule')) {
               $query = "ALTER TABLE  `glpi_plugin_formcreator_questions`
                         CHANGE `plugin_formcreator_sections_id` `plugin_formcreator_sections_id` INT NOT NULL,
                         ADD `show_rule` enum('always','hidden','shown') NOT NULL DEFAULT 'always'";
               $DB->query($query) or die ($DB->error());
            }
         }

         /**
          * Migration of special chars from previous versions
          *
          * @since 0.85-1.2.3
          */
         // Migrate "questions" table
         $query  = "SELECT `id`, `name`, `values`, `default_values`, `description`
                    FROM `glpi_plugin_formcreator_questions`";
         $result = $DB->query($query);
         while ($line = $DB->fetch_array($result)) {
            $query_update = "UPDATE `glpi_plugin_formcreator_questions` SET
                               `name`           = '" . plugin_formcreator_encode($line['name']) . "',
                               `values`         = '" . plugin_formcreator_encode($line['values']) . "',
                               `default_values` = '" . plugin_formcreator_encode($line['default_values']) . "',
                               `description`    = '" . plugin_formcreator_encode($line['description']) . "'
                             WHERE `id` = " . $line['id'];
            $DB->query($query_update) or die ($DB->error());
         }

         /**
          * Add natural language search
          *
          * @since 0.90-1.4
          */
         // An error may occur if the Search index does not exists
         // This is not critical as we need to (re) create it
         If (isIndex($table, 'Search')) {
            $query = "ALTER TABLE `$table` DROP INDEX `Search`";
            $DB->query($query);
         }

         // Re-add FULLTEXT index
         $query = "ALTER TABLE `$table` ADD FULLTEXT INDEX `Search` (`name`, `description`)";
         $DB->query($query) or die ($DB->error());

         // add uuid to questions
         if (!FieldExists($table, 'uuid', false)) {
            $migration->addField($table, 'uuid', 'string');
            $migration->migrationOneTable($table);
         }

         // fill missing uuid (force update of questions, see self::prepareInputForUpdate)
         $obj = new self();
         $all_questions = $obj->find("uuid IS NULL");
         foreach($all_questions as $questions_id => $question) {
            $obj->update(array('id' => $questions_id));
         }
      }

      return true;
   }

   /**
    * Database table uninstallation for the item type
    *
    * @return boolean True on success
    */
   public static function uninstall()
   {
      global $DB;

      $table = self::getTable();
      $DB->query("DROP TABLE IF EXISTS `$table`");

      // Delete logs of the plugin
      $DB->query("DELETE FROM `glpi_logs` WHERE itemtype = '" . __CLASS__ . "'");

      return true;
   }

   /**
    * Clone a question
    * @param  array  $input with these keys
    *                       - id the id of the question to clone
    * @return integer the question id of the new clone
    */
   public function cloneItem($input = []) {
      global $DB;

      if ($DB->isSlave()) {
         return false;
      }

      if (!$this->getFromDB($input[static::getIndexName()])) {
         return false;
      }

      // export and import the current question without uuid in order to clone it
      return $this->import($this->getField('plugin_formcreator_sections_id'),
                           $this->export(true));
   }


   /**
    * Import a section's question into the db
    * @see PluginFormcreatorSection::import
    *
    * @param  integer $sections_id  id of the parent section
    * @param  array   $question the question data (match the question table)
    * @return integer the question's id
    */
   public static function import($sections_id = 0, $question = array()) {
      $item = new self;

      $question['plugin_formcreator_sections_id'] = $sections_id;
      $question['_skip_checks']                   = true;

      if ($questions_id = plugin_formcreator_getFromDBByField($item, 'uuid', $question['uuid'])) {
         // add id key
         $question['id'] = $questions_id;

         // update question
         $item->update($question);
      } else {
         //create question
         $questions_id = $item->add($question);
      }

      if ($questions_id
          && isset($question['_conditions'])) {
         foreach($question['_conditions'] as $condition) {
            PluginFormcreatorQuestion_Condition::import($questions_id, $condition);
         }
      }

      return $questions_id;
   }

   /**
    * Export in an array all the data of the current instanciated question
    * @param boolean $remove_uuid remove the uuid key
    *
    * @return array the array with all data (with sub tables)
    */
   public function export($remove_uuid = false) {
      if (!$this->getID()) {
         return false;
      }

      $form_question_condition = new PluginFormcreatorQuestion_Condition;
      $question                = $this->fields;

      // remove key and fk
      unset($question['id'],
            $question['plugin_formcreator_sections_id']);

      // get question conditions
      $question['_conditions'] = [];
      $all_conditions = $form_question_condition->find("plugin_formcreator_questions_id = ".$this->getID());
      foreach($all_conditions as $conditions_id => $condition) {
         if ($form_question_condition->getFromDB($conditions_id)) {
            $question['_conditions'][] = $form_question_condition->export($remove_uuid);
         }
      }

      if ($remove_uuid) {
         $question['uuid'] = '';
      }

      return $question;
   }
}
