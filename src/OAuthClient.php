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
use Glpi\OAuth\Server;

final class OAuthClient extends CommonDBTM
{
    public static $rightname = 'oauth_client';

    public static $undisclosedFields = [
        'secret',
    ];

    public static function getTypeName($nb = 0)
    {
        return _n('OAuth client', 'OAuth clients', $nb);
    }

    public static function getIcon()
    {
        return 'ti ti-key';
    }

    public function showForm($ID, array $options = [])
    {
        TemplateRenderer::getInstance()->display('pages/setup/oauthclient.html.twig', [
            'item' => $this,
            'params' => $options,
            'allowed_scopes' => Server::getAllowedScopes(),
        ]);
        return true;
    }

    public function rawSearchOptions()
    {
        $opts = [];

        $opts[] = [
            'id' => 'common',
            'name' => self::getTypeName(1),
        ];
        $opts[] = [
            'id' => 1,
            'table' => self::getTable(),
            'field' => 'name',
            'name' => __('Name'),
            'datatype' => 'itemlink',
        ];
        $opts[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false, // implicit field is id
            'datatype'           => 'number'
        ];

        $opts[] = [
            'id' => '3',
            'table' => self::getTable(),
            'field' => 'identifier',
            'name' => __('Client ID'),
            'datatype' => 'itemlink',
        ];

        return $opts;
    }

    /**
     * @throws Exception
     */
    public static function getNewIDOrSecret()
    {
        return bin2hex(random_bytes(Server::ID_SECRET_LENGTH_BYTES));
    }

    public function prepareInputForAdd($input)
    {
        $key = new GLPIKey();
        $input['identifier'] = self::getNewIDOrSecret();
        $input['secret'] = $key->encrypt(self::getNewIDOrSecret());

        $input['grants'] = json_encode($input['grants'] ?? []);
        $input['scopes'] = json_encode($input['scopes'] ?? []);

        if (empty($input['redirect_uri'])) {
            $input['redirect_uri'] = ['/api.php/oauth2/redirection'];
        }
        $input['redirect_uri'] = json_encode($input['redirect_uri']);

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $key = new GLPIKey();
        if (isset($input['secret'])) {
            $input['secret'] = $key->encrypt($input['secret']);
        }
        if (isset($input['grants'])) {
            $input['grants'] = json_encode($input['grants']);
        }
        if (isset($input['scopes'])) {
            $input['scopes'] = json_encode($input['scopes']);
        }
        $input['redirect_uri'] = json_encode($input['redirect_uri'] ?? []);

        return $input;
    }

    public function post_getFromDB()
    {
        $key = new GLPIKey();
        if (isset($this->fields['secret'])) {
            $this->fields['secret'] = $key->decrypt($this->fields['secret']);
        }
        if (isset($this->fields['grants'])) {
            $this->fields['grants'] = json_decode($this->fields['grants'], true);
        }
        if (isset($this->fields['scopes'])) {
            $this->fields['scopes'] = json_decode($this->fields['scopes'], true);
        }
        $this->fields['redirect_uri'] = json_decode($this->fields['redirect_uri'], true);
    }

    public function post_getEmpty()
    {
        $this->fields['grants'] = [];
    }
}
