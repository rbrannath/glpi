<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace tests\units;

use DbTestCase;

/* Test for inc/reminder.class.php */

class Reminder extends DbTestCase
{
    public function testAddVisibilityRestrict()
    {
         //first, as a super-admin
         $this->login();
         $users_id = \Session::getLoginUserID();
         $expected = preg_replace('/\s+/', ' ', "(`glpi_reminders`.`users_id` = '$users_id'
               OR `glpi_reminders_users`.`users_id` = '$users_id'
               OR (`glpi_profiles_reminders`.`profiles_id` = '4'
                    AND (`glpi_profiles_reminders`.`no_entity_restriction` = '1'
                         OR ((`glpi_profiles_reminders`.`entities_id` IN ('2', '3', '4')
                                   OR (`glpi_profiles_reminders`.`is_recursive` = '1'
                                        AND `glpi_profiles_reminders`.`entities_id` IN ('0'))))))
               OR ((`glpi_entities_reminders`.`entities_id` IN ('2', '3', '4')
                         OR (`glpi_entities_reminders`.`is_recursive` = '1'
                              AND `glpi_entities_reminders`.`entities_id` IN ('0')))))");
         $this->string(\Reminder::addVisibilityRestrict())
              ->isIdenticalTo($expected);

         $this->login('normal', 'normal');
         $expected = preg_replace('/\s+/', ' ', "(`glpi_reminders`.`users_id` = '5'
               OR `glpi_reminders_users`.`users_id` = '5'
               OR (`glpi_profiles_reminders`.`profiles_id` = '2'
                    AND (`glpi_profiles_reminders`.`no_entity_restriction` = '1'
                         OR (`glpi_profiles_reminders`.`entities_id` IN ('0', '2', '1', '3', '4'))))
               OR (`glpi_entities_reminders`.`entities_id` IN ('0', '2', '1', '3', '4')))");
         $this->string(trim(preg_replace('/\s+/', ' ', \Reminder::addVisibilityRestrict())))
              ->isIdenticalTo($expected);

         $this->login('tech', 'tech');
         $expected = preg_replace('/\s+/', ' ', "(`glpi_reminders`.`users_id` = '4'
               OR `glpi_reminders_users`.`users_id` = '4'
               OR (`glpi_profiles_reminders`.`profiles_id` = '6'
                    AND (`glpi_profiles_reminders`.`no_entity_restriction` = '1'
                         OR (`glpi_profiles_reminders`.`entities_id` IN ('0', '2', '1', '3', '4'))))
               OR (`glpi_entities_reminders`.`entities_id` IN ('0', '2', '1', '3', '4')))");
         $this->string(trim(preg_replace('/\s+/', ' ', \Reminder::addVisibilityRestrict())))
              ->isIdenticalTo($expected);

         $bkp_groups = $_SESSION['glpigroups'];
         $_SESSION['glpigroups'] = [42, 1337];
         $str = \Reminder::addVisibilityRestrict();
         $_SESSION['glpigroups'] = $bkp_groups;
         $expected = preg_replace('/\s+/', ' ', "(`glpi_reminders`.`users_id` = '4'
               OR `glpi_reminders_users`.`users_id` = '4'
               OR (`glpi_groups_reminders`.`groups_id` IN ('42', '1337')
                    AND (`glpi_groups_reminders`.`no_entity_restriction` = '1'
                         OR (`glpi_groups_reminders`.`entities_id` IN ('0', '2', '1', '3', '4')))) 
               OR (`glpi_profiles_reminders`.`profiles_id` = '6'
                    AND (`glpi_profiles_reminders`.`no_entity_restriction` = '1'
                         OR (`glpi_profiles_reminders`.`entities_id` IN ('0', '2', '1', '3', '4'))))
               OR (`glpi_entities_reminders`.`entities_id` IN ('0', '2', '1', '3', '4')))");
         $this->string(trim(preg_replace('/\s+/', ' ', $str)))
              ->isIdenticalTo($expected);
    }
}
