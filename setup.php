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

use Glpi\Plugin\Hooks;

global $CFG_GLPI;
// Version of the plugin (major.minor.bugfix)
define('PLUGIN_FORMCREATOR_VERSION', '2.13.10');
// Schema version of this version (major.minor only)
define('PLUGIN_FORMCREATOR_SCHEMA_VERSION', '2.13');
// is or is not an official release of the plugin
define('PLUGIN_FORMCREATOR_IS_OFFICIAL_RELEASE', true);

// Minimal GLPI version, inclusive
define ('PLUGIN_FORMCREATOR_GLPI_MIN_VERSION', '10.0.10');
// Maximum GLPI version, exclusive (ignored if PLUGIN_FORMCREATOR_IS_OFFICIAL_RELEASE == false)
define ('PLUGIN_FORMCREATOR_GLPI_MAX_VERSION', '10.1');

define('FORMCREATOR_ROOTDOC', Plugin::getWebDir('formcreator'));

// Advanced features for Formcreator
define('PLUGIN_FORMCREATOR_ADVANCED_VALIDATION', 'advform');

/**
 * Define the plugin's version and informations
 *
 * @return Array [name, version, author, homepage, license, minGlpiVersion]
 */
function plugin_version_formcreator() {
   plugin_formcreator_savePreviousVersion();

   $glpiVersion = rtrim(GLPI_VERSION, '-dev');
   if (!method_exists(Plugin::class, 'checkGlpiVersion') && version_compare($glpiVersion, PLUGIN_FORMCREATOR_GLPI_MIN_VERSION, 'lt')) {
      echo 'This plugin requires GLPI >= ' . PLUGIN_FORMCREATOR_GLPI_MIN_VERSION;
      return false;
   }
   $webDir = Plugin::getWebDir('formcreator');
   $requirements = [
      'name'           => 'Form Creator',
      'version'        => PLUGIN_FORMCREATOR_VERSION,
      'author'         => '<a href="http://www.teclib.com">Teclib\'</a>',
      'homepage'       => 'https://github.com/pluginsGLPI/formcreator',
      'license'        => '<a href="' . $webDir . '/LICENSE.md" target="_blank">GPLv2</a>',
      'requirements'   => [
         'glpi'           => [
            'min'            => PLUGIN_FORMCREATOR_GLPI_MIN_VERSION,
         ]
      ]
   ];

   if (PLUGIN_FORMCREATOR_IS_OFFICIAL_RELEASE) {
      // This is not a development version
      $requirements['requirements']['glpi']['max'] = PLUGIN_FORMCREATOR_GLPI_MAX_VERSION;
   }
   return $requirements;
}

/**
 * Check plugin's prerequisites before installation
 *
 * @return boolean
 */
function plugin_formcreator_check_prerequisites() {
   $prerequisitesSuccess = true;

   if (version_compare(GLPI_VERSION, PLUGIN_FORMCREATOR_GLPI_MIN_VERSION, 'lt')
       || PLUGIN_FORMCREATOR_IS_OFFICIAL_RELEASE && version_compare(GLPI_VERSION, PLUGIN_FORMCREATOR_GLPI_MAX_VERSION, 'ge')) {
      echo "This plugin requires GLPI >= " . PLUGIN_FORMCREATOR_GLPI_MIN_VERSION . " and GLPI < " . PLUGIN_FORMCREATOR_GLPI_MAX_VERSION . "<br>";
      $prerequisitesSuccess = false;
   }

   if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
      echo "Run composer install --no-dev in the plugin directory<br>";
      $prerequisitesSuccess = false;
   }

   if (!is_readable(__DIR__ . '/lib/.yarn-integrity') || !is_file(__DIR__ . '/lib/.yarn-integrity')) {
      echo "Run yarn install --prod in the plugin directory<br>";
      $prerequisitesSuccess = false;
   }

   return $prerequisitesSuccess;
}

/**
 * Check plugin's config before activation (if needed)
 *
 * @param string $verbose Set true to show all messages (false by default)
 * @return boolean
 */
function plugin_formcreator_check_config($verbose = false) {
   return true;
}

/**
 * Initialize all classes and generic variables of the plugin
 */
function plugin_init_formcreator() {
   global $CFG_GLPI;

   plugin_formcreator_permanent_hook();

   array_push($CFG_GLPI["ticket_types"], PluginFormcreatorFormAnswer::class);
   array_push($CFG_GLPI["document_types"], PluginFormcreatorFormAnswer::class);
   array_push($CFG_GLPI["document_types"], PluginFormcreatorForm::class);

   $plugin = new Plugin();
   if (!$plugin->isActivated('formcreator')) {
      return;
   }

   plugin_formcreator_redirect();

   spl_autoload_register('plugin_formcreator_autoload');
   require_once(__DIR__ . '/vendor/autoload.php');

   plugin_formcreator_hook();

   if (isset($_SERVER['REQUEST_URI'])) {
      plugin_formcreator_registerClasses();

      $pages = [
         FORMCREATOR_ROOTDOC . '/front/targetticket.form.php',
         FORMCREATOR_ROOTDOC . '/front/formdisplay.php',
         FORMCREATOR_ROOTDOC . '/front/form.form.php',
         FORMCREATOR_ROOTDOC . '/front/formanswer.form.php',
         FORMCREATOR_ROOTDOC . '/front/issue.form.php',
         FORMCREATOR_ROOTDOC . '/front/form_language.form.php',
         '/front/entity.form.php',
      ];
      foreach ($pages as $page) {
         if (strpos($_SERVER['REQUEST_URI'], $page) !== false) {
            Html::requireJs('tinymce');
            break;
         }
      }
   }

   $CFG_GLPI['javascript']['admin'][strtolower(PluginFormcreatorForm::class)] = ['gridstack'];
   $CFG_GLPI['javascript']['helpdesk'][strtolower(PluginFormcreatorFormlist::class)] = ['gridstack'];
   $CFG_GLPI['javascript']['helpdesk'][strtolower(PluginFormcreatorIssue::class)] = ['photoswipe'];
}

/**
 * Tells if helpdesk replacement is enabled for the current user
 *
 * @return boolean|integer
 */
function plugin_formcreator_replaceHelpdesk() {
   if (!isset($_SESSION['glpiactive_entity'])) {
      return false;
   }

   if (Session::getCurrentInterface() != 'helpdesk') {
      return false;
   }

   $helpdeskMode = PluginFormcreatorEntityconfig::getUsedConfig('replace_helpdesk', $_SESSION['glpiactive_entity']);
   if ($helpdeskMode != PluginFormcreatorEntityConfig::CONFIG_GLPI_HELPDSK) {
      return $helpdeskMode;
   }

   return false;
}


/**
 * Generate unique id for form based on server name, glpi directory and basetime
 **/
function plugin_formcreator_getUuid() {
   //encode uname -a, ex Linux localhost 2.4.21-0.13mdk #1 Fri Mar 14 15:08:06 EST 2003 i686
   $serverSubSha1 = substr(sha1(php_uname('a')), 0, 8);
   // encode script current dir, ex : /var/www/glpi_X
   $dirSubSha1    = substr(sha1(__FILE__), 0, 8);

   return uniqid("$serverSubSha1-$dirSubSha1-", true);
}

/**
 * Retrieve an item from the database
 *
 * @param $item instance of CommonDBTM object
 * @param $field field of object's table to search in
 * @param $value value to search in provided field
 *
 * @return true if succeed else false
 */
function plugin_formcreator_getFromDBByField(CommonDBTM $item, $field = '', $value = '') {
   global $DB;

   // != 0 because 0 is consider as empty
   if (!$item instanceof Entity
       && (strlen($value) == 0
           || $value === 0)) {
      return false;
   }

   $value = $DB->escape($value);
   $found = $item->getFromDBByRequest([
      'WHERE' => [$item::getTable() . '.' . $field => $value],
      'LIMIT' => 1
   ]);

   if ($found) {
      return $item->getID();
   } else {
      return false;
   }
}

/**
 * Autoloader
 * @param string $classname
 */
function plugin_formcreator_autoload($classname) {
   if (strpos($classname, 'PluginFormcreator') === 0) {
      // useful only for installer GLPi autoloader already handles inc/ folder
      $filename = __DIR__ . '/inc/' . strtolower(str_replace('PluginFormcreator', '', $classname)). '.class.php';
      if (is_readable($filename) && is_file($filename)) {
         include_once($filename);
         return true;
      }
   }
}

/**
 * Show the last SQL error, logs its backtrace and dies
 * @param Migration $migration
 */
function plugin_formcreator_upgrade_error(Migration $migration) {
   global $DB;

   $error = $DB->error();
   $migration->log($error . "\n" . Toolbox::backtrace(false, '', ['Toolbox::backtrace()']), false);
   die($error . "<br><br> Please, check migration log");
}

/**
 * Permanent hooks, must be set even when the plugin is disabled
 *
 * @return void
 */
function plugin_formcreator_permanent_hook(): void {
   global $PLUGIN_HOOKS;

   // Set the plugin CSRF compliance (required since GLPI 0.84)
   $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['formcreator'] = true;

   // Can assign FormAnswer to tickets
   $PLUGIN_HOOKS['assign_to_ticket']['formcreator'] = true;

   // hook to update issues when an operation occurs on a ticket
   $PLUGIN_HOOKS[Hooks::ITEM_ADD]['formcreator'] = [
      Ticket::class => 'plugin_formcreator_hook_add_ticket',
      ITILFollowup::class => 'plugin_formcreator_hook_update_itilFollowup',
      Ticket_User::class => 'plugin_formcreator_hook_update_ticket_actors',
   ];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['formcreator'] = [
      User::class  => 'plugin_formcreator_hook_update_user',
   ];
   $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['formcreator'] = [
      Ticket::class => 'plugin_formcreator_hook_update_ticket',
      TicketValidation::class => 'plugin_formcreator_hook_update_ticketvalidation',
      Profile::class => 'plugin_formcreator_hook_update_profile',
   ];
   $PLUGIN_HOOKS[Hooks::ITEM_DELETE]['formcreator'] = [
      Ticket::class => 'plugin_formcreator_hook_delete_ticket'
   ];
   $PLUGIN_HOOKS[Hooks::ITEM_RESTORE]['formcreator'] = [
      Ticket::class => 'plugin_formcreator_hook_restore_ticket'
   ];
   $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['formcreator'] = [
      Ticket::class => 'plugin_formcreator_hook_purge_ticket',
      TicketValidation::class => 'plugin_formcreator_hook_purge_ticketvalidation',
      Ticket_User::class => 'plugin_formcreator_hook_update_ticket_actors',
   ];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['formcreator'] = [
      PluginFormcreatorTargetTicket::class => 'plugin_formcreator_hook_pre_purge_targetTicket',
      PluginFormcreatorTargetChange::class => 'plugin_formcreator_hook_pre_purge_targetChange'
   ];
   // hook to add custom actions on a ticket in service catalog
   $PLUGIN_HOOKS[Hooks::TIMELINE_ACTIONS]['formcreator'] = 'plugin_formcreator_timelineActions';

   $PLUGIN_HOOKS[Hooks::ITEM_TRANSFER]['formcreator'] = 'plugin_formcreator_transfer';

}

/**
 * Hooks to run when the plugin is active
 *
 * @return void
 */
function plugin_formcreator_hook(): void {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   // Add specific CSS
   $PLUGIN_HOOKS[Hooks::ADD_CSS]['formcreator'][] = PluginFormcreatorCommon::getCssFilename();

   $PLUGIN_HOOKS[Hooks::PRE_SHOW_TAB]['formcreator'] = [
      PluginFormcreatorCommon::class, 'hookPreShowTab',
   ];
   $PLUGIN_HOOKS[Hooks::POST_SHOW_TAB]['formcreator'] = [
      PluginFormcreatorCommon::class, 'hookPostShowTab',
   ];

   // Load JS and CSS files if we are on a page which need them
   if (isset($_SERVER['REQUEST_URI'])) {
      if (strpos($_SERVER['REQUEST_URI'], 'formcreator') !== false
         || strpos($_SERVER['REQUEST_URI'], 'central.php') !== false
         || isset($_SESSION['glpiactiveprofile']) &&
            Session::getCurrentInterface() == 'helpdesk') {

         // Add specific JavaScript
         $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['formcreator'][] = 'js/scripts.js';
      }

      if (isset($_SESSION['glpiactiveentities_string'])) {
         if (strpos($_SERVER['REQUEST_URI'], 'helpdesk') !== false
               || strpos($_SERVER['REQUEST_URI'], 'central.php') !== false
               || strpos($_SERVER['REQUEST_URI'], 'formcreator/front/formlist.php') !== false
               || strpos($_SERVER['REQUEST_URI'], 'formcreator/front/wizard.php') !== false) {
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['formcreator'][] = 'lib/jquery-slinky/dist/slinky.min.js';
            $CFG_GLPI['javascript']['seek_assistance'][PluginFormcreatorForm::class] = [
               'dashboard',
               'gridstack'
            ];
         }
         if (strpos($_SERVER['REQUEST_URI'], 'issue.php') !== false) {
            $CFG_GLPI['javascript']['my_assistance_requests'][PluginFormcreatorIssue::class] = [
               'dashboard',
               'gridstack'
            ];
         }
         if (strpos($_SERVER['REQUEST_URI'], 'formdisplay.php') !== false) {
            $CFG_GLPI['javascript']['seek_assistance'][PluginFormcreatorForm::class] = [
               'dashboard',
               'gridstack'
            ];
         }
         if (strpos($_SERVER['REQUEST_URI'], 'knowbaseitem.php') !== false) {
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['formcreator'][] = 'lib/jquery-slinky/dist/slinky.min.js';
            $CFG_GLPI['javascript']['faq'][PluginFormcreatorForm::class] = [
               'dashboard',
               'gridstack'
            ];
         }
      }
   }

   $PLUGIN_HOOKS['dashboard_cards']['formcreator'] = 'plugin_formcreator_hook_dashboard_cards';

   if (Session::getLoginUserID() === false) {
      return;
   }

   $PLUGIN_HOOKS['menu_toadd']['formcreator']['helpdesk'] = PluginFormcreatorFormlist::class;
   $PLUGIN_HOOKS['menu_toadd']['formcreator']['my_assistance_requests'] = PluginFormcreatorIssue::class;

   // Massive Action definition
   $PLUGIN_HOOKS['use_massive_action']['formcreator'] = 1;

   // Add a link in the main menu plugins for technician and admin panel
   $PLUGIN_HOOKS['menu_entry']['formcreator'] = 'front/formlist.php';

   $PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['formcreator'] = [PluginFormcreatorCommon::class, 'hookRedefineMenu'];

   // Config page
   if (Session::haveRight('entity', UPDATE)) {
      $PLUGIN_HOOKS['menu_toadd']['formcreator']['admin'] = PluginFormcreatorForm::class;
   }
}

function plugin_formcreator_registerClasses() {
   // Load menu entries if user is logged in and if he has access to at least one form
   if (Session::getLoginUserID() !== false) {
      Plugin::registerClass(PluginFormcreatorEntityconfig::class, ['addtabon' => Entity::class]);
   }
   Plugin::registerClass(PluginFormcreatorForm::class, ['addtabon' => Central::class]);

   // Load field class and all its method to manage fields
   Plugin::registerClass(PluginFormcreatorFields::class);

   // Notification
   Plugin::registerClass(PluginFormcreatorFormAnswer::class, [
      'notificationtemplates_types' => true
   ]);

   Plugin::registerClass(PluginFormcreatorEntityconfig::class, ['addtabon' => Entity::class]);
}

function plugin_formcreator_redirect() {
   global $CFG_GLPI;

   if (!isset($_SERVER['REQUEST_URI']) || !isset($_SESSION['glpiactiveentities_string'])) {
      return;
   }

   // Redirect to helpdesk replacement
   if (strpos($_SERVER['REQUEST_URI'], "front/helpdesk.public.php") !== false) {
      if (!isset($_REQUEST['newprofile']) && !isset($_REQUEST['active_entity'])) {
         // Not changing profile or active entity
         if (Session::getCurrentInterface() !== false
               && isset($_SESSION['glpiactive_entity'])) {
            // Interface and active entity are set in session
            if (plugin_formcreator_replaceHelpdesk()) {
               switch (PluginFormcreatorEntityConfig::getUsedConfig('service_catalog_home', $_SESSION['glpiactive_entity'])) {
                  default:
                  case PluginFormcreatorEntityConfig::CONFIG_SERVICE_CATALOG_HOME_SEARCH:
                     $homepage = '/front/wizard.php';
                     break;

                  case PluginFormcreatorEntityConfig::CONFIG_SERVICE_CATALOG_HOME_ISSUE:
                     $homepage = '/front/issue.php';
                     break;
               }
               Html::redirect(Plugin::getWebDir('formcreator') . $homepage);
            }
         }
      }
   }

   if (Session::getCurrentInterface() != 'helpdesk') {
      return;
   }

   if (!plugin_formcreator_replaceHelpdesk()) {
      return;
   }

   if (strpos($_SERVER['REQUEST_URI'], "front/ticket.form.php") !== false) {
      if (isset($_POST['update']) || isset($_POST['delete'])) {
         return;
      }

      $decodedUrl = [];
      $openItilFollowup = '';
      if (isset($_GET['_openfollowup'])) {
         $openItilFollowup = '&_openfollowup=1';
      }
      if (isset($_SERVER['QUERY_STRING'])) {
         parse_str($_SERVER['QUERY_STRING'], $decodedUrl);
         if (isset($decodedUrl['forcetab'])) {
            Session::setActiveTab(Ticket::class, $decodedUrl['forcetab']);
         }
      }
      if (!isset($_GET['id'])) {
         // invalid url
         Html::redirect($CFG_GLPI["root_doc"]);
      }

      // When an ticket has a matching issue (it means that the ticket is the only generated ticket)
      $issue = new PluginFormcreatorIssue();
      $issues = $issue->find([
         'itemtype' => Ticket::class,
         'items_id'  => (int) $_GET['id']
      ]);
      if (count($issues) == 1) {
         $issueId = array_pop($issues)['id'];
         $issue->getFromDB($issueId);
         Html::redirect($issue->getFormURLWithID($issue->getID()) . $openItilFollowup);
      }

      // When no or several tickets matches an issue, rely use the Form Answer
      $itemTicket = new Item_Ticket();
      $itemTicket->getFromDBByCrit([
         'itemtype' => PluginFormcreatorFormAnswer::class,
         'tickets_id'  => (int) $_GET['id']
      ]);
      if ($itemTicket->isNewItem()) {
         // No formanswer found
         Html::displayNotFoundError();
      }

      $issue->getFromDBByCrit([
         'itemtype' => PluginFormcreatorFormAnswer::class,
         'items_id'  => $itemTicket->fields['items_id']
      ]);
      if ($issue->isNewItem()) {
         // No formanswer found
         Html::displayNotFoundError();
      }
      $ticket = Ticket::getById($itemTicket->fields['tickets_id']);
      if ($ticket === false) {
         Html::redirect($issue->getFormURLWithID($itemTicket->fields['items_id']) . $openItilFollowup);
      }

      Html::redirect($issue->getFormURLWithID($issue->getID()) . '&tickets_id=' . $itemTicket->fields['tickets_id']);
   }

   $pages = [
      'front/reservationitem.php' => FORMCREATOR_ROOTDOC . '/front/reservationitem.php',
      // 'front/helpdesk.faq.php' => FORMCREATOR_ROOTDOC . '/front/wizard.php',
      'front/ticket.php' => FORMCREATOR_ROOTDOC . '/front/issue.php',
   ];
   foreach ($pages as $srcPage => $dstPage) {
      if (strpos($_SERVER['REQUEST_URI'], $srcPage) !== false && strpos($_SERVER['REQUEST_URI'], $dstPage) === false) {
         if ($srcPage == 'front/reservationitem.php') {
            $_SESSION['plugin_formcreator']['redirected']['POST'] = $_POST;
         }
         Html::redirect($dstPage);
         break;
      }
   }
}

function plugin_formcreator_options() {
   return [
      Plugin::OPTION_AUTOINSTALL_DISABLED => true,
   ];
}

/**
 * Get the path to the empty SQL schema file
 *
 * @return string|null
 */
function plugin_formcreator_getSchemaPath(string $version = null): ?string {
   if ($version === null) {
      $version = PLUGIN_FORMCREATOR_VERSION;
   }

   // Drop suffixes for alpha, beta, rc versions
   $matches = [];
   preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches);
   $version = $matches[1];

   return Plugin::getPhpDir('formcreator') . "/install/mysql/plugin_formcreator_{$version}_empty.sql";
}

/**
 * Detect a versin change and save the previous version in the DB
 *
 * Used to proceed a DB sanity check before an upgrade
 * @see PluginFormcreatorInstall::upgrade
 * @see PluginFormcreatorInstall::checkSchema
 *
 * @return void
 */
function plugin_formcreator_savePreviousVersion(): void {
   $plugin = new Plugin();
   $plugin->getFromDBbyDir('formcreator');
   $oldVersion = $plugin->fields['version'] ?? null;
   if ($oldVersion !== null && $oldVersion != PLUGIN_FORMCREATOR_VERSION) {
      Config::setConfigurationValues('formcreator', [
         'previous_version' => $oldVersion,
      ]);
   }
}
