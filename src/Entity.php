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

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\Event;
use Glpi\Plugin\Hooks;

/**
 * Entity class
 */
class Entity extends CommonTreeDropdown
{
    use Glpi\Features\Clonable;
    use MapGeolocation;

    public $must_be_replace             = true;
    public $dohistory                   = true;

    public $first_level_menu            = "admin";
    public $second_level_menu           = "entity";

    public static $rightname            = 'entity';
    protected $usenotepad               = true;

    public const READHELPDESK       = 1024;
    public const UPDATEHELPDESK     = 2048;

    /** @var int Value dynamically determined */
    public const CONFIG_AUTO                    = -1;
    /** @var int Value inherited from the parent entity */
    public const CONFIG_PARENT                  = -2;
    /** @var int Never */
    public const CONFIG_NEVER                   = -10;

    /** @var int Automatically assign technician based on the item and then the category */
    public const AUTO_ASSIGN_HARDWARE_CATEGORY  = 1;
    /** @var int Automatically assign technician based on the category and then the hardware */
    public const AUTO_ASSIGN_CATEGORY_HARDWARE  = 2;

    // Possible values for "anonymize_support_agents" setting
    /** @var int Support agents not anonymized */
    public const ANONYMIZE_DISABLED            = 0;
    /** @var int Replace the agent and group name with a generic name */
    public const ANONYMIZE_USE_GENERIC         = 1;
    /** @var int Replace the agent and group name with a customisable nickname */
    public const ANONYMIZE_USE_NICKNAME        = 2;
    /** @var int Replace the agent's name with a generic name */
    public const ANONYMIZE_USE_GENERIC_USER    = 3;
    /** @var int Replace the agent's name with a customisable nickname */
    public const ANONYMIZE_USE_NICKNAME_USER   = 4;
    /** @var int Replace the group's name with a generic name */
    public const ANONYMIZE_USE_GENERIC_GROUP   = 5;

    // Array of "right required to update" => array of fields allowed
    // Missing field here couldn't be update (no right)
    private static $field_right = [
        'entity' => [
            // Address
            'address', 'country', 'email', 'fax', 'notepad',
            'longitude','latitude','altitude',
            'phonenumber', 'postcode', 'state', 'town',
            'website', 'registration_number',
            // Advanced (could be user_authtype ?)
            'authldaps_id', 'entity_ldapfilter', 'ldap_dn',
            'mail_domain', 'tag',
            // Inventory
            'entities_strategy_software', 'entities_id_software', 'level', 'name',
            'completename', 'entities_id',
            'ancestors_cache', 'sons_cache', 'comment', 'transfers_strategy', 'transfers_id',
            'agent_base_url', '2fa_enforcement_strategy',
            // Automatically update of the elements related to the computers
            'is_contact_autoupdate', 'is_user_autoupdate', 'is_group_autoupdate', 'is_location_autoupdate', 'state_autoupdate_mode',
            'is_contact_autoclean', 'is_user_autoclean', 'is_group_autoclean', 'is_location_autoclean', 'state_autoclean_mode',
        ],
        // Inventory
        'infocom' => [
            'autofill_buy_date', 'autofill_delivery_date',
            'autofill_order_date', 'autofill_use_date',
            'autofill_warranty_date',
            'autofill_decommission_date'
        ],
        // Notification
        'notification' => [
            'admin_email', 'replyto_email', 'from_email',
            'admin_email_name', 'replyto_email_name', 'from_email_name',
            'noreply_email_name','noreply_email',
            'delay_send_emails',
            'is_notif_enable_default',
            'default_cartridges_alarm_threshold',
            'default_consumables_alarm_threshold',
            'default_contract_alert', 'default_infocom_alert',
            'mailing_signature', 'url_base', 'cartridges_alert_repeat',
            'consumables_alert_repeat', 'notclosed_delay',
            'use_licenses_alert', 'use_certificates_alert',
            'send_licenses_alert_before_delay',
            'send_certificates_alert_before_delay',
            'certificates_alert_repeat_interval',
            'use_contracts_alert',
            'send_contracts_alert_before_delay',
            'use_reservations_alert', 'use_infocoms_alert',
            'send_infocoms_alert_before_delay',
            'notification_subject_tag', 'use_domains_alert',
            'send_domains_alert_close_expiries_delay', 'send_domains_alert_expired_delay',
            'approval_reminder_repeat_interval',
        ],
        // Helpdesk
        'entity_helpdesk' => [
            'calendars_strategy', 'calendars_id', 'tickettype', 'auto_assign_mode',
            'autoclose_delay',
            'inquest_config', 'inquest_rate', 'inquest_delay', 'inquest_duration',
            'inquest_URL', 'inquest_max_rate', 'inquest_default_rate',
            'inquest_mandatory_comment', 'max_closedate',
            'inquest_config_change', 'inquest_rate_change', 'inquest_delay_change', 'inquest_duration_change',
            'inquest_URL_change', 'inquest_max_rate_change', 'inquest_default_rate_change',
            'inquest_mandatory_comment_change', 'max_closedate_change',
            'tickettemplates_strategy', 'tickettemplates_id',
            'changetemplates_strategy', 'changetemplates_id',
            'problemtemplates_strategy', 'problemtemplates_id',
            'suppliers_as_private', 'autopurge_delay', 'anonymize_support_agents', 'display_users_initials',
            'contracts_strategy_default', 'contracts_id_default'
        ],
        // Configuration
        'config' => ['enable_custom_css', 'custom_css_code']
    ];

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'delete';
        $forbidden[] = 'purge';
        $forbidden[] = 'restore';
        $forbidden[] = 'CommonDropdown' . MassiveAction::CLASS_ACTION_SEPARATOR . 'merge';
        return $forbidden;
    }

    public function getCloneRelations(): array
    {
        return [];
    }

    public function pre_updateInDB()
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (($key = array_search('name', $this->updates, true)) !== false) {
            // Check if entity does not exist
            $iterator = $DB->request([
                'FROM' => static::getTable(),
                'WHERE' => [
                    'name' => $this->input['name'],
                    'entities_id' => $this->input['entities_id'],
                    'id' => ['<>', $this->input['id']]
                ]
            ]);

            if (count($iterator)) {
                //To display a message
                $this->fields['name'] = $this->oldvalues['name'];
                unset($this->updates[$key], $this->oldvalues['name']);
                Session::addMessageAfterRedirect(
                    __s('An entity with that name already exists at the same level.'),
                    false,
                    ERROR
                );
            }
        }
    }

    public function pre_deleteItem()
    {
        /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
        global $GLPI_CACHE;

        // Security do not delete root entity
        if ($this->getID() === 0) {
            return false;
        }

        // Security do not delete entity with children
        if (countElementsInTable(static::getTable(), ['entities_id' => $this->input['id']])) {
            Session::addMessageAfterRedirect(
                __s('You cannot delete an entity which contains sub-entities.'),
                false,
                ERROR
            );
            return false;
        }

        // Cleaning sons calls getAncestorsOf and thus... Re-create cache. Call it before clean.
        $this->cleanParentsSons();
        $ckey = 'ancestors_cache_' . static::getTable() . '_' . $this->getID();
        $GLPI_CACHE->delete($ckey);

        return true;
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Entity', 'Entities', $nb);
    }

    public static function canCreate(): bool
    {
        // Do not show the create button if no recusive access on current entity
        return parent::canCreate() && Session::haveRecursiveAccessToEntity(Session::getActiveEntity());
    }

    public function canCreateItem(): bool
    {
        // Check the parent
        return Session::haveRecursiveAccessToEntity($this->getField('entities_id'));
    }

    public static function canUpdate(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [UPDATE, self::UPDATEHELPDESK])
              || Session::haveRight('notification', UPDATE));
    }

    public function canUpdateItem(): bool
    {
        // Check the current entity
        return Session::haveAccessToEntity($this->getField('id'));
    }

    public function canViewItem(): bool
    {
        // Check the current entity
        return Session::haveAccessToEntity($this->getField('id'));
    }

    public static function isNewID($ID): bool
    {
        return (($ID < 0) || $ID === '');
    }

    public function maybeLocated()
    {
        return true;
    }

    /**
     * Check right on each field before add / update
     *
     * @since 0.84 (before in entitydata.class)
     *
     * @param array $input
     *
     * @return array (filtered input)
     **/
    private function checkRightDatas($input): array
    {
        $tmp = [];

        if (isset($input['id'])) {
            $tmp['id'] = $input['id'];
        }

        foreach (self::$field_right as $right => $fields) {
            if ($right === 'entity_helpdesk') {
                if (Session::haveRight(self::$rightname, self::UPDATEHELPDESK)) {
                    foreach ($fields as $field) {
                        if (isset($input[$field])) {
                             $tmp[$field] = $input[$field];
                        }
                    }
                }
            } else {
                if (Session::haveRight($right, UPDATE)) {
                    foreach ($fields as $field) {
                        if (isset($input[$field])) {
                            $tmp[$field] = $input[$field];
                        }
                    }
                }
            }
        }
       // Add framework  / internal ones
        foreach ($input as $key => $val) {
            if ($key[0] === '_') {
                $tmp[$key] = $input[$key];
            }
        }

        return $tmp;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     **/
    public function prepareInputForAdd($input)
    {
        /** @var \DBmysql $DB */
        global $DB;
        $input['name'] = isset($input['name']) ? trim($input['name']) : '';
        if (empty($input["name"])) {
            Session::addMessageAfterRedirect(
                __s("You can't add an entity without name"),
                false,
                ERROR
            );
            return false;
        }

        $input = parent::prepareInputForAdd($input);
        if ($input === false) {
            return false;
        }

        $input = $this->handleConfigStrategyFields($input);

        $result = $DB->request([
            'SELECT' => [
                new QueryExpression(QueryFunction::max('id') . '+1', 'newID')
            ],
            'FROM'   => static::getTable()
        ])->current();
        $input['id'] = $result['newID'];

        $input['max_closedate'] = $_SESSION["glpi_currenttime"];

        if (
            empty($input['latitude']) && empty($input['longitude']) && empty($input['altitude']) &&
            !empty($input[static::getForeignKeyField()])
        ) {
            $parent = new static();
            $parent->getFromDB($input[static::getForeignKeyField()]);
            $input['latitude'] = $parent->fields['latitude'];
            $input['longitude'] = $parent->fields['longitude'];
            $input['altitude'] = $parent->fields['altitude'];
        }

        if (!Session::isCron()) { // Filter input for connected
            $input = $this->checkRightDatas($input);
        }
        return $input;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     **/
    public function prepareInputForUpdate($input)
    {
        // Force entities_id = NULL for root entity
        if ((int) $input['id'] === 0) {
            $input['entities_id'] = null;
            $input['level']       = 1;
        }

        $input = parent::prepareInputForUpdate($input);
        if ($input === false) {
            return false;
        }

        $input = $this->handleConfigStrategyFields($input);
        $input = $this->handleSatisfactionSurveyConfigOnUpdate($input);

        if (!Session::isCron()) { // Filter input for connected
            $input = $this->checkRightDatas($input);
        }

        return $input;
    }

    /**
     * Handle foreign key config fields splitting between "id" and "strategy" fields.
     *
     * @param array $input
     *
     * @return array
     */
    private function handleConfigStrategyFields(array $input): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        foreach ($input as $field => $value) {
            $strategy_field = str_replace('_id', '_strategy', $field);
            if (preg_match('/_id(_.+)?/', $field) === 1 && $DB->fieldExists(static::getTable(), $strategy_field)) {
                if ($value > 0 || ((int) $value === 0 && preg_match('/^entities_id(_\w+)?/', $field) === 1)) {
                    // Value contains a valid id -> set strategy to 0 (prevent inheritance).
                    $input[$strategy_field] = 0;
                } elseif ($value < 0) {
                    // Value is negative -> move it into strategy field.
                    $input[$field] = 0;
                    $input[$strategy_field] = $value;
                }
            }
        }

        return $input;
    }

    /**
     * Handle satisfaction survey configuration on update.
     *
     * @param array $input
     *
     * @return array
     */
    private function handleSatisfactionSurveyConfigOnUpdate(array $input): array
    {
        foreach (['', '_change'] as $suffix) {
            $config_key = 'inquest_config' . $suffix;
            $rate_key   = 'inquest_rate' . $suffix;
            $max_key    = 'max_closedate' . $suffix;

            // If inquest config or rate change, defines the `max_closedate` config to ensure that new configuration
            // will only be applied to tickets that are not already solved.
            if (
                (
                    isset($input[$rate_key])
                    && ($this->fields[$rate_key] == 0 || is_null($this->fields[$max_key]))
                    && $input[$rate_key] != $this->fields[$rate_key]
                )
                || (
                    isset($input[$config_key])
                    && ($this->fields[$config_key] == self::CONFIG_PARENT || is_null($this->fields[$max_key]))
                    && $input[$config_key] != $this->fields[$config_key]
                )
            ) {
                $input[$max_key] = $_SESSION['glpi_currenttime'];
            }

            // `max_closedate` cannot be empty (cron would not work).
            if (array_key_exists($max_key, $input) && empty($input[$max_key])) {
                unset($input[$max_key]);
            }
        }

        return $input;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addImpactTab($ong, $options);
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab('Profile_User', $ong, $options);
        $this->addStandardTab('Rule', $ong, $options);
        $this->addStandardTab('Document_Item', $ong, $options);
        $this->addStandardTab('Notepad', $ong, $options);
        $this->addStandardTab('KnowbaseItem_Item', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            switch ($item::class) {
                case self::class:
                    $ong    = [];
                    $ong[1] = self::createTabEntry(self::getTypeName(Session::getPluralNumber()));
                    $ong[2] = self::createTabEntry(__('Address'), 0, $item::class, Location::getIcon());
                    $ong[3] = self::createTabEntry(__('Advanced information'));
                    if (Notification::canView()) {
                        $ong[4] = self::createTabEntry(Notification::getTypeName(Session::getPluralNumber()), 0, $item::class, Notification::getIcon());
                    }
                    if (
                        Session::haveRightsOr(
                            self::$rightname,
                            [self::READHELPDESK, self::UPDATEHELPDESK]
                        )
                    ) {
                        $ong[5] = self::createTabEntry(__('Assistance'), 0, $item::class, 'ti ti-headset');
                    }
                    $ong[6] = self::createTabEntry(_n('Asset', 'Assets', Session::getPluralNumber()), 0, $item::class, 'ti ti-package');
                    if (Session::haveRight(Config::$rightname, UPDATE)) {
                        $ong[7] = self::createTabEntry(__('UI customization'), 0, $item::class, 'ti ti-palette');
                    }
                    $ong[8] = self::createTabEntry(__('Security'), 0, $item::class, 'ti ti-shield-lock');

                    return $ong;
            }
        }
        return '';
    }

    /**
     * @since 0.84 (before in entitydata.class)
     **/
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item::class === self::class) {
            switch ($tabnum) {
                case 1:
                    $item->showChildren();
                    break;

                case 2:
                    self::showStandardOptions($item);
                    break;

                case 3:
                    self::showAdvancedOptions($item);
                    break;

                case 4:
                    self::showNotificationOptions($item);
                    break;

                case 5:
                    self::showHelpdeskOptions($item);
                    break;

                case 6:
                    self::showInventoryOptions($item);
                    break;

                case 7:
                    self::showUiCustomizationOptions($item);
                    break;
                case 8:
                    self::showSecurityOptions($item);
                    break;
            }
        }
        return true;
    }

    /**
     * Print a good title for entity pages
     *
     *@return void
     **/
    public function title()
    {
       // Empty title for entities
    }

    /**
     * Get the ID of entity assigned to the object
     *
     * simply return ID
     *
     * @return integer ID of the entity
     **/
    public function getEntityID()
    {
        return $this->fields["id"] ?? -1;
    }

    public function isEntityAssign()
    {
        return true;
    }

    public function maybeRecursive()
    {
        return true;
    }

    public function isRecursive()
    {
        return true;
    }

    public function post_getFromDB()
    {
        // Copy config "strategy" fields in corresponding "id" field
        // when "strategy" is < 0.
        foreach ($this->fields as $field_key => $value) {
            if (preg_match('/_strategy(_.+)?/', $field_key) === 1 && $value < 0) {
                $id_field_key = str_replace('_strategy', '_id', $field_key);
                $this->fields[$id_field_key] = $this->fields[$field_key];
            }
        }
    }

    public function post_addItem()
    {
        parent::post_addItem();

       // Add right to current user - Hack to avoid login/logout
        $_SESSION['glpiactiveentities'][$this->fields['id']] = $this->fields['id'];
        $_SESSION['glpiactiveentities_string']              .= ",'" . $this->fields['id'] . "'";
        // Root entity cannot be deleted, so if we added an entity this means GLPI is now multi-entity
        $_SESSION['glpi_multientitiesmode'] = 1;
    }

    public function post_updateItem($history = true)
    {
        /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
        global $GLPI_CACHE;

        parent::post_updateItem($history);

        // Delete any cache entry corresponding to an updated entity config
        // for current entities and all its children
        $entities_ids = array_merge([$this->fields['id']], getSonsOf(self::getTable(), $this->fields['id']));
        $ignored_fields = [
            'name',
            'completename',
            'entities_id',
            'level',
            'sons_cache',
            'ancestors_cache',
            'date_mod',
            'date_creation',
        ];
        $cache_entries = [];
        foreach ($this->updates as $field) {
            if (in_array($field, $ignored_fields, true)) {
                continue; // Ignore fields that cannot be used as config inheritance logic
            }
            foreach ($entities_ids as $entity_id) {
                $cache_entries[] = sprintf('entity_%d_config_%s', $entity_id, $field);
            }
        }
        $GLPI_CACHE->deleteMultiple($cache_entries);
    }

    public function cleanDBonPurge()
    {
        // most use entities_id, RuleDictionnarySoftwareCollection use new_entities_id
        Rule::cleanForItemAction($this, '%entities_id');
        Rule::cleanForItemCriteria($this);

        $pu = new Profile_User();
        $pu->deleteByCriteria(['entities_id' => $this->fields['id']]);

        $this->deleteChildrenAndRelationsFromDb(
            [
                Entity_KnowbaseItem::class,
                Entity_Reminder::class,
                Entity_RSSFeed::class,
            ]
        );
    }

    /**
     * Clean caches related to entity selector.
     *
     * @since 10.0
     *
     * @return void
     * @deprecated 10.0.12
     */
    public function cleanEntitySelectorCache()
    {
        Toolbox::deprecated('`Entity::cleanEntitySelectorCache()` no longer has any effect as the entity selector is no longer cached as a unique entry');
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics')
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => static::getTable(),
            'field'              => 'completename',
            'name'               => __('Complete name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '14',
            'table'              => static::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'address',
            'name'               => __('Address'),
            'massiveaction'      => false,
            'datatype'           => 'text'
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'website',
            'name'               => __('Website'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'phonenumber',
            'name'               => Phone::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'email',
            'name'               => _n('Email', 'Emails', 1),
            'datatype'           => 'email',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '10',
            'table'              => static::getTable(),
            'field'              => 'fax',
            'name'               => __('Fax'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '25',
            'table'              => static::getTable(),
            'field'              => 'postcode',
            'name'               => __('Postal code'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'town',
            'name'               => __('City'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => static::getTable(),
            'field'              => 'state',
            'name'               => _x('location', 'State'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => static::getTable(),
            'field'              => 'country',
            'name'               => __('Country'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '67',
            'table'              => static::getTable(),
            'field'              => 'latitude',
            'name'               => __('Latitude'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '68',
            'table'              => static::getTable(),
            'field'              => 'longitude',
            'name'               => __('Longitude'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '69',
            'table'              => static::getTable(),
            'field'              => 'altitude',
            'name'               => __('Altitude'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => __('Comments'),
            'datatype'           => 'text'
        ];

        $tab[] = [
            'id'                 => '122',
            'table'              => static::getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '121',
            'table'              => static::getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '70',
            'table'              => static::getTable(),
            'field'              => 'registration_number',
            'name'               => _x('infocom', 'Administrative number'),
            'datatype'           => 'string',
            'autocomplete'       => true
        ];

        // add objectlock search options
        $tab = array_merge($tab, ObjectLock::rawSearchOptionsToAdd(static::class));

        $tab = array_merge($tab, Notepad::rawSearchOptionsToAdd());

        $tab[] = [
            'id'                 => 'advanced',
            'name'               => __('Advanced information')
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => static::getTable(),
            'field'              => 'ldap_dn',
            'name'               => __('LDAP directory information attribute representing the entity'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => static::getTable(),
            'field'              => 'tag',
            'name'               => __('Information in inventory tool (TAG) representing the entity'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => 'glpi_authldaps',
            'field'              => 'name',
            'name'               => __('LDAP directory of an entity'),
            'massiveaction'      => false,
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '17',
            'table'              => static::getTable(),
            'field'              => 'entity_ldapfilter',
            'name'               => __('Search filter (if needed)'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => static::getTable(),
            'field'              => 'mail_domain',
            'name'               => __('Mail domain'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => 'notif',
            'name'               => __('Notification options')
        ];

        $tab[] = [
            'id'                 => '60',
            'table'              => static::getTable(),
            'field'              => 'delay_send_emails',
            'name'               => __('Delay to send email notifications'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'number',
            'min'                => 0,
            'max'                => 60,
            'step'               => 1,
            'unit'               => 'minute',
            'toadd'              => [self::CONFIG_PARENT => __('Inheritance of the parent entity')]
        ];

        $tab[] = [
            'id'                 => '61',
            'table'              => static::getTable(),
            'field'              => 'is_notif_enable_default',
            'name'               => __('Enable notifications by default'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'string'
        ];

        $tab[] = [
            'id'                 => '18',
            'table'              => static::getTable(),
            'field'              => 'admin_email',
            'name'               => __('Administrator email address'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => static::getTable(),
            'field'              => 'replyto_email',
            'name'               => __('Reply-To address'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '62',
            'table'              => static::getTable(),
            'field'              => 'from_email',
            'name'               => __('Email sender address'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '63',
            'table'              => static::getTable(),
            'field'              => 'noreply_email',
            'name'               => __('No-Reply address'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '21',
            'table'              => static::getTable(),
            'field'              => 'notification_subject_tag',
            'name'               => __('Prefix for notifications'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '22',
            'table'              => static::getTable(),
            'field'              => 'admin_email_name',
            'name'               => __('Administrator name'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '23',
            'table'              => static::getTable(),
            'field'              => 'replyto_email_name',
            'name'               => __('Reply-To name'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '64',
            'table'              => static::getTable(),
            'field'              => 'from_email_name',
            'name'               => __('Email sender name'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '65',
            'table'              => static::getTable(),
            'field'              => 'noreply_email_name',
            'name'               => __('No-Reply name'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '24',
            'table'              => static::getTable(),
            'field'              => 'mailing_signature',
            'name'               => __('Email signature'),
            'datatype'           => 'text'
        ];
        $tab[] = [
            'id'                 => '76',
            'table'              => static::getTable(),
            'field'              => 'url_base',
            'name'               => __('URL of the application'),
            'datatype'           => 'text'
        ];

        $tab[] = [
            'id'                 => '26',
            'table'              => static::getTable(),
            'field'              => 'cartridges_alert_repeat',
            'name'               => __('Alarms on cartridges'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '27',
            'table'              => static::getTable(),
            'field'              => 'consumables_alert_repeat',
            'name'               => __('Alarms on consumables'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '29',
            'table'              => static::getTable(),
            'field'              => 'use_licenses_alert',
            'name'               => __('Alarms on expired licenses'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '53',
            'table'              => static::getTable(),
            'field'              => 'send_licenses_alert_before_delay',
            'name'               => __('Send license alarms before'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '30',
            'table'              => static::getTable(),
            'field'              => 'use_contracts_alert',
            'name'               => __('Alarms on contracts'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '54',
            'table'              => static::getTable(),
            'field'              => 'send_contracts_alert_before_delay',
            'name'               => __('Send contract alarms before'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '31',
            'table'              => static::getTable(),
            'field'              => 'use_infocoms_alert',
            'name'               => __('Alarms on financial and administrative information'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '55',
            'table'              => static::getTable(),
            'field'              => 'send_infocoms_alert_before_delay',
            'name'               => __('Send financial and administrative information alarms before'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '32',
            'table'              => static::getTable(),
            'field'              => 'use_reservations_alert',
            'name'               => __('Alerts on reservations'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '48',
            'table'              => static::getTable(),
            'field'              => 'default_contract_alert',
            'name'               => __('Default value for alarms on contracts'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '49',
            'table'              => static::getTable(),
            'field'              => 'default_infocom_alert',
            'name'               => __('Default value for alarms on financial and administrative information'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '50',
            'table'              => static::getTable(),
            'field'              => 'default_cartridges_alarm_threshold',
            'name'               => __('Default threshold for cartridges count'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '52',
            'table'              => static::getTable(),
            'field'              => 'default_consumables_alarm_threshold',
            'name'               => __('Default threshold for consumables count'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '57',
            'table'              => static::getTable(),
            'field'              => 'use_certificates_alert',
            'name'               => __('Alarms on expired certificates'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '58',
            'table'              => static::getTable(),
            'field'              => 'send_certificates_alert_before_delay',
            'name'               => __('Send Certificate alarms before'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => 'helpdesk',
            'name'               => __('Assistance')
        ];

        $tab[] = [
            'id'                 => '47',
            'table'              => static::getTable(),
            'field'              => 'tickettemplates_id', // not a dropdown because of special value
            'name'               => _n('Ticket template', 'Ticket templates', 1),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific',
            'additionalfields'   => ['tickettemplates_strategy']
        ];

        $tab[] = [
            'id'                 => '33',
            'table'              => static::getTable(),
            'field'              => 'autoclose_delay',
            'name'               => __('Automatic closing of solved tickets after'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'number',
            'min'                => 1,
            'max'                => 99,
            'step'               => 1,
            'unit'               => 'day',
            'toadd'              => [
                self::CONFIG_PARENT  => __('Inheritance of the parent entity'),
                self::CONFIG_NEVER   => __('Never'),
                0                  => __('Immediatly')
            ]
        ];

        $tab[] = [
            'id'                 => '59',
            'table'              => static::getTable(),
            'field'              => 'autopurge_delay',
            'name'               => __('Automatic purge of closed tickets after'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'number',
            'min'                => 1,
            'max'                => 3650,
            'step'               => 1,
            'unit'               => 'day',
            'toadd'              => [
                self::CONFIG_PARENT  => __('Inheritance of the parent entity'),
                self::CONFIG_NEVER   => __('Never'),
                0                  => __('Immediatly')
            ]
        ];

        $tab[] = [
            'id'                 => '34',
            'table'              => static::getTable(),
            'field'              => 'notclosed_delay',
            'name'               => __('Alerts on tickets which are not solved'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '35',
            'table'              => static::getTable(),
            'field'              => 'auto_assign_mode',
            'name'               => __('Automatic assignment of tickets'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '36',
            'table'              => static::getTable(),
            'field'              => 'calendars_id',// not a dropdown because of special valu
            'name'               => _n('Calendar', 'Calendars', 1),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific',
            'additionalfields'   => ['calendars_strategy']
        ];

        $tab[] = [
            'id'                 => '37',
            'table'              => static::getTable(),
            'field'              => 'tickettype',
            'name'               => __('Tickets default type'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '75',
            'table'              => static::getTable(),
            'field'              => 'contracts_id_default',
            'name'               => __('Default contract'),
            'datatype'           => 'specific',
            'nosearch'           => true,
            'additionalfields'   => ['contracts_strategy_default'],
            'toadd'              => [
                self::CONFIG_PARENT => __('Inheritance of the parent entity'),
                self::CONFIG_AUTO   => __('Contract in ticket entity'),
            ]
        ];

        $tab[] = [
            'id'                 => 'assets',
            'name'               => _n('Asset', 'Assets', Session::getPluralNumber())
        ];

        $tab[] = [
            'id'                 => '38',
            'table'              => static::getTable(),
            'field'              => 'autofill_buy_date',
            'name'               => __('Date of purchase'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '39',
            'table'              => static::getTable(),
            'field'              => 'autofill_order_date',
            'name'               => __('Order date'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '40',
            'table'              => static::getTable(),
            'field'              => 'autofill_delivery_date',
            'name'               => __('Delivery date'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '41',
            'table'              => static::getTable(),
            'field'              => 'autofill_use_date',
            'name'               => __('Startup date'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '42',
            'table'              => static::getTable(),
            'field'              => 'autofill_warranty_date',
            'name'               => __('Start date of warranty'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '43',
            'table'              => static::getTable(),
            'field'              => 'inquest_config',
            'name'               => sprintf(__('Satisfaction survey configuration (%s)'), Ticket::getTypeName(1)),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '44',
            'table'              => static::getTable(),
            'field'              => 'inquest_rate',
            'name'               => sprintf(__('Satisfaction survey trigger rate (%s)'), Ticket::getTypeName(1)),
            'massiveaction'      => false,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '45',
            'table'              => static::getTable(),
            'field'              => 'inquest_delay',
            'name'               => sprintf(__('Create satisfaction survey after (%s)'), Ticket::getTypeName(1)),
            'massiveaction'      => false,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '46',
            'table'              => static::getTable(),
            'field'              => 'inquest_URL',
            'name'               => sprintf(__('Satisfaction survey URL (%s)'), Ticket::getTypeName(1)),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '71',
            'table'              => static::getTable(),
            'field'              => 'inquest_config_change',
            'name'               => sprintf(__('Satisfaction survey configuration (%s)'), Change::getTypeName(1)),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        $tab[] = [
            'id'                 => '72',
            'table'              => static::getTable(),
            'field'              => 'inquest_rate_change',
            'name'               => sprintf(__('Satisfaction survey trigger rate (%s)'), Change::getTypeName(1)),
            'massiveaction'      => false,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '73',
            'table'              => static::getTable(),
            'field'              => 'inquest_delay_change',
            'name'               => sprintf(__('Create satisfaction survey after (%s)'), Change::getTypeName(1)),
            'massiveaction'      => false,
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '74',
            'table'              => static::getTable(),
            'field'              => 'inquest_URL_change',
            'name'               => sprintf(__('Satisfaction survey URL (%s)'), Change::getTypeName(1)),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '51',
            'table'              => static::getTable(),
            'field'              => 'entities_id_software',
            'linkfield'          => 'entities_id_software', // not a dropdown because of special value
                                 //TRANS: software in plural
            'name'               => __('Entity for software creation'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific',
            'additionalfields'   => ['entities_strategy_software']
        ];

        $tab[] = [
            'id'                 => '56',
            'table'              => static::getTable(),
            'field'              => 'autofill_decommission_date',
            'name'               => __('Decommission date'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific'
        ];

        return $tab;
    }

    /**
     * @since 0.83 (before addRule)
     *
     * @param array $input
     * @used-by front/dropdown.common.form.php
     **/
    public function executeAddRule($input)
    {
        $this->check($_POST["affectentity"], UPDATE);

        $collection = RuleCollection::getClassByType($_POST['sub_type']);
        $rule       = $collection->getRuleClass();
        $ruleid     = $rule->add($_POST);

        if ($ruleid) {
           //Add an action associated to the rule
            $ruleAction = new RuleAction();

           //Action is : affect computer to this entity
            $ruleAction->addActionByAttributes(
                "assign",
                $ruleid,
                "entities_id",
                $_POST["affectentity"]
            );

            switch ($_POST['sub_type']) {
                case 'RuleRight':
                    if ($_POST["profiles_id"]) {
                        $ruleAction->addActionByAttributes(
                            "assign",
                            $ruleid,
                            "profiles_id",
                            $_POST["profiles_id"]
                        );
                    }
                    $ruleAction->addActionByAttributes(
                        "assign",
                        $ruleid,
                        "is_recursive",
                        $_POST["is_recursive"]
                    );
            }
        }

        Event::log(
            $ruleid,
            "rules",
            4,
            "setup",
            //TRANS: %s is the user login
            sprintf(__('%s adds the item'), $_SESSION["glpiname"])
        );

        Html::back();
    }

    /**
     * get all entities with a notification option set
     * manage CONFIG_PARENT (or NULL) value
     *
     * @param string $field Name of the field to search (>0)
     *
     * @return array<int, string> Array of id => value
     **/
    public static function getEntitiesToNotify($field)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $entities = [];

        // root entity first
        $ent = new self();
        if ($ent->getFromDB(0)) {  // always exists
            $val = $ent->getField($field);
            if ($val > 0) {
                $entities[0] = $val;
            }
        }

        // Others entities in level order (parent first)
        $iterator = $DB->request([
            'SELECT' => [
                'id AS entity',
                'entities_id AS parent',
                $field
            ],
            'FROM'   => self::getTable(),
            'ORDER'  => 'level ASC'
        ]);

        foreach ($iterator as $entitydata) {
            if (
                (is_null($entitydata[$field])
                || ($entitydata[$field] == self::CONFIG_PARENT))
                && isset($entities[$entitydata['parent']])
            ) {
                // config inherit from parent
                $entities[$entitydata['entity']] = $entities[$entitydata['parent']];
            } else if ($entitydata[$field] > 0) {
               // config found in entity
                $entities[$entitydata['entity']] = $entitydata[$field];
            }
        }

        return $entities;
    }

    /**
     * @since 0.84
     *
     * @param Entity $entity object
     **/
    public static function showStandardOptions(Entity $entity)
    {
        $ID = $entity->getField('id');
        if (!$entity->can($ID, READ)) {
            return false;
        }
        TemplateRenderer::getInstance()->display('pages/admin/entity/address.html.twig', [
            'item' => $entity,
            'params' => [
                'candel' => false // No deleting from the non-main tab
            ]
        ]);
        return true;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param Entity $entity object
     **/
    public static function showAdvancedOptions(Entity $entity)
    {
        $ID          = $entity->getField('id');
        if (!$entity->can($ID, READ)) {
            return false;
        }
        TemplateRenderer::getInstance()->display('pages/admin/entity/advanced.html.twig', [
            'item' => $entity,
            'params' => [
                'candel' => false // No deleting from the non-main tab
            ],
            'can_use_ldap' => Toolbox::canUseLdap()
        ]);
        return true;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param Entity $entity object
     **/
    public static function showInventoryOptions(Entity $entity)
    {
        $ID = $entity->getField('id');
        if (!$entity->can($ID, READ)) {
            return false;
        }

        $states = getAllDataFromTable('glpi_states');
        $state_options[0] = __('No autofill');
        if ($ID > 0) {
            $state_options[self::CONFIG_PARENT] = __('Inheritance of the parent entity');
        }
        foreach ($states as $state) {
            $state_options[Infocom::ON_STATUS_CHANGE . '_' . $state['id']] = sprintf(__('Fill when shifting to state %s'), $state['name']);
        }
        $state_options[Infocom::COPY_WARRANTY_DATE] = __('Copy the start date of warranty');

        $warranty_options = [
            0 => __('No autofill'),
            Infocom::COPY_BUY_DATE      => __('Copy the date of purchase'),
            Infocom::COPY_ORDER_DATE    => __('Copy the order date'),
            Infocom::COPY_DELIVERY_DATE => __('Copy the delivery date')
        ];
        if ($ID > 0) {
            $warranty_options[self::CONFIG_PARENT] = __('Inheritance of the parent entity');
        }

        $decom_options = [
            0                           => __('No autofill'),
            Infocom::COPY_BUY_DATE      => __('Copy the date of purchase'),
            Infocom::COPY_ORDER_DATE    => __('Copy the order date'),
            Infocom::COPY_DELIVERY_DATE => __('Copy the delivery date')
        ];
        if ($ID > 0) {
            $decom_options[self::CONFIG_PARENT] = __('Inheritance of the parent entity');
        }

        foreach ($states as $state) {
            $decom_options[Infocom::ON_STATUS_CHANGE . '_' . $state['id']] = sprintf(__('Fill when shifting to state %s'), $state['name']);
        }

        $entities = [];
        if ($ID > 0) {
            $entities = [$entity->fields['entities_id']];
            foreach (getAncestorsOf('glpi_entities', $entity->fields['entities_id']) as $ent) {
                if (Session::haveAccessToEntity($ent)) {
                    $entities[] = $ent;
                }
            }
        }

        $fn_get_inheritance_label = static function (string $field, ?string $strategy_field = null) use ($entity): ?string {
            $result = null;
            if ($entity->fields[$field] == self::CONFIG_PARENT) {
                if ($strategy_field === null) {
                    $strategy_field = $field;
                }
                $inherited_strategy = self::getUsedConfig($strategy_field, $entity->fields['entities_id']);
                $inherited_value    = $inherited_strategy === 0
                    ? self::getUsedConfig($strategy_field, $entity->fields['entities_id'], $field)
                    : $inherited_strategy;
                $result = self::inheritedValue(
                    self::getSpecificValueToDisplay($field, $inherited_value),
                    false,
                    false
                );
            }
            return $result;
        };

        $inheritance_labels = [
            'entities_id_software'          => $fn_get_inheritance_label('entities_id_software', 'entities_strategy_software'),
            'transfers_id'                  => $fn_get_inheritance_label('transfers_id', 'transfers_strategy'),
            'autofill_buy_date'             => $fn_get_inheritance_label('autofill_buy_date'),
            'autofill_order_date'           => $fn_get_inheritance_label('autofill_order_date'),
            'autofill_delivery_date'        => $fn_get_inheritance_label('autofill_delivery_date'),
            'autofill_use_date'             => $fn_get_inheritance_label('autofill_use_date'),
            'autofill_warranty_date'        => $fn_get_inheritance_label('autofill_warranty_date'),
            'autofill_decommission_date'    => $fn_get_inheritance_label('autofill_decommission_date'),
            'agent_base_url'                => self::inheritedValue(
                self::getUsedConfig('agent_base_url', $ID, '', ''),
                false,
                false
            ),
        ];

        $fields = ["contact", "user", "group", "location"];
        foreach ($fields as $field) {
            $inheritance_labels["is_{$field}_autoupdate"] = $fn_get_inheritance_label("is_{$field}_autoupdate");
            $inheritance_labels["is_{$field}_autoclean"] = $fn_get_inheritance_label("is_{$field}_autoclean");
        }
        $inheritance_labels['state_autoupdate_mode'] = $fn_get_inheritance_label('state_autoupdate_mode');
        $inheritance_labels['state_autoclean_mode'] = $fn_get_inheritance_label('state_autoclean_mode');

        TemplateRenderer::getInstance()->display('pages/admin/entity/assets.html.twig', [
            'item' => $entity,
            'params' => [
                'canedit' => $entity->can($ID, UPDATE),
                'candel' => false, // No deleting from the non-main tab
                'entities_id' => $entity->fields['entities_id'],
            ],
            'status_options' => $state_options,
            'warranty_options' => $warranty_options,
            'decom_options' => $decom_options,
            'entities' => $entities,
            'inheritance_labels' => $inheritance_labels,
        ]);
        return true;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param Entity $entity object
     **/
    public static function showNotificationOptions(Entity $entity)
    {

        $ID = $entity->getField('id');
        if (
            !$entity->can($ID, READ)
            || !Notification::canView()
        ) {
            return false;
        }

        $fn_get_inheritance_label = static function (string $field) use ($ID): string {
            return self::inheritedValue(
                self::getUsedConfig($field, $ID, '', ''),
                false,
                false
            );
        };

        $fn_get_specific_inheritance_label = static function (string $field) use ($entity): string {
            $tid = self::getUsedConfig($field, $entity->getField('entities_id'));
            return self::inheritedValue(
                self::getSpecificValueToDisplay($field, $tid),
                false,
                false
            );
        };

        $inheritance_labels = [
            'admin_email' => $fn_get_inheritance_label('admin_email'),
            'admin_email_name' => $fn_get_inheritance_label('admin_email_name'),
            'from_email' => $fn_get_inheritance_label('from_email'),
            'from_email_name' => $fn_get_inheritance_label('from_email_name'),
            'noreply_email' => $fn_get_inheritance_label('noreply_email'),
            'noreply_email_name' => $fn_get_inheritance_label('noreply_email_name'),
            'replyto_email' => $fn_get_inheritance_label('replyto_email'),
            'replyto_email_name' => $fn_get_inheritance_label('replyto_email_name'),
            'notification_subject_tag' => $fn_get_inheritance_label('notification_subject_tag'),
            'delay_send_emails' => null,
            'is_notif_enable_default' => null,
            'mailing_signature' => $fn_get_inheritance_label('mailing_signature'),
            'url_base' => $fn_get_inheritance_label('url_base'),
            'cartridges_alert_repeat' => $fn_get_specific_inheritance_label('cartridges_alert_repeat'),
            'default_cartridges_alarm_threshold' => $fn_get_specific_inheritance_label('default_cartridges_alarm_threshold'),
            'consumables_alert_repeat' => $fn_get_specific_inheritance_label('consumables_alert_repeat'),
            'default_consumables_alarm_threshold' => $fn_get_specific_inheritance_label('default_consumables_alarm_threshold'),
            'use_contracts_alert' => $fn_get_specific_inheritance_label('use_contracts_alert'),
            'default_contract_alert' => $fn_get_specific_inheritance_label('default_contract_alert'),
            'send_contracts_alert_before_delay' => $fn_get_specific_inheritance_label('send_contracts_alert_before_delay'),
            'use_infocoms_alert' => $fn_get_specific_inheritance_label('use_infocoms_alert'),
            'default_infocom_alert' => $fn_get_specific_inheritance_label('default_infocom_alert'),
            'send_infocoms_alert_before_delay' => $fn_get_specific_inheritance_label('send_infocoms_alert_before_delay'),
            'use_licenses_alert' => $fn_get_specific_inheritance_label('use_licenses_alert'),
            'send_licenses_alert_before_delay' => $fn_get_specific_inheritance_label('send_licenses_alert_before_delay'),
            'use_certificates_alert' => $fn_get_specific_inheritance_label('use_certificates_alert'),
            'send_certificates_alert_before_delay' => $fn_get_specific_inheritance_label('send_certificates_alert_before_delay'),
            'certificates_alert_repeat_interval' => $fn_get_specific_inheritance_label('certificates_alert_repeat_interval'),
            'use_reservations_alert' => $fn_get_specific_inheritance_label('use_reservations_alert'),
            'notclosed_delay' => $fn_get_specific_inheritance_label('notclosed_delay'),
            'use_domains_alert' => $fn_get_specific_inheritance_label('use_domains_alert'),
            'send_domains_alert_close_expiries_delay' => $fn_get_specific_inheritance_label('send_domains_alert_close_expiries_delay'),
            'send_domains_alert_expired_delay' => $fn_get_specific_inheritance_label('send_domains_alert_expired_delay'),
            'approval_reminder_repeat_interval' => $fn_get_specific_inheritance_label('approval_reminder_repeat_interval'),
        ];
        if ($entity->fields['delay_send_emails'] == self::CONFIG_PARENT) {
            $tid = self::getUsedConfig('delay_send_emails', $entity->getField('entities_id'));
            $inheritance_labels['delay_send_emails'] = self::inheritedValue(
                $entity->getValueToDisplay('delay_send_emails', $tid, ['html' => true]),
                false,
                false
            );
        }
        if ($entity->fields['is_notif_enable_default'] == self::CONFIG_PARENT) {
            $tid = self::getUsedConfig('is_notif_enable_default', $entity->getField('entities_id'));
            $inheritance_labels['is_notif_enable_default'] = self::inheritedValue(
                self::getSpecificValueToDisplay('is_notif_enable_default', $tid),
                false,
                false
            );
        }

       // Notification right applied
        $canedit = (Notification::canUpdate()
                  && Session::haveAccessToEntity($ID));
        TemplateRenderer::getInstance()->display('pages/admin/entity/notifications.html.twig', [
            'item' => $entity,
            'params' => [
                'canedit' => (Notification::canUpdate() && Session::haveAccessToEntity($ID)),
                'candel' => false, // No deleting from the non-main tab
                'entities_id' => $entity->fields['entities_id'],
            ],
            'inheritance_labels' => $inheritance_labels,
            'contract_alert_choices' => [
                0 => Dropdown::EMPTY_VALUE,
                4 => __('End'),
                8 => __('Notice'),
                12 => __('End + Notice'),
                64 => __('Period end'),
                72 => __('Period end + Notice'),
            ],
            'infocom_alert_choices' => [
                0 => Dropdown::EMPTY_VALUE,
                4 => __('Warranty expiration date'),
            ]
        ]);
        return true;
    }

    /**
     * UI customization configuration form.
     *
     * @param Entity $entity object
     *
     * @return void
     *
     * @since 9.5.0
     */
    public static function showUiCustomizationOptions(Entity $entity)
    {
        $ID = $entity->getField('id');
        if (!$entity->can($ID, READ) || !Session::haveRight(Config::$rightname, UPDATE)) {
            return false;
        }

       // Notification right applied
        $canedit = Session::haveRight(Config::$rightname, UPDATE)
         && Session::haveAccessToEntity($ID);
        $enable_css_options = [];
        if (($ID > 0) ? 1 : 0) {
            $enable_css_options[self::CONFIG_PARENT] = __('Inherits configuration from the parent entity');
        }
        $enable_css_options[0] = __('No');
        $enable_css_options[1] = __('Yes');

        $enable_css_inheritance_label = null;
        $inherited_css = null;
        $inherited_value = null;
        if ($entity->fields['enable_custom_css'] === self::CONFIG_PARENT) {
            $inherited_strategy = self::getUsedConfig('enable_custom_css', $entity->fields['entities_id']);
            $inherited_value = $inherited_strategy === 0
                ? self::getUsedConfig('enable_custom_css', $entity->fields['entities_id'], 'enable_custom_css')
                : $inherited_strategy;
            $enable_css_inheritance_label = self::inheritedValue(
                self::getSpecificValueToDisplay('enable_custom_css', $inherited_value),
                false,
                false
            );
            $inherited_css = self::getUsedConfig('enable_custom_css', $entity->fields['entities_id'], 'custom_css_code');
        }

        TemplateRenderer::getInstance()->display('pages/admin/entity/custom_ui.html.twig', [
            'item' => $entity,
            'enable_css_options' => $enable_css_options,
            'enable_css_inheritance_label' => $enable_css_inheritance_label,
            'enabled_css_inherited_value' => $inherited_value,
            'inherited_css' => $inherited_css,
            'params' => [
                'canedit' => $canedit,
                'candel' => false, // No deleting from the non-main tab
            ],
        ]);
    }

    /**
     * Security configuration form.
     *
     * @param Entity $entity The entity
     * @return void|false
     * @since 11.0.0
     */
    public static function showSecurityOptions(Entity $entity)
    {
        $ID = $entity->getField('id');
        if (!$entity->can($ID, READ)) {
            return false;
        }

        $canedit = Session::haveAccessToEntity($ID);

        TemplateRenderer::getInstance()->display('pages/2fa/2fa_config.html.twig', [
            'canedit' => $canedit,
            'item'   => $entity,
            'action' => Toolbox::getItemTypeFormURL(__CLASS__)
        ]);
    }

    /**
     * Returns tag containing custom CSS code applied to entity.
     *
     * @return string
     */
    public function getCustomCssTag()
    {
        $enable_custom_css = self::getUsedConfig(
            'enable_custom_css',
            $this->fields['id']
        );

        if (!$enable_custom_css) {
            return '';
        }

        $custom_css_code = self::getUsedConfig(
            'enable_custom_css',
            $this->fields['id'],
            'custom_css_code'
        );

        if (empty($custom_css_code)) {
            return '';
        }

        return '<style>' . strip_tags($custom_css_code) . '</style>';
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param string $field
     * @param string $value
     **/
    private static function getEntityIDByField($field, $value)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => self::getTable(),
            'WHERE'  => [$field => $value]
        ]);

        if (count($iterator) === 1) {
            $result = $iterator->current();
            return $result['id'];
        }
        return -1;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param $value
     **/
    public static function getEntityIDByDN($value)
    {
        return self::getEntityIDByField("ldap_dn", $value);
    }

    /**
     * @since 0.84
     *
     * @param $value
     **/
    public static function getEntityIDByCompletename($value)
    {
        return self::getEntityIDByField("completename", $value);
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param $value
     **/
    public static function getEntityIDByTag($value)
    {
        return self::getEntityIDByField("tag", $value);
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param $value
     **/
    public static function getEntityIDByDomain($value)
    {
        return self::getEntityIDByField("mail_domain", $value);
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param $entities_id
     **/
    public static function isEntityDirectoryConfigured($entities_id)
    {
        $entity = new self();

        if (
            $entity->getFromDB($entities_id)
            && ($entity->getField('authldaps_id') > 0)
        ) {
            return true;
        }

        // If there's a directory marked as default
        if (AuthLDAP::getDefault()) {
            return true;
        }
        return false;
    }

    /**
     * @since 0.84 (before in entitydata.class)
     *
     * @param Entity $entity object
     **/
    public static function showHelpdeskOptions(Entity $entity)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $ID = $entity->getField('id');
        if (
            !$entity->can($ID, READ)
            || !Session::haveRightsOr(
                self::$rightname,
                [self::READHELPDESK, self::UPDATEHELPDESK]
            )
        ) {
            return false;
        }
        $canedit = (Session::haveRight(self::$rightname, self::UPDATEHELPDESK)
                  && Session::haveAccessToEntity($ID));

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form method='post' name=form action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "' data-track-changes='true'>";
        }

        echo "<table class='tab_cadre_fixe'>";

        Plugin::doHook(Hooks::PRE_ITEM_FORM, ['item' => $entity, 'options' => []]);

        echo "<tr><th colspan='4'>" . __('Templates configuration') . "</th></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2'>" . _n('Ticket template', 'Ticket templates', 1) .
           "</td>";
        echo "<td colspan='2'>";
        $toadd = [];
        if ($ID != 0) {
            $toadd = [self::CONFIG_PARENT => __('Inheritance of the parent entity')];
        }

        $options = ['value'  => $entity->fields["tickettemplates_id"],
            'entity' => $ID,
            'toadd'  => $toadd
        ];

        TicketTemplate::dropdown($options);

        if ($entity->fields["tickettemplates_id"] == self::CONFIG_PARENT) {
            $tt  = new TicketTemplate();
            $tid = self::getUsedConfig('tickettemplates_strategy', $ID, 'tickettemplates_id', 0);
            if (!$tid) {
                self::inheritedValue(Dropdown::EMPTY_VALUE, true);
            } else if ($tt->getFromDB($tid)) {
                self::inheritedValue($tt->getLink(), true);
            }
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2'>" . _n('Change template', 'Change templates', 1) .
           "</td>";
        echo "<td colspan='2'>";
        $toadd = [];
        if ($ID != 0) {
            $toadd = [self::CONFIG_PARENT => __('Inheritance of the parent entity')];
        }

        $options = ['value'  => $entity->fields["changetemplates_id"],
            'entity' => $ID,
            'toadd'  => $toadd
        ];

        ChangeTemplate::dropdown($options);

        if ($entity->fields["changetemplates_id"] == self::CONFIG_PARENT) {
            $tt  = new ChangeTemplate();
            $tid = self::getUsedConfig('changetemplates_strategy', $ID, 'changetemplates_id', 0);
            if (!$tid) {
                self::inheritedValue(Dropdown::EMPTY_VALUE, true);
            } else if ($tt->getFromDB($tid)) {
                self::inheritedValue($tt->getLink(), true);
            }
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2'>" . _n('Problem template', 'Problem templates', 1) .
           "</td>";
        echo "<td colspan='2'>";
        $toadd = [];
        if ($ID != 0) {
            $toadd = [self::CONFIG_PARENT => __('Inheritance of the parent entity')];
        }

        $options = ['value'  => $entity->fields["problemtemplates_id"],
            'entity' => $ID,
            'toadd'  => $toadd
        ];

        ProblemTemplate::dropdown($options);

        if ($entity->fields["problemtemplates_id"] == self::CONFIG_PARENT) {
            $tt  = new ProblemTemplate();
            $tid = self::getUsedConfig('problemtemplates_strategy', $ID, 'problemtemplates_id', 0);
            if (!$tid) {
                self::inheritedValue(Dropdown::EMPTY_VALUE, true);
            } else if ($tt->getFromDB($tid)) {
                self::inheritedValue($tt->getLink(), true);
            }
        }
        echo "</td></tr>";

        echo "<tr><th colspan='4'>" . __('Tickets configuration') . "</th></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2'>" . _n('Calendar', 'Calendars', 1) . "</td>";
        echo "<td colspan='2'>";
        $options = ['value'      => $entity->fields["calendars_id"],
            'emptylabel' => __('24/7')
        ];

        if ($ID != 0) {
            $options['toadd'] = [self::CONFIG_PARENT => __('Inheritance of the parent entity')];
        }
        Calendar::dropdown($options);

        if ($entity->fields["calendars_id"] == self::CONFIG_PARENT) {
            $calendar = new Calendar();
            $cid = self::getUsedConfig('calendars_strategy', $ID, 'calendars_id', 0);
            if (!$cid) {
                self::inheritedValue(__('24/7'), true);
            } else if ($calendar->getFromDB($cid)) {
                self::inheritedValue($calendar->getLink(), true);
            }
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2'>" . __('Tickets default type') . "</td>";
        echo "<td colspan='2'>";
        $toadd = [];
        if ($ID != 0) {
            $toadd = [self::CONFIG_PARENT => __('Inheritance of the parent entity')];
        }
        Ticket::dropdownType('tickettype', ['value' => $entity->fields["tickettype"],
            'toadd' => $toadd
        ]);

        if ($entity->fields['tickettype'] == self::CONFIG_PARENT) {
            self::inheritedValue(Ticket::getTicketTypeName(self::getUsedConfig(
                'tickettype',
                $ID,
                '',
                Ticket::INCIDENT_TYPE
            )), true);
        }
        echo "</td></tr>";

       // Auto assign mode
        echo "<tr class='tab_bg_1'><td  colspan='2'>" . __('Automatic assignment of tickets, changes and problems') . "</td>";
        echo "<td colspan='2'>";
        $autoassign = self::getAutoAssignMode();

        if ($ID == 0) {
            unset($autoassign[self::CONFIG_PARENT]);
        }

        Dropdown::showFromArray(
            'auto_assign_mode',
            $autoassign,
            ['value' => $entity->fields["auto_assign_mode"]]
        );

        if ($entity->fields['auto_assign_mode'] == self::CONFIG_PARENT) {
            $auto_assign_mode = self::getUsedConfig('auto_assign_mode', $entity->fields['entities_id']);
            self::inheritedValue($autoassign[$auto_assign_mode], true);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td  colspan='2'>" . __('Mark followup added by a supplier though an email collector as private') . "</td>";
        echo "<td colspan='2'>";
        $supplierValues = self::getSuppliersAsPrivateValues();
        $currentSupplierValue = $entity->fields['suppliers_as_private'];

        if ($ID == 0) { // Remove parent option for root entity
            unset($supplierValues[self::CONFIG_PARENT]);
        }

        Dropdown::showFromArray(
            'suppliers_as_private',
            $supplierValues,
            ['value' => $currentSupplierValue]
        );

       // If the entity is using it's parent value, print it
        if ($currentSupplierValue == self::CONFIG_PARENT) {
            $parentSupplierValue = self::getUsedConfig(
                'suppliers_as_private',
                $entity->fields['entities_id']
            );
            self::inheritedValue($supplierValues[$parentSupplierValue], true);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td  colspan='2'>" . __('Anonymize support agents') . "</td>";
        echo "<td colspan='2'>";
        $anonymize_values = self::getAnonymizeSupportAgentsValues();
        $current_anonymize_value = $entity->fields['anonymize_support_agents'];

        if ($ID == 0) { // Remove parent option for root entity
            unset($anonymize_values[self::CONFIG_PARENT]);
        }

        Dropdown::showFromArray(
            'anonymize_support_agents',
            $anonymize_values,
            ['value' => $current_anonymize_value]
        );

       // If the entity is using it's parent value, print it
        if ($current_anonymize_value == self::CONFIG_PARENT) {
            $parent_helpdesk_value = self::getUsedConfig(
                'anonymize_support_agents',
                $entity->fields['entities_id']
            );
            self::inheritedValue($anonymize_values[$parent_helpdesk_value], true);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td  colspan='2'>" . __("Display initials for users without pictures") . "</td>";
        echo "<td colspan='2'>";
        $initialsValues = self::getDisplayUsersInitialsValues();
        $currentInitialsValue = $entity->fields['display_users_initials'];

        if ($ID == 0) { // Remove parent option for root entity
            unset($initialsValues[self::CONFIG_PARENT]);
        }

        Dropdown::showFromArray(
            'display_users_initials',
            $initialsValues,
            ['value' => $currentInitialsValue]
        );

       // If the entity is using it's parent value, print it
        if ($currentInitialsValue == self::CONFIG_PARENT) {
            $parentSupplierValue = self::getUsedConfig(
                'display_users_initials',
                $entity->fields['entities_id']
            );
            self::inheritedValue($initialsValues[$parentSupplierValue], true);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td  colspan='2'>" . __('Default contract') . "</td>";
        echo "<td colspan='2'>";
        $current_default_contract_value = $entity->fields['contracts_id_default'];

        $toadd = [
            self::CONFIG_PARENT => __('Inheritance of the parent entity'),
            self::CONFIG_AUTO   => __('Contract in ticket entity'),
        ];

        if ($ID == 0) { // Remove parent option for root entity
            unset($toadd[self::CONFIG_PARENT]);
        }

        Contract::dropdown([
            'name'      => 'contracts_id_default',
            'condition' => ['is_template' => 0, 'is_deleted' => 0] + Contract::getNotExpiredCriteria(),
            'entity'    => $entity->getID(),
            'toadd'     => $toadd,
            'value'     => $current_default_contract_value,
        ]);

        // If the entity is using it's parent value, print it
        if ($current_default_contract_value == self::CONFIG_PARENT) {
            $inherited_default_contract_strategy = self::getUsedConfig(
                'contracts_strategy_default',
                $entity->fields['entities_id']
            );
            $inherited_default_contract_id = self::getUsedConfig(
                'contracts_strategy_default',
                $entity->fields['entities_id'],
                'contracts_id_default',
                0
            );
            $contract = new Contract();
            if ($inherited_default_contract_strategy == self::CONFIG_AUTO) {
                $display_value = __('Contract in ticket entity');
            } elseif ($inherited_default_contract_id > 0 && $contract->getFromDB($inherited_default_contract_id)) {
                $display_value = $contract->fields['name'];
            } else {
                $display_value = Dropdown::EMPTY_VALUE;
            }

            self::inheritedValue($display_value, true);
        }
        echo "</td></tr>";

        echo "<tr><th colspan='4'>" . __('Automatic closing configuration') . "</th></tr>";

        echo "<tr class='tab_bg_1'>" .
         "<td>" . __('Automatic closing of solved tickets after');

       //Check if crontask is disabled
        $crontask = new CronTask();
        $criteria = [
            'itemtype'  => 'Ticket',
            'name'      => 'closeticket',
            'state'     => CronTask::STATE_DISABLE
        ];
        if ($crontask->getFromDBByCrit($criteria)) {
            echo "<br/><strong>" . __('Close ticket action is disabled.') . "</strong>";
        }

        echo "</td>";
        echo "<td>";
        $autoclose = [self::CONFIG_PARENT => __('Inheritance of the parent entity'),
            self::CONFIG_NEVER  => __('Never'),
            0                   => __('Immediatly')
        ];
        if ($ID == 0) {
            unset($autoclose[self::CONFIG_PARENT]);
        }

        Dropdown::showNumber(
            'autoclose_delay',
            ['value' => $entity->fields['autoclose_delay'],
                'min'   => 1,
                'max'   => 99,
                'step'  => 1,
                'toadd' => $autoclose,
                'unit'  => 'day'
            ]
        );

        if ($entity->fields['autoclose_delay'] == self::CONFIG_PARENT) {
            $autoclose_mode = self::getUsedConfig(
                'autoclose_delay',
                $entity->fields['entities_id'],
                '',
                self::CONFIG_NEVER
            );

            if ($autoclose_mode >= 0) {
                self::inheritedValue(sprintf(_n('%d day', '%d days', $autoclose_mode), $autoclose_mode), true);
            } else {
                self::inheritedValue($autoclose[$autoclose_mode], true);
            }
        }
        echo "<td>" . __('Automatic purge of closed tickets after');

       //Check if crontask is disabled
        $crontask = new CronTask();
        $criteria = [
            'itemtype'  => 'Ticket',
            'name'      => 'purgeticket',
            'state'     => CronTask::STATE_DISABLE
        ];
        if ($crontask->getFromDBByCrit($criteria)) {
            echo "<br/><strong>" . __('Purge ticket action is disabled.') . "</strong>";
        }
        echo "</td>";
        echo "<td>";
        $autopurge = [
            self::CONFIG_PARENT => __('Inheritance of the parent entity'),
            self::CONFIG_NEVER  => __('Never')
        ];
        if ($ID == 0) {
            unset($autopurge[self::CONFIG_PARENT]);
        }

        Dropdown::showNumber(
            'autopurge_delay',
            [
                'value' => $entity->fields['autopurge_delay'],
                'min'   => 1,
                'max'   => 3650,
                'step'  => 1,
                'toadd' => $autopurge,
                'unit'  => 'day'
            ]
        );

        if ($entity->fields['autopurge_delay'] == self::CONFIG_PARENT) {
            $autopurge_mode = self::getUsedConfig(
                'autopurge_delay',
                $entity->fields['entities_id'],
                '',
                self::CONFIG_NEVER
            );

            if ($autopurge_mode >= 0) {
                self::inheritedValue(sprintf(_n('%d day', '%d days', $autopurge_mode), $autopurge_mode), true);
            } else {
                self::inheritedValue($autopurge[$autopurge_mode], true);
            }
        }
        echo "</td></tr>";

        $inquest_types = [
            Ticket::class   => TicketSatisfaction::class,
            Change::class   => ChangeSatisfaction::class,
        ];

        /**
         * @var CommonITILObject $itemtype
         * @var CommonITILSatisfaction $inquest_itemtype
         */
        foreach ($inquest_types as $itemtype => $inquest_itemtype) {
            $config_title = sprintf(__('Configuring the satisfaction survey: %s'), $itemtype::getTypeName(Session::getPluralNumber()));
            // Get suffix for entity config fields. For backwards compatibility, ticket values have no suffix.
            $config_suffix = $itemtype::getType() === 'Ticket' ? '' : ('_' . strtolower($itemtype::getType()));

            echo "<tr><th colspan='4'>" . $config_title . "</th></tr>";

            echo "<tr class='tab_bg_1'>" .
                "<td colspan='2'>" . __('Configuring the satisfaction survey') . "</td>";
            echo "<td colspan='2'>";

            /// no inquest case = rate 0
            $typeinquest = [
                self::CONFIG_PARENT => __('Inheritance of the parent entity'),
                CommonITILSatisfaction::TYPE_INTERNAL => __('Internal survey'),
                CommonITILSatisfaction::TYPE_EXTERNAL => __('External survey')
            ];

            // No inherit from parent for root entity
            if ($ID == 0) {
                unset($typeinquest[self::CONFIG_PARENT]);
                if ($entity->fields['inquest_config' . $config_suffix] == self::CONFIG_PARENT) {
                    $entity->fields['inquest_config' . $config_suffix] = 1;
                }
            }
            $rand = Dropdown::showFromArray(
                'inquest_config' . $config_suffix,
                $typeinquest,
                $options = ['value' => $entity->fields['inquest_config' . $config_suffix]]
            );
            echo "</td></tr>\n";

            if ($entity->fields['inquest_config' . $config_suffix] == self::CONFIG_PARENT) {
                $inquestconfig = self::getUsedConfig('inquest_config' . $config_suffix, $entity->fields['entities_id']);
                $inquestrate = self::getUsedConfig(
                    'inquest_config' . $config_suffix,
                    $entity->fields['entities_id'],
                    'inquest_rate' . $config_suffix
                );
                echo "<tr class='tab_bg_1'><td colspan='4'>";

                $inherit = "";
                if ($inquestrate == 0) {
                    $inherit .= __('Disabled');
                } else {
                    $inherit .= $typeinquest[$inquestconfig] . '<br>';
                    $inqconf = self::getUsedConfig(
                        'inquest_config' . $config_suffix,
                        $entity->fields['entities_id'],
                        'inquest_delay' . $config_suffix
                    );

                    $inherit .= sprintf(_n('%d day', '%d days', $inqconf), $inqconf);
                    $inherit .= "<br>";
                    //TRANS: %d is the percentage. %% to display %
                    $inherit .= sprintf(__('%d%%'), $inquestrate);

                    if ($inquestconfig == 2) {
                        $inherit .= "<br>";
                        $inherit .= self::getUsedConfig(
                            'inquest_config' . $config_suffix,
                            $entity->fields['entities_id'],
                            'inquest_URL' . $config_suffix
                        );
                    }
                }
                self::inheritedValue($inherit, true);
                echo "</td></tr>";
            }

            echo "<tr class='tab_bg_1'><td colspan='4'>";

            $_POST = [
                ('inquest_config' . $config_suffix) => $entity->fields['inquest_config' . $config_suffix],
                'entities_id' => $ID
            ];
            $params = [
                ('inquest_config' . $config_suffix) => '__VALUE__',
                'entities_id' => $ID
            ];
            echo "<div id='inquestconfig'>";
            $ajax_file =  GLPI_ROOT . '/ajax/' . strtolower($inquest_itemtype::getType()) . '.php';
            include $ajax_file;
            echo "</div>\n";

            echo "</td></tr>";
        }

        Plugin::doHook(Hooks::POST_ITEM_FORM, ['item' => $entity, 'options' => &$options]);

        echo "</table>";

        if ($canedit) {
            echo "<div class='center'>";
            echo "<input type='hidden' name='id' value='" . $entity->fields["id"] . "'>";
            echo "<input type='submit' name='update' value=\"" . _sx('button', 'Save') . "\"
                  class='btn btn-primary'>";
            echo "</div>";
            Html::closeForm();
        }

        echo "</div>";

        Ajax::updateItemOnSelectEvent(
            "dropdown_inquest_config$rand",
            "inquestconfig",
            $CFG_GLPI["root_doc"] . "/ajax/ticketsatisfaction.php",
            $params
        );
    }

    /**
     * Retrieve data of current entity or parent entity
     *
     * @since 0.84 (before in entitydata.class)
     *
     * @param string  $fieldref       name of the referent field to know if we look at parent entity
     * @param integer $entities_id
     * @param string  $fieldval       name of the field that we want value (default '')
     * @param mixed   $default_value  value to return (default -2)
     **/
    public static function getUsedConfig($fieldref, $entities_id, $fieldval = '', $default_value = -2)
    {
        /**
         * @var \DBmysql $DB
         * @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE
         */
        global $DB, $GLPI_CACHE;

        $id_using_strategy = [
            'calendars_id',
            'changetemplates_id',
            'contracts_id_default',
            'entities_id_software',
            'problemtemplates_id',
            'tickettemplates_id',
            'transfers_id',
        ];
        if (in_array($fieldref, $id_using_strategy)) {
            $fieldval = $fieldref;
            $fieldref = str_replace('_id', '_strategy', $fieldref);
            $default_value = 0;
            trigger_error(
                sprintf(
                    'Entity config "%s" should be get using its reference field "%s" with a "0" default value',
                    $fieldval,
                    $fieldref
                )
            );
        }

        if (empty($fieldval)) {
            $fieldval = $fieldref;
        }

        $ref_cache_key = sprintf('entity_%d_config_%s', $entities_id, $fieldref);
        $val_cache_key = sprintf('entity_%d_config_%s', $entities_id, $fieldval);

        $ref = $GLPI_CACHE->get($ref_cache_key);
        $val = $fieldref === $fieldval ? $ref : $GLPI_CACHE->get($val_cache_key);

        if ($ref === null || $val === null) {
            $entities_query = [
                'SELECT' => ['id', 'entities_id', $fieldref],
                'FROM'   => self::getTable(),
                'WHERE'  => ['id' => array_merge([$entities_id], getAncestorsOf(self::getTable(), $entities_id))]
            ];
            if ($fieldval !== $fieldref) {
                $entities_query['SELECT'][] = $fieldval;
            }
            $entities_data = iterator_to_array($DB->request($entities_query));

            $current_id = $entities_id;
            while ($current_id !== null) {
                if (!array_key_exists($current_id, $entities_data)) {
                    break; // Cannot find entity data, so cannot continue
                }

                $entity_data = $entities_data[$current_id];

                $ref = $entity_data[$fieldref];
                $inherits = (is_numeric($default_value) && $ref == self::CONFIG_PARENT)
                    || (!is_numeric($default_value) && !$ref);
                if (!$inherits) {
                    $val = $entity_data[$fieldval];
                    break;
                }

                // Value inherited: parse parent data
                $current_id = $entity_data['entities_id'];
            }
        }

        $GLPI_CACHE->setMultiple(
            [
                $ref_cache_key => $ref,
                $val_cache_key => $val,
            ]
        );

        return $val ?? $default_value;
    }

    /**
     * Generate link for ITIL Object satisfaction
     *
     * @since 0.84 (before in entitydata.class)
     *
     * @param CommonITILObject $item ITIL Object item to create the survey link for
     *
     * @return string Url contents
     **/
    public static function generateLinkSatisfaction($item)
    {
        $config_suffix = $item::getType() === 'Ticket' ? '' : ('_' . strtolower($item::getType()));
        $url = self::getUsedConfig('inquest_config' . $config_suffix, $item->fields['entities_id'], 'inquest_URL' . $config_suffix);

        $tag_prefix = strtoupper($item::getType());

        if (strstr($url, "[ITEMTYPE]")) {
            $url = str_replace("[ITEMTYPE]", $item::getType(), $url);
        }
        if (strstr($url, "[ITEMTYPE_NAME]")) {
            $url = str_replace("[ITEMTYPE_NAME]", $item::getTypeName(1), $url);
        }

        if (strstr($url, "[{$tag_prefix}_ID]")) {
            $url = str_replace("[{$tag_prefix}_ID]", $item->fields['id'], $url);
        }

        if (strstr($url, "[{$tag_prefix}_NAME]")) {
            $url = str_replace("[{$tag_prefix}_NAME]", urlencode($item->fields['name']), $url);
        }

        if (strstr($url, "[{$tag_prefix}_CREATEDATE]")) {
            $url = str_replace("[{$tag_prefix}_CREATEDATE]", $item->fields['date'], $url);
        }

        if (strstr($url, "[{$tag_prefix}_SOLVEDATE]")) {
            $url = str_replace("[{$tag_prefix}_SOLVEDATE]", $item->fields['solvedate'], $url);
        }

        if ($item::getType() === 'Ticket') {
            if (strstr($url, "[REQUESTTYPE_ID]")) {
                $url = str_replace("[REQUESTTYPE_ID]", $item->fields['requesttypes_id'], $url);
            }

            if (strstr($url, "[REQUESTTYPE_NAME]")) {
                $url = str_replace(
                    "[REQUESTTYPE_NAME]",
                    urlencode(Dropdown::getDropdownName(
                        'glpi_requesttypes',
                        $item->fields['requesttypes_id']
                    )),
                    $url
                );
            }
        }

        if (strstr($url, "[{$tag_prefix}_PRIORITY]")) {
            $url = str_replace("[{$tag_prefix}_PRIORITY]", $item->fields['priority'], $url);
        }

        if (strstr($url, "[{$tag_prefix}_PRIORITYNAME]")) {
            $url = str_replace(
                "[{$tag_prefix}_PRIORITYNAME]",
                urlencode(CommonITILObject::getPriorityName($item->fields['priority'])),
                $url
            );
        }

        if (strstr($url, "[TICKETCATEGORY_ID]")) {
            Toolbox::deprecated('[TICKETCATEGORY_ID] in survey URLs tag are deprecated, use [ITILCATEGORY_ID] instead');
            $url = str_replace("[TICKETCATEGORY_ID]", $item->fields['itilcategories_id'], $url);
        }
        if (strstr($url, "[ITILCATEGORY_ID]")) {
            $url = str_replace("[ITILCATEGORY_ID]", $item->fields['itilcategories_id'], $url);
        }

        if (strstr($url, "[TICKETCATEGORY_NAME]")) {
            Toolbox::deprecated('[TICKETCATEGORY_NAME] in survey URLs tag are deprecated, use [ITILCATEGORY_NAME] instead');
            $url = str_replace(
                "[TICKETCATEGORY_NAME]",
                urlencode(Dropdown::getDropdownName(
                    'glpi_itilcategories',
                    $item->fields['itilcategories_id']
                )),
                $url
            );
        }
        if (strstr($url, "[ITILCATEGORY_NAME]")) {
            $url = str_replace(
                "[ITILCATEGORY_NAME]",
                urlencode(Dropdown::getDropdownName(
                    'glpi_itilcategories',
                    $item->fields['itilcategories_id']
                )),
                $url
            );
        }

        if ($item::getType() === 'Ticket') {
            if (strstr($url, "[TICKETTYPE_ID]")) {
                $url = str_replace("[TICKETTYPE_ID]", $item->fields['type'], $url);
            }

            if (strstr($url, "[TICKET_TYPENAME]")) {
                $url = str_replace(
                    "[TICKET_TYPENAME]",
                    Ticket::getTicketTypeName($item->fields['type']),
                    $url
                );
            }
        }

        if (strstr($url, "[SOLUTIONTYPE_ID]")) {
            $url = str_replace("[SOLUTIONTYPE_ID]", $item->fields['solutiontypes_id'], $url);
        }

        if (strstr($url, "[SOLUTIONTYPE_NAME]")) {
            $url = str_replace(
                "[SOLUTIONTYPE_NAME]",
                urlencode(Dropdown::getDropdownName(
                    'glpi_solutiontypes',
                    $item->fields['solutiontypes_id']
                )),
                $url
            );
        }

        if ($item::getType() === 'Ticket') {
            if (strstr($url, "[SLA_TTO_ID]")) {
                $url = str_replace("[SLA_TTO_ID]", $item->fields['slas_id_tto'], $url);
            }

            if (strstr($url, "[SLA_TTO_NAME]")) {
                $url = str_replace(
                    "[SLA_TTO_NAME]",
                    urlencode(Dropdown::getDropdownName(
                        'glpi_slas',
                        $item->fields['slas_id_tto']
                    )),
                    $url
                );
            }

            if (strstr($url, "[SLA_TTR_ID]")) {
                $url = str_replace("[SLA_TTR_ID]", $item->fields['slas_id_ttr'], $url);
            }

            if (strstr($url, "[SLA_TTR_NAME]")) {
                $url = str_replace(
                    "[SLA_TTR_NAME]",
                    urlencode(Dropdown::getDropdownName(
                        'glpi_slas',
                        $item->fields['slas_id_ttr']
                    )),
                    $url
                );
            }

            if (strstr($url, "[SLALEVEL_ID]")) {
                $url = str_replace("[SLALEVEL_ID]", $item->fields['slalevels_id_ttr'], $url);
            }

            if (strstr($url, "[SLALEVEL_NAME]")) {
                $url = str_replace(
                    "[SLALEVEL_NAME]",
                    urlencode(Dropdown::getDropdownName(
                        'glpi_slalevels',
                        $item->fields['slalevels_id_ttr']
                    )),
                    $url
                );
            }
        }

        return $url;
    }

    /**
     * get value for auto_assign_mode
     *
     * @since 0.84 (created in version 0.83 in entitydata.class)
     *
     * @param integer|null $val if not set, ask for all values, else for 1 value (default NULL)
     *
     * @return string|array
     * @phpstan-return $val === null ? array<int|string, string> : string
     **/
    public static function getAutoAssignMode(?int $val = null): string|array
    {
        $tab = [
            self::CONFIG_PARENT                  => __('Inheritance of the parent entity'),
            self::CONFIG_NEVER                   => __('No'),
            self::AUTO_ASSIGN_HARDWARE_CATEGORY  => __('Based on the item then the category'),
            self::AUTO_ASSIGN_CATEGORY_HARDWARE  => __('Based on the category then the item'),
        ];

        if (is_null($val)) {
            return $tab;
        }
        return $tab[$val] ?? NOT_AVAILABLE;
    }

    /**
     * get value for display_users_initials
     *
     * @since 10.0.0
     *
     * @return array
     **/
    public static function getDisplayUsersInitialsValues(): array
    {
        return [
            self::CONFIG_PARENT => __('Inheritance of the parent entity'),
            0                   => __('No'),
            1                   => __('Yes'),
        ];
    }

    /**
     * get value for suppliers_as_private
     *
     * @since 9.5
     *
     * @return array
     **/
    public static function getSuppliersAsPrivateValues(): array
    {
        return [
            self::CONFIG_PARENT => __('Inheritance of the parent entity'),
            0                   => __('No'),
            1                   => __('Yes'),
        ];
    }

    /**
     * Get values for anonymize_support_agents
     *
     * @since 9.5
     *
     * @return array
     **/
    public static function getAnonymizeSupportAgentsValues(): array
    {
        return [
            self::CONFIG_PARENT => __('Inheritance of the parent entity'),
            self::ANONYMIZE_DISABLED => __('Disabled'),
            self::ANONYMIZE_USE_GENERIC => __("Replace the agent and group name with a generic name"),
            self::ANONYMIZE_USE_NICKNAME => __("Replace the agent and group name with a customisable nickname"),
            self::ANONYMIZE_USE_GENERIC_USER => __("Replace the agent's name with a generic name"),
            self::ANONYMIZE_USE_NICKNAME_USER => __("Replace the agent's name with a customisable nickname"),
            self::ANONYMIZE_USE_GENERIC_GROUP => __("Replace the group's name with a generic name"),
        ];
    }

    /**
     * @param array $options
     * @return int|string Returns the HTML code if the `display` option is false. Otherwise, the random number used for the dropdown is returned.
     * @since 0.84
     */
    public static function dropdownAutoAssignMode(array $options): int|string
    {
        $p['name']    = 'auto_assign_mode';
        $p['value']   = 0;
        $p['display'] = true;

        if (count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $tab = self::getAutoAssignMode();
        return Dropdown::showFromArray($p['name'], $tab, $p);
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'use_licenses_alert':
            case 'use_certificates_alert':
            case 'use_contracts_alert':
            case 'use_domains_alert':
            case 'use_infocoms_alert':
            case 'is_notif_enable_default':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                return Dropdown::getYesNo($values[$field]);

            case 'use_reservations_alert':
                switch ($values[$field]) {
                    case self::CONFIG_PARENT:
                        return __('Inheritance of the parent entity');

                    case 0:
                        return __('Never');
                }
                return sprintf(_n('%d hour', '%d hours', $values[$field]), $values[$field]);

            case 'default_cartridges_alarm_threshold':
            case 'default_consumables_alarm_threshold':
                switch ($values[$field]) {
                    case self::CONFIG_PARENT:
                        return __('Inheritance of the parent entity');

                    case 0:
                        return __('Never');
                }
                return $values[$field];

            case 'send_contracts_alert_before_delay':
            case 'send_infocoms_alert_before_delay':
            case 'send_licenses_alert_before_delay':
            case 'send_certificates_alert_before_delay':
            case 'send_domains_alert_close_expiries_delay':
            case 'send_domains_alert_expired_delay':
                switch ($values[$field]) {
                    case self::CONFIG_PARENT:
                        return __('Inheritance of the parent entity');

                    case 0:
                        return __('No');
                }
                return sprintf(_n('%d day', '%d days', $values[$field]), $values[$field]);

            case 'cartridges_alert_repeat':
            case 'consumables_alert_repeat':
            case 'approval_reminder_repeat_interval':
                switch ($values[$field]) {
                    case self::CONFIG_PARENT:
                        return __('Inheritance of the parent entity');

                    case self::CONFIG_NEVER:
                    case 0: // For compatibility issue
                        return __('Never');

                    case DAY_TIMESTAMP:
                        return __('Each day');

                    case WEEK_TIMESTAMP:
                        return __('Each week');

                    case MONTH_TIMESTAMP:
                        return __('Each month');

                    default:
                       // Display value if not defined
                        return $values[$field];
                }
                break;

            case 'notclosed_delay':   // 0 means never
                switch ($values[$field]) {
                    case self::CONFIG_PARENT:
                        return __('Inheritance of the parent entity');

                    case 0:
                        return __('Never');
                }
                return sprintf(_n('%d day', '%d days', $values[$field]), $values[$field]);

            case 'auto_assign_mode':
                return self::getAutoAssignMode((int) $values[$field]);

            case 'tickettype':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                return Ticket::getTicketTypeName($values[$field]);

            case 'autofill_buy_date':
            case 'autofill_order_date':
            case 'autofill_delivery_date':
            case 'autofill_use_date':
            case 'autofill_warranty_date':
            case 'autofill_decommission_date':
                switch ($values[$field]) {
                    case self::CONFIG_PARENT:
                        return __('Inheritance of the parent entity');

                    case Infocom::COPY_WARRANTY_DATE:
                        return __('Copy the start date of warranty');

                    case Infocom::COPY_BUY_DATE:
                        return __('Copy the date of purchase');

                    case Infocom::COPY_ORDER_DATE:
                        return __('Copy the order date');

                    case Infocom::COPY_DELIVERY_DATE:
                        return __('Copy the delivery date');

                    default:
                        if (strstr($values[$field], '_')) {
                            list($type,$sid) = explode('_', $values[$field], 2);
                            if ($type == Infocom::ON_STATUS_CHANGE) {
                                       // TRANS %s is the name of the state
                                return sprintf(
                                    __('Fill when shifting to state %s'),
                                    Dropdown::getDropdownName('glpi_states', $sid)
                                );
                            }
                        }
                }
                return __('No autofill');

            case 'inquest_config':
            case 'inquest_config_change':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                return CommonITILSatisfaction::getTypeInquestName($values[$field]);

            case 'default_contract_alert':
                return Contract::getAlertName($values[$field]);

            case 'default_infocom_alert':
                return Infocom::getAlertName($values[$field]);

            case 'entities_id_software':
                $strategy = $values['entities_strategy_software'] ?? $values[$field];
                if ($strategy == self::CONFIG_NEVER) {
                    return __('No change of entity');
                }
                if ($strategy == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                return Dropdown::getDropdownName('glpi_entities', $values[$field]);

            case 'tickettemplates_id':
                $strategy = $values['tickettemplates_strategy'] ?? $values[$field];
                if ($strategy == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                return Dropdown::getDropdownName(TicketTemplate::getTable(), $values[$field]);

            case 'calendars_id':
                $strategy = $values['calendars_strategy'] ?? $values[$field];
                if ($strategy == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                } elseif ($values[$field] == 0) {
                    return __('24/7');
                }
                return Dropdown::getDropdownName('glpi_calendars', $values[$field]);

            case 'transfers_id':
                $strategy = $values['transfers_strategy'] ?? $values[$field];
                if ($strategy == self::CONFIG_NEVER) {
                    return __('No automatic transfer');
                }
                if ($strategy == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                } elseif ($values[$field] == 0) {
                    return __('No automatic transfer');
                }
                return Dropdown::getDropdownName('glpi_transfers', $values[$field]);

            case 'contracts_id_default':
                $strategy = $values['contracts_strategy_default'] ?? $values[$field];
                if ($strategy === self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                if ($strategy === self::CONFIG_AUTO) {
                    return __('Contract in ticket entity');
                }

                return Dropdown::getDropdownName(Contract::getTable(), $values[$field]);

            case 'is_contact_autoupdate':
            case 'is_user_autoupdate':
            case 'is_group_autoupdate':
            case 'is_location_autoupdate':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                } elseif ($values[$field]) {
                    return __('Copy');
                }
                return __('Do not copy');

            case 'is_contact_autoclean':
            case 'is_user_autoclean':
            case 'is_group_autoclean':
            case 'is_location_autoclean':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                } elseif ($values[$field]) {
                    return __('Clear');
                }
                return __('Do not delete');

            case 'state_autoupdate_mode':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                $states = State::getBehaviours(
                    __('Copy computer status'),
                );
                return $states[$values[$field]];
            case 'state_autoclean_mode':
                if ($values[$field] == self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                $states = State::getBehaviours(
                    __('Clear status'),
                );
                return $states[$values[$field]];
            case 'enable_custom_css':
                if ($values[$field] === self::CONFIG_PARENT) {
                    return __('Inheritance of the parent entity');
                }
                return Dropdown::getYesNo($values[$field]);
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'use_licenses_alert':
            case 'use_certificates_alert':
            case 'use_contracts_alert':
            case 'use_infocoms_alert':
                $options['name']  = $name;
                $options['value'] = $values[$field];
                return Alert::dropdownYesNo($options);

            case 'cartridges_alert_repeat':
            case 'consumables_alert_repeat':
            case 'approval_reminder_repeat_interval':
                $options['name']  = $name;
                $options['value'] = $values[$field];
                return Alert::dropdown($options);

            case 'send_contracts_alert_before_delay':
            case 'send_infocoms_alert_before_delay':
            case 'send_licenses_alert_before_delay':
            case 'send_certificates_alert_before_delay':
                $options['unit']         = 'day';
                $options['never_string'] = __('No');
                return Alert::dropdownIntegerNever($name, $values[$field], $options);

            case 'use_reservations_alert':
                $options['unit']  = 'hour';
                return Alert::dropdownIntegerNever($name, $values[$field], $options);

            case 'notclosed_delay':
                $options['unit']  = 'hour';
                return Alert::dropdownIntegerNever($name, $values[$field], $options);

            case 'auto_assign_mode':
                $options['name']  = $name;
                $options['value'] = $values[$field];

                return self::dropdownAutoAssignMode($options);

            case 'tickettype':
                $options['value'] = $values[$field];
                $options['toadd'] = [self::CONFIG_PARENT => __('Inheritance of the parent entity')];
                return Ticket::dropdownType($name, $options);

            case 'autofill_buy_date':
            case 'autofill_order_date':
            case 'autofill_delivery_date':
            case 'autofill_use_date':
            case 'autofill_decommission_date':
                $tab[0]                   = __('No autofill');
                $tab[self::CONFIG_PARENT] = __('Inheritance of the parent entity');
                $states = getAllDataFromTable('glpi_states');
                foreach ($states as $state) {
                    $tab[Infocom::ON_STATUS_CHANGE . '_' . $state['id']]
                           //TRANS: %s is the name of the state
                    = sprintf(__('Fill when shifting to state %s'), $state['name']);
                }
                $tab[Infocom::COPY_WARRANTY_DATE] = __('Copy the start date of warranty');
                if ($field != 'autofill_buy_date') {
                    $tab[Infocom::COPY_BUY_DATE] = __('Copy the date of purchase');
                    if ($field != 'autofill_order_date') {
                        $tab[Infocom::COPY_ORDER_DATE] = __('Copy the order date');
                        if ($field != 'autofill_delivery_date') {
                             $options[Infocom::COPY_DELIVERY_DATE] = __('Copy the delivery date');
                        }
                    }
                }
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, $tab, $options);

            case 'autofill_warranty_date':
                $tab = [0                           => __('No autofill'),
                    Infocom::COPY_BUY_DATE      => __('Copy the date of purchase'),
                    Infocom::COPY_ORDER_DATE    => __('Copy the order date'),
                    Infocom::COPY_DELIVERY_DATE => __('Copy the delivery date'),
                    self::CONFIG_PARENT         => __('Inheritance of the parent entity')
                ];
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, $tab, $options);

            case 'inquest_config':
            case 'inquest_config_change':
                $typeinquest = [self::CONFIG_PARENT  => __('Inheritance of the parent entity'),
                    1                    => __('Internal survey'),
                    2                    => __('External survey')
                ];
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, $typeinquest, $options);

            case 'default_contract_alert':
                $options['name']  = $name;
                $options['value'] = $values[$field];
                return Contract::dropdownAlert($options);

            case 'default_infocom_alert':
                $options['name']  = $name;
                $options['value'] = $values[$field];
                return Infocom::dropdownAlert($options);

            case 'entities_id_software':
                $options['toadd'] = [self::CONFIG_NEVER => __('No change of entity')]; // Keep software in PC entity
                $options['toadd'][self::CONFIG_PARENT] = __('Inheritance of the parent entity');

                return self::dropdown($options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function getRights($interface = 'central')
    {
        $values = parent::getRights();
        $values[self::READHELPDESK]   = ['short' => __('Read parameters'),
            'long'  => __('Read helpdesk parameters')
        ];
        $values[self::UPDATEHELPDESK] = ['short' => __('Update parameters'),
            'long'  => __('Update helpdesk parameters')
        ];

        return $values;
    }

    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {
        switch ($field['type']) {
            case 'setlocation':
                $this->showMap();
                break;
            default:
                throw new \RuntimeException("Unknown {$field['type']}");
        }
    }

    public static function inheritedValue($value = "", bool $inline = false, bool $display = true): string
    {
        if (trim($value) === '') {
            return "";
        }

        $value = htmlspecialchars($value);
        $out = "<div class='badge bg-azure-lt m-1 py-1 " . ($inline ? "inline" : "") . "'
                   title='" . __s("Value inherited from a parent entity") . "'
                   data-bs-toggle='tooltip'>
         <i class='fas fa-level-down-alt me-1'></i>
         $value
      </div>";

        if ($display) {
            echo $out;
            return "";
        }

        return $out;
    }

    public static function getIcon()
    {
        return "ti ti-stack";
    }

    public static function getAnonymizeConfig(?int $entities_id = null)
    {
        if ($entities_id === null) {
            $entities_id = Session::getActiveEntity();
        }
        return self::getUsedConfig('anonymize_support_agents', $entities_id);
    }

    public static function getDefaultContract(int $entities_id): int
    {
        $entity_default_contract_strategy = self::getUsedConfig('contracts_strategy_default', $entities_id);

        if ($entity_default_contract_strategy === self::CONFIG_AUTO) {
            // Contract in current entity
            $contract = new Contract();
            $criteria = [
                'entities_id' => $entities_id,
                'is_deleted'  => 0,
                'is_template' => 0,
            ];
            $criteria[] = Contract::getNotExpiredCriteria();
            $contracts = $contract->find($criteria);

            return count($contracts) ? current($contracts)['id'] : 0;
        }

        return self::getUsedConfig('contracts_strategy_default', $entities_id, 'contracts_id_default', 0);
    }

    /**
     * Return HTML code for entity badge showing its completename.
     *
     * @param string $entity_string
     * @param string|null $title
     * @return string
     */
    public static function badgeCompletename(string $entity_string = "", ?string $title = null): string
    {
        $names  = explode(' > ', trim($entity_string));

        // Construct HTML with special chars encoded.
        if ($title === null) {
            $title = htmlspecialchars(implode(' > ', $names));
        }
        $breadcrumbs = implode(
            '<i class="fas fa-caret-right mx-1"></i>',
            array_map(
                static fn (string $name) => '<span class="text-nowrap">' . htmlspecialchars($name) . '</span>',
                $names
            )
        );

        return '<span class="glpi-badge" title="' . $title . '">' . $breadcrumbs . "</span>";
    }

    /**
     * Return HTML code for entity badge showing its completename.
     *
     * @param int $entity_id
     *
     * @return string|null
     */
    public static function badgeCompletenameById(int $entity_id): ?string
    {
        $entity = new self();
        if ($entity->getFromDB($entity_id)) {
            return self::badgeCompletename($entity->fields['completename']);
        }
        return null;
    }

    /**
     * Return HTML code for entity badge showing its completename with last entity as HTML link.
     *
     * @param object $entity
     *
     * @return string
     */
    public static function badgeCompletenameLink(object $entity): string
    {
        $names = explode(' > ', trim($entity->fields['completename']));

        // Construct HTML with special chars encoded.
        $title       = htmlspecialchars(implode(' > ', $names));
        $last_name   = array_pop($names);
        $breadcrumbs = implode(
            '<i class="fas fa-caret-right mx-1"></i>',
            array_map(
                static fn (string $name) => '<span class="text-nowrap text-muted">' . htmlspecialchars($name) . '</span>',
                $names
            )
        );

        $last_url  = '<i class="fas fa-caret-right mx-1"></i>' . '<a href="' . $entity->getLinkURL() . '" title="' . $title . '">' . htmlspecialchars($last_name) . '</a>';

        return '<span class="glpi-badge" title="' . $title . '">' . $breadcrumbs . $last_url . '</span>';
    }

    /**
     * Return HTML code for entity badge showing its completename with last entity as HTML link.
     *
     * @param int $entity_id
     *
     * @return string|null
     * @used-by templates/components/itilobject/fields_panel.html.twig
     */
    public static function badgeCompletenameLinkById(int $entity_id): ?string
    {
        $entity = new self();
        if ($entity->getFromDB($entity_id)) {
            return self::badgeCompletenameLink($entity);
        }
        return null;
    }

    private static function getEntityTree(int $entities_id_root): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $sons = getSonsOf('glpi_entities', $entities_id_root);
        if (!isset($sons[$entities_id_root])) {
            $sons[$entities_id_root] = $entities_id_root;
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'entities_id'],
            'FROM'   => 'glpi_entities',
            'WHERE'  => ['entities_id' => $sons],
            'ORDER'  => 'name'
        ]);

        $grouped = [];
        foreach ($iterator as $row) {
            if (!array_key_exists($row['entities_id'], $grouped)) {
                $grouped[$row['entities_id']] = [];
            }
            $grouped[$row['entities_id']][] = [
                'id'   => $row['id'],
                'name' => $row['name']
            ];
        }

        \Glpi\Debug\Profiler::getInstance()->start('constructTreeFromList');
        $fn_construct_tree_from_list = static function (array $list, int $root) use (&$fn_construct_tree_from_list): array {
            $tree = [];
            if (array_key_exists($root, $list)) {
                foreach ($list[$root] as $data) {
                    $tree[$data['id']] = [
                        'name' => $data['name'],
                        'tree' => $fn_construct_tree_from_list($list, $data['id']),
                    ];
                }
            }
            return $tree;
        };

        $constructed = $fn_construct_tree_from_list($grouped, $entities_id_root);
        \Glpi\Debug\Profiler::getInstance()->stop('constructTreeFromList');
        return [
            $entities_id_root => [
                'name' => Dropdown::getDropdownName('glpi_entities', $entities_id_root),
                'tree' => $constructed,
            ],
        ];
    }

    public static function getEntitySelectorTree(): array
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $base_path = $CFG_GLPI['root_doc'] . "/front/central.php";
        if (Session::getCurrentInterface() === 'helpdesk') {
            $base_path = $CFG_GLPI["root_doc"] . "/front/helpdesk.public.php";
        }

        $ancestors = getAncestorsOf('glpi_entities', $_SESSION['glpiactive_entity']);

        \Glpi\Debug\Profiler::getInstance()->start('Generate entity tree');
        $entitiestree = [];
        foreach ($_SESSION['glpiactiveprofile']['entities'] as $default_entity) {
            $default_entity_id = $default_entity['id'];
            $entitytree = $default_entity['is_recursive'] ? self::getEntityTree($default_entity_id) : [$default_entity['id'] => $default_entity];

            $adapt_tree = static function (&$entities) use (&$adapt_tree, $base_path) {
                foreach ($entities as $entities_id => &$entity) {
                    $entity['key']   = $entities_id;

                    $title = "<a href='$base_path?active_entity={$entities_id}'>{$entity['name']}</a>";
                    $entity['title'] = $title;
                    unset($entity['name']);

                    if (isset($entity['tree']) && count($entity['tree']) > 0) {
                        $entity['folder'] = true;

                        $entity['title'] .= "<a href='$base_path?active_entity={$entities_id}&is_recursive=1'>
            <i class='fas fa-angle-double-down ms-1' data-bs-toggle='tooltip' data-bs-placement='right' title='" . __('+ sub-entities') . "'></i>
            </a>";

                        $children = $adapt_tree($entity['tree']);
                        $entity['children'] = array_values($children);
                    }

                    unset($entity['tree']);
                }

                return $entities;
            };
            $adapt_tree($entitytree);

            $entitiestree = array_merge($entitiestree, $entitytree);
        }
        \Glpi\Debug\Profiler::getInstance()->stop('Generate entity tree');

        /* scans the tree to select the active entity */
        $select_tree = static function (&$entities) use (&$select_tree, $ancestors) {
            foreach ($entities as &$entity) {
                if (isset($ancestors[$entity['key']])) {
                    $entity['expanded'] = 'true';
                }
                if ($entity['key'] == $_SESSION['glpiactive_entity']) {
                    $entity['selected'] = 'true';
                }
                if (isset($entity['children'])) {
                    $select_tree($entity['children']);
                }
            }
        };
        $select_tree($entitiestree);

        return $entitiestree;
    }
}
