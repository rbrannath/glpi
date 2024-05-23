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

/* Test for inc/contract.class.php */

class Contract extends DbTestCase
{
    public function testClone()
    {
        $this->login();
        $this->setEntity('_test_root_entity', true);

        $contract = new \Contract();
        $input = [
            'name' => 'A test contract',
            'entities_id'  => 0
        ];
        $cid = $contract->add($input);
        $this->integer($cid)->isGreaterThan(0);

        $cost = new \ContractCost();
        $cost_id = $cost->add([
            'contracts_id' => $cid,
            'name'         => 'Test cost'
        ]);
        $this->integer($cost_id)->isGreaterThan(0);

        $suppliers_id = getItemByTypeName('Supplier', '_suplier01_name', true);
        $this->integer($suppliers_id)->isGreaterThan(0);

        $link_supplier = new \Contract_Supplier();
        $link_id = $link_supplier->add([
            'suppliers_id' => $suppliers_id,
            'contracts_id' => $cid
        ]);
        $this->integer($link_id)->isGreaterThan(0);

        $this->boolean($link_supplier->getFromDB($link_id))->isTrue();
        $relation_items = $link_supplier->getItemsAssociatedTo($contract->getType(), $cid);
        $this->array($relation_items)->hasSize(1, 'Original Contract_Supplier not found!');

        $citem = new \Contract_Item();
        $citems_id = $citem->add([
            'contracts_id' => $cid,
            'itemtype'     => 'Computer',
            'items_id'     => getItemByTypeName('Computer', '_test_pc01', true)
        ]);
        $this->integer($citems_id)->isGreaterThan(0);

        $this->boolean($citem->getFromDB($citems_id))->isTrue();
        $relation_items = $citem->getItemsAssociatedTo($contract->getType(), $cid);
        $this->array($relation_items)->hasSize(1, 'Original Contract_Item not found!');

        $cloned = $contract->clone();
        $this->integer($cloned)->isGreaterThan($cid);

        foreach ($contract->getCloneRelations() as $rel_class) {
            $this->integer(
                countElementsInTable(
                    $rel_class::getTable(),
                    ['contracts_id' => $cloned]
                )
            )->isIdenticalTo(1, 'Missing relation with ' . $rel_class);
        }
    }

    public function getSpecificValueToDisplayProvider()
    {
        $this->login();
        $_SESSION['glpi_currenttime'] = '2024-04-22 10:00:00';
        return [
            [
                'field' => '_virtual_expiration',
                'values' => [
                    'begin_date' => '2020-01-01',
                    'duration' => 6,
                    'renewal' => \Contract::RENEWAL_NEVER
                ],
                'expected' => "<span class='red'>2020-07-01</span>"
            ],
            [
                'field' => '_virtual_expiration',
                'values' => [
                    'begin_date' => '2020-01-01',
                    'duration' => 6,
                    'renewal' => \Contract::RENEWAL_TACIT
                ],
                'expected' => "2024-07-01"
            ],
            [
                'field' => '_virtual_expiration',
                'values' => [
                    'begin_date' => '2020-01-01',
                    'duration' => 6,
                    'renewal' => \Contract::RENEWAL_EXPRESS
                ],
                'expected' => "<span class='red'>2020-07-01</span>"
            ],
            [
                'field' => '_virtual_expiration',
                'values' => [
                    'begin_date' => '2025-01-01',
                    'duration' => 6,
                    'renewal' => \Contract::RENEWAL_NEVER
                ],
                'expected' => '2025-07-01'
            ],
            [
                'field' => '_virtual_expiration',
                'values' => [
                    'begin_date' => '2025-01-01',
                    'duration' => 6,
                    'renewal' => \Contract::RENEWAL_TACIT
                ],
                'expected' => '2025-07-01'
            ],
            [
                'field' => '_virtual_expiration',
                'values' => [
                    'begin_date' => '2025-01-01',
                    'duration' => 6,
                    'renewal' => \Contract::RENEWAL_EXPRESS
                ],
                'expected' => '2025-07-01'
            ],
        ];
    }

    /**
     * @dataProvider getSpecificValueToDisplayProvider
     */
    public function testGetSpecificValueToDisplay($field, $values, $expected)
    {
        $this->login();
        $this->setEntity('_test_root_entity', true);
        $contract = new \Contract();
        $this->string($contract->getSpecificValueToDisplay($field, $values))->isEqualTo($expected);
    }
}
