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

class Link extends DbTestCase
{
    protected function linkContentProvider(): iterable
    {
        $this->login();

        // Create network
        $network = $this->createItem(
            \Network::class,
            [
                'name' => 'LAN',
            ]
        );

        // Create computer
        $item = $this->createItem(
            \Computer::class,
            [
                'name'              => 'Test computer',
                'serial'            => 'ABC0004E6',
                'otherserial'       => 'X0000015',
                'uuid'              => 'c938f085-4192-4473-a566-46734bbaf6ad',
                'entities_id'       => $_SESSION['glpiactive_entity'],
                'groups_id'         => getItemByTypeName(\Group::class, '_test_group_1', true),
                'locations_id'      => getItemByTypeName(\Location::class, '_location01', true),
                'networks_id'       => $network->getID(),
                'users_id'          => getItemByTypeName(\User::class, 'glpi', true),
                'computermodels_id' => getItemByTypeName(\ComputerModel::class, '_test_computermodel_1', true),
            ]
        );

        // Attach domains
        $domain1 = $this->createItem(
            \Domain::class,
            [
                'name'        => 'domain1.tld',
                'entities_id' => $_SESSION['glpiactive_entity'],
            ]
        );
        $this->createItem(
            \Domain_Item::class,
            [
                'domains_id' => $domain1->getID(),
                'itemtype'   => \Computer::class,
                'items_id'   => $item->getID(),
            ]
        );
        $domain2 = $this->createItem(
            \Domain::class,
            [
                'name'        => 'domain2.tld',
                'entities_id' => $_SESSION['glpiactive_entity'],
            ]
        );
        $this->createItem(
            \Domain_Item::class,
            [
                'domains_id' => $domain2->getID(),
                'itemtype'   => \Computer::class,
                'items_id'   => $item->getID(),
            ]
        );

        $networkport_1 = $this->createItem('NetworkPort', [
            'name' => 'eth0',
            'itemtype' => 'Computer',
            'items_id' => $item->getID(),
            'instantiation_type' => 'NetworkPortEthernet',
            'mac' => 'aa:aa:aa:aa:aa:aa'
        ]);
        $networkport_2 = $this->createItem('NetworkPort', [
            'name' => 'eth1',
            'itemtype' => 'Computer',
            'items_id' => $item->getID(),
            'instantiation_type' => 'NetworkPortEthernet',
            'mac' => 'bb:bb:bb:bb:bb:bb'
        ]);
        $networkname_1 = $this->createItem('NetworkName', [
            'itemtype' => 'NetworkPort',
            'items_id' => $networkport_1->getID(),
        ]);
        $networkname_2 = $this->createItem('NetworkName', [
            'itemtype' => 'NetworkPort',
            'items_id' => $networkport_2->getID(),
        ]);
        $ip_1 = $this->createItem('IPAddress', [
            'itemtype' => 'NetworkName',
            'items_id' => $networkname_1->getID(),
            'name' => '10.10.13.12',
        ]);
        $ip_2 = $this->createItem('IPAddress', [
            'itemtype' => 'NetworkName',
            'items_id' => $networkname_2->getID(),
            'name' => '10.10.13.13',
        ]);

        $item_2 = $this->createItem(
            \Computer::class,
            [
                'name'         => 'Test computer 2',
                'entities_id'  => $_SESSION['glpiactive_entity'],
            ]
        );

        // Empty link
        yield [
            'link'     => '',
            'item'     => $item,
            'safe_url' => false,
            'expected' => [''],
        ];
        yield [
            'link'     => '',
            'item'     => $item,
            'safe_url' => true,
            'expected' => ['#'],
        ];

        foreach ([true, false] as $safe_url) {
            // Link that is actually a title (it is a normal usage!)
            yield [
                'link'     => '{{ LOCATION }} > {{ SERIAL }}/{{ MODEL }} ({{ USER }})',
                'item'     => $item,
                'safe_url' => $safe_url,
                'expected' => [$safe_url ? '#' : '_location01 > ABC0004E6/_test_computermodel_1 (glpi)'],
            ];

            // Link that is actually a long text (it is a normal usage!)
            yield [
                'link'     => <<<TEXT
id:       {{ ID }}
name:     {{ NAME }}
serial:   {{ SERIAL }}/{{ OTHERSERIAL }}
model:    {{ MODEL }}
location: {{ LOCATION }} ({{ LOCATIONID }})
domain:   {{ DOMAIN }} ({{ NETWORK }})
owner:    {{ USER }}/{{ GROUP }}
TEXT,
                'item'     => $item,
                'safe_url' => $safe_url,
                'expected' => [
                    $safe_url
                        ? '#'
                        : <<<TEXT
id:       {$item->getID()}
name:     Test computer
serial:   ABC0004E6/X0000015
model:    _test_computermodel_1
location: _location01 (1)
domain:   domain1.tld (LAN)
owner:    glpi/_test_group_1
TEXT,
                ],
            ];

            // Valid http link
            yield [
                'link'     => 'https://{{ LOGIN }}@{{ DOMAIN }}/{{ item.uuid }}/',
                'item'     => $item,
                'safe_url' => $safe_url,
                'expected' => ['https://_test_user@domain1.tld/c938f085-4192-4473-a566-46734bbaf6ad/'],
            ];
        }

        // Javascript link
        yield [
            'link'     => 'javascript:alert(1);" title="{{ NAME }}"',
            'item'     => $item,
            'safe_url' => false,
            'expected' => ['javascript:alert(1);" title="Test computer"'],
        ];
        yield [
            'link'     => 'javascript:alert(1);" title="{{ NAME }}"',
            'item'     => $item,
            'safe_url' => true,
            'expected' => ['#'],
        ];
        yield [
            'link'     => '{% for domain in DOMAINS %}{{ domain }} {% endfor %}',
            'item'     => $item,
            'safe_url' => false,
            'expected' => ['domain1.tld domain2.tld '],
        ];
        yield [
            'link'     => '{{ NAME }} {{ MAC }} {{ IP }}',
            'item'     => $item,
            'safe_url' => false,
            'expected' => [
                'ip' . $ip_1->getID() => 'Test computer aa:aa:aa:aa:aa:aa 10.10.13.12',
                'ip' . $ip_2->getID() => 'Test computer bb:bb:bb:bb:bb:bb 10.10.13.13'
            ],
        ];
        yield [
            'link'     => '{{ NAME }} {{ MAC }} {{ IP }}',
            'item'     => $item_2,
            'safe_url' => false,
            'expected' => [],
        ];
        yield [
            'link'     => '<script>alert(1);</script>',
            'item'     => $item,
            'safe_url' => true,
            'expected' => ['#'],
        ];
    }

    /**
     * @dataProvider linkContentProvider
     */
    public function testGenerateLinkContents(
        string $link,
        \CommonDBTM $item,
        bool $safe_url,
        array $expected
    ): void {
        $this->newTestedInstance();
        $this->array($this->testedInstance->generateLinkContents($link, $item, $safe_url))
            ->isEqualTo($expected);

        // Validates that default values is true
        if ($safe_url) {
            $this->array($this->testedInstance->generateLinkContents($link, $item))
                ->isEqualTo($expected);
        }
    }

    protected function invalidLinkContentsProvider()
    {
        return [
            ['{{'],
            ['{% if ID'],
            ['{% if ID %}']
        ];
    }

    /**
     * @dataProvider invalidLinkContentsProvider
     */
    public function testInvalidLinkContents($content)
    {
        $link = new \Link();
        $this->boolean($link->add([
            'link' => $content
        ]))->isFalse();
        $this->hasSessionMessages(ERROR, [
            __('Invalid twig template syntax')
        ]);
        $this->boolean($link->add([
            'data' => $content
        ]))->isFalse();
        $this->hasSessionMessages(ERROR, [
            __('Invalid twig template syntax')
        ]);
    }
}
