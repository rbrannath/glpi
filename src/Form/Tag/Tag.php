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

namespace Glpi\Form\Tag;

/**
 * Simple data structure that will be json_encoded and sent to the
 * GLPI.RichText.FormTags component.
 */
final readonly class Tag
{
    public string $label;
    public string $html;

    public function __construct(
        string $label,
        string $value,
        string $provider,
    ) {
        $this->label = $label;

        // Build HTML representation of the tag.
        $properties = [
            "contenteditable"        => "false",
            "data-form-tag"          => "true",
            "data-form-tag-value"    => $value,
            "data-form-tag-provider" => $provider,
        ];
        $properties = implode(" ", array_map(
            fn($key, $value) => sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value)),
            array_keys($properties),
            array_values($properties),
        ));
        $this->html = sprintf('<span %s>%s</span>', $properties, htmlspecialchars($label));
    }
}
