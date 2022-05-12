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
class PluginFormcreatorUpgradeTo2_14 {
   /** @var Migration */
   protected $migration;

   /**
    * @param Migration $migration
    */
   public function upgrade(Migration $migration) {
       $this->migration = $migration;

       $this->addTtoToIssues();
       $this->addRights();
   }

   public function addTtoToIssues() {
        $table = (new DBUtils())->getTableForItemType(PluginFormcreatorIssue::class);
        $this->migration->addField($table, 'time_to_own', 'timestamp', ['after' => 'users_id_recipient']);
        $this->migration->addField($table, 'time_to_resolve', 'timestamp', ['after' => 'time_to_own']);
        $this->migration->addField($table, 'internal_time_to_own', 'timestamp', ['after' => 'time_to_resolve']);
        $this->migration->addField($table, 'internal_time_to_resolve', 'timestamp', ['after' => 'internal_time_to_own']);
        $this->migration->addField($table, 'solvedate', 'timestamp', ['after' => 'internal_time_to_resolve']);
        $this->migration->addField($table, 'date', 'timestamp', ['after' => 'solvedate']);
        $this->migration->addField($table, 'takeintoaccount_delay_stat', 'int', ['after' => 'date']);

        $this->migration->addKey($table, 'time_to_own');
        $this->migration->addKey($table, 'time_to_resolve');
        $this->migration->addKey($table, 'internal_time_to_own');
        $this->migration->addKey($table, 'internal_time_to_resolve');
        $this->migration->addKey($table, 'solvedate');
        $this->migration->addKey($table, 'date');
   }

   public function addRights() {
      // Add rights
      global $DB;
      $profiles = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => Profile::getTable(),
      ]);
      foreach ($profiles as $profile) {
         $rights = ProfileRight::getProfileRights(
            $profile['id'],
            [
               Entity::$rightname,
               PluginFormcreatorForm::$rightname,
            ]
         );
         if (($rights[Entity::$rightname] & (UPDATE + CREATE + DELETE + PURGE)) == 0) {
            continue;
         }
         $right = READ + UPDATE + CREATE + DELETE + PURGE;
         ProfileRight::updateProfileRights($profile['id'], [
            PluginFormcreatorForm::$rightname => $right,
         ]);
      }
   }
}
