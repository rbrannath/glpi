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
use Glpi\Plugin\Hooks;

/**
 * Rule Class store all information about a GLPI rule :
 *   - description
 *   - criterias
 *   - actions
 **/
class Rule extends CommonDBTM
{
    use Glpi\Features\Clonable;

    public $dohistory             = true;

   // Specific ones
   ///Actions affected to this rule
    public $actions               = [];
   ///Criterias affected to this rule
    public $criterias             = [];

   /// restrict matching to self::AND_MATCHING or self::OR_MATCHING : specify value to activate
    public $restrict_matching     = false;

    protected $rules_id_field     = 'rules_id';
    protected $ruleactionclass    = 'RuleAction';
    protected $rulecriteriaclass  = 'RuleCriteria';

    public $specific_parameters   = false;

    public $regex_results         = [];
    public $criterias_results     = [];

    public static $rightname             = 'config';

    public const RULE_NOT_IN_CACHE       = -1;
    public const RULE_WILDCARD           = '*';

    // Generic rules engine
    public const PATTERN_IS              = 0;
    public const PATTERN_IS_NOT          = 1;
    public const PATTERN_CONTAIN         = 2;
    public const PATTERN_NOT_CONTAIN     = 3;
    public const PATTERN_BEGIN           = 4;
    public const PATTERN_END             = 5;
    public const REGEX_MATCH             = 6;
    public const REGEX_NOT_MATCH         = 7;
    public const PATTERN_EXISTS          = 8;
    public const PATTERN_DOES_NOT_EXISTS = 9;
    public const PATTERN_FIND            = 10; // Global criteria
    public const PATTERN_UNDER           = 11;
    public const PATTERN_NOT_UNDER       = 12;
    public const PATTERN_IS_EMPTY        = 30; // Global criteria
    public const PATTERN_CIDR            = 333;
    public const PATTERN_NOT_CIDR        = 334;

    // Specific to date and datetime
    public const PATTERN_DATE_IS_BEFORE    = 31;
    public const PATTERN_DATE_IS_AFTER     = 32;
    public const PATTERN_DATE_IS_EQUAL     = 33;
    public const PATTERN_DATE_IS_NOT_EQUAL = 34;

    public const AND_MATCHING            = "AND";
    public const OR_MATCHING             = "OR";

    public function getCloneRelations(): array
    {
        return [
            RuleAction::class,
            RuleCriteria::class
        ];
    }

    public static function getTable($classname = null)
    {
        return parent::getTable(__CLASS__);
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Rule', 'Rules', $nb);
    }

    /**
     *  Get correct Rule object for specific rule
     *
     *  @since 0.84
     *
     *  @param integer $rules_id ID of the rule
     **/
    public static function getRuleObjectByID($rules_id)
    {
        $rule = new self();
        if ($rule->getFromDB($rules_id)) {
            if (class_exists($rule->fields['sub_type'])) {
                $realrule = new $rule->fields['sub_type']();
                return $realrule;
            }
        }
        return null;
    }

    /**
     *  Get condition array for rule. If empty array no condition used
     *  maybe overridden to define conditions using binary combination :
     *   example array(1 => Condition1,
     *                 2 => Condition2,
     *                 3 => Condition1&Condition2)
     *
     *  @since 0.85
     *
     *  @return array of conditions
     **/
    public static function getConditionsArray()
    {
        return [];
    }

    /**
     * Is this rule use condition
     *
     * @return boolean
     **/
    public function useConditions()
    {
        return (count(static::getConditionsArray()) > 0);
    }

    /**
     * Display a dropdown with all the rule conditions
     *
     * @since 0.85
     *
     * @param array $options array of parameters
     **/
    public static function dropdownConditions($options = [])
    {
        $p = array_replace([
            'name'      => 'condition',
            'value'     => 0,
            'display'   => true,
            'on_change' => ''
        ], $options);

        $elements = static::getConditionsArray();
        if (count($elements)) {
            return Dropdown::showFromArray($p['name'], $elements, $p);
        }

        return false;
    }

    /**
     * Get rule condition type Name
     *
     * @param integer $value condition ID
     *
     * @return string
     **/
    public static function getConditionName($value)
    {
        $cond = static::getConditionsArray();
        return $cond[$value] ?? NOT_AVAILABLE;
    }

    public static function getMenuContent()
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $menu = [];

        if (
            Session::haveRight("rule_ldap", READ)
            || Session::haveRight("rule_import", READ)
            || Session::haveRight("rule_location", READ)
            || Session::haveRight("rule_ticket", READ)
            || Session::haveRight("rule_change", READ)
            || Session::haveRight("rule_softwarecategories", READ)
            || Session::haveRight("rule_mailcollector", READ)
        ) {
            $menu['rule']['title'] = static::getTypeName(Session::getPluralNumber());
            $menu['rule']['page']  = static::getSearchURL(false);
            $menu['rule']['icon']  = static::getIcon();

            foreach ($CFG_GLPI["rulecollections_types"] as $rulecollectionclass) {
                $rulecollection = new $rulecollectionclass();
                if ($rulecollection->canList()) {
                    $ruleclassname = $rulecollection->getRuleClassName();
                    $menu['rule']['options'][$rulecollection->menu_option]['title']
                              = $rulecollection->getRuleClass()->getTitle();
                    $menu['rule']['options'][$rulecollection->menu_option]['page']
                              = $ruleclassname::getSearchURL(false);
                    $menu['rule']['options'][$rulecollection->menu_option]['links']['search']
                              = $ruleclassname::getSearchURL(false);
                    if ($rulecollection->canCreate()) {
                        $menu['rule']['options'][$rulecollection->menu_option]['links']['add']
                              = $ruleclassname::getFormURL(false);
                    }
                }
            }
        }

        if (
            Transfer::canView()
            && Session::isMultiEntitiesMode()
        ) {
            $menu['rule']['title'] = static::getTypeName(Session::getPluralNumber());
            $menu['rule']['page']  = static::getSearchURL(false);
            $menu['rule']['icon']  = static::getIcon();

            $menu['rule']['options']['transfer']['title']           = __('Transfer');
            $menu['rule']['options']['transfer']['page']            = "/front/transfer.php";
            $menu['rule']['options']['transfer']['links']['search'] = "/front/transfer.php";

            if (Session::haveRightsOr("transfer", [CREATE, UPDATE])) {
                $menu['rule']['options']['transfer']['links']['transfer_list']
                                                                 = "/front/transfer.action.php";
                $menu['rule']['options']['transfer']['links']['add'] = Transfer::getFormURL(false);
            }
        }

        if (
            Session::haveRight("rule_dictionnary_dropdown", READ)
            || Session::haveRight("rule_dictionnary_software", READ)
            || Session::haveRight("rule_dictionnary_printer", READ)
        ) {
            $menu['dictionnary']['title']    = _n('Dictionary', 'Dictionaries', Session::getPluralNumber());
            $menu['dictionnary']['shortcut'] = '';
            $menu['dictionnary']['page']     = '/front/dictionnary.php';
            $menu['dictionnary']['icon']     = static::getIcon();

            $menu['dictionnary']['options']['manufacturers']['title']
                           = _n('Manufacturer', 'Manufacturers', Session::getPluralNumber());
            $menu['dictionnary']['options']['manufacturers']['page']
                           = '/front/ruledictionnarymanufacturer.php';
            $menu['dictionnary']['options']['manufacturers']['links']['search']
                           = '/front/ruledictionnarymanufacturer.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['manufacturers']['links']['add']
                              = '/front/ruledictionnarymanufacturer.form.php';
            }

            $menu['dictionnary']['options']['software']['title']
                           = _n('Software', 'Software', Session::getPluralNumber());
            $menu['dictionnary']['options']['software']['page']
                           = '/front/ruledictionnarysoftware.php';
            $menu['dictionnary']['options']['software']['links']['search']
                           = '/front/ruledictionnarysoftware.php';

            if (RuleDictionnarySoftware::canCreate()) {
                $menu['dictionnary']['options']['software']['links']['add']
                              = '/front/ruledictionnarysoftware.form.php';
            }

            $menu['dictionnary']['options']['model.computer']['title']
                           = _n('Computer model', 'Computer models', Session::getPluralNumber());
            $menu['dictionnary']['options']['model.computer']['page']
                           = '/front/ruledictionnarycomputermodel.php';
            $menu['dictionnary']['options']['model.computer']['links']['search']
                           = '/front/ruledictionnarycomputermodel.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['model.computer']['links']['add']
                              = '/front/ruledictionnarycomputermodel.form.php';
            }

            $menu['dictionnary']['options']['model.monitor']['title']
                           = _n('Monitor model', 'Monitor models', Session::getPluralNumber());
            $menu['dictionnary']['options']['model.monitor']['page']
                           = '/front/ruledictionnarymonitormodel.php';
            $menu['dictionnary']['options']['model.monitor']['links']['search']
                           = '/front/ruledictionnarymonitormodel.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['model.monitor']['links']['add']
                              = '/front/ruledictionnarymonitormodel.form.php';
            }

            $menu['dictionnary']['options']['model.printer']['title']
                           = _n('Printer model', 'Printer models', Session::getPluralNumber());
            $menu['dictionnary']['options']['model.printer']['page']
                           = '/front/ruledictionnaryprintermodel.php';
            $menu['dictionnary']['options']['model.printer']['links']['search']
                           = '/front/ruledictionnaryprintermodel.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['model.printer']['links']['add']
                              = '/front/ruledictionnaryprintermodel.form.php';
            }

            $menu['dictionnary']['options']['model.peripheral']['title']
                           = _n('Peripheral model', 'Peripheral models', Session::getPluralNumber());
            $menu['dictionnary']['options']['model.peripheral']['page']
                           = '/front/ruledictionnaryperipheralmodel.php';
            $menu['dictionnary']['options']['model.peripheral']['links']['search']
                           = '/front/ruledictionnaryperipheralmodel.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['model.peripheral']['links']['add']
                              = '/front/ruledictionnaryperipheralmodel.form.php';
            }

            $menu['dictionnary']['options']['model.networking']['title']
                           = _n('Networking equipment model', 'Networking equipment models', Session::getPluralNumber());
            $menu['dictionnary']['options']['model.networking']['page']
                           = '/front/ruledictionnarynetworkequipmentmodel.php';
            $menu['dictionnary']['options']['model.networking']['links']['search']
                           = '/front/ruledictionnarynetworkequipmentmodel.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['model.networking']['links']['add']
                              = '/front/ruledictionnarynetworkequipmentmodel.form.php';
            }

            $menu['dictionnary']['options']['model.phone']['title']
                           = _n('Phone model', 'Phone models', Session::getPluralNumber());
            $menu['dictionnary']['options']['model.phone']['page']
                           = '/front/ruledictionnaryphonemodel.php';
            $menu['dictionnary']['options']['model.phone']['links']['search']
                           = '/front/ruledictionnaryphonemodel.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['model.phone']['links']['add']
                              = '/front/ruledictionnaryphonemodel.form.php';
            }

            $menu['dictionnary']['options']['type.computer']['title']
                           = _n('Computer type', 'Computer types', Session::getPluralNumber());
            $menu['dictionnary']['options']['type.computer']['page']
                           = '/front/ruledictionnarycomputertype.php';
            $menu['dictionnary']['options']['type.computer']['links']['search']
                           = '/front/ruledictionnarycomputertype.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['type.computer']['links']['add']
                              = '/front/ruledictionnarycomputertype.form.php';
            }

            $menu['dictionnary']['options']['type.monitor']['title']
                           = _n('Monitor type', 'Monitors types', Session::getPluralNumber());
            $menu['dictionnary']['options']['type.monitor']['page']
                           = '/front/ruledictionnarymonitortype.php';
            $menu['dictionnary']['options']['type.monitor']['links']['search']
                           = '/front/ruledictionnarymonitortype.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['type.monitor']['links']['add']
                              = '/front/ruledictionnarymonitortype.form.php';
            }

            $menu['dictionnary']['options']['type.printer']['title']
                           = _n('Printer type', 'Printer types', Session::getPluralNumber());
            $menu['dictionnary']['options']['type.printer']['page']
                           = '/front/ruledictionnaryprintertype.php';
            $menu['dictionnary']['options']['type.printer']['links']['search']
                           = '/front/ruledictionnaryprintertype.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['type.printer']['links']['add']
                              = '/front/ruledictionnaryprintertype.form.php';
            }

            $menu['dictionnary']['options']['type.peripheral']['title']
                           = _n('Peripheral type', 'Peripheral types', Session::getPluralNumber());
            $menu['dictionnary']['options']['type.peripheral']['page']
                           = '/front/ruledictionnaryperipheraltype.php';
            $menu['dictionnary']['options']['type.peripheral']['links']['search']
                           = '/front/ruledictionnaryperipheraltype.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['type.peripheral']['links']['add']
                              = '/front/ruledictionnaryperipheraltype.form.php';
            }

            $menu['dictionnary']['options']['type.networking']['title']
                           = _n('Networking equipment type', 'Networking equipment types', Session::getPluralNumber());
            $menu['dictionnary']['options']['type.networking']['page']
                           = '/front/ruledictionnarynetworkequipmenttype.php';
            $menu['dictionnary']['options']['type.networking']['links']['search']
                           = '/front/ruledictionnarynetworkequipmenttype.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['type.networking']['links']['add']
                              = '/front/ruledictionnarynetworkequipmenttype.form.php';
            }

            $menu['dictionnary']['options']['type.phone']['title']
                           = _n('Phone type', 'Phone types', Session::getPluralNumber());
            $menu['dictionnary']['options']['type.phone']['page']
                           = '/front/ruledictionnaryphonetype.php';
            $menu['dictionnary']['options']['type.phone']['links']['search']
                           = '/front/ruledictionnaryphonetype.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['type.phone']['links']['add']
                              = '/front/ruledictionnaryphonetype.form.php';
            }

            $menu['dictionnary']['options']['os']['title']
                           = OperatingSystem::getTypeName(1);
            $menu['dictionnary']['options']['os']['page']
                           = '/front/ruledictionnaryoperatingsystem.php';
            $menu['dictionnary']['options']['os']['links']['search']
                           = '/front/ruledictionnaryoperatingsystem.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['os']['links']['add']
                              = '/front/ruledictionnaryoperatingsystem.form.php';
            }

            $menu['dictionnary']['options']['os_sp']['title']
                           = OperatingSystemServicePack::getTypeName(1);
            $menu['dictionnary']['options']['os_sp']['page']
                           = '/front/ruledictionnaryoperatingsystemservicepack.php';
            $menu['dictionnary']['options']['os_sp']['links']['search']
                           = '/front/ruledictionnaryoperatingsystemservicepack.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['os_sp']['links']['add']
                              = '/front/ruledictionnaryoperatingsystemservicepack.form.php';
            }

            $menu['dictionnary']['options']['os_version']['title']
                           = OperatingSystemVersion::getTypeName(1);
            $menu['dictionnary']['options']['os_version']['page']
                           = '/front/ruledictionnaryoperatingsystemversion.php';
            $menu['dictionnary']['options']['os_version']['links']['search']
                           = '/front/ruledictionnaryoperatingsystemversion.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['os_version']['links']['add']
                              = '/front/ruledictionnaryoperatingsystemversion.form.php';
            }

            $menu['dictionnary']['options']['os_arch']['title']
                           = OperatingSystemArchitecture::getTypeName(1);
            $menu['dictionnary']['options']['os_arch']['page']
                           = '/front/ruledictionnaryoperatingsystemarchitecture.php';
            $menu['dictionnary']['options']['os_arch']['links']['search']
                           = '/front/ruledictionnaryoperatingsystemarchitecture.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['os_arch']['links']['add']
                              = '/front/ruledictionnaryoperatingsystemarchitecture.form.php';
            }

            $menu['dictionnary']['options']['os_edition']['title']
            = OperatingSystemEdition::getTypeName(1);
            $menu['dictionnary']['options']['os_edition']['page']
                        = '/front/ruledictionnaryoperatingsystemedition.php';
            $menu['dictionnary']['options']['os_edition']['links']['search']
                        = '/front/ruledictionnaryoperatingsystemedition.php';

            if (RuleDictionnaryDropdown::canCreate()) {
                $menu['dictionnary']['options']['os_edition']['links']['add']
                        = '/front/ruledictionnaryoperatingsystemedition.form.php';
            }

            $menu['dictionnary']['options']['printer']['title']
                           = _n('Printer', 'Printers', Session::getPluralNumber());
            $menu['dictionnary']['options']['printer']['page']
                           = '/front/ruledictionnaryprinter.php';
            $menu['dictionnary']['options']['printer']['links']['search']
                           = '/front/ruledictionnaryprinter.php';

            if (RuleDictionnaryPrinter::canCreate()) {
                $menu['dictionnary']['options']['printer']['links']['add']
                              = '/front/ruledictionnaryprinter.form.php';
            }
        }

        if (count($menu)) {
            $menu['is_multi_entries'] = true;
            return $menu;
        }

        return false;
    }

    public function getRuleActionClass()
    {
        return $this->ruleactionclass;
    }

    public function getRuleCriteriaClass()
    {
        return $this->rulecriteriaclass;
    }

    public function getRuleIdField()
    {
        return $this->rules_id_field;
    }

    public function isEntityAssign()
    {
        return false;
    }

    public function post_getEmpty()
    {
        $this->fields['is_active'] = 0;
    }

    /**
     * Get title used in rule
     *
     * @return string Title of the rule
     **/
    public function getTitle()
    {
        return __('Rules management');
    }

    /**
     * @since 0.84
     *
     * @return class-string<RuleCollection>
     **/
    public function getCollectionClassName()
    {
        $parent = static::class;
        do {
            $collection_class = $parent . 'Collection';
            $parent = get_parent_class($parent);
        } while ($parent !== 'CommonDBTM' && $parent !== false && !class_exists($collection_class));
        if ($collection_class === null) {
            throw new \LogicException(sprintf('Unable to find collection class for `%s`.', static::getType()));
        }
        return $collection_class;
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (!$this->isEntityAssign()) {
            unset($actions[MassiveAction::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_transfer_list']);
        }
        if ($isadmin) {
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'move_rule'] = "<i class='fas fa-arrows-alt-v'></i>"
                . __s('Move');
        }
        $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'export'] = "<i class='fas fa-file-download'></i>"
            . _sx('button', 'Export');

        return $actions;
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'move_rule':
                $input = $ma->getInput();
                $values = [
                    RuleCollection::MOVE_AFTER  => __('After'),
                    RuleCollection::MOVE_BEFORE => __('Before')
                ];
                Dropdown::showFromArray('move_type', $values, ['width' => '20%']);

                $entity = $input['entity'] ?? "";
                $condition = $input['condition'] ?? 0;
                echo Html::hidden('rule_class_name', ['value' => $input['rule_class_name']]);

                self::dropdown([
                    'sub_type'        => $input['rule_class_name'],
                    'name'            => "ranking",
                    'condition'       => $condition,
                    'entity'          => $entity,
                    'width'           => '50%',
                    'order'           => 'ranking'
                ]);
                echo "<br><br><input type='submit' name='massiveaction' class='btn btn-primary' value='" .
                           _sx('button', 'Move') . "'>\n";
                return true;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'export':
                if (count($ids)) {
                    $_SESSION['exportitems'] = $ids;
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
                    $ma->setRedirect('rule.backup.php?action=download&itemtype=' . $item::class);
                }
                break;

            case 'move_rule':
                $input          = $ma->getInput();
                $collectionname = $input['rule_class_name'] . 'Collection';
                $rulecollection = new $collectionname();
                if ($rulecollection->canUpdate()) {
                    foreach ($ids as $id) {
                        if ($item->getFromDB($id)) {
                            if ($rulecollection->moveRule($id, $input['ranking'], $input['move_type'])) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                }
                break;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function getForbiddenSingleMassiveActions()
    {
        $excluded = parent::getForbiddenSingleMassiveActions();
        $excluded[] = __CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'move_rule';
        return $excluded;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '1',
            'table'              => static::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
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
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'ranking',
            'name'               => __('Ranking'),
            'datatype'           => 'number',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'description',
            'name'               => __('Description'),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'match',
            'name'               => __('Logical operator'),
            'datatype'           => 'specific',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => static::getTable(),
            'field'              => 'is_active',
            'name'               => __('Active'),
            'datatype'           => 'bool'
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
            'field'              => 'sub_type',
            'name'               => __('Subtype'),
            'datatype'           => 'text'
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '86',
            'table'              => static::getTable(),
            'field'              => 'is_recursive',
            'name'               => __('Child entities'),
            'datatype'           => 'bool',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '19',
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

        return $tab;
    }

    /**
     * @param  string $field
     * @param  array $values
     * @param  array $options
     *
     * @return string
     **/
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        if (isset($options['searchopt']['real_type'])) {
            $ruleclass = new $options['searchopt']['real_type']();
            return $ruleclass->getSpecificValueToDisplay($field, $values, $options);
        }

        if ($field === 'match') {
            return match ($values[$field]) {
                self::AND_MATCHING => __('AND'),
                self::OR_MATCHING => __('OR'),
                default => NOT_AVAILABLE
            };
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * @param  string $field
     * @param  string $name              (default '')
     * @param  string|array $values            (default '')
     * @param  array $options
     *
     * @return string
     **/
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        if (isset($options['searchopt']['real_type'])) {
            $ruleclass = new $options['searchopt']['real_type']();
            return $ruleclass->getSpecificValueToSelect($field, $name, $values, $options);
        }

        switch ($field) {
            case 'match':
                if (!empty($values['itemtype'])) {
                    $options['value'] = $values[$field];
                    $options['name']  = $name;
                    $rule             = new static();
                    return $rule->dropdownRulesMatch($options);
                }
                break;
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Show the rule
     *
     * @param integer $ID    ID of the rule
     * @param array $options array of possible options:
     *     - target filename : where to go when done.
     *     - withtemplate boolean : template or basic item
     *
     * @return void
     **/
    public function showForm($ID, array $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $new_item = static::isNewID($ID);
        if (!$new_item) {
            $this->check($ID, READ);
        } else {
           // Create item
            $this->checkGlobal(UPDATE);
        }

        $canedit = $this->canEdit(static::$rightname);
        $rand = mt_rand();
        $this->showFormHeader($options);

        $plugin = isPluginItemType(static::class);
        $base_url = $CFG_GLPI["root_doc"] . ($plugin !== false ? Plugin::getWebDir($plugin['plugin'], false) : '');

        $add_buttons = [];
        if (!$new_item && $canedit) {
            $add_buttons = [
                [
                    'text' => _x('button', 'Test'),
                    'type' => 'button',
                    'onclick' => "$('#ruletestmodal').modal('show');",
                ]
            ];
        }
        TemplateRenderer::getInstance()->display('pages/admin/rules/form.html.twig', [
            'item' => $this,
            'match_operators' => $this->getRulesMatch(),
            'conditions' => static::getConditionsArray(),
            'rand' => $rand,
            'test_url' => $base_url . "/front/rule.test.php",
            'params' => [
                'canedit' => $canedit,
                'addbuttons' => $add_buttons,
            ]
        ]);
    }

    /**
     * Get all logical operators supported by the rule type for matching criteria
     * @param string|null $value Value to restrict the result to. If null, all supported operators are returned.
     * @return array Array of operators with their localized name
     * @phpstan-return array<string, string>
     */
    public function getRulesMatch(?string $value = null): array
    {
        $elements = [];
        if ($value === null || $value === self::AND_MATCHING) {
            $elements[self::AND_MATCHING] = __('AND');
        }

        if ($value === null || $value === self::OR_MATCHING) {
            $elements[self::OR_MATCHING]  = __('OR');
        }
        return $elements;
    }

    /**
     * Display a dropdown with all the rule matching
     *
     * @since 0.84 new proto
     *
     * @param array $options array of parameters
     *
     * @return integer|string
     **/
    protected function dropdownRulesMatch($options = [])
    {
        $p = array_replace([
            'name'     => 'match',
            'value'    => '',
            'restrict' => $this->restrict_matching,
            'display'  => true
        ], $options);
        $p['restrict'] = is_string($p['restrict']) ? $p['restrict'] : null;

        return Dropdown::showFromArray($p['name'], $this->getRulesMatch($p['restrict']), $p);
    }

    /**
     * Get all criteria for a given rule
     *
     * @param integer $ID              the rule_description ID
     * @param boolean $withcriterias   1 to retrieve all the criteria for a given rule (default 0)
     * @param boolean $withactions     1 to retrieve all the actions for a given rule (default 0)
     *
     * @return boolean
     **/
    public function getRuleWithCriteriasAndActions($ID, $withcriterias = 0, $withactions = 0)
    {
        if (static::isNewID($ID)) {
            return $this->getEmpty();
        }
        if ($this->getFromDB($ID)) {
            if (
                $withactions
                && ($RuleAction = getItemForItemtype($this->ruleactionclass))
            ) {
                $this->actions = $RuleAction->getRuleActions($ID);
            }

            if (
                $withcriterias
                && ($RuleCriterias = getItemForItemtype($this->rulecriteriaclass))
            ) {
                $this->criterias = $RuleCriterias->getRuleCriterias($ID);
            }

            return true;
        }

        return false;
    }

    /**
     * display title for action form
     **/
    public function getTitleAction()
    {
        foreach ($this->getActions() as $val) {
            if (
                isset($val['force_actions'])
                && (in_array('regex_result', $val['force_actions'], true)
                 || in_array('append_regex_result', $val['force_actions'], true))
            ) {
                echo "<div class='alert alert-info'>" . __s('It is possible to affect the result of a regular expression using the string #0') . "</div>";
                return;
            }
        }
    }

    /**
     * Get maximum number of Actions of the Rule (0 = unlimited)
     *
     * @return integer the maximum number of actions
     **/
    public function maxActionsCount()
    {
        return count(array_filter($this->getAllActions(), static fn ($action_obj) => !isset($action_obj['duplicatewith'])));
    }

    /**
     * Display all rules actions
     *
     * @param integer $rules_id rule ID
     * @param array   $options  array of options : may be readonly
     *
     * @return void
     **/
    public function showActionsList($rules_id, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $rand = mt_rand();
        $p['readonly'] = false;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $canedit = $this->canEdit($rules_id);

        if ($p['readonly']) {
            $canedit = false;
        }
        $this->getTitleAction();

        if ($canedit) {
            echo "<div id='viewaction{$rules_id}{$rand}'></div>";
        }

        $max_actions_count = $this->maxActionsCount();
        if (
            $canedit
            && ($max_actions_count === 0
              || (count($this->actions) < $max_actions_count))
        ) {
            $params = [
                'type'                => $this->ruleactionclass,
                'parenttype'          => static::class,
                $this->rules_id_field => $rules_id,
                'id'                  => -1
            ];
            $js = "function viewAddAction{$rules_id}{$rand}() {";
            $js .= Ajax::updateItemJsCode(
                toupdate: "viewaction{$rules_id}{$rand}",
                url: $CFG_GLPI["root_doc"] . "/ajax/viewsubitem.php",
                parameters: $params,
                display: false
            );
            $js .= "};";
            echo Html::scriptBlock($js);
            echo "<div class='center mt-1 mb-3'>" .
               "<a class='btn btn-primary' href='javascript:viewAddAction{$rules_id}{$rand}();'>";
            echo __('Add a new action') . "</a></div>";
        }

        $entries = [];
        foreach ($this->actions as $action) {
            $field = $this->getActionName($action->fields['field']);
            $action_type = RuleAction::getActionByID($action->fields['action_type']);
            $value = isset($action->fields['value'])
                ? $this->getActionValue($action->fields['field'], $action->fields['action_type'], $action->fields['value'])
                : '';
            $entries[] = [
                'itemtype' => $this->ruleactionclass,
                'id' => $action->getID(),
                'field' => $field,
                'action_type' => $action_type,
                'value' => $value
            ];
        }

        $massiveactionparams = [
            'num_displayed'  => min($_SESSION['glpilist_limit'], count($entries)),
            'check_itemtype' => static::class,
            'check_items_id' => $rules_id,
            'container'      => 'mass' . $this->ruleactionclass . $rand,
            'extraparams'    => [
                'rule_class_name' => static::class
            ]
        ];

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'datatable_id' => 'datatable_ruleaction' . $rules_id . $rand,
            'is_tab' => true,
            'nofilter' => true,
            'columns' => [
                'field' => _n('Field', 'Fields', Session::getPluralNumber()),
                'action_type' => __('Action type'),
                'value' => __('Value')
            ],
            'entries' => $entries,
            'row_class' => 'cursor-pointer',
            'total_number' => count($entries),
            'filtered_number' => count($entries),
            'showmassiveactions' => $canedit,
            'massiveactionparams' => $massiveactionparams,
        ]);

        if ($canedit) {
            $rule_class = static::class;
            echo Html::scriptBlock(<<<JS
                $(() => {
                    $('#datatable_ruleaction{$rules_id}{$rand}').on('click', 'tbody tr', (e) => {
                        //ignore click in first column (the massive action checkbox)
                        if ($(e.target).closest('td').is('td:first-child')) {
                            return;
                        }
                        const action_id = $(e.currentTarget).data('id');
                        if (action_id) {
                            $('#viewaction{$rules_id}{$rand}').load('/ajax/viewsubitem.php',{
                                type: "{$this->ruleactionclass}",
                                parenttype: "{$rule_class}",
                                rules_id: $rules_id,
                                id: action_id
                            });
                        }
                    });
                });
JS
            );
        }
    }

    public function maybeRecursive()
    {
        return false;
    }

    /**
     * Display all rules criteria
     *
     * @param integer $rules_id
     * @param array $options   array of options : may be readonly
     *
     * @return void
     **/
    public function showCriteriasList($rules_id, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $rand = mt_rand();
        $p['readonly'] = false;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $canedit = $this->canEdit($rules_id);
        if ($canedit) {
            echo "<div id='viewcriteria{$rules_id}{$rand}'></div>";
            $params = [
                'type'                => $this->rulecriteriaclass,
                'parenttype'          => static::class,
                $this->rules_id_field => $rules_id,
                'id'                  => -1
            ];
            $js = "function viewAddCriteria{$rules_id}{$rand}() {";
            $js .= Ajax::updateItemJsCode(
                toupdate: "viewcriteria{$rules_id}{$rand}",
                url: $CFG_GLPI["root_doc"] . "/ajax/viewsubitem.php",
                parameters: $params,
                display: false
            );
            $js .= "};";
            echo Html::scriptBlock($js);
            echo "<div class='center mt-1 mb-3'>" .
                "<a class='btn btn-primary' href='javascript:viewAddCriteria{$rules_id}{$rand}();'>";
            echo __('Add a new criterion') . "</a></div>";
        }

        $entries = [];
        foreach ($this->criterias as $criteria) {
            $criterion = $this->getCriteriaName($criteria->fields["criteria"]);
            $condition = RuleCriteria::getConditionByID($criteria->fields["condition"], get_class($this), $criteria->fields["criteria"]);
            $pattern   = $this->getCriteriaDisplayPattern($criteria->fields["criteria"], $criteria->fields["condition"], $criteria->fields["pattern"]);
            $entries[] = [
                'itemtype' => $this->rulecriteriaclass,
                'id' => $criteria->getID(),
                'criteria' => $criterion,
                'condition' => $condition,
                'pattern' => $pattern
            ];
        }

        $massiveactionparams = [
            'num_displayed'  => min($_SESSION['glpilist_limit'], count($entries)),
            'check_itemtype' => static::class,
            'check_items_id' => $rules_id,
            'container'      => 'mass' . $this->rulecriteriaclass . $rand,
            'extraparams'    => [
                'rule_class_name' => static::class
            ]
        ];

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'datatable_id' => 'datatable_rulecriteria' . $rules_id . $rand,
            'is_tab' => true,
            'nofilter' => true,
            'columns' => [
                'criteria' => _n('Criterion', 'Criteria', 1),
                'condition' => __('Condition'),
                'pattern' => __('Reason')
            ],
            'entries' => $entries,
            'row_class' => 'cursor-pointer',
            'total_number' => count($entries),
            'filtered_number' => count($entries),
            'showmassiveactions' => $canedit,
            'massiveactionparams' => $massiveactionparams,
        ]);

        if ($canedit) {
            $rule_class = static::class;
            echo Html::scriptBlock(<<<JS
                $(() => {
                    $('#datatable_rulecriteria{$rules_id}{$rand}').on('click', 'tbody tr', (e) => {
                        //ignore click in first column (the massive action checkbox)
                        if ($(e.target).closest('td').is('td:first-child')) {
                            return;
                        }
                        const criteria_id = $(e.currentTarget).data('id');
                        if (criteria_id) {
                            $('#viewcriteria{$rules_id}{$rand}').load('/ajax/viewsubitem.php',{
                                type: "{$this->rulecriteriaclass}",
                                parenttype: "{$rule_class}",
                                rules_id: $rules_id,
                                id: criteria_id
                            });
                        }
                    });
                });
JS
            );
        }
    }

    /**
     * Display the dropdown of the criteria for the rule
     *
     * @since 0.84 new proto
     *
     * @param array $options array of options : may be readonly
     *
     * @return integer|string the initial value (first)
     **/
    public function dropdownCriteria($options = [])
    {
        $p['name']                = 'criteria';
        $p['display']             = true;
        $p['value']               = '';
        $p['display_emptychoice'] = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $items      = [];
        $group      = [];
        $groupname  = _n('Criterion', 'Criteria', Session::getPluralNumber());
        foreach ($this->getAllCriteria() as $ID => $crit) {
           // Manage group system
            if (!is_array($crit)) {
                if (count($group)) {
                    asort($group);
                    $items[$groupname] = $group;
                }
                $group     = [];
                $groupname = $crit;
            } else {
                $group[$ID] = $crit['name'];
            }
        }
        if (count($group)) {
            asort($group);
            $items[$groupname] = $group;
        }
        return Dropdown::showFromArray($p['name'], $items, $p);
    }

    /**
     * Display the dropdown of the actions for the rule
     *
     * @param array $options already used actions
     *
     * @return integer|string the initial value (first non used)
     **/
    public function dropdownActions($options = [])
    {
        $p['name']                = 'field';
        $p['display']             = true;
        $p['used']                = [];
        $p['value']               = '';
        $p['display_emptychoice'] = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $actions = $this->getAllActions();

       // For each used actions see if several set is available
       // Force actions to available actions for several
        foreach ($p['used'] as $key => $ID) {
            if (isset($actions[$ID]['permitseveral'])) {
                unset($p['used'][$key]);
            }
        }

       // Complete used array with duplicate items
       // add duplicates of used items
        foreach ($p['used'] as $ID) {
            if (isset($actions[$ID]['duplicatewith'])) {
                $p['used'][$actions[$ID]['duplicatewith']] = $actions[$ID]['duplicatewith'];
            }
        }

       // Parse for duplicates of already used items
        foreach ($actions as $ID => $act) {
            if (
                isset($actions[$ID]['duplicatewith'])
                && in_array($actions[$ID]['duplicatewith'], $p['used'])
            ) {
                $p['used'][$ID] = $ID;
            }
        }

        $items = [];
        foreach ($actions as $ID => $act) {
            $items[$ID] = $act['name'];
        }
        return Dropdown::showFromArray($p['name'], $items, $p);
    }

    /**
     * Get a criteria description by his ID
     *
     * @param integer $ID the criteria's ID
     *
     * @return array the criteria array
     **/
    public function getCriteria($ID)
    {
        $criteria = $this->getAllCriteria();
        return $criteria[$ID] ?? [];
    }

    /**
     * Get action description by its ID
     *
     * @param integer $ID the action's ID
     *
     * @return array the action array
     **/
    public function getAction($ID)
    {
        $actions = $this->getAllActions();
        return $actions[$ID] ?? [];
    }

    /**
     * Get a criteria description by his ID
     *
     * @param integer $ID the criteria's ID
     *
     * @return string the criteria's description
     **/
    public function getCriteriaName($ID)
    {
        $criteria = $this->getCriteria($ID);
        return $criteria['name'] ?? __('Unavailable');
    }

    /**
     * Get action description by his ID
     *
     * @param integer $ID the action's ID
     *
     * @return string the action's description
     **/
    public function getActionName($ID)
    {
        $action = $this->getAction($ID);
        return $action['name'] ?? '';
    }

    /**
     * Process the rule
     *
     * @param array &$input the input data used to check criterias
     * @param array &$output the initial ouput array used to be manipulate by actions
     * @param array &$params parameters for all internal functions
     * @param array &$options array options:
     *                     - only_criteria : only react on specific criteria
     *
     * @return void
     */
    public function process(&$input, &$output, &$params, &$options = [])
    {
        if ($this->validateCriterias($options)) {
            $this->regex_results     = [];
            $this->criterias_results = [];
            $input = $this->prepareInputDataForProcess($input, $params);

            if ($this->checkCriterias($input)) {
                unset($output["_no_rule_matches"]);
                $refoutput = $output;
                $output    = $this->executeActions($output, $params, $input);

                $this->updateOnlyCriteria($options, $refoutput, $output);
                //Hook
                $hook_params["sub_type"] = static::class;
                $hook_params["ruleid"]   = $this->fields["id"];
                $hook_params["input"]    = $input;
                $hook_params["output"]   = $output;
                Plugin::doHook(Hooks::RULE_MATCHED, $hook_params);
                $output["_rule_process"] = true;
            }
        }
    }

    /**
     * Update Only criteria options if needed
     *
     * @param array &$options   options :
     *                            - only_criteria : only react on specific criteria
     * @param array $refoutput   the initial output array used to be manipulated by actions
     * @param array $newoutput   the output array after actions process
     *
     * @return void
     **/
    public function updateOnlyCriteria(&$options, $refoutput, $newoutput)
    {
        if (count($this->actions)) {
            if (
                isset($options['only_criteria'])
                && !is_null($options['only_criteria'])
                && is_array($options['only_criteria'])
            ) {
                foreach ($this->actions as $action) {
                    if (
                        !isset($refoutput[$action->fields["field"]])
                        || ($refoutput[$action->fields["field"]]
                        != $newoutput[$action->fields["field"]])
                    ) {
                        if (!in_array($action->fields["field"], $options['only_criteria'])) {
                            $options['only_criteria'][] = $action->fields["field"];
                        }

                       // Add linked criteria if available
                        $crit = $this->getCriteria($action->fields["field"]);
                        if (isset($crit['linked_criteria'])) {
                            $tmp = $crit['linked_criteria'];
                            if (!is_array($crit['linked_criteria'])) {
                                $tmp = [$tmp];
                            }
                            foreach ($tmp as $toadd) {
                                if (!in_array($toadd, $options['only_criteria'])) {
                                    $options['only_criteria'][] = $toadd;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Are criteria valid to be processed
     *
     * @since 0.85
     *
     * @param array $options
     *
     * @return boolean
     **/
    public function validateCriterias($options)
    {
        if (count($this->criterias)) {
            if (
                isset($options['only_criteria'])
                && !is_null($options['only_criteria'])
                && is_array($options['only_criteria'])
            ) {
                foreach ($this->criterias as $criterion) {
                    if (in_array($criterion->fields['criteria'], $options['only_criteria'])) {
                         return true;
                    }
                }
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Check criteria
     *
     * @param array $input the input data used to check criteri
     *
     * @return boolean if criteria match
     **/
    public function checkCriterias($input)
    {
        reset($this->criterias);

        if ($this->fields["match"] == self::AND_MATCHING) {
            $doactions = true;

            foreach ($this->criterias as $criterion) {
                $definition_criterion = $this->getCriteria($criterion->fields['criteria']);
                if (!isset($definition_criterion['is_global']) || !$definition_criterion['is_global']) {
                    $doactions &= $this->checkCriteria($criterion, $input);
                    if (!$doactions) {
                        break;
                    }
                }
            }
        } else { // OR MATCHING
            $doactions = false;
            foreach ($this->criterias as $criterion) {
                $definition_criterion = $this->getCriteria($criterion->fields['criteria']);

                if (
                    !isset($definition_criterion['is_global'])
                    || !$definition_criterion['is_global']
                ) {
                    $doactions |= $this->checkCriteria($criterion, $input);
                    if ($doactions) {
                        break;
                    }
                }
            }
        }

       //If all simple criteria match, and if necessary, check complex criteria
        if ($doactions) {
            return $this->findWithGlobalCriteria($input);
        }
        return false;
    }

    /**
     * Check criteria
     *
     * @param array $input          the input data used to check criteria
     * @param array &$check_results
     *
     * @return void
     **/
    public function testCriterias($input, &$check_results)
    {
        reset($this->criterias);

        foreach ($this->criterias as $criterion) {
            $result = $this->checkCriteria($criterion, $input);
            $check_results[$criterion->fields["id"]]["name"]   = $criterion->fields["criteria"];
            $check_results[$criterion->fields["id"]]["value"]  = $criterion->fields["pattern"];
            $check_results[$criterion->fields["id"]]["result"] = ((!$result) ? 0 : 1);
            $check_results[$criterion->fields["id"]]["id"]     = $criterion->fields["id"];
        }
    }

    /**
     * Process a criteria of a rule
     *
     * @param RuleCriteria &$criteria  criteria to check
     * @param array &$input     the input data used to check criteria
     *
     * @return boolean
     **/
    public function checkCriteria(&$criteria, &$input)
    {
        $partial_regex_result = [];
        // Undefine criteria field : set to blank
        if (!isset($input[$criteria->fields["criteria"]])) {
            $input[$criteria->fields["criteria"]] = '';
        }

        // If the value is not an array
        if (!is_array($input[$criteria->fields["criteria"]])) {
            $value = $this->getCriteriaValue(
                $criteria->fields["criteria"],
                $criteria->fields["condition"],
                $input[$criteria->fields["criteria"]]
            );

            $res   = RuleCriteria::match(
                $criteria,
                $value,
                $this->criterias_results,
                $partial_regex_result
            );
        } else {
            // If the value is, in fact, an array of values
            // Negative condition : Need to match all condition (never be)
            if (
                in_array($criteria->fields["condition"], self::getNegativesConditions())
            ) {
                $res = true;
                foreach ($input[$criteria->fields["criteria"]] as $tmp) {
                    $value = $this->getCriteriaValue(
                        $criteria->fields["criteria"],
                        $criteria->fields["condition"],
                        $tmp
                    );

                    $res &= RuleCriteria::match(
                        $criteria,
                        $value,
                        $this->criterias_results,
                        $partial_regex_result
                    );
                    if (!$res) {
                           break;
                    }
                }
            } else {
                // Positive condition : Need to match one
                $res = false;
                foreach ($input[$criteria->fields["criteria"]] as $crit) {
                    $value = $this->getCriteriaValue(
                        $criteria->fields["criteria"],
                        $criteria->fields["condition"],
                        $crit
                    );

                    $res |= RuleCriteria::match(
                        $criteria,
                        $value,
                        $this->criterias_results,
                        $partial_regex_result
                    );
                }
            }
        }

        // Found regex on this criteria
        if (count($partial_regex_result)) {
            // No regex existing : put found
            if (!count($this->regex_results)) {
                $this->regex_results = $partial_regex_result;
            } else { // Already existing regex : append found values
                $temp_result = [];
                foreach ($partial_regex_result as $new) {
                    foreach ($this->regex_results as $old) {
                        $temp_result[] = array_merge($old, $new);
                    }
                }
                $this->regex_results = $temp_result;
            }
        }

        return $res;
    }

    /**
     * @param array $input
     *
     * return boolean
     */
    public function findWithGlobalCriteria($input)
    {
        return true;
    }

    /**
     * Specific prepare input data for the rule
     *
     * @param array $input  the input data used to check criteria
     * @param array $params parameters
     *
     * @return array the updated input data
     **/
    public function prepareInputDataForProcess($input, $params)
    {
        return $input;
    }

    /**
     * Get all data needed to process rules (core + plugins)
     *
     * @since 0.84
     * @param array $input  the input data used to check criteria
     * @param array $params parameters
     *
     * @return array the updated input data
     **/
    public function prepareAllInputDataForProcess($input, $params)
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        $input = $this->prepareInputDataForProcess($input, $params);
        if (isset($PLUGIN_HOOKS['use_rules'])) {
            foreach ($PLUGIN_HOOKS['use_rules'] as $plugin => $val) {
                if (!Plugin::isPluginActive($plugin)) {
                    continue;
                }
                if (is_array($val) && in_array(static::class, $val, true)) {
                    $results = Plugin::doOneHook(
                        $plugin,
                        "rulePrepareInputDataForProcess",
                        ['input'  => $input,
                            'params' => $params
                        ]
                    );
                    if (is_array($results)) {
                        foreach ($results as $result) {
                            $input[] = $result;
                        }
                    }
                }
            }
        }
        return $input;
    }

    /**
     * Execute plugins actions if needed.
     *
     * @since 9.3.2 Added $input parameter
     * @since 0.84
     *
     * @param RuleAction $action
     * @param array      $output  rule execution output
     * @param array      $params  parameters
     * @param array      $input   the input data
     *
     * @return array Updated output
     */
    public function executePluginsActions($action, $output, $params, array $input = [])
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        if (isset($PLUGIN_HOOKS['use_rules'])) {
            $params['criterias_results'] = $this->criterias_results;
            $params['rule_itemtype']     = static::class;
            foreach ($PLUGIN_HOOKS['use_rules'] as $plugin => $val) {
                if (!Plugin::isPluginActive($plugin)) {
                    continue;
                }
                if (is_array($val) && in_array(static::class, $val, true)) {
                    $results = Plugin::doOneHook($plugin, "executeActions", ['output' => $output,
                        'params' => $params,
                        'action' => $action,
                        'input'  => $input
                    ]);
                    if (is_array($results)) {
                        foreach ($results as $id => $result) {
                            $output[$id] = $result;
                        }
                    }
                }
            }
        }
        return $output;
    }

    /**
     * Execute the actions as defined in the rule.
     *
     * @since 9.3.2 Added $input parameter
     *
     * @param array $output  the fields to manipulate
     * @param array $params  parameters
     * @param array $input   the input data
     *
     * @return array Updated output
     **/
    public function executeActions($output, $params, array $input = [])
    {
        if (count($this->actions)) {
            foreach ($this->actions as $action) {
                switch ($action->fields["action_type"]) {
                    case "assign":
                        $output[$action->fields["field"]] = $action->fields["value"];
                        break;

                    case "append":
                        $actions = $this->getActions();
                        $value   = $action->fields["value"];
                        if (
                            isset($actions[$action->fields["field"]]["appendtoarray"])
                            && isset($actions[$action->fields["field"]]["appendtoarrayfield"])
                        ) {
                             $value = $actions[$action->fields["field"]]["appendtoarray"];
                             $value[$actions[$action->fields["field"]]["appendtoarrayfield"]]
                            = $action->fields["value"];
                        }
                        $output[$actions[$action->fields["field"]]["appendto"]][] = $value;
                        break;

                    case "regex_result":
                    case "append_regex_result":
                     //Regex result : assign value from the regex
                     //Append regex result : append result from a regex
                        if (isset($this->regex_results[0])) {
                            $res = RuleAction::getRegexResultById(
                                $action->fields["value"],
                                $this->regex_results[0]
                            );
                        } else {
                            $res = $action->fields["value"];
                        }

                        if ($action->fields["action_type"] == "append_regex_result") {
                            if (isset($params[$action->fields["field"]])) {
                                $res = $params[$action->fields["field"]] . $res;
                            } else {
                             //keep rule value to append in a separate entry
                                $output[$action->fields['field'] . '_append'] = $res;
                            }
                        }

                        $output[$action->fields["field"]] = $res;
                        break;

                    default:
                        //plugins actions
                        $executeaction = clone $this;
                        $output = $executeaction->executePluginsActions($action, $output, $params, $input);
                        break;
                }
            }
        }
        return $output;
    }

    public function cleanDBonPurge()
    {
        // Delete a rule and all associated criteria and actions
        if (!empty($this->ruleactionclass)) {
            $ruleactionclass = $this->ruleactionclass;
            $ra = new $ruleactionclass();
            $ra->deleteByCriteria([$this->rules_id_field => $this->fields['id']]);
        }

        if (!empty($this->rulecriteriaclass)) {
            $rulecriteriaclass = $this->rulecriteriaclass;
            $rc = new $rulecriteriaclass();
            $rc->deleteByCriteria([$this->rules_id_field => $this->fields['id']]);
        }
    }

    /**
     * Get the data for the list of rules
     * @param bool $display_criteria
     * @param bool $display_actions
     * @param bool $display_entity
     * @param bool $can_edit
     * @return array
     * @see RuleCollection::showListRules()
     */
    final public function getDataForList(bool $display_criteria, bool $display_actions, bool $display_entity, bool $can_edit): array
    {
        // name, description, condition, criteria, actions, is_active, entities
        $data = [];
        $link = $this->getLink();
        if (!empty($this->fields["comment"])) {
            $link = sprintf(
                __('%1$s %2$s'),
                $link,
                Html::showToolTip($this->fields["comment"], ['display' => false])
            );
        }
        $data['name'] = $link;
        $data['description'] = $this->fields["description"];
        if ($this->useConditions()) {
            $data['condition'] = static::getConditionName($this->fields["condition"]);
        }

        if (
            $display_criteria
            && ($RuleCriterias = getItemForItemtype($this->rulecriteriaclass))
        ) {
            $data['criteria'] = '';
            foreach ($RuleCriterias->getRuleCriterias($this->fields['id']) as $RuleCriteria) {
                $to_display = $this->getMinimalCriteria($RuleCriteria->fields);
                $data['criteria'] .= '<span class="glpi-badge mb-1">'
                    . implode('<i class="ti ti-caret-right-filled mx-1"></i>', array_map('htmlspecialchars', $to_display))
                    . '</span><br />';
            }
        }
        if (
            $display_actions
            && ($RuleAction = getItemForItemtype($this->ruleactionclass))
        ) {
            $data['actions'] = '';
            foreach ($RuleAction->getRuleActions($this->fields['id']) as $RuleAction) {
                $to_display = $this->getMinimalAction($RuleAction->fields);
                $data['actions'] .= '<span class="glpi-badge mb-1">'
                    . implode('<i class="ti ti-caret-right-filled mx-1"></i>', array_map('htmlspecialchars', $to_display))
                    . '</span><br />';
            }
        }

        $active = $this->fields['is_active'];
        $data['is_active'] = sprintf(
            '<i class="ti ti-circle-filled %s" title="%s"></i>',
            $active ? 'text-success' : 'text-danger',
            $active ? __s('Rule is active') : __s('Rule is inactive'),
        );

        if ($display_entity) {
            $entname = htmlspecialchars(Dropdown::getDropdownName('glpi_entities', $this->fields['entities_id']));
            if ($this->maybeRecursive() && $this->fields['is_recursive']) {
                $entname = sprintf(__s('%1$s %2$s'), $entname, "<span class='fw-bold'>(" . __s('R') . ")</span>");
            }

            $data['entity'] = $entname;
        }

        if ($can_edit) {
            $data['sort'] = "<i class='ti ti-grip-horizontal grip-rule cursor-grab'></i>";
        }

        return $data;
    }

    public function prepareInputForAdd($input)
    {
        // If no uuid given, generate a new one
        if (!isset($input['uuid'])) {
            $input["uuid"] = self::getUuid();
        }

        if (static::class === 'Rule' && !isset($input['sub_type'])) {
            trigger_error('Sub type not specified creating a new rule', E_USER_WARNING);
            return false;
        }

        if (!isset($input['sub_type'])) {
            $input['sub_type'] = static::class;
        } else if (static::class !== 'Rule' && $input['sub_type'] !== static::class) {
            Toolbox::logDebug(
                sprintf(
                    'Creating a %s rule with %s subtype.',
                    static::class,
                    $input['sub_type']
                )
            );
        }

        // Before adding, add the ranking of the new rule to be handled via the moveRule method
        $valid_ranking = isset($input['ranking']) && is_numeric($input['ranking']) && $input['ranking'] >= 0;
        $input["_ranking"] = (int) ($valid_ranking ? $input['ranking'] : $this->getNextRanking($input['sub_type']));
        unset($input['ranking']);

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = parent::prepareInputForUpdate($input);

        if ($input !== false) {
            if (isset($input['ranking'])) {
                // Could be set this way from the API.
                // In this case, we should use moveRule rather than updating directly.
                $input["_ranking"] = $input['ranking'];
                unset($input['ranking']);
            } else if (isset($input['_ranking'])) {
                // Set this way from RuleCollection::moveRule to avoid infinite loop
                $input['ranking'] = $input['_ranking'];
                unset($input['_ranking']);
            }
        }
        return $input;
    }

    public function post_addItem()
    {
        parent::post_addItem();
        $this->handleRankChange(true);
    }

    public function post_updateItem($history = true)
    {
        parent::post_updateItem($history);
        $this->handleRankChange();
    }

    /**
     * Handles any rank change from the API or another source except {@link RuleCollection::moveRule()}
     * by moving the rule rather than directly setting the rank to handle the other rules and avoid rules with the same rank.
     * @return void
     */
    private function handleRankChange($new_rule = false)
    {
        if (isset($this->input['_ranking'])) {
            if (isset($this->fields['ranking']) && (int) $this->input['_ranking'] === (int) $this->fields['ranking']) {
                // No change in ranking, nothing to do.
                return;
            }
            // Ensure we always use the right rule class in case the original object was instantiated as the base class 'Rule'.
            $rule_class = $this->fields['sub_type'] ?? static::class;
            $rule = new $rule_class();
            $collection_class = $rule->getCollectionClassName();
            $collection = new $collection_class();
            $collection->moveRule($this->fields['id'], 0, $this->input['_ranking'], $new_rule);
            $this->getFromDB($this->fields['id']);
        }
    }

    /**
     * Get the next ranking for a specified rule
     * @param string|null $sub_type Specific class for the rule. Defaults to the current class at runtime.
     *
     * @return integer
     **/
    public function getNextRanking(?string $sub_type = null)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['MAX' => 'ranking AS rank'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['sub_type' => $sub_type ?? static::class]
        ]);

        if (count($iterator)) {
            $data = $iterator->current();
            return $data["rank"] === null ? 0 : $data['rank'] + 1;
        }
        return 0;
    }

    /**
     * Show preview result of a rule
     *
     * @param string $target    where to go if action
     * @param array $input     input data array
     * @param array $params    params used (see addSpecificParamsForPreview)
     **/
    public function showRulePreviewResultsForm($target, $input, $params)
    {

        $actions       = $this->getAllActions();
        $check_results = [];
        $output        = [];

       //Test all criteria, without stopping at the first good one
        $this->testCriterias($input, $check_results);
       //Process the rule
        $this->process($input, $output, $params);
        if (!$criteria = getItemForItemtype($this->rulecriteriaclass)) {
            return;
        }

        echo "<div class='spaced'>";
        echo "<table class='tab_cadrehov'>";
        echo "<tr><th colspan='4'>" . __('Result details') . "</th></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td class='center b'>" . _n('Criterion', 'Criteria', 1) . "</td>";
        echo "<td class='center b'>" . __('Condition') . "</td>";
        echo "<td class='center b'>" . __('Reason') . "</td>";
        echo "<td class='center b'>" . _n('Validation', 'Validations', 1) . "</td>";
        echo "</tr>\n";

        foreach ($check_results as $ID => $criteria_result) {
            echo "<tr class='tab_bg_1'>";
            $criteria->getFromDB($criteria_result["id"]);
            echo $this->getMinimalCriteriaText($criteria->fields);
            if ($criteria->fields['condition'] != self::PATTERN_FIND) {
                echo "<td class='b'>" . Dropdown::getYesNo($criteria_result["result"]) . "</td></tr>\n";
            } else {
                echo "<td class='b'>" . Dropdown::EMPTY_VALUE . "</td></tr>\n";
            }
        }
        echo "</table></div>";

        $global_result = (isset($output["_rule_process"]) ? 1 : 0);

        echo "<div class='spaced'>";
        echo "<table class='tab_cadrehov'>";
        echo "<tr><th colspan='2'>" . __('Rule results') . "</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td class='center b'>" . _n('Validation', 'Validations', 1) . "</td><td>";
        echo Dropdown::getYesNo($global_result) . "</td></tr>";

        $output = $this->preProcessPreviewResults($output);

        foreach ($output as $criteria => $value) {
            $action_def = array_filter($actions, static function ($def, $key) use ($criteria) {
                return $key === $criteria || (array_key_exists('appendto', $def) && $def['appendto'] === $criteria);
            }, ARRAY_FILTER_USE_BOTH);
            $action_def_key = key($action_def);
            if (count($action_def)) {
                $action_def = reset($action_def);
            } else {
                continue;
            }

            if (isset($action_def['type'])) {
                $actiontype = $action_def['type'];
            } else {
                $actiontype = '';
            }

            // Some action values can be an array (appendto actions). So, we will force everything to be an array and loop over it when displaying the rows.
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $v) {
                $action_value = $this->getActionValue($action_def_key, $actiontype, $v);
                echo "<tr class='tab_bg_2'>";
                echo "<td>" . $action_def["name"] . "</td>";
                echo "<td>$action_value</td></tr>";
            }
        }

       //If a regular expression was used, and matched, display the results
        if (count($this->regex_results)) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . __('Result of the regular expression') . "</td>";
            echo "<td>";
            if (!empty($this->regex_results[0])) {
                echo "<table class='tab_cadre'>";
                echo "<tr><th>" . __('Key') . "</th><th>" . __('Value') . "</th></tr>";
                foreach ($this->regex_results[0] as $key => $value) {
                    echo "<tr class='tab_bg_1'>";
                    echo "<td>$key</td><td>$value</td></tr>";
                }
                echo "</table>";
            }
            echo "</td></tr>\n";
        }
        echo "</tr>\n";
        echo "</table></div>";
    }

    /**
     * @param array  $fields
     * @param string $addtotd   (default '')
     **/
    public function getMinimalCriteriaText($fields, $addtotd = '')
    {
        $to_display = $this->getMinimalCriteria($fields);
        $text  = "<td $addtotd>" . htmlspecialchars($to_display['criterion']) . "</td>";
        $text .= "<td $addtotd>" . htmlspecialchars($to_display['condition']) . "</td>";
        $text .= "<td $addtotd>" . htmlspecialchars($to_display['pattern']) . "</td>";
        return $text;
    }

    /**
     * @param array $fields
     *
     * @return array
     **/
    private function getMinimalCriteria(array $fields): array
    {
        $criterion = $this->getCriteriaName($fields["criteria"]);
        $condition = RuleCriteria::getConditionByID($fields["condition"], get_class($this), $fields["criteria"]);
        $pattern   = $this->getCriteriaDisplayPattern($fields["criteria"], $fields["condition"], $fields["pattern"]);

        return [
            'criterion' => $criterion,
            'condition' => $condition,
            'pattern'   => $pattern,
        ];
    }

    /**
     * @param array  $fields
     * @param string $addtotd   (default '')
     **/
    public function getMinimalActionText($fields, $addtotd = '')
    {
        $to_display = $this->getMinimalAction($fields);
        $text  = "<td $addtotd>" . htmlspecialchars($to_display['field']) . "</td>";
        $text .= "<td $addtotd>" . htmlspecialchars($to_display['type']) . "</td>";
        $text .= "<td $addtotd>" . htmlspecialchars($to_display['value']) . "</td>";
        return $text;
    }

    /**
     * @param array $fields
     *
     * @return array
     **/
    private function getMinimalAction(array $fields): array
    {
        $field = $this->getActionName($fields["field"]);
        $type  = RuleAction::getActionByID($fields["action_type"]);
        $value = isset($fields["value"])
            ? $this->getActionValue($fields["field"], $fields['action_type'], $fields["value"])
            : '';

        return [
            'field' => $field,
            'type'  => $type,
            'value' => $value,
        ];
    }

    /**
     * Return a value associated with a pattern associated to a criteria to display it
     *
     * @param integer  $ID        the given criteria
     * @param integer  $condition condition used
     * @param ?string  $pattern   the pattern
     *
     * @return ?string
     **/
    public function getCriteriaDisplayPattern($ID, $condition, $pattern)
    {

        if (
            ($condition == self::PATTERN_EXISTS)
            || ($condition == self::PATTERN_DOES_NOT_EXISTS)
            || ($condition == self::PATTERN_FIND)
        ) {
            return __('Yes');
        } else if (
            in_array($condition, self::getConditionsWithComplexValues())
        ) {
            $crit = $this->getCriteria($ID);

            if (isset($crit['type'])) {
                switch ($crit['type']) {
                    case "yesonly":
                    case "yesno":
                        return Dropdown::getYesNo($pattern);

                    case "date":
                        $dates = Html::getGenericDateTimeSearchItems([]);
                        return $dates[$pattern] ?? Html::convDate(
                            Html::computeGenericDateTimeSearch($pattern, true)
                        );

                    case "datetime":
                        $dates = Html::getGenericDateTimeSearchItems([
                            'with_time'   => true,
                        ]);
                        return $dates[$pattern] ?? Html::convDateTime(
                            Html::computeGenericDateTimeSearch($pattern)
                        );

                    case "dropdown":
                        $addentity = Dropdown::getDropdownName($crit["table"], $pattern);
                        if ($this->isEntityAssign()) {
                            $itemtype = getItemTypeForTable($crit["table"]);
                            $item     = getItemForItemtype($itemtype);
                            if (
                                $item
                                && $item->getFromDB($pattern)
                                && $item->isEntityAssign()
                            ) {
                                $addentity = sprintf(
                                    __('%1$s (%2$s)'),
                                    $addentity,
                                    Dropdown::getDropdownName(
                                        'glpi_entities',
                                        $item->getEntityID()
                                    )
                                );
                            }
                        }
                        $tmp = $addentity;
                        return (($tmp == '&nbsp;') ? NOT_AVAILABLE : $tmp);

                    case "dropdown_users":
                        return getUserName($pattern);

                    case "dropdown_assets_itemtype":
                    case "dropdown_tracking_itemtype":
                        if ($item = getItemForItemtype($pattern)) {
                            return $item->getTypeName(1);
                        }
                        if (empty($pattern)) {
                            return __('General');
                        }
                        break;

                    case "dropdown_status":
                        if ($this instanceof RuleCommonITILObject) {
                            $itil = $this::getItemtype();
                            return $itil::getStatus($pattern);
                        } else {
                            return Ticket::getStatus($pattern);
                        }

                    case "dropdown_priority":
                        return CommonITILObject::getPriorityName($pattern);

                    case "dropdown_urgency":
                        return CommonITILObject::getUrgencyName($pattern);

                    case "dropdown_impact":
                        return CommonITILObject::getImpactName($pattern);

                    case "dropdown_tickettype":
                        return Ticket::getTicketTypeName($pattern);

                    case "dropdown_validation_status":
                        return CommonITILValidation::getStatus($pattern);
                }
            }
        }
        if ($result = $this->getAdditionalCriteriaDisplayPattern($ID, $condition, $pattern)) {
            return $result;
        }
        return $pattern;
    }

    /**
     * Used to get specific criteria patterns
     *
     * @param integer $ID        the given criteria
     * @param integer $condition condition used
     * @param string  $pattern   the pattern
     *
     * @return mixed|false  A value associated with the criteria, or false otherwise
     **/
    public function getAdditionalCriteriaDisplayPattern($ID, $condition, $pattern)
    {
        return false;
    }

    /**
     * Display item used to select a pattern for a criteria
     *
     * @param string  $name      criteria name
     * @param integer $ID        the given criteria
     * @param integer $condition condition used
     * @param string  $value     the pattern (default '')
     * @param boolean $test      Is to test rule ? (false by default)
     *
     * @return void
     **/
    public function displayCriteriaSelectPattern($name, $ID, $condition, $value = "", $test = false)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $crit    = $this->getCriteria($ID);
        $display = false;
        $tested  = false;

        if (
            isset($crit['type'])
            && ($test || in_array($condition, self::getConditionsWithComplexValues()))
        ) {
            $tested = true;
            switch ($crit['type']) {
                case "yesonly":
                    Dropdown::showYesNo($name, $crit['table'], 0);
                    $display = true;
                    break;

                case "yesno":
                    Dropdown::showYesNo($name, $value);
                    $display = true;
                    break;

                case "date":
                    Html::showGenericDateTimeSearch($name, $value);
                    $display = true;
                    break;

                case "datetime":
                    Html::showGenericDateTimeSearch($name, $value, [
                        'with_time' => true,
                    ]);
                    $display = true;
                    break;

                case "dropdown":
                    $param = ['name'  => $name,
                        'value' => $value
                    ];
                    if (isset($crit['condition'])) {
                        $param['condition'] = $crit['condition'];
                    }
                    Dropdown::show(getItemTypeForTable($crit['table']), $param);

                    $display = true;
                    break;

                case "dropdown_users":
                    User::dropdown(['value'  => $value,
                        'name'   => $name,
                        'right'  => 'all'
                    ]);
                    $display = true;
                    break;

                case "dropdown_tracking_itemtype":
                    Dropdown::showItemTypes($name, array_keys(Ticket::getAllTypesForHelpdesk()));
                    $display = true;
                    break;

                case "dropdown_assets_itemtype":
                    Dropdown::showItemTypes($name, $CFG_GLPI['asset_types'], ['value' => $value]);
                    $display = true;
                    break;

                case "dropdown_inventory_itemtype":
                    $types = $CFG_GLPI['state_types'];
                    $types[''] = __('No item type defined');
                    Dropdown::showItemTypes($name, $types, ['value' => $value]);
                    $display = true;
                    break;

                case "dropdown_urgency":
                    CommonITILObject::dropdownUrgency(['name'  => $name,
                        'value' => $value
                    ]);
                    $display = true;
                    break;

                case "dropdown_impact":
                    CommonITILObject::dropdownImpact(['name'  => $name,
                        'value' => $value
                    ]);
                    $display = true;
                    break;

                case "dropdown_priority":
                    CommonITILObject::dropdownPriority(['name'  => $name,
                        'value' => $value,
                        'withmajor' => true
                    ]);
                    $display = true;
                    break;

                case "dropdown_status":
                    if ($this instanceof RuleCommonITILObject) {
                        $itil = $this::getItemtype();
                        $itil::dropdownStatus(['name' => $name,
                            'value' => $value
                        ]);
                    } else {
                        Ticket::dropdownStatus(['name' => $name,
                            'value' => $value
                        ]);
                    }
                    $display = true;
                    break;

                case "dropdown_tickettype":
                    Ticket::dropdownType($name, ['value' => $value]);
                    $display = true;
                    break;

                case "dropdown_validation_status":
                    CommonITILValidation::dropdownStatus($name, [
                        'global' => true,
                        'value' => $value,
                    ]);
                    $display = true;
                    break;

                default:
                    $tested = false;
                    break;
            }
        }
       //Not a standard condition
        if (!$tested) {
            $display = $this->displayAdditionalRuleCondition($condition, $crit, $name, $value, $test);
        }

        $hiddens = [
            self::PATTERN_EXISTS,
            self::PATTERN_DOES_NOT_EXISTS,
            RuleImportAsset::PATTERN_ENTITY_RESTRICT,
            RuleImportAsset::PATTERN_NETWORK_PORT_RESTRICT,
            RuleImportAsset::PATTERN_ONLY_CRITERIA_RULE,
        ];
        if (!$display && in_array($condition, $hiddens)) {
            echo Html::hidden($name, ['value' => 1]);
            $display = true;
        }

        if (
            !$display
            && ($rc = getItemForItemtype($this->rulecriteriaclass))
        ) {
            echo Html::input($name, ['value' => $value]);
        }
    }

    /**
     * Return a "display" value associated with a pattern associated to a criteria
     *
     * @param integer $ID     the given action
     * @param string  $type   the type of action
     * @param string  $value  the value
     *
     * @return string
     **/
    public function getActionValue($ID, $type, $value)
    {
        $action = $this->getAction($ID);
        if (isset($action['type'])) {
            switch ($action['type']) {
                case "dropdown":
                    if (in_array($type, ['defaultfromuser', 'fromuser', 'fromitem', 'firstgroupfromuser'], true)) {
                        return Dropdown::getYesNo($value);
                    }

                   // $type == regex_result display text
                    if ($type == 'regex_result') {
                        return $this->displayAdditionRuleActionValue($value);
                    }

                   // $type == assign
                    $name = Dropdown::getDropdownName($action["table"], $value);
                    return $name === '' ? NOT_AVAILABLE : $name;

                case "dropdown_status":
                    if ($this instanceof RuleCommonITILObject) {
                        $itil = $this::getItemtype();
                        return $itil::getStatus($value);
                    } else {
                        return Ticket::getStatus($value);
                    }

                case "dropdown_assign":
                case "dropdown_users":
                case "dropdown_users_validate":
                    return getUserName($value);

                case "dropdown_groups_validate":
                    $name = Dropdown::getDropdownName('glpi_groups', $value);
                    return $name == '' ? NOT_AVAILABLE : $name;

                case "dropdown_validation_percent":
                    return Dropdown::getValueWithUnit($value, '%');

                case "yesonly":
                case "yesno":
                    return Dropdown::getYesNo($value);

                case "dropdown_urgency":
                    return CommonITILObject::getUrgencyName($value);

                case "dropdown_impact":
                    return CommonITILObject::getImpactName($value);

                case "dropdown_priority":
                    return CommonITILObject::getPriorityName($value);

                case "dropdown_tickettype":
                    return Ticket::getTicketTypeName($value);

                case "dropdown_management":
                    return Dropdown::getGlobalSwitch($value);

                case "dropdown_validation_status":
                    return CommonITILValidation::getStatus($value);

                default:
                    return $this->displayAdditionRuleActionValue($value);
            }
        }

        return $value;
    }

    /**
     * Return a value associated with a pattern associated to a criteria to display it
     *
     * @param integer $ID        the given criteria
     * @param integer $condition condition used
     * @param string  $value     the pattern
     *
     * @return string
     **/
    public function getCriteriaValue($ID, $condition, $value)
    {
        $conditions = array_merge([
            self::PATTERN_DOES_NOT_EXISTS,
            self::PATTERN_EXISTS
        ], self::getConditionsWithComplexValues());

        if (!in_array($condition, $conditions)) {
            $crit = $this->getCriteria($ID);
            if (isset($crit['type'])) {
                return match ($crit['type']) {
                    'dropdown' => ($result = Dropdown::getDropdownName($crit["table"], $value, false, false)) === '&nbsp;'
                        ? ''
                        : $result,
                    'dropdown_assign', 'dropdown_users' => getUserName($value),
                    'yesonly', 'yesno' => Dropdown::getYesNo($value),
                    'dropdown_impact' => CommonITILObject::getImpactName($value),
                    'dropdown_urgency' => CommonITILObject::getUrgencyName($value),
                    'dropdown_priority' => CommonITILObject::getPriorityName($value),
                    'dropdown_validation_status' => CommonITILValidation::getStatus($value),
                    default => $value,
                };
            }
        }

        return $value;
    }

    /**
     * Function used to display type specific criteria during rule's preview
     *
     * @param array $fields fields values
     **/
    public function showSpecificCriteriasForPreview($fields)
    {
    }

    /**
     * Function used to add specific params before rule processing
     *
     * @param array $params parameters
     *
     * @return array
     **/
    public function addSpecificParamsForPreview($params)
    {
        return $params;
    }

    /**
     * Criteria form used to preview rule
     *
     * @param string  $target   target of the form
     * @param integer $rules_id ID of the rule
     **/
    public function showRulePreviewCriteriasForm($target, $rules_id)
    {
        $criteria = $this->getAllCriteria();

        if ($this->getRuleWithCriteriasAndActions($rules_id, 1, 0)) {
            echo "<form name='testrule_form' id='testrule_form' method='post' action='$target'>\n";
            echo "<div class='spaced'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th colspan='3'>" . _n('Criterion', 'Criteria', Session::getPluralNumber()) . "</th></tr>";

            $type_match        = (($this->fields["match"] == self::AND_MATCHING) ? __('AND') : __('OR'));
            $already_displayed = [];
            $first             = true;

           //Brower all criteria
            foreach ($this->criterias as $criterion) {
                //Look for the criteria in the field of already displayed criteria :
                //if present, don't display it again
                if (!in_array($criterion->fields["criteria"], $already_displayed)) {
                    $already_displayed[] = $criterion->fields["criteria"];
                    echo "<tr class='tab_bg_1'>";
                    echo "<td>";

                    if ($first) {
                        echo "&nbsp;";
                        $first = false;
                    } else {
                        echo $type_match;
                    }

                    echo "</td>";
                    $criteria_constants = $criteria[$criterion->fields["criteria"]];
                    echo "<td>" . $criteria_constants["name"] . "</td>";
                    echo "<td>";
                    $value = "";
                    if (isset($_POST[$criterion->fields["criteria"]])) {
                        $value = $_POST[$criterion->fields["criteria"]];
                    }

                    $this->displayCriteriaSelectPattern(
                        $criterion->fields['criteria'],
                        $criterion->fields['criteria'],
                        $criterion->fields['condition'],
                        $value,
                        true
                    );
                    echo "</td></tr>\n";
                }
            }
            $this->showSpecificCriteriasForPreview($_POST);

            echo "<tr><td class='tab_bg_2 center' colspan='3'>";
            echo "<input type='submit' name='test_rule' value=\"" . _sx('button', 'Test') . "\"
                class='btn btn-primary'>";
            echo "<input type='hidden' name='" . $this->rules_id_field . "' value='$rules_id'>";
            echo "<input type='hidden' name='sub_type' value='" . $this->getType() . "'>";
            echo "</td></tr>\n";
            echo "</table></div>\n";
            Html::closeForm();
        }
    }

    /**
     * @param array $output
     *
     * @return array
     **/
    public function preProcessPreviewResults($output)
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        if (isset($PLUGIN_HOOKS['use_rules'])) {
            $params['criterias_results'] = $this->criterias_results;
            $params['rule_itemtype']     = static::class;
            foreach ($PLUGIN_HOOKS['use_rules'] as $plugin => $val) {
                if (!Plugin::isPluginActive($plugin)) {
                    continue;
                }
                if (is_array($val) && in_array(static::class, $val, true)) {
                    $results = Plugin::doOneHook(
                        $plugin,
                        "preProcessRulePreviewResults",
                        ['output' => $output,
                            'params' => $params
                        ]
                    );
                    if (is_array($results)) {
                        foreach ($results as $id => $result) {
                            $output[$id] = $result;
                        }
                    }
                }
            }
        }
        return $output;
    }

    /**
     * Dropdown rules for a defined sub_type of rule
     *
     * @param array $options array of possible options:
     *    - name : string / name of the select (default is depending on itemtype)
     *    - sub_type : integer / sub_type of rule
     *    - hide_if_no_elements  : boolean / hide dropdown if there is no elements (default false)
     **/
    public static function dropdown($options = [])
    {
        $p = array_replace([
            'sub_type' => '',
            'name'     => 'rules_id',
            'entity'   => '',
            'condition' => 0,
            'hide_if_no_elements' => false,
        ], $options);

        if ($p['sub_type'] === '') {
            return false;
        }

        $conditions = [
            'sub_type' => $p['sub_type']
        ];
        if ($p['condition'] > 0) {
            $conditions['condition'] = ['&', (int)$p['condition']];
        }

        $p['condition'] = $conditions;
        return Dropdown::show($p['sub_type'], $p);
    }

    public function getAllCriteria()
    {
        return self::doHookAndMergeResults("getRuleCriteria", $this->getCriterias(), static::class);
    }

    public function getCriterias()
    {
        return [];
    }

    /**
     * @since 0.84
     * @return array
     */
    public function getAllActions()
    {
        return self::doHookAndMergeResults("getRuleActions", $this->getActions(), static::class);
    }

    public function getActions()
    {
        $actions = [];
        $collection_class = $this->getCollectionClassName();
        /** @var RuleCollection $collection */
        $collection = new $collection_class();
        if (!$collection->stop_on_first_match) {
            $actions['_stop_rules_processing'] = [
                'name' => __('Skip remaining rules'),
                'type' => 'yesonly',
            ];
        }
        return $actions;
    }

    /**
     *  Execute a hook if necessary and merge results
     *
     *  @since 0.84
     *
     * @param string $hook            the hook to execute
     * @param array $params   array  input parameters
     * @param string $itemtype        (default '')
     *
     * @return array input parameters merged with hook parameters
     **/
    public static function doHookAndMergeResults($hook, $params = [], $itemtype = '')
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        if (empty($itemtype)) {
            $itemtype = static::getType();
        }

       //Agregate all plugins criteria for this rules engine
        $toreturn = $params;
        if (isset($PLUGIN_HOOKS['use_rules'])) {
            foreach ($PLUGIN_HOOKS['use_rules'] as $plugin => $val) {
                if (!Plugin::isPluginActive($plugin)) {
                    continue;
                }
                if (is_array($val) && in_array($itemtype, $val)) {
                    $results = Plugin::doOneHook($plugin, $hook, ['rule_itemtype' => $itemtype,
                        'values'        => $params
                    ]);
                    if (is_array($results)) {
                        foreach ($results as $id => $result) {
                            $toreturn[$id] = $result;
                        }
                    }
                }
            }
        }
        return $toreturn;
    }

    /**
     * @param string $sub_type
     *
     * @return array
     **/
    public static function getActionsByType($sub_type)
    {
        if ($rule = getItemForItemtype($sub_type)) {
            return $rule->getAllActions();
        }
        return [];
    }

    /**
     * Return all rules from database
     *
     * @param array $crit array of criteria (at least, 'field' and 'value')
     *
     * @return array of Rule objects
     **/
    public function getRulesForCriteria($crit)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $rules = [];

       /// TODO : not working for SLALevels : no sub_type

       //Get all the rules whose sub_type is $sub_type and entity is $ID
        $query = [
            'SELECT' => static::getTable() . '.id',
            'FROM'   => [
                getTableForItemType($this->ruleactionclass),
                static::getTable()
            ],
            'WHERE'  => [
                getTableForItemType($this->ruleactionclass) . "." . $this->rules_id_field   => new QueryExpression(DBmysql::quoteName(static::getTable() . '.id')),
                static::getTable() . '.sub_type'                                           => static::class

            ]
        ];

        foreach ($crit as $field => $value) {
            $query['WHERE'][getTableForItemType($this->ruleactionclass) . '.' . $field] = $value;
        }

        $iterator = $DB->request($query);

        foreach ($iterator as $rule) {
            $affect_rule = new Rule();
            $affect_rule->getRuleWithCriteriasAndActions($rule["id"], 0, 1);
            $rules[]     = $affect_rule;
        }
        return $rules;
    }

    /**
     * @param integer $ID
     *
     * @return void
     **/
    public function showNewRuleForm($ID)
    {
        echo "<form method='post' action='" . Toolbox::getItemTypeFormURL('Entity') . "'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='7'>" . $this->getTitle() . "</th></tr>\n";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td><td>";
        echo Html::input('name', ['value' => '', 'size' => '33']);
        echo "</td><td>" . __('Description') . "</td><td>";
        echo Html::input('description', ['value' => '', 'size' => '33']);
        echo "</td><td>" . __('Logical operator') . "</td><td>";
        $this->dropdownRulesMatch();
        echo "</td><td class='tab_bg_2 center'>";
        echo "<input type=hidden name='sub_type' value='" . get_class($this) . "'>";
        echo "<input type=hidden name='entities_id' value='0'>";
        echo "<input type=hidden name='affectentity' value='$ID'>";
        echo "<input type=hidden name='_method' value='AddRule'>";
        echo "<input type='submit' name='execute' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
        echo "</td></tr>\n";
        echo "</table>";
        Html::closeForm();
    }

    /**
     * @param CommonDBTM $item
     *
     * @return void
     **/
    public function showAndAddRuleForm($item)
    {
        $rand    = mt_rand();
        $canedit = self::canUpdate();

        if (
            $canedit
            && ($item instanceof Entity)
        ) {
            $this->showNewRuleForm($item->getField('id'));
        }

         //Get all rules and actions
        $crit = ['field' => getForeignKeyFieldForTable($item->getTable()),
            'value' => $item->getField('id')
        ];

        $rules = $this->getRulesForCriteria($crit);
        $nb    = count($rules);
        echo "<div class='spaced'>";

        if (!$nb) {
            echo "<table class='tab_cadre_fixehov'>";
            echo "<tr><th>" . __('No item found') . "</th>";
            echo "</tr>\n";
            echo "</table>\n";
        } else {
            if ($canedit) {
                Html::openMassiveActionsForm('mass' . get_called_class() . $rand);
                $massiveactionparams
                = ['num_displayed'
                           => min($_SESSION['glpilist_limit'], $nb),
                    'specific_actions'
                           => ['update' => _x('button', 'Update'),
                               'purge'  => _x('button', 'Delete permanently')
                           ]
                ];
                  //     'extraparams'
                //           => array('rule_class_name' => $this->getRuleClassName()));
                Html::showMassiveActions($massiveactionparams);
            }
            echo "<table class='tab_cadre_fixehov'>";
            $header_begin  = "<tr>";
            $header_top    = '';
            $header_bottom = '';
            $header_end    = '';
            if ($canedit) {
                $header_begin  .= "<th width='10'>";
                $header_top    .= Html::getCheckAllAsCheckbox('mass' . get_called_class() . $rand);
                $header_bottom .= Html::getCheckAllAsCheckbox('mass' . get_called_class() . $rand);
                $header_end    .= "</th>";
            }
            $header_end .= "<th>" . $this->getTitle() . "</th>";
            $header_end .= "<th>" . __('Description') . "</th>";
            $header_end .= "<th>" . __('Active') . "</th>";
            $header_end .= "</tr>\n";
            echo $header_begin . $header_top . $header_end;

            Session::initNavigateListItems(
                get_class($this),
                //TRANS: %1$s is the itemtype name,
                              //       %2$s is the name of the item (used for headings of a list)
                                        sprintf(
                                            __('%1$s = %2$s'),
                                            $item->getTypeName(1),
                                            $item->getName()
                                        )
            );

            foreach ($rules as $rule) {
                Session::addToNavigateListItems(get_class($this), $rule->fields["id"]);
                echo "<tr class='tab_bg_1'>";

                if ($canedit) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $rule->fields["id"]);
                    echo "</td>";
                    echo "<td><a href='" . $this->getFormURLWithID($rule->fields["id"])
                                   . "&amp;onglet=1'>" . $rule->fields["name"] . "</a></td>";
                } else {
                    echo "<td>" . $rule->fields["name"] . "</td>";
                }

                echo "<td>" . $rule->fields["description"] . "</td>";
                echo "<td>" . Dropdown::getYesNo($rule->fields["is_active"]) . "</td>";
                echo "</tr>\n";
            }
            echo $header_begin . $header_bottom . $header_end;
            echo "</table>\n";

            if ($canedit) {
                $massiveactionparams['ontop'] = false;
                Html::showMassiveActions($massiveactionparams);
                Html::closeForm();
            }
        }
        echo "</div>";
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * Add more criteria specific to this type of rule
     *
     * @return array
     **/
    public static function addMoreCriteria()
    {
        return [];
    }

    /**
     * Add more actions specific to this type of rule
     *
     * @param string $value
     *
     * @return string
     **/
    public function displayAdditionRuleActionValue($value)
    {
        return $value;
    }

    /**
     * @param $condition
     * @param $criteria
     * @param $name
     * @param $value
     * @param bool $test (false by default)
     * @return false
     */
    public function displayAdditionalRuleCondition($condition, $criteria, $name, $value, $test = false)
    {
        return false;
    }

    /**
     * @param array  $action
     * @param string $value          value to display (default '')
     *
     * @return boolean
     **/
    public function displayAdditionalRuleAction(array $action, $value = '')
    {
        return false;
    }

    /**
     * Clean Rule with Action or Criteria linked to an item
     *
     * @param Object $item
     * @param string $field name (default is FK to item)
     * @param object $ruleitem (instance of Rules of SlaLevel)
     * @param string $table (glpi_ruleactions, glpi_rulescriterias or glpi_slalevelcriterias)
     * @param string $valfield (value or pattern)
     * @param string $fieldfield (criteria of field)
     **/
    private static function cleanForItemActionOrCriteria(
        $item,
        $field,
        $ruleitem,
        $table,
        $valfield,
        $fieldfield
    ) {
        /** @var \DBmysql $DB */
        global $DB;

        $fieldid = getForeignKeyFieldForTable($ruleitem->getTable());

        if (empty($field)) {
            $field = getForeignKeyFieldForTable($item->getTable());
        }

        if (isset($item->input['_replace_by']) && ($item->input['_replace_by'] > 0)) {
            $DB->update(
                $table,
                [
                    $valfield => $item->input['_replace_by']
                ],
                [
                    $valfield   => $item->getField('id'),
                    $fieldfield => ['LIKE', $field]
                ]
            );
        } else {
            $iterator = $DB->request([
                'SELECT' => [$fieldid],
                'FROM'   => $table,
                'WHERE'  => [
                    $valfield   => $item->getField('id'),
                    $fieldfield => ['LIKE', $field]
                ]
            ]);

            if (count($iterator) > 0) {
                $input['is_active'] = 0;

                foreach ($iterator as $data) {
                    $input['id'] = $data[$fieldid];
                    $ruleitem->update($input);
                }
                Session::addMessageAfterRedirect(
                    __s('Rules using the object have been disabled.'),
                    true
                );
            }
        }
    }

    /**
     * Clean Rule with Action is assign to an item
     *
     * @param Object $item
     * @param string $field name (default is FK to item) (default '')
     **/
    public static function cleanForItemAction($item, $field = '')
    {
        self::cleanForItemActionOrCriteria(
            $item,
            $field,
            new self(),
            'glpi_ruleactions',
            'value',
            'field'
        );

        self::cleanForItemActionOrCriteria(
            $item,
            $field,
            new SlaLevel(),
            'glpi_slalevelactions',
            'value',
            'field'
        );

        self::cleanForItemActionOrCriteria(
            $item,
            $field,
            new OlaLevel(),
            'glpi_olalevelactions',
            'value',
            'field'
        );
    }

    /**
     * Clean Rule with Criteria on an item
     *
     * @param Object $item
     * @param string $field name (default is FK to item) (default '')
     **/
    public static function cleanForItemCriteria($item, $field = '')
    {
        self::cleanForItemActionOrCriteria(
            $item,
            $field,
            new self(),
            'glpi_rulecriterias',
            'pattern',
            'criteria'
        );
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            $nb = 0;
            switch ($item::class) {
                case Entity::class:
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $types      = [];
                        $collection = new RuleRightCollection();
                        if ($collection->canList()) {
                            $types[] = 'RuleRight';
                        }
                        $collection = new RuleImportEntityCollection();
                        if ($collection->canList()) {
                            $types[] = 'RuleImportEntity';
                        }
                        $collection = new RuleMailCollectorCollection();
                        if ($collection->canList()) {
                             $types[] = 'RuleMailCollector';
                        }
                        if (count($types)) {
                             $nb = countElementsInTable(
                                 ['glpi_rules', 'glpi_ruleactions'],
                                 [
                                     'glpi_ruleactions.rules_id'   => new QueryExpression(DBmysql::quoteName('glpi_rules.id')),
                                     'glpi_rules.sub_type'         => $types,
                                     'glpi_ruleactions.field'      => 'entities_id',
                                     'glpi_ruleactions.value'      => $item->getID()
                                 ]
                             );
                        }
                    }
                    return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class);

                case SLA::class:
                case OLA::class:
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = countElementsInTable(
                            'glpi_ruleactions',
                            ['field' => $item::getFieldNames($item->fields['type'])[1],
                                'value' => $item->getID()
                            ]
                        );
                    }
                    return self::createTabEntry(self::getTypeName($nb), $nb, $item::class);

                default:
                    if ($item instanceof self) {
                        $ong    = [];
                        $nbcriteria = 0;
                        $nbaction   = 0;
                        if ($_SESSION['glpishow_count_on_tabs']) {
                              $nbcriteria = countElementsInTable(
                                  getTableForItemType($item->getRuleCriteriaClass()),
                                  [$item->getRuleIdField() => $item->getID()]
                              );
                              $nbaction   = countElementsInTable(
                                  getTableForItemType($item->getRuleActionClass()),
                                  [$item->getRuleIdField() => $item->getID()]
                              );
                        }

                        $ong[1] = self::createTabEntry(
                            RuleCriteria::getTypeName(Session::getPluralNumber()),
                            $nbcriteria,
                            $item::getType(),
                            RuleCriteria::getIcon()
                        );
                        $ong[2] = self::createTabEntry(
                            RuleAction::getTypeName(Session::getPluralNumber()),
                            $nbaction,
                            $item::getType(),
                            RuleAction::getIcon()
                        );
                        return $ong;
                    }
            }
        }
        return '';
    }

    /**
     * @param CommonGLPI $item         CommonGLPI object
     * @param integer    $tabnum       (default 1)
     * @param integer    $withtemplate (default 0)
     **/
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item::class === Entity::class) {
            $collection = new RuleRightCollection();
            if ($collection->canList()) {
                $ldaprule = new RuleRight();
                $ldaprule->showAndAddRuleForm($item);
            }

            $collection = new RuleImportEntityCollection();
            if ($collection->canList()) {
                $importrule = new RuleImportEntity();
                $importrule->showAndAddRuleForm($item);
            }

            $collection = new RuleMailCollectorCollection();
            if ($collection->canList()) {
                $mailcollector = new RuleMailCollector();
                $mailcollector->showAndAddRuleForm($item);
            }
        } else if ($item instanceof LevelAgreement) {
            $item->showRulesList();
        } else if ($item instanceof self) {
            $item->getRuleWithCriteriasAndActions($item->getID(), 1, 1);
            switch ($tabnum) {
                case 1:
                    $item->showCriteriasList($item->getID());
                    break;

                case 2:
                    $item->showActionsList($item->getID());
                    break;
            }
        }

        return true;
    }

    /**
     * Generate unique id for rule based on server name, glpi directory and basetime
     *
     * @since 0.85
     *
     * @return string uuid
     **/
    public static function getUuid()
    {
        // encode uname -a, ex Linux localhost 2.4.21-0.13mdk #1 Fri Mar 14 15:08:06 EST 2003 i686
        $serverSubSha1 = substr(sha1(php_uname('a')), 0, 8);
        // encode script current dir, ex : /var/www/glpi_X
        $dirSubSha1    = substr(sha1(__FILE__), 0, 8);

        return uniqid("$serverSubSha1-$dirSubSha1-", true);
    }

    /**
     * Display debug information for current object
     *
     * @since 0.85
     *
     * @retrun void
     **/
    public function showDebug()
    {
        echo "<div class='spaced'>";
        printf(__s('%1$s: %2$s'), "<b>UUID</b>", htmlspecialchars($this->fields['uuid']));
        echo "</div>";
    }

    public static function canCreate(): bool
    {
        return static::canUpdate();
    }

    public static function canPurge(): bool
    {
        return static::canUpdate();
    }

    public static function getIcon()
    {
        return "ti ti-book";
    }

    public function prepareInputForClone($input)
    {
        // get ranking
        $nextRanking = $this->getNextRanking();

        // Update fields of the new collection
        $input['is_active']   = 0;
        $input['ranking']     = $nextRanking;
        $input['uuid']        = static::getUuid();

        return $input;
    }

    /**
     * Get all "negatives" condition (is not, does not contains, ...)
     *
     * @return array
     */
    public static function getNegativesConditions(): array
    {
        return [
            self::PATTERN_IS_NOT,
            self::PATTERN_NOT_CONTAIN,
            self::REGEX_NOT_MATCH,
            self::PATTERN_DOES_NOT_EXISTS,
            self::PATTERN_DATE_IS_NOT_EQUAL,
        ];
    }

    /**
     * Get all condition that may not be displayed as a simple text
     * For example, a dropdown value or a date
     *
     * @return array
     */
    public static function getConditionsWithComplexValues(): array
    {
        return [
            self::PATTERN_IS,
            self::PATTERN_IS_NOT,
            self::PATTERN_NOT_UNDER,
            self::PATTERN_UNDER,
            self::PATTERN_DATE_IS_BEFORE,
            self::PATTERN_DATE_IS_AFTER,
            self::PATTERN_DATE_IS_EQUAL,
            self::PATTERN_DATE_IS_NOT_EQUAL,
        ];
    }

    /**
     * Create rules (initialisation).
     *
     * @param boolean $reset        Whether to reset before adding new rules, defaults to true
     * @param boolean $with_plugins Use plugins rules or not
     * @param boolean $check        Check if rule exists before creating
     *
     * @return boolean
     *
     * @FIXME Make it final in GLPI 11.0.
     * @FIXME Remove $reset, $with_plugins and $check parameters in GLPI 11.0, they are actually not used or have no effect where they are used.
     */
    public static function initRules($reset = true, $with_plugins = true, $check = false): bool
    {
        $self = new static();

        if (!static::hasDefaultRules()) {
            return false;
        }

        if ($reset === true) {
            $rules = $self->find(['sub_type' => static::class]);
            foreach ($rules as $data) {
                $delete = $self->delete($data);
                if (!$delete) {
                    return false; // Do not continue if reset failed
                }
            }

            $check = false; // Nothing to check
        }

        $xml = simplexml_load_file(self::getDefaultRulesFilePath());
        if ($xml === false) {
            return false;
        }

        $ranking_increment = 0;
        if ($reset === false) {
            /** @var \DBmysql $DB */
            global $DB;
            $ranking_increment = $DB->request([
                'SELECT' => ['MAX' => 'ranking AS rank'],
                'FROM'   => static::getTable(),
                'WHERE'  => ['sub_type' => static::class]
            ])->current()['rank'];
        }

        $has_errors = false;
        foreach ($xml->xpath('/rules/rule') as $rulexml) {
            if ((string)$rulexml->sub_type !== self::getType()) {
                trigger_error(sprintf('Unexpected rule type for rule `%s`.', (string)$rulexml->uuid), E_USER_WARNING);
                $has_errors = true;
                continue;
            }
            if ((string)$rulexml->entities_id !== 'Root entity') {
                trigger_error(sprintf('Unexpected entity value for rule `%s`.', (string)$rulexml->uuid), E_USER_WARNING);
                $has_errors = true;
                continue;
            }

            $rule = new static();

            if ($check === true && $rule->getFromDBByCrit(['uuid' => (string)$rulexml->uuid])) {
                // Rule already exists, ignore it.
                continue;
            }

            $rule_input = [
                'entities_id'  => 0, // Always add default rules to root entity
                'sub_type'     => self::getType(),
                'ranking'      => (int)$rulexml->ranking + $ranking_increment,
                'name'         => (string)$rulexml->name,
                'description'  => (string)$rulexml->description,
                'match'        => (string)$rulexml->match,
                'is_active'    => (int)$rulexml->is_active,
                'comment'      => (string)$rulexml->comment,
                'is_recursive' => (int)$rulexml->is_recursive,
                'uuid'         => (string)$rulexml->uuid,
                'condition'    => (string)$rulexml->condition,
            ];

            $rule_id = $rule->add($rule_input);
            if ($rule_id === false) {
                trigger_error(
                    sprintf('Unable to create rule `%s`.', (string)$rulexml->uuid),
                    E_USER_WARNING
                );
                $has_errors = true;
                continue;
            }

            foreach ($rulexml->xpath('./rulecriteria') as $criteriaxml) {
                $criteria_input = [
                    'rules_id'  => $rule_id,
                    'criteria'  => (string)$criteriaxml->criteria,
                    'condition' => (string)$criteriaxml->condition,
                    'pattern'   => (string)$criteriaxml->pattern,
                ];

                $criteria = new RuleCriteria();
                $criteria_id = $criteria->add($criteria_input);
                if ($criteria_id === false) {
                    trigger_error(
                        sprintf('Unable to create criteria for rule `%s`.', (string)$rulexml->uuid),
                        E_USER_WARNING
                    );
                    $has_errors = true;
                    continue;
                }
            }

            foreach ($rulexml->xpath('./ruleaction') as $actionxml) {
                $action_input = [
                    'rules_id'    => $rule_id,
                    'action_type' => (string)$actionxml->action_type,
                    'field'       => (string)$actionxml->field,
                    'value'       => (string)$actionxml->value,
                ];

                $action = new RuleAction();
                $action_id = $action->add($action_input);
                if ($action_id === false) {
                    trigger_error(
                        sprintf('Unable to create action for rule `%s`.', (string)$rulexml->uuid),
                        E_USER_WARNING
                    );
                    $has_errors = true;
                    continue;
                }
            }
        }

        return !$has_errors;
    }

    /**
     * Check whether default rules exists.
     *
     * @return boolean
     */
    final public static function hasDefaultRules(): bool
    {
        return file_exists(self::getDefaultRulesFilePath());
    }

    /**
     * Returns default rules file path.
     *
     * @return string
     */
    private static function getDefaultRulesFilePath(): string
    {
        return sprintf(
            '%s/resources/Rules/%s.xml',
            GLPI_ROOT,
            static::class
        );
    }
}
