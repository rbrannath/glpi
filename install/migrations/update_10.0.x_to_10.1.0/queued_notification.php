<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
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

/**
 * @var \Migration $migration
 */

// Register crontask
CronTask::register('QueuedNotification', 'queuednotificationcleanstaleajax', DAY_TIMESTAMP, [
    'state'     => CronTask::STATE_DISABLE,
    'mode'      => CronTask::MODE_INTERNAL,
]);

$current_config = Config::getConfigurationValues('core');
if (!isset($current_config['notifications_ajax_expiration_delay'])) {
    $migration->addConfig([
        'notifications_ajax_expiration_delay' => '30'
    ]);
}
