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


use Glpi\Dashboard\Right as DashboardRight;
use Glpi\Dashboard\Dashboard;

/**
 * Install all necessary elements for the plugin
 * @param array $args ARguments passed from CLI
 * @return boolean True if success
 */
function plugin_formcreator_install(array $args = []): bool {
   spl_autoload_register('plugin_formcreator_autoload');

   $version   = plugin_version_formcreator();
   $migration = new Migration($version['version']);
   require_once(__DIR__ . '/install/install.php');
   $install = new PluginFormcreatorInstall();
   if (!$install->isPluginInstalled()
      || isset($args['force-install'])
      && $args['force-install'] === true) {
      return $install->install($migration, $args);
   }
   return $install->upgrade($migration, $args);
}

/**
 * Uninstall previously installed elements of the plugin
 *
 * @return boolean True if success
 */
function plugin_formcreator_uninstall() {
   require_once(__DIR__ . '/install/install.php');
   $install = new PluginFormcreatorInstall();
   $install->uninstall();
}

/**
 * Define Dropdown tables to be manage in GLPI :
 */
function plugin_formcreator_getDropdown() {
   return [
      'PluginFormcreatorCategory' => _n('Form category', 'Form categories', 2, 'formcreator'),
   ];
}


function plugin_formcreator_addDefaultSelect($itemtype) {
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         return "`glpi_plugin_formcreator_issues`.`itemtype`, ";
   }
   return "";
}


function plugin_formcreator_addDefaultJoin($itemtype, $ref_table, &$already_link_tables) {
   $join = '';
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         // Get default joins for tickets
         $join = Search::addDefaultJoin(Ticket::getType(), Ticket::getTable(), $already_link_tables);
         // but we want to join in issues
         $join = str_replace('`glpi_tickets`.`id`', '`glpi_plugin_formcreator_issues`.`itemtype` = "Ticket" AND `glpi_plugin_formcreator_issues`.`items_id`', $join);
         $join = str_replace('`glpi_tickets`', '`glpi_plugin_formcreator_issues`', $join);
         $join = str_replace('`users_id_recipient`', '`requester_id`', $join);
         if (Plugin::isPluginActive(PLUGIN_FORMCREATOR_ADVANCED_VALIDATION)) {
            $join .= PluginAdvformCommon::addDefaultJoin($itemtype, $ref_table, $already_link_tables);
         } else {
            $issueSo = Search::getOptions($itemtype);
            $join .= Search::addLeftJoin(
               $itemtype,
               $ref_table,
               $already_link_tables,
               $issueSo[9]['table'],
               $issueSo[9]['linkfield'],
               0,
               0,
               $issueSo[9]['joinparams']
            );
            $join .= Search::addLeftJoin(
               $itemtype,
               $ref_table,
               $already_link_tables,
               $issueSo[11]['table'],
               $issueSo[11]['linkfield'],
               0,
               0,
               $issueSo[11]['joinparams']
            );
            $join .= Search::addLeftJoin(
               $itemtype,
               $ref_table,
               $already_link_tables,
               $issueSo[16]['table'],
               $issueSo[16]['linkfield'],
               0,
               0,
               $issueSo[16]['joinparams']
            );
            $join .= Search::addLeftJoin(
               $itemtype,
               $ref_table,
               $already_link_tables,
               $issueSo[42]['table'],
               $issueSo[42]['linkfield'],
               0,
               0,
               $issueSo[42]['joinparams']
            );
            $join .= Search::addLeftJoin(
               $itemtype,
               $ref_table,
               $already_link_tables,
               $issueSo[43]['table'],
               $issueSo[43]['linkfield'],
               0,
               0,
               $issueSo[43]['joinparams']
            );
            $join .= Search::addLeftJoin(
               $itemtype,
               $ref_table,
               $already_link_tables,
               $issueSo[44]['table'],
               $issueSo[44]['linkfield'],
               0,
               0,
               $issueSo[44]['joinparams']
            );
         }
         break;

      case PluginFormcreatorFormAnswer::class:
         if (Plugin::isPluginActive(PLUGIN_FORMCREATOR_ADVANCED_VALIDATION)) {
            $join .= PluginAdvformCommon::addDefaultJoin($itemtype, $ref_table, $already_link_tables);
         }
         break;
   }
   return $join;
}

/**
 * Define specific search request
 *
 * @param  String $itemtype    Itemtype for the search engine
 * @return String          Specific search request
 */
function plugin_formcreator_addDefaultWhere($itemtype) {
   $currentUser = Session::getLoginUserID();
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         if (Session::haveRight(Entity::$rightname, UPDATE)) {
            // The user is a Formcreator administrator
            return '';
         }
         // Simplified interface or service catalog
         // Use default where from Tickets
         $condition = Search::addDefaultWhere(Ticket::class);
         if ($condition != '') {
            // Replace references to ticket tables with issues table
            $condition = str_replace('`glpi_tickets`', '`glpi_plugin_formcreator_issues`', $condition);
            $condition = str_replace('`users_id_recipient`', '`requester_id`', $condition);
            $condition .= ' OR ';
         }
         // condition where current user is a validator of the issue
         // Search optin ID 9 is either from Formcreator, either from AdvForms
         $issueSearchOptions = Search::getOptions($itemtype);
         $complexJoinId = Search::computeComplexJoinID($issueSearchOptions[9]['joinparams']);
         $colname = $issueSearchOptions[9]['linkfield'];
         $condition .= "`glpi_users_{$colname}_$complexJoinId`.`id` = '$currentUser'";

         // condition where current user is a member of a validator group of the issue
         $groupList = [];
         foreach (Group_User::getUserGroups($currentUser) as $group) {
            $groupList[] = $group['id'];
         }
         if (count($groupList) > 0) {
            $groupList = array_filter($groupList);
            $groupList = implode("', '", $groupList);
            // Search option ID 16 is either from Formcreator, either from AdvForms
            $complexJoinId = Search::computeComplexJoinID($issueSearchOptions[16]['joinparams']);
            $colname = $issueSearchOptions[16]['linkfield'];
            $condition .= " OR `glpi_groups_{$colname}_$complexJoinId`.`id` IN ('$groupList')";
         }

         // condition where current user is a validator of a issue of type ticket
         $complexJoinId = Search::computeComplexJoinID($issueSearchOptions[11]['joinparams']);
         $condition .= " OR `glpi_users_users_id_validate_$complexJoinId`.`id` = '$currentUser'";

         // condition where the current user is a requester of a ticket linked to a form answer typed issue
         $complexJoinId = Search::computeComplexJoinID($issueSearchOptions[42]['joinparams']);
         $condition .= " OR `glpi_users_$complexJoinId`.`id` = '$currentUser'";

         // condition where the current user is a watcher of a ticket linked to a form answer typed issue
         $complexJoinId = Search::computeComplexJoinID($issueSearchOptions[43]['joinparams']);
         $condition .= " OR `glpi_users_$complexJoinId`.`id` = '$currentUser'";

         // condition where the current user is assigned of a ticket linked to a form answer typed issue
         $complexJoinId = Search::computeComplexJoinID($issueSearchOptions[44]['joinparams']);
         $condition .= " OR `glpi_users_$complexJoinId`.`id` = '$currentUser'";

         // Add users_id_recipient
         $condition .= " OR `glpi_plugin_formcreator_issues`.`users_id_recipient` = '$currentUser' ";
         return "($condition)";
      break;

      case PluginFormcreatorFormAnswer::class:
         $table = $itemtype::getTable();
         if (isset($_SESSION['formcreator']['form_search_answers'])
             && $_SESSION['formcreator']['form_search_answers']) {
            // Context is displaying the answers for a given form
            $formFk = PluginFormcreatorForm::getForeignKeyField();
            return "`$table`.`$formFk` = ".
                         $_SESSION['formcreator']['form_search_answers'];
         }
         if (Session::haveRight('config', UPDATE)) {
            return '';
         }

         // Check the user is a requester
         $condition = "`$table`.`requester_id` = $currentUser";

         if (Plugin::isPluginActive(PLUGIN_FORMCREATOR_ADVANCED_VALIDATION)) {
            return PluginAdvformCommon::addDefaultWhere($itemtype);
         } else {
            // Check the user is a validator of the form answer
            $condition .= " OR (`$table`.`users_id_validator` = $currentUser";

            // check user is a member of validator groups of the form answer
            $groups = Group_User::getUserGroups($currentUser);
            if (count($groups) < 1) {
               // The user is not a member of any group
               $condition .= ")";
               return $condition;
            }

            $groupIDs = [];
            foreach ($groups as $group) {
               if ($group['id'] === null) {
                  continue;
               }
               $groupIDs[] = $group['id'];
            }
            $groupIDs = implode(',', $groupIDs);
            $condition .= " OR `$table`.`groups_id_validator` IN ($groupIDs)";
            $condition .= ")";

            return "$condition";
         }
         break;
   }
   return '';
}

function plugin_formcreator_addWhere($link, $nott, $itemtype, $ID, $val, $searchtype) {
   $searchopt = &Search::getOptions($itemtype);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];

   switch ($table.".".$field) {
      case "glpi_plugin_formcreator_issues.status" :
         $tocheck = [];
         /** @var CommonITILObject $item  */
         if ($item = getItemForItemtype($itemtype)) {
            switch ($val) {
               case 'all':
                  $tocheck = array_keys($item->getAllStatusArray());
                  break;

               case Ticket::SOLVED:
                  $tocheck = $item->getSolvedStatusArray();
                  break;

               case Ticket::INCOMING:
                  $tocheck = $item->getNewStatusArray();
                  break;

               case Ticket::WAITING:
                  $tocheck = $item->getPendingStatusArray();
                  break;

               case Ticket::CLOSED:
                  $tocheck = $item->getClosedStatusArray();
                  break;

               case 'process' :
                  // getProcessStatusArray should be an abstract method of CommonITILObject
                  $tocheck = $item->getProcessStatusArray();
                  break;

               case 'notclosed' :
                  $tocheck = $item->getAllStatusArray();
                  foreach ($item->getClosedStatusArray() as $status) {
                     unset($tocheck[$status]);
                  }
                  $tocheck = array_keys($tocheck);
                  break;

               case 'old' :
                  $tocheck = array_merge($item->getSolvedStatusArray(),
                                         $item->getClosedStatusArray());
                  break;

               case 'notold' :
                  $tocheck = $item->getAllStatusArray();
                  foreach ($item->getSolvedStatusArray() as $status) {
                     unset($tocheck[$status]);
                  }
                  foreach ($item->getClosedStatusArray() as $status) {
                     unset($tocheck[$status]);
                  }
                  unset($tocheck[PluginFormcreatorFormAnswer::STATUS_REFUSED]);

                  $tocheck = array_keys($tocheck);
                  break;
            }
         }

         if (count($tocheck) == 0) {
            $statuses = $item->getAllStatusArray();
            if (isset($statuses[$val])) {
               $tocheck = [$val];
            }
         }

         if (count($tocheck)) {
            if ($nott) {
               return $link." `$table`.`$field` NOT IN ('".implode("','", $tocheck)."')";
            }
            return $link." `$table`.`$field` IN ('".implode("','", $tocheck)."')";
         }
         break;
   }
}


function plugin_formcreator_AssignToTicket($types) {
   $types[PluginFormcreatorFormAnswer::class] = PluginFormcreatorFormAnswer::getTypeName();

   return $types;
}


function plugin_formcreator_MassiveActions($itemtype) {

   switch ($itemtype) {
      case PluginFormcreatorForm::class:
         return [
            PluginFormcreatorForm::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Duplicate'    => _x('button', 'Duplicate'),
            PluginFormcreatorForm::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Transfert'    => __('Transfer'),
            PluginFormcreatorForm::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Export'       => _sx('button', 'Export'),
            PluginFormcreatorForm::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'AccessRights' => _sx('button', 'Access rights', 'formcreator'),
         ];
   }
   return [];
}


function plugin_formcreator_giveItem($itemtype, $ID, $data, $num) {
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         return PluginFormcreatorIssue::giveItem($itemtype, $ID, $data, $num);
         break;
   }

   return "";
}

function plugin_formcreator_hook_add_ticket(CommonDBTM $item) {
   global $CFG_GLPI, $DB;

   if (!($item instanceof Ticket)) {
      return;
   }
   if (isset($CFG_GLPI['plugin_formcreator_disable_hook_create_ticket'])) {
      // run this hook only if the plugin is not generating tickets
      return;
   }

   $requester = $DB->request([
      'SELECT' => 'users_id',
      'FROM' => Ticket_User::getTable(),
      'WHERE' => [
         'tickets_id' => $item->getID(),
         'type' =>  CommonITILActor::REQUESTER,
      ],
      'ORDER' => ['id'],
      'LIMIT' => '1',
   ])->current();
   if ($requester === null) {
      $requester = [
         'users_id' => 0,
      ];
   }

   $validationStatus = PluginFormcreatorCommon::getTicketStatusForIssue($item);

   $issueName = $item->fields['name'] != '' ? addslashes($item->fields['name']) : '(' . $item->getID() . ')';
   $issue = new PluginFormcreatorIssue();
   $issue->add([
      'name'               => $issueName,
      'display_id'         => 't_' . $item->getID(),
      'items_id'           => $item->getID(),
      'itemtype'           => Ticket::class,
      'status'             => $validationStatus,
      'date_creation'      => $item->fields['date'],
      'date_mod'           => $item->fields['date_mod'],
      'entities_id'        => $item->fields['entities_id'],
      'is_recursive'       => '0',
      'requester_id'       => $requester['users_id'],
      'comment'            => addslashes($item->fields['content']),
      'users_id_recipient' => $item->fields['users_id_recipient'],
   ]);
}

function plugin_formcreator_hook_update_ticket_actors(CommonITILActor $item) {
   if ($item::$itemtype_1 == Ticket::class) {
      if ($item->fields['type'] == CommonITILActor::REQUESTER) {
         $ticket_id = $item->fields['tickets_id'];
         $ticket = new Ticket();
         if ($ticket->getFromDB($ticket_id)) {
            plugin_formcreator_hook_update_ticket($ticket);
         }
      }
   }
}

function plugin_formcreator_hook_update_ticket(CommonDBTM $item) {
   global $DB;

   if (!($item instanceof Ticket)) {
      return;
   }

   $id = $item->getID();

   $validationStatus = PluginFormcreatorCommon::getTicketStatusForIssue($item);

   $issueName = $item->fields['name'] != ''
      ? $item->fields['name']
      : '(' . $item->getID() . ')';
   $issue = new PluginFormcreatorIssue();
   $issue->getFromDBByCrit([
      'AND' => [
         'itemtype'  => Ticket::class,
         'items_id'  => $id
      ]
   ]);
   if (!$issue->isNewItem()) {
      // find the 1st requester
      $requester = $DB->request([
         'SELECT' => 'users_id',
         'FROM' => Ticket_User::getTable(),
         'WHERE' => [
            'tickets_id' => $item->getID(),
            'type' => CommonITILActor::REQUESTER,
         ],
         'ORDER' => ['id'],
         'LIMIT' => '1',
      ])->current();
      $requester = $requester['users_id'] ?? 0;

      $issue->update([
         'id'                 => $issue->getID(),
         'items_id'           => $id,
         'display_id'         => "t_$id",
         'itemtype'           => Ticket::class,
         'name'               => $DB->escape($issueName),
         'status'             => $validationStatus,
         'date_creation'      => $item->fields['date'],
         'date_mod'           => $item->fields['date_mod'],
         'entities_id'        => $item->fields['entities_id'],
         'is_recursive'       => '0',
         'requester_id'       => $requester,
         'users_id_recipient' => $requester,
         'comment'            => $DB->escape($item->fields['content']),
      ]);
   }

   // No issue linked to the ticket,
   // then find the form answer linked to the ticket
   $formAnswer = new PluginFormcreatorFormAnswer();
   if (!$formAnswer->getFromDbByTicket($id)) {
      // Should not happen as one and only one form answer shall be linked to a ticket
      // If several formanswer found, the previous getFromDBByCrit() logs an error
      return;
   }

   // set the minimal status to the form answer (which will forward the status to the issue)
   $minimalStatus = $formAnswer->getAggregatedStatus();
   if ($minimalStatus === null) {
      return;
   }
   $formAnswer->updateStatus($minimalStatus);
}

function plugin_formcreator_hook_delete_ticket(CommonDBTM $item) {
   if (!($item instanceof Ticket)) {
      return;
   }

   $id = $item->getID();

   // Update the formanswer's status (for cases where a form answer has several tickets)
   $formAnswer = new PluginFormcreatorFormAnswer();
   if ($formAnswer->getFromDbByTicket($id)) {
      $minimalStatus = $formAnswer->getAggregatedStatus();
      if ($minimalStatus === null) {
         // There is no more ticket in the form anwer
         $formAnswer->updateStatus(CommonITILObject::CLOSED);
      } else {
         $formAnswer->updateStatus($minimalStatus);
      }
   }

   // Delete the issue associated to the ticlet
   // (when a form generated one and only one ticket)
   // TODO: add is_deleted column to issue ?
   $issue = new PluginFormcreatorIssue();
   $issue->deleteByCriteria([
      'items_id' => $id,
      'itemtype' => Ticket::getType(),
   ], 1);
}

function plugin_formcreator_hook_restore_ticket(CommonDBTM $item) {
   $formAnswer = new PluginFormcreatorFormAnswer();
   if ($formAnswer->getFromDbByTicket($item)) {
      $relations = (new Item_Ticket())->find([
         'itemtype' => $formAnswer->getType(),
         'items_id' => $formAnswer->getID(),
      ]);
      if (count($relations) === 1) {
         // Recreate the issue when one and only one ticket has been created by the form
         $formAnswer->createIssue();
      }
      $minimalStatus = $formAnswer->getAggregatedStatus();
      if ($minimalStatus !== null) {
         $formAnswer->updateStatus($minimalStatus);
      }
      return;
   }

   plugin_formcreator_hook_add_ticket($item);
}

function plugin_formcreator_hook_purge_ticket(CommonDBTM $item) {
   if (!($item instanceof Ticket)) {
      return;
   }

   $id = $item->getID();

   // Update the formanswer's status (for cases where a form answer has several tickets)
   $formAnswer = new PluginFormcreatorFormAnswer();
   if ($formAnswer->getFromDbByTicket($id)) {
      $minimalStatus = $formAnswer->getAggregatedStatus();
      if ($minimalStatus === null) {
         // There is no more ticket in the form anwer
         $formAnswer->updateStatus(CommonITILObject::CLOSED);
      } else {
         $formAnswer->updateStatus($minimalStatus);
      }
   }

   // delete issue if any
   $issue = new PluginFormcreatorIssue();
   $issue->deleteByCriteria([
      'items_id' => $id,
      'itemtype' => Ticket::getType()
   ], 1);
}

function plugin_formcreator_hook_pre_purge_targetTicket(CommonDBTM $item) {
   $item->pre_purgeItem();
}

function plugin_formcreator_hook_pre_purge_targetChange(CommonDBTM $item) {
   $item->pre_purgeItem();
}

function plugin_formcreator_hook_update_ticketvalidation(CommonDBTM $item) {
   $ticket = new Ticket();
   $ticket->getFromDB($item->fields['tickets_id']);
   if ($ticket->isNewItem()) {
      // Should not happen
      return;
   }

   $status = PluginFormcreatorCommon::getTicketStatusForIssue($ticket);

   $issue = new PluginFormcreatorIssue();
   $issue->getFromDBByCrit([
      'itemtype' => Ticket::getType(),
      'items_id' => $item->fields['tickets_id']
   ]);
   if ($issue->isNewItem()) {
      return;
   }
   $issue->update([
      'id'     => $issue->getID(),
      'status' => $status
   ]);
}

function plugin_formcreator_hook_update_itilFollowup($followup) {
   $itemtype = $followup->fields['itemtype'];
   if ($itemtype != Ticket::getType()) {
      return;
   }

   $item = new Ticket();
   if (!$item->getFromDB($followup->fields['items_id'])) {
      return;
   }

   $validationStatus = PluginFormcreatorCommon::getTicketStatusForIssue($item);
   $issue = new PluginFormcreatorIssue();
   $issue->getFromDBByCrit([
      'AND' => [
         'itemtype' => $itemtype,
         'items_id' => $item->getID(),
      ]
   ]);
   if ($issue->isNewItem()) {
      return;
   }
   $issue->update([
      'id'           => $issue->getID(),
      'itemtype'     => $itemtype,
      'items_id'     => $item->getID(),
      'status'       => $validationStatus,
      'date_mod'     => $item->fields['date_mod'],
   ]);
}

function plugin_formcreator_dynamicReport($params) {
   switch ($params['item_type']) {
      case PluginFormcreatorFormAnswer::class;
         if ($url = parse_url($_SERVER['HTTP_REFERER'])) {
            if (strpos($url['path'],
                       Toolbox::getItemTypeFormURL(PluginFormcreatorForm::class)) !== false) {
               parse_str($url['query'], $query);
               if (isset($query['id'])) {
                  $item = PluginFormcreatorCommon::getForm();
                  $item->getFromDB($query['id']);
                  PluginFormcreatorFormAnswer::showForForm($item, $params);
                  return true;
               }
            }
         }
         break;
   }

   return false;
}

/**
 * Hook for timeline_actions; display a new action for a CommonITILObject
 * @see CommonITILObject
 *
 * @return void
 */
function plugin_formcreator_timelineActions($options) {
   $item = $options['item'];
   if (!$item->canDeleteItem()) {
      return;
   }

   if (!(isset($_SESSION['glpiactiveprofile']) &&
       $_SESSION['glpiactiveprofile']['interface'] == 'helpdesk')) {
      return;
   }
   echo "<li>";
   echo "<button class='btn btn-primary' onclick='".
      "javascript:plugin_formcreator_cancelMyTicket(".$item->fields['id'].");'>"
      ."<i class='fa'></i>".__('Cancel my ticket', 'formcreator')."</button>";
   echo "</li>";
}

function plugin_formcreator_hook_dashboard_cards($cards) {
   if ($cards === null) {
      $cards = [];
   }

   $counters = [
      'all'        => __('All', 'formcreator'),
      'incoming'   => __('New', 'formcreator'),
      'assigned'   => __('Assigned', 'formcreator'),
      'waiting'    => __('Waiting', 'formcreator'),
      'validate'   => __('To validate', 'formcreator'),
      'solved'     => __('Solved', 'formcreator'),
      'closed'     => __('Closed', 'formcreator'),
      // Aggregaterd statuses
      'old'        => __('Old', 'formcreator'), // Solved + closed
   ];
   foreach ($counters as $key => $label) {
      $cards['plugin_formcreator_' . $key . '_issues'] = [
         'widgettype' => ['bigNumber'],
         'itemtype'   => PluginFormcreatorIssue::getType(),
         'group'      => __('Assistance'),
         'label'      => sprintf(__("Number of %s"), $label),
         'provider'   => 'PluginFormcreatorIssue::nbIssues',
         'args'       => [
            'params' => [
               'status' => $key,
               'label'  => $label,
            ]
         ],
         'cache'      => false,
         'filters'    => []
      ];
   }

   $cards['plugin_formcreator_issues_summary'] = [
      'widgettype' => ['summaryNumbers'],
      'itemtype'   => PluginFormcreatorIssue::getType(),
      'group'      => __('Assistance'),
      'label'      => __('Issues summary', 'formcreator'),
      'provider'   => 'PluginFormcreatorIssue::getIssuesSummary',
      'cache'      => false,
      'filters'    => []
   ];

   return $cards;
}

function plugin_formcreator_hook_update_profile(CommonDBTM $item) {
   $dashboard = new Dashboard;
   if (!$dashboard->getFromDB('plugin_formcreator_issue_counters')) {
      return;
   }
   $dashboardRight = new DashboardRight();
   if ($item->fields['interface'] == 'helpdesk') {
      $dashboardRight->getFromDBByCrit([
         'dashboards_dashboards_id' => $dashboard->fields['id'],
         'itemtype' => Profile::getType(),
         'items_id' => $item->getID(),
      ]);
      if ($dashboardRight->isNewItem()) {
         $dashboardRight->add([
            'dashboards_dashboards_id' => $dashboard->fields['id'],
            'itemtype' => Profile::getType(),
            'items_id' => $item->getID(),
         ]);
      }
   } else {
      $dashboardRight->delete([
         'dashboards_dashboards_id' => $dashboard->fields['id'],
         'itemtype' => Profile::getType(),
         'items_id' => $item->getID(),
      ], 1);
   }
}

function plugin_formcreator_hook_update_user(CommonDBTM $item) {
   if ($item::getType() != User::getType()) {
      return;
   }

   if (isset($item->input['default_dashboard_mini_ticket'])) {

      if (in_array($item->input['default_dashboard_mini_ticket'], ['plugin_formcreator_issue_counters', 'plugin_formcreator_issue_summary'])) {
         Session::addMessageAfterRedirect(__('Formcreator\'s mini dashboard not usable as default. This Setting has been ignored.', 'formcreator'), false, WARNING);
         unset($item->input['default_dashboard_mini_ticket']);
      }
   }
}

function plugin_formcreator_transfer(array $options) {
   if ($options['type'] != Ticket::class) {
      return;
   }

   if ($options['id'] == $options['newID']) {
      $issue = new PluginFormcreatorIssue();
      if (!$issue->getFromDbByCrit([
         'itemtype' => $options['type'],
         'items_id' => $options['id'],
      ])) {
         // No matching issue found
         return;
      }
      $issue->update([
         'id' => $issue->getID(),
         'entities_id' => $options['entities_id'],
      ]);
   } else {
      $item_ticket = new Item_Ticket();
      if (!$item_ticket->getFromDBByCrit([
         'itemtype'   => PluginFormcreatorFormAnswer::class,
         'tickets_id' => $options['id'],
      ])) {
         // No matching form answer found
         return;
      }
      $item_ticket->add([
         'itemtype'   => PluginFormcreatorFormAnswer::class,
         'items_id'   => $item_ticket->fields['items_id'],
         'tickets_id' => $options['newID'],
      ]);
   }
}
