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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginFormcreatorFormAnswer extends CommonDBTM
{
   public $dohistory  = true;
   public $usenotepad = true;
   public $usenotepadrights = true;

   const SOPTION_ANSWER = 900000;

   // Values choosen to not conflict with status of ticket constants
   // @see PluginFormcreatorIssue::getNewStatusArray
   const STATUS_WAITING = 101;
   const STATUS_REFUSED = 102;
   const STATUS_ACCEPTED = 103;

   private $questionFields = [];
   private $questions      = [];

   public static function getStatuses() {
      return [
         self::STATUS_WAITING  => __('Waiting', 'formcreator'),
         self::STATUS_REFUSED  => __('Refused', 'formcreator'),
         self::STATUS_ACCEPTED => __('Accepted', 'formcreator'),
      ];
   }

   /**
    * Check if current user have the right to create and modify requests
    *
    * @return boolean True if he can create and modify requests
    */
   public static function canCreate() {
      return true;
   }

   /**
    * Check if current user have the right to read requests
    *
    * @return boolean True if he can read requests
    */
   public static function canView() {
      return true;
   }

   public function canViewItem() {
      global $DB;

      if (!isset($_SESSION['glpiID'])) {
         return false;
      }

      if (Session::haveRight('entity', UPDATE)) {
         return true;
      }

      if ($_SESSION['glpiID'] == $this->fields['requester_id']) {
         return true;
      }

      if ($_SESSION['glpiID'] == $this->fields['users_id_validator']) {
         return true;
      }

      $groupUser = new Group_User();
      $groups = $groupUser->getUserGroups($_SESSION['glpiID']);
      if (in_array($this->fields['users_id_validator'], $groups)) {
         return true;
      }

      $request = [
         'SELECT' => PluginFormcreatorForm_Validator::getTable() . '.*',
         'FROM' => $this::getTable(),
         'INNER JOIN' => [
            PluginFormcreatorForm::getTable() => [
               'FKEY' => [
                  PluginFormcreatorForm::getTable() => PluginFormcreatorForm::getIndexName(),
                  $this::getTable() => PluginFormcreatorForm::getForeignKeyField(),
               ],
            ],
            PluginFormcreatorForm_Validator::getTable() => [
               'FKEY' => [
                  PluginFormcreatorForm::getTable() => PluginFormcreatorForm::getIndexName(),
                  PluginFormcreatorForm_Validator::getTable() => PluginFormcreatorForm::getForeignKeyField()
               ]
            ]
         ],
         'WHERE' => [$this::getTable() . '.id' => $this->getID()],
      ];
      foreach ($DB->request($request) as $row) {
         if ($row['itemtype'] == User::class) {
            if ($_SESSION['glpiID'] == $row['items_id']) {
               return true;
            }
         } else {
            if (in_array($row['items_id'], $groups)) {
               return true;
            }
         }
      }

      return false;
   }

   public static function canPurge() {
      return true;
   }

   public function canPurgeItem() {
      return Session::haveRight('entity', UPDATE);
   }

   /**
    * Returns the type name with consideration of plural
    *
    * @param number $nb Number of item(s)
    * @return string Itemtype name
    */
   public static function getTypeName($nb = 0) {
      return _n('Form answer', 'Form answers', $nb, 'formcreator');
   }

   public function rawSearchOptions() {
      $tab = [];

      $display_for_form = isset($_SESSION['formcreator']['form_search_answers'])
                          && $_SESSION['formcreator']['form_search_answers'];

      $tab[] = [
         'id'                 => 'common',
         'name'               => __('Characteristics')
      ];

      $tab[] = [
         'id'                 => '1',
         'table'              => $this->getTable(),
         'field'              => 'name',
         'name'               => __('Name'),
         'datatype'           => 'itemlink',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '2',
         'table'              => $this::getTable(),
         'field'              => 'id',
         'name'               => __('ID'),
         'searchtype'         => 'contains',
         'datatype'           => 'itemlink',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '3',
         'table'              => 'glpi_plugin_formcreator_forms',
         'field'              => 'name',
         'name'               => __('Form', 'formcreator'),
         'searchtype'         => 'contains',
         'datatype'           => 'string',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '4',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'name'               => __('Requester'),
         'datatype'           => 'itemlink',
         'massiveaction'      => false,
         'linkfield'          => 'requester_id'
      ];

      $tab[] = [
         'id'                 => '5',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'name'               => __('Form approver', 'formcreator'),
         'datatype'           => 'itemlink',
         'massiveaction'      => false,
         'linkfield'          => 'users_id_validator'
      ];

      $tab[] = [
         'id'                 => '6',
         'table'              => $this::getTable(),
         'field'              => 'request_date',
         'name'               => __('Creation date'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'            => '7',
         'table'         => getTableForItemType('Group'),
         'field'         => 'completename',
         'name'          => __('Form approver group', 'formcreator'),
         'datatype'      => 'itemlink',
         'massiveaction' => false,
         'linkfield'     => 'groups_id_validator',
      ];

      $tab[] = [
         'id'                 => '8',
         'table'              => $this::getTable(),
         'field'              => 'status',
         'name'               => __('Status'),
         'searchtype'         => [
            '0'                  => 'equals',
            '1'                  => 'notequals'
         ],
         'datatype'           => 'specific',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id' => '9',
         'table' => PluginFormcreatorForm::getTable(),
         'field' => 'id',
         'name' => __('ID'),
         'searchtype' => 'contains',
         'datatype' => 'integer',
         'massiveaction' => false,
      ];

      if ($display_for_form) {
         $optindex = self::SOPTION_ANSWER;
         $question = new PluginFormcreatorQuestion;
         $questions = $question->getQuestionsFromForm($_SESSION['formcreator']['form_search_answers']);

         foreach ($questions as $current_question) {
            $questions_id = $current_question->getID();
            $tab[] = [
               'id'            => $optindex,
               'table'         => PluginFormcreatorAnswer::getTable(),
               'field'         => 'answer',
               'name'          => $current_question->fields['name'],
               'datatype'      => 'string',
               'massiveaction' => false,
               'nosearch'      => false,
               'joinparams'    => [
                  'jointype'  => 'child',
                  'condition' => "AND NEWTABLE.`plugin_formcreator_questions_id` = $questions_id",
               ]
            ];

            $optindex++;
         }
      }

      return $tab;
   }

   /**
    * Define how to display a specific value in search result table
    *
    * @param  String $field   Name of the field as define in $this->getSearchOptions()
    * @param  Mixed  $values  The value as it is stored in DB
    * @param  Array  $options Options (optional)
    * @return Mixed           Value to be displayed
    */
   public static function getSpecificValueToDisplay($field, $values, array $options = []) {
      global $CFG_GLPI;

      if (!is_array($values)) {
         $elements = self::getStatuses();
         $values = [$field => $elements[$values]];
      }
      switch ($field) {
         case 'status' :
            $output = '<img src="' . $CFG_GLPI['root_doc'] . '/plugins/formcreator/pics/' . strtolower($values[$field]) . '.png"
                         alt="' . __($values[$field], 'formcreator') . '" title="' . __($values[$field], 'formcreator') . '" /> ';
            return $output;
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   /**
    * Define how to display search field for a specific type
    *
    * @since version 0.84
    *
    * @param String $field           Name of the field as define in $this->getSearchOptions()
    * @param String $name            Name attribute for the field to be posted (default '')
    * @param Array  $values          Array of all values to display in search engine (default '')
    * @param Array  $options         Options (optional)
    *
    * @return String                 Html string to be displayed for the form field
    */
   public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'status' :
            $elements = $this->getStatuses();
            $output = Dropdown::showFromArray($name, $elements, ['display' => false, 'value' => $values[$field]]);
            return $output;
            break;
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }
   /**
    * Display a list of all forms on the configuration page
    *
    * @param  CommonGLPI $item         Instance of a CommonGLPI Item (The Config Item)
    * @param  integer    $tabnum       Number of the current tab
    * @param  integer    $withtemplate
    *
    * @see CommonDBTM::displayTabContentForItem
    *
    * @return null                     Nothing, just display the list
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item instanceof PluginFormcreatorForm) {
         self::showForForm($item);
      } else {
         $item->showForm($item->fields['id']);
      }
   }

   public function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);
      if ($this->fields['id'] > 0) {
         $this->addStandardTab(Ticket::class, $ong, $options);
         $this->addStandardTab(Document_Item::class, $ong, $options);
         $this->addStandardTab(Notepad::class, $ong, $options);
         $this->addStandardTab(Log::class, $ong, $options);
      }
      return $ong;
   }

   /**
    * Return the name of the tab for item including forms like the config page
    *
    * @param  CommonGLPI $item         Instance of a CommonGLPI Item (The Config Item)
    * @param  integer    $withtemplate
    *
    * @return String                   Name to be displayed
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item instanceof PluginFormcreatorForm) {
         $dbUtils = new DbUtils();
         $formFk = PluginFormcreatorForm::getForeignKeyField();
         $number = $dbUtils->countElementsInTableForMyEntities(
            static::getTable(),
            [
               $formFk => $item->getID(),
            ]
         );
         return self::createTabEntry(self::getTypeName($number), $number);
      } else {
         return $this->getTypeName();
      }
   }

   static function showForForm(PluginFormcreatorForm $form, $params = []) {
      // set a session var to tweak search results
      $_SESSION['formcreator']['form_search_answers'] = $form->getID();

      // prepare params for search
      $item            = new PluginFormcreatorFormAnswer();
      $searchOptions   = $item->rawSearchOptions();
      $filteredOptions = [];
      foreach ($searchOptions as $value) {
         if (is_numeric($value['id']) && $value['id'] <= 7) {
            $filteredOptions[$value['id']] = $value;
         }
      }
      $searchOptions = $filteredOptions;
      $sopt_keys     = array_keys($searchOptions);

      $forcedisplay  = array_combine($sopt_keys, $sopt_keys);

      // do search
      $params = Search::manageParams(__CLASS__, $params, false);
      $data   = Search::prepareDatasForSearch(__CLASS__, $params, $forcedisplay);
      Search::constructSQL($data);
      Search::constructData($data);
      Search::displayData($data);

      // remove previous session var (restore default view)
      unset($_SESSION['formcreator']['form_search_answers']);
   }

   /**
    * Can the current user validate the form ?
    *
    * @param PluginFormcreatorForm $form
    */
   public function canValidate($form, CommonDBTM $form_answer) {
      if (!Session::haveRight('ticketvalidation', TicketValidation::VALIDATEINCIDENT)
         && !Session::haveRight('ticketvalidation', TicketValidation::VALIDATEREQUEST)) {
         return false;
      }

      switch ($form->fields['validation_required']) {
         case PluginFormcreatorForm_Validator::VALIDATION_USER:
            return (Session::getLoginUserID() == $form_answer->fields['users_id_validator']);
            break;

         case PluginFormcreatorForm_Validator::VALIDATION_GROUP:
            // Check the user is member of at least one validator group for the form answers
            $condition = [
               'glpi_groups.id' => new QuerySubQuery([
                  'SELECT' => ['items_id'],
                  'FROM'   => PluginFormcreatorForm_Validator::getTable(),
                  'WHERE'  => [
                     'itemtype'                    => Group::class,
                     'plugin_formcreator_forms_id' => $form->getID()
                  ]
               ])
            ];
            $groupList = Group_User::getUserGroups(Session::getLoginUserID(), $condition);
            return (count($groupList) > 0);
            break;
      }

      return false;
   }

   public function showForm($ID, $options = []) {
      global $DB;

      if (!isset($ID) || !$this->getFromDB($ID)) {
         Html::displayNotFoundError();
      }
      $options = ['canedit' => false];

      // Print css media
      echo Html::css(FORMCREATOR_ROOTDOC."/css/print_form_answer.css", ['media' => 'print']);

      // start form
      echo "<div class='form_answer'>";
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      $form = new PluginFormcreatorForm();
      $formId = $this->fields['plugin_formcreator_forms_id'];
      $form->getFromDB($formId);

      $canEdit = $this->fields['status'] == self::STATUS_REFUSED
                 && $_SESSION['glpiID'] == $this->fields['requester_id'];
      $canValidate = $this->canValidate($form, $this);

      echo '<tr><td colspan="4" class="formcreator_form form_horizontal">';

      // Form Header
      echo '<div class="form_header">';
      echo "<h1>";
      echo $form->fields['name']."&nbsp;";
      echo "<img src='".FORMCREATOR_ROOTDOC."/pics/print.png' class='pointer print_button'
                 title='".__("Print this form", 'formcreator')."' onclick='window.print();'>";
      echo "</h1>";
      if (!empty($form->fields['content'])) {
         echo html_entity_decode($form->fields['content']);
      }
      echo '</div>';

      if ($this->fields['status'] == self::STATUS_REFUSED) {
         echo '<div class="refused_header">';
         echo '<div>' . nl2br($this->fields['comment']) . '</div>';
         echo '</div>';
      } else if ($this->fields['status'] == self::STATUS_ACCEPTED) {
         echo '<div class="accepted_header">';
         echo '<div>';
         if (!empty($this->fields['comment'])) {
            echo nl2br($this->fields['comment']);
         } else if ($form->fields['validation_required']) {
            echo __('Form accepted by validator.', 'formcreator');
         } else {
            echo __('Form successfully saved.', 'formcreator');
         }
         echo '</div>';
         echo '</div>';
      }

      echo '<div class="form_section">';

      // TODO: code very close to PluginFormcreatorTargetBase::getFullForm() (factorizable ?)
      // compute all questions
      $questionTable = PluginFormcreatorQuestion::getTable();
      $sectionTable = PluginFormcreatorSection::getTable();
      $answerTable = PluginFormcreatorAnswer::getTable();
      $formFk = PluginFormcreatorForm::getForeignKeyField();
      $questionFk = PluginFormcreatorQuestion::getForeignKeyField();
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $formAnswerFk = PluginFormcreatorFormAnswer::getForeignKeyField();
      $request = [
         'SELECT' => [
            $sectionTable => ['name as section_name'],
            $questionTable => ['*'],
            $answerTable => ['answer'],
         ],
         'FROM' => [
            $questionTable,
         ],
         'LEFT JOIN' => [
            $answerTable => [
               'FKEY' => [
                  $answerTable => $questionFk,
                  $questionTable => 'id',
               ],
            ],
         ],
         'INNER JOIN' => [
            $sectionTable => [
               'FKEY' => [
                  $questionTable => $sectionFk,
                  $sectionTable => 'id',
               ],
            ],
         ],
         'WHERE' => [
            'AND' => [
               "$answerTable.$formAnswerFk" => $ID,
               "$sectionTable.$formFk" => $form->getID(),
            ],
         ],
         'GROUPBY' => [
            "$questionTable.id",
         ],
         'ORDER' => [
            "$sectionTable.order ASC",
            "$sectionTable.id ASC",
            "$questionTable.order *ASC",
         ],
      ];
      $questions = $DB->request($request);
      $last_section = '';
      $questionsCount = $questions->count();
      $fields = $form->getFields();
      foreach ($questions as $question_line) {
         $question = new PluginFormcreatorQuestion();
         $question->getFromDB($question_line['id']);
         $fields[$question_line['id']]->deserializeValue($question_line['answer']);
      }
      foreach ($questions as $question_line) {
         // Get and display current section if needed
         if ($last_section != $question_line['section_name']) {
            echo '<h2>'.$question_line['section_name'].'</h2>';
            $last_section = $question_line['section_name'];
         }

         if ($canEdit) {
            $fields[$question_line['id']]->show($canEdit);
         } else {
            if (($question_line['fieldtype'] != "description" && $question_line['fieldtype'] != "hidden")) {
               if (PluginFormcreatorFields::isVisible($question_line['id'], $fields)) {
                  $fields[$question_line['id']]->show($canEdit);
               }
            }
         }
      }
      if ($canEdit) {
         echo Html::scriptBlock('$(function() {
            formcreatorShowFields($("form[name=\'form\']"));
         })');
      }

      //add requester info
      echo '<div class="form-group">';
      echo '<label for="requester">' . __('Requester', 'formcreator') . '</label>';
      echo Dropdown::getDropdownName('glpi_users', $this->fields['requester_id']);
      echo '</div>';

      // Display submit button
      if (($this->fields['status'] == self::STATUS_REFUSED) && ($_SESSION['glpiID'] == $this->fields['requester_id'])) {
         echo '<div class="form-group line'.(($questionsCount + 1) % 2).'">';
         echo '<div class="center">';
         echo '<input type="submit" name="save_formanswer" class="submit_button" value="'.__('Save').'" />';
         echo '</div>';
         echo '</div>';

         // Display validation form
      } else if (($this->fields['status'] == self::STATUS_WAITING) && $canValidate) {
         if (Session::haveRight('ticketvalidation', TicketValidation::VALIDATEINCIDENT)
            || Session::haveRight('ticketvalidation', TicketValidation::VALIDATEREQUEST)) {
            echo '<div class="form-group required line1">';
            echo '<label for="comment">' . __('Comment', 'formcreator') . ' <span class="red">*</span></label>';
            echo '<textarea class="form-control"
                     rows="5"
                     name="comment"
                     id="comment">' . $this->fields['comment'] . '</textarea>';
            echo '<div class="help-block">' . __('Required if refused', 'formcreator') . '</div>';
            echo '</div>';

            echo '<div class="form-group line1">';
            echo '<div class="center" style="float: left; width: 50%;">';
            echo '<input type="submit" name="refuse_formanswer" class="submit_button"
                     value="' . __('Refuse', 'formcreator') . '" onclick="return checkComment(this);" />';
            echo '</div>';
            echo '<div class="center">';
            echo '<input type="submit" name="accept_formanswer" class="submit_button" value="' . __('Accept', 'formcreator') . '" />';
            echo '</div>';
            echo '</div>';
            $options['canedit'] = true;
            $options['candel'] = false;
         }
      }

      echo '<input type="hidden" name="plugin_formcreator_forms_id" value="' . $form->getID() . '">';
      echo '<input type="hidden" name="id" value="' . $this->getID() . '">';
      echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';

      echo '</div>';
      //      echo '</form>';
      echo '<script type="text/javascript">
               function checkComment(field) {
                  if (document.getElementById("comment").value == "") {
                     alert("' . __('Refused comment is required!', 'formcreator') . '");
                     return false;
                  }
               }
            </script>';

      echo '</td></tr>';

      $this->showFormButtons($options);
      echo "</div>"; // .form_answer
      return true;
   }

   /**
    * Prepare input data for adding the question
    * Check fields values and get the order for the new question
    *
    * @param array $input data used to add the item
    *
    * @return array the modified $input array
    */
   public function prepareInputForAdd($input) {
      // A requester submits his answers to a form
      if (!isset($input['plugin_formcreator_forms_id'])) {
         return false;
      }

      if (!$this->validateFormAnswer($input)) {
         // Validation of answers failed
         return false;
      }

      $form = new PluginFormcreatorForm();
      $form->getFromDB($input['plugin_formcreator_forms_id']);
      $input['name'] = Toolbox::addslashes_deep($form->getName());

      // Does the form need to be validated?
      $status = self::STATUS_ACCEPTED;
      $usersIdValidator = 0;
      $groupIdValidator = 0;
      $usersIdValidator = 0;
      switch ($form->fields['validation_required']) {
         case PluginFormcreatorForm::VALIDATION_USER:
            $status = self::STATUS_WAITING;
            $usersIdValidator = isset($input['formcreator_validator'])
                              ? $input['formcreator_validator']
                              : 0;
            break;

         case PluginFormcreatorForm::VALIDATION_GROUP:
            $status = self::STATUS_WAITING;
            $groupIdValidator = isset($input['formcreator_validator'])
                              ? $input['formcreator_validator']
                              : 0;
            break;
      }

      $input['entities_id'] = isset($_SESSION['glpiactive_entity'])
                            ? $_SESSION['glpiactive_entity']
                            : $form->fields['entities_id'];
      
      $input['is_recursive']                = $form->fields['is_recursive'];
      $input['plugin_formcreator_forms_id'] = $form->getID();
      $input['requester_id']                = isset($_SESSION['glpiID'])
                                            ? $_SESSION['glpiID']
                                            : 0;
      $input['users_id_validator']          = $usersIdValidator;
      $input['groups_id_validator']         = $groupIdValidator;
      $input['status']                      = $status;
      $input['request_date']                = date('Y-m-d H:i:s');
      $input['comment']                     = '';
                
      return $input;
   }

   /**
    * Prepare input data for updating the question
    * Check fields values and get the order for the new question
    *
    * @param array $input data used to update the item
    *
    * @return array the modified $input array
    */
   public function prepareInputForUpdate($input) {
      return $input;
   }

   /**
    * Actions done before deleting an item. In case of failure, prevents
    * actual deletion of the item
    *
    * @return boolean true if pre_delete actions succeeded, false if not
    */
   public function pre_deleteItem() {
      $issue = new PluginFormcreatorIssue();
      $issue->deleteByCriteria([
         'original_id'     => $this->getID(),
         'sub_itemtype'    => self::getType(),
      ]);

      return true;
   }

   /**
    * Create or update answers of a form
    *
    * @param PluginFormcreatorForm  $form
    * @param array                  $data answers
    * @param array                  $fields array of field: question id => instance
    * @return integer               ID of the created or updated Form Answer
    */
   public function saveAnswers(PluginFormcreatorForm $form, $data, $fields) {
      global $DB;

      $formanswers_id = isset($data['id'])
                        ? intval($data['id'])
                        : -1;

      $question = new PluginFormcreatorQuestion();
      $questions = $question->getQuestionsFromForm($form->getID());

      // Update form answers
      if (isset($data['save_formanswer'])) {
         $status = $data['status'];
         $this->update([
            'id'        => $formanswers_id,
            'status'    => $status,
            'comment'   => isset($data['comment']) ? $data['comment'] : 'NULL'
         ]);

         // Update questions answers
         if ($status == self::STATUS_WAITING) {
            foreach ($questions as $questionId => $question) {
               $answer = new PluginFormcreatorAnswer();
               $answer->getFromDBByCrit([
                  'plugin_formcreator_formanswers_id' => $formanswers_id,
                  'plugin_formcreator_questions_id' => $questionId,
               ]);
               $answer->update([
                  'id'     => $answer->getID(),
                  'answer' => $fields[$questionId]->serializeValue(),
               ], 0);
            }
         }
         $is_newFormAnswer = false;

      } else {
         // Create new form answer object

         // Does the form need to be validated?
         $status = self::STATUS_ACCEPTED;
         $usersIdValidator = 0;
         $groupIdValidator = 0;
         $usersIdValidator = 0;
         switch ($form->fields['validation_required']) {
            case PluginFormcreatorForm::VALIDATION_USER:
               $status = self::STATUS_WAITING;
               $usersIdValidator = isset($data['formcreator_validator'])
                                 ? $data['formcreator_validator']
                                 : 0;
               break;

            case PluginFormcreatorForm::VALIDATION_GROUP:
               $status = self::STATUS_WAITING;
               $groupIdValidator = isset($data['formcreator_validator'])
                                 ? $data['formcreator_validator']
                                 : 0;
               break;
         }

         $formanswers_id = $this->add([
            'entities_id'                 => isset($_SESSION['glpiactive_entity'])
                                             ? $_SESSION['glpiactive_entity']
                                             : $form->fields['entities_id'],
            'is_recursive'                => $form->fields['is_recursive'],
            'plugin_formcreator_forms_id' => $form->getID(),
            'requester_id'                => isset($_SESSION['glpiID'])
                                             ? $_SESSION['glpiID']
                                             : 0,
            'users_id_validator'          => $usersIdValidator,
            'groups_id_validator'         => $groupIdValidator,
            'status'                      => $status,
            'request_date'                => date('Y-m-d H:i:s'),
         ]);

         // Save questions answers
         foreach ($questions as $questionId => $question) {
            $answer = new PluginFormcreatorAnswer();
            $answer->add([
               'plugin_formcreator_formanswers_id'  => $formanswers_id,
               'plugin_formcreator_questions_id'    => $question->getID(),
               'answer'                             => $fields[$questionId]->serializeValue(),
            ], [], 0);
            foreach ($fields[$questionId]->getDocumentsForTarget() as $documentId) {
               $docItem = new Document_Item();
               $docItem->add([
                  'documents_id' => $documentId,
                  'itemtype'     => __CLASS__,
                  'items_id'     => $this->getID(),
               ]);
            }
         }
         $is_newFormAnswer = true;
      }

      if ($form->fields['validation_required'] || ($status == self::STATUS_ACCEPTED)) {
         switch ($status) {
            case self::STATUS_WAITING :
               // Notify the requester
               NotificationEvent::raiseEvent('plugin_formcreator_form_created', $this);
               // Notify the validator
               NotificationEvent::raiseEvent('plugin_formcreator_need_validation', $this);
               break;
            case self::STATUS_REFUSED :
               // Notify the requester
               NotificationEvent::raiseEvent('plugin_formcreator_refused', $this);
               break;
            case self::STATUS_ACCEPTED :
               // Notify the requester
               if ($form->fields['validation_required']) {
                  NotificationEvent::raiseEvent('plugin_formcreator_accepted', $this);
               } else {
                  NotificationEvent::raiseEvent('plugin_formcreator_form_created', $this);
               }

               if (!$this->generateTarget()) {
                  Session::addMessageAfterRedirect(__('Cannot generate targets!', 'formcreator'), true, ERROR);

                  // TODO: find a way to validate the answers
                  // It the form is not being validated, nothing gives the power to anyone to validate the answers
                  $this->update([
                     'id'     => $this->getID(),
                     'status' => self::STATUS_WAITING,
                  ]);
                  return false;
               }
               break;
         }

         // Update issues table
         $issue = new PluginFormcreatorIssue();
         if ($status != self::STATUS_REFUSED) {
            // If cannot get itemTicket from DB it happens either
            // when no item exist
            // or when several rows matches
            // Both are processed the same way
            $itemTicket = new Item_Ticket();
            $rows = $DB->request([
               'SELECT' => ['id'],
               'FROM'   => $itemTicket::getTable(),
               'WHERE'  => [
                  'itemtype' => PluginFormcreatorFormAnswer::class,
                  'items_id' => $formanswers_id,
               ]
            ]);
            if ($rows->count() != 1) {
               if ($is_newFormAnswer) {
                  // This is a new answer for the form. Create an issue
                  $issue->add([
                     'original_id'     => $formanswers_id,
                     'sub_itemtype'    => PluginFormcreatorFormAnswer::class,
                     'name'            => addslashes($this->fields['name']),
                     'status'          => $status,
                     'date_creation'   => $this->fields['request_date'],
                     'date_mod'        => $this->fields['request_date'],
                     'entities_id'     => $this->fields['entities_id'],
                     'is_recursive'    => $this->fields['is_recursive'],
                     'requester_id'    => $this->fields['requester_id'],
                     'validator_id'    => $this->fields['users_id_validator'],
                     'comment'         => '',
                  ]);
               } else {
                  $issue->getFromDBByCrit([
                     'AND' => [
                       'sub_itemtype' => PluginFormcreatorFormAnswer::class,
                       'original_id'  => $formanswers_id
                     ]
                  ]);
                  $issue->update([
                     'id'              => $issue->getID(),
                     'original_id'     => $formanswers_id,
                     'sub_itemtype'    => PluginFormcreatorFormAnswer::class,
                     'name'            => addslashes($this->fields['name']),
                     'status'          => $status,
                     'date_creation'   => $this->fields['request_date'],
                     'date_mod'        => $this->fields['request_date'],
                     'entities_id'     => $this->fields['entities_id'],
                     'is_recursive'    => $this->fields['is_recursive'],
                     'requester_id'    => $this->fields['requester_id'],
                     'validator_id'    => $this->fields['users_id_validator'],
                     'comment'         => '',
                  ]);
               }
            } else {
               $result = $rows->next();
               $itemTicket->getFromDB($result['id']);
               $ticket = new Ticket();
               if (!$ticket->getFromDB($itemTicket->fields['tickets_id'])) {
                  throw new RuntimeException('Formcreator: Missing ticket ' . $itemTicket->fields['tickets_id'] . ' for formanswer ' . $formanswers_id);
               }
               $ticketId = $ticket->getID();
               if ($is_newFormAnswer) {
                  $issue->add([
                     'original_id'     => $ticketId,
                     'sub_itemtype'    => Ticket::class,
                     'name'            => addslashes($ticket->getField('name')),
                     'status'          => $ticket->getField('status'),
                     'date_creation'   => $ticket->getField('date'),
                     'date_mod'        => $ticket->getField('date_mod'),
                     'entities_id'     => $ticket->getField('entities_id'),
                     'is_recursive'    => '0',
                     'requester_id'    => $ticket->getField('users_id_recipient'),
                     'validator_id'    => '',
                     'comment'         => addslashes($ticket->getField('content')),
                  ]);
               } else {
                  $issue->getFromDBByCrit([
                    'AND' => [
                      'sub_itemtype' => PluginFormcreatorFormAnswer::class,
                      'original_id'  => $formanswers_id
                    ]
                  ]);
                  $issue->update([
                     'id'              => $issue->getID(),
                     'original_id'     => $ticketId,
                     'sub_itemtype'    => Ticket::class,
                     'name'            => addslashes($ticket->getField('name')),
                     'status'          => $ticket->getField('status'),
                     'date_creation'   => $ticket->getField('date'),
                     'date_mod'        => $ticket->getField('date_mod'),
                     'entities_id'     => $ticket->getField('entities_id'),
                     'is_recursive'    => '0',
                     'requester_id'    => $ticket->getField('users_id_recipient'),
                     'validator_id'    => '',
                     'comment'         => addslashes($ticket->getField('content')),
                  ]);
               }
            }
         } else {
            $issue->getFromDBByCrit([
              'AND' => [
                'sub_itemtype' => PluginFormcreatorFormAnswer::class,
                'original_id'  => $formanswers_id
              ]
            ]);
            $issue->update([
               'id'              => $issue->getID(),
               'sub_itemtype'    => PluginFormcreatorFormAnswer::class,
               'original_id'     => $formanswers_id,
               'status'          => $status,
            ]);
         }
      }

      Session::addMessageAfterRedirect(__('The form has been successfully saved!', 'formcreator'), true, INFO);

      // TODO: This reveals a real refactor need in this method !
      return $formanswers_id;
   }

   /**
    * Update the answers
    *
    * @param array $input
    * @return boolean
    */
   public function updateAnswers($input) {
      $form = new PluginFormcreatorForm();
      $form->getFromDB((int) $_POST['formcreator_form']);
      $input['status'] = self::STATUS_WAITING;

      $fields = $form->getFields();
      foreach ($fields as $id => $question) {
         $fields[$id]->parseAnswerValues($input);
      }
      return $this->saveAnswers($form, $input, $fields);
   }

   /**
    * Mark answers of a form as refused
    *
    * @param array $input
    *
    * @return boolean
    */
   public function refuseAnswers($input) {
      $input['status']          = self::STATUS_REFUSED;
      $input['save_formanswer'] = true;

      $form   = new PluginFormcreatorForm();
      $form->getFromDB((int) $input['formcreator_form']);

      // Prepare form fields for validation
      if (!$this->canValidate($form, $this)) {
         Session::addMessageAfterRedirect(__('You are not the validator of these answers', 'formcreator'), true, ERROR);
         return false;
      }

      $fields = $form->getFields();
      foreach ($fields as $id => $question) {
         $fields[$id]->parseAnswerValues($input);
      }
      return $this->saveAnswers($form, $input, $fields);
   }

   /**
    * Mark answers of a form as accepted
    *
    * @param array $input
    *
    * @return boolean
    */
   public function acceptAnswers($input) {
      $input['status']                      = self::STATUS_ACCEPTED;
      $input['save_formanswer']             = true;

      $form   = new PluginFormcreatorForm();
      $form->getFromDB((int) $input['formcreator_form']);

      // Prepare form fields for validation
      if (!$this->canValidate($form, $this)) {
         Session::addMessageAfterRedirect(__('You are not the validator of these answers', 'formcreator'), true, ERROR);
         return false;
      }

      $fields = $form->getFields();
      foreach ($fields as $id => $question) {
         $fields[$id]->parseAnswerValues($input);
      }
      return $this->saveAnswers($form, $input, $fields);
   }

   /**
    * Generates all targets for the answers
    */
   public function generateTarget() {
      global $CFG_GLPI;

      $success = true;

      // Get all targets
      $form = new PluginFormcreatorForm();
      $form->getFromDB($this->fields['plugin_formcreator_forms_id']);
      $all_targets = $form->getTargetsFromForm();

      $CFG_GLPI['plugin_formcreator_disable_hook_create_ticket'] = '1';

      // Generate targets
      $generatedTargets = new PluginFormcreatorComposite(new PluginFormcreatorItem_TargetTicket(), new Ticket_Ticket());
      foreach ($all_targets as $targetType => $targets) {
         foreach ($targets as $targetObject) {
            $generatedTarget = $targetObject->save($this);
            if ($generatedTarget === null) {
               $success = false;
               break;
            }
            // Map [itemtype of the target] [item ID of the target] = ID of the generated target
            $generatedTargets->addTarget($targetObject, $generatedTarget);
         }
      }
      $generatedTargets->buildCompositeRelations();

      Session::addMessageAfterRedirect(__('The form has been successfully saved!', 'formcreator'), true, INFO);
      unset($CFG_GLPI['plugin_formcreator_disable_hook_create_ticket']);
      return $success;
   }

   /**
    * Gets answers of all fields of a form answer
    *
    * @param integer $formAnswerId
    * @return array
    */
   public function getAnswers($formAnswerId) {
      global $DB;

      $answers = $DB->request([
         'SELECT' => ['plugin_formcreator_questions_id', 'answer'],
         'FROM'   => PluginFormcreatorAnswer::getTable(),
         'WHERE'  => [
            'plugin_formcreator_formanswers_id' => $formAnswerId
         ]
      ]);
      $answers_values = [];
      foreach ($answers as $found_answer) {
         $answers_values['formcreator_field_' . $found_answer['plugin_formcreator_questions_id']] = $found_answer['answer'];
      }
      return $answers_values;
   }

   /**
    * Gets the associated form
    *
    * @return PluginFormcreatorForm|null the form used to create this set of answers
    */
   public function getForm() {
      $form = new PluginFormcreatorForm();
      $form->getFromDB($this->fields[$form::getForeignKeyField()]);

      if ($form->isNewItem()) {
         return null;
      }
      return $form;
   }

   /**
    * Get entire form to be inserted into a target content
    *
    * @param boolean $richText If true, enable rich text output
    * @return String Full form questions and answers to be print
    */
   public function getFullForm($richText = false) {
      global $DB;

      $question_no = 0;
      $output      = '';
      $eol = "\r\n";

      if ($richText) {
         $output .= '<h1>' . __('Form data', 'formcreator') . '</h1>';
      } else {
         $output .= __('Form data', 'formcreator') . $eol;
         $output .= '=================';
         $output .= $eol . $eol;
      }

      // retrieve answers
      $answers_values = $this->getAnswers($this->getID());
      $form = new PluginFormcreatorForm();
      $form->getFromDB($this->fields['plugin_formcreator_forms_id']);
      $fields = $form->getFields();
      foreach ($fields as $questionId => $question) {
         $answer = $answers_values['formcreator_field_' . $questionId];
         $fields[$questionId]->deserializeValue($answer);
      }

      // TODO: code very close to PluginFormcreatorTargetBase::parseTags() (factorizable ?)
      // compute all questions
      $questionTable = PluginFormcreatorQuestion::getTable();
      $sectionTable = PluginFormcreatorSection::getTable();
      $formFk = PluginFormcreatorForm::getForeignKeyField();
      $sectionFk = PluginFormcreatorSection::getForeignKeyField();
      $questions = $DB->request([
         'SELECT' => [
            $sectionTable => ['name as section_name'],
            $questionTable => ['*'],
         ],
         'FROM' => [
            $questionTable,
         ],
         'INNER JOIN' => [
            $sectionTable => [
               'FKEY' => [
                  $questionTable => $sectionFk,
                  $sectionTable => 'id',
               ],
            ],
         ],
         'WHERE' => [
            'AND' => [
               "$sectionTable.$formFk" => $this->fields['plugin_formcreator_forms_id'],
            ],
         ],
         'GROUPBY' => [
            "$questionTable.id",
         ],
         'ORDER' => [
            "$sectionTable.order ASC",
            "$sectionTable.id ASC",
            "$questionTable.order *ASC",
         ],
      ]);
      $last_section = "";
      while ($question_line = $questions->next()) {
         // Get and display current section if needed
         if ($last_section != $question_line['section_name']) {
            if ($richText) {
               $output .= '<h2>' . $question_line['section_name'] . '</h2>';
            } else {
               $output .= $eol . $question_line['section_name'] . $eol;
               $output .= '---------------------------------' . $eol;
            }
            $last_section = $question_line['section_name'];
         }

         // Don't save tags in "full form"
         if ($question_line['fieldtype'] == 'tag') {
            continue;
         }

         if (!PluginFormcreatorFields::isVisible($question_line['id'], $fields)) {
            continue;
         }

         if ($question_line['fieldtype'] != 'description') {
            $question_no++;
            if ($richText) {
               $output .= '<div>';
               $output .= '<b>' . $question_no . ') ##question_' . $question_line['id'] . '## : </b>';
               $output .= '##answer_' . $question_line['id'] . '##';
               $output .= '</div>';
            } else {
               $output .= $question_no . ') ##question_' . $question_line['id'] . '## : ';
               $output .= '##answer_' . $question_line['id'] . '##' . $eol . $eol;
            }
         }
      }

      return $output;
   }

   public function post_addItem() {
      // Save questions answers
      $question = new PluginFormcreatorQuestion();
      $this->questions = $question->getQuestionsFromForm($this->input['plugin_formcreator_forms_id']);

      foreach ($this->questions as $questionId => $question) {
         $answer = new PluginFormcreatorAnswer();
         $answer->add([
            'plugin_formcreator_formanswers_id'  => $this->getID(),
            'plugin_formcreator_questions_id'    => $question->getID(),
            'answer'                             => $this->questionFields[$questionId]->serializeValue(),
         ], [], 0);
         foreach ($this->questionFields[$questionId]->getDocumentsForTarget() as $documentId) {
            $docItem = new Document_Item();
            $docItem->add([
               'documents_id' => $documentId,
               'itemtype'     => __CLASS__,
               'items_id'     => $this->getID(),
            ]);
         }
      }
      $this->sendNotification();
      if ($this->input['status'] == self::STATUS_ACCEPTED) {
         if (!$this->generateTarget()) {
            Session::addMessageAfterRedirect(__('Cannot generate targets!', 'formcreator'), true, ERROR);

            // TODO: find a way to validate the answers
            // It the form is not being validated, nothing gives the power to anyone to validate the answers
            $this->update([
               'id'     => $this->getID(),
               'status' => self::STATUS_WAITING,
            ]);
         }
      }
      $this->createIssue();
      Session::addMessageAfterRedirect(__('The form has been successfully saved!', 'formcreator'), true, INFO);
   } 

   public function post_updateItem($history = 1) {
      $this->sendNotification();
      if ($this->input['status'] == self::STATUS_ACCEPTED) {
         if (!$this->generateTarget()) {
            Session::addMessageAfterRedirect(__('Cannot generate targets!', 'formcreator'), true, ERROR);

            // TODO: find a way to validate the answers
            // It the form is not being validated, nothing gives the power to anyone to validate the answers
            $this->update([
               'id'     => $this->getID(),
               'status' => self::STATUS_WAITING,
            ]);
         }
      }
      $this->updateIssue();
      Session::addMessageAfterRedirect(__('The form has been successfully saved!', 'formcreator'), true, INFO);
   }

   /**
    * Actions done after the PURGE of the item in the database
    * Delete answers
    *
    * @return void
    */
   public function post_purgeItem() {
      global $DB;

      $formAnswerFk = PluginFormcreatorFormAnswer::getForeignKeyField();
      $DB->delete(
         self::getTable(), [
            $formAnswerFk,
         ]
      );

      // If the form was waiting for validation
      if ($this->fields['status'] == self::STATUS_WAITING) {
         // Notify the requester
         NotificationEvent::raiseEvent('plugin_formcreator_deleted', $this);
      }
   }

   /**
    * Parse target content to replace TAGS like ##FULLFORM## by the values
    *
    * @param  string $content                            String to be parsed
    * @param  PluginFormcreatorTargetInterface $target   Target for which output is being generated
    * @param  boolean $richText                          Disable rich text mode for field rendering
    * @return string                                     Parsed string with tags replaced by form values
    */
   public function parseTags($content, PluginFormcreatorTargetInterface $target, $richText = false) {
      // retrieve answers
      $answers_values = $this->getAnswers($this->getID());

      // Retrieve questions
      $formFk = PluginFormcreatorForm::getForeignKeyField();
      $questions = (new PluginFormcreatorQuestion())
         ->getQuestionsFromForm($this->fields[$formFk]);

      // Prepare all fields of the form
      $form = new PluginFormcreatorForm();
      $form->getFromDB($this->fields[$formFk]);
      $fields = $form->getFields();
      foreach ($questions as $questionId => $question) {
         $answer = $answers_values['formcreator_field_' . $questionId];
         $fields[$questionId]->deserializeValue($answer);
      }

      foreach ($questions as $questionId => $question) {
         if (!PluginFormcreatorFields::isVisible($questionId, $fields)) {
            $name = '';
            $value = '';
         } else {
            $name  = $question->fields['name'];
            $value = $fields[$questionId]->getValueForTargetText($richText);
         }

         $content = str_replace('##question_' . $questionId . '##', Toolbox::addslashes_deep($name), $content);
         $content = str_replace('##answer_' . $questionId . '##', Toolbox::addslashes_deep($value), $content);
         foreach ($fields[$questionId]->getDocumentsForTarget() as $documentId) {
            $target->addAttachedDocument($documentId);
         }
         if ($question->fields['fieldtype'] === 'file') {
            if (strpos($content, '##answer_' . $questionId . '##') !== false) {
               if (!is_array($value)) {
                  $value = [$value];
               }
            }
         }

         if ($fields[$questionId] instanceof PluginFormcreatorDropdownField) {
            $content = $fields[$questionId]->parseObjectProperties($answer, $content);
         }
      }

      return $content;
   }

   /**
    * Validates answers of a form
    *
    * @param array $input fields from the HTML form
    * @return boolean true if answers are valid, false otherwise
    */
   protected function validateFormAnswer($input) {
      // Find the form the requester is answering to
      $form = new PluginFormcreatorForm();
      $form->getFromDB($input['plugin_formcreator_forms_id']);

      $valid = true;
      $fieldValidities = [];

      $this->questionFields = $form->getFields();
      foreach ($this->questionFields as $id => $question) {
         $fieldValidities[$id] = $this->questionFields[$id]->parseAnswerValues($input);
      }
      // any invalid field will invalidate the answers
      $valid = !in_array(false, $fieldValidities, true);

      // Mandatory field must be filled
      // and fields must contain a value matching the constraints of the field (range for example)
      if ($valid) {
         foreach ($this->questionFields as $id => $question) {
            if (!$this->questionFields[$id]->isPrerequisites()) {
               continue;
            }
            if (PluginFormcreatorFields::isVisible($id, $this->questionFields) && !$this->questionFields[$id]->isValid()) {
               $valid = false;
               break;
            }
         }
      }

      // Check required_validator
      if ($form->fields['validation_required'] && empty($input['formcreator_validator'])) {
         Session::addMessageAfterRedirect(__('You must select validator!', 'formcreator'), false, ERROR);
         $valid = false;
      }

      if (!$valid) {
         // Save answers in session to display it again with the same values
         $_SESSION['formcreator']['data'] = Toolbox::stripslashes_deep($input);
         return false;
      }

      return true;
   }

   private function sendNotification() {
      switch ($this->input['status']) {
         case self::STATUS_WAITING :
            // Notify the requester
            NotificationEvent::raiseEvent('plugin_formcreator_form_created', $this);
            // Notify the validator
            NotificationEvent::raiseEvent('plugin_formcreator_need_validation', $this);
            break;
         case self::STATUS_REFUSED :
            // Notify the requester
            NotificationEvent::raiseEvent('plugin_formcreator_refused', $this);
            break;
         case self::STATUS_ACCEPTED :
            // Notify the requester
            $form = new PluginFormcreatorForm();
            $form->getFromDB($this->fields['plugin_formcreator_forms_id']);
            if ($form->fields['validation_required'] != PluginFormcreatorForm::VALIDATION_NONE) {
               NotificationEvent::raiseEvent('plugin_formcreator_accepted', $this);
            } else {
               NotificationEvent::raiseEvent('plugin_formcreator_form_created', $this);
            }

            break;
      }
   }

   private function createIssue() {
      global $DB;

      $issue = new PluginFormcreatorIssue();
      if ($this->input['status'] != self::STATUS_REFUSED) {
         // If cannot get itemTicket from DB it happens either
         // when no item exist
         // or when several rows matches
         // Both are processed the same way
         $itemTicket = new Item_Ticket();
         $rows = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $itemTicket::getTable(),
            'WHERE'  => [
               'itemtype' => PluginFormcreatorFormAnswer::class,
               'items_id' => $this->getID(),
            ]
         ]);
         if ($rows->count() != 1) {
            // This is a new answer for the form. Create an issue
            $issue->add([
               'original_id'     => $this->getID(),
               'sub_itemtype'    => PluginFormcreatorFormAnswer::class,
               'name'            => addslashes($this->fields['name']),
               'status'          => $this->fields['status'],
               'date_creation'   => $this->fields['request_date'],
               'date_mod'        => $this->fields['request_date'],
               'entities_id'     => $this->fields['entities_id'],
               'is_recursive'    => $this->fields['is_recursive'],
               'requester_id'    => $this->fields['requester_id'],
               'validator_id'    => $this->fields['users_id_validator'],
               'comment'         => '',
            ]);
         } else {
            $result = $rows->next();
            $itemTicket->getFromDB($result['id']);
            $ticket = new Ticket();
            if (!$ticket->getFromDB($itemTicket->fields['tickets_id'])) {
               throw new RuntimeException('Formcreator: Missing ticket ' . $itemTicket->fields['tickets_id'] . ' for formanswer ' . $this->getID());
            }
            $ticketId = $ticket->getID();
            $issue->add([
               'original_id'     => $ticketId,
               'sub_itemtype'    => Ticket::class,
               'name'            => addslashes($ticket->getField('name')),
               'status'          => $ticket->getField('status'),
               'date_creation'   => $ticket->getField('date'),
               'date_mod'        => $ticket->getField('date_mod'),
               'entities_id'     => $ticket->getField('entities_id'),
               'is_recursive'    => '0',
               'requester_id'    => $ticket->getField('users_id_recipient'),
               'validator_id'    => '',
               'comment'         => addslashes($ticket->getField('content')),
            ]);
         }
      }
   }

   private function updateIssue() {
      global $DB;

      $issue = new PluginFormcreatorIssue();
      if ($this->input['status'] != self::STATUS_REFUSED) {
         $itemTicket = new Item_Ticket();
         $rows = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $itemTicket::getTable(),
            'WHERE'  => [
               'itemtype' => PluginFormcreatorFormAnswer::class,
               'items_id' => $this->getID(),
            ]
         ]);
         if ($rows->count() != 1) {
            $issue->getFromDBByCrit([
               'AND' => [
               'sub_itemtype' => PluginFormcreatorFormAnswer::class,
               'original_id'  => $this->getID()
               ]
            ]);
            $issue->update([
               'id'              => $issue->getID(),
               'original_id'     => $this->getID(),
               'sub_itemtype'    => PluginFormcreatorFormAnswer::class,
               'name'            => addslashes($this->fields['name']),
               'status'          => $this->fields['status'],
               'date_creation'   => $this->fields['request_date'],
               'date_mod'        => $this->fields['request_date'],
               'entities_id'     => $this->fields['entities_id'],
               'is_recursive'    => $this->fields['is_recursive'],
               'requester_id'    => $this->fields['requester_id'],
               'validator_id'    => $this->fields['users_id_validator'],
               'comment'         => '',
            ]);
         } else {
            $result = $rows->next();
            $itemTicket->getFromDB($result['id']);
            $ticket = new Ticket();
            if (!$ticket->getFromDB($itemTicket->fields['tickets_id'])) {
               throw new RuntimeException('Formcreator: Missing ticket ' . $itemTicket->fields['tickets_id'] . ' for formanswer ' . $this->getID());
            }
            $ticketId = $ticket->getID();
            $issue->getFromDBByCrit([
               'AND' => [
                 'sub_itemtype' => PluginFormcreatorFormAnswer::class,
                 'original_id'  => $this->getID()
               ]
             ]);
             $issue->update([
                'id'              => $issue->getID(),
                'original_id'     => $ticketId,
                'sub_itemtype'    => Ticket::class,
                'name'            => addslashes($ticket->getField('name')),
                'status'          => $ticket->getField('status'),
                'date_creation'   => $ticket->getField('date'),
                'date_mod'        => $ticket->getField('date_mod'),
                'entities_id'     => $ticket->getField('entities_id'),
                'is_recursive'    => '0',
                'requester_id'    => $ticket->getField('users_id_recipient'),
                'validator_id'    => '',
                'comment'         => addslashes($ticket->getField('content')),
             ]);
         }
      }
   }
}
