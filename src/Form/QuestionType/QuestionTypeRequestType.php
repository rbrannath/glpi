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

namespace Glpi\Form\QuestionType;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Form\Question;
use Override;
use Ticket;

final class QuestionTypeRequestType extends AbstractQuestionType
{
    /**
     * Retrieve the default value for the request type question type
     *
     * @param Question|null $question The question to retrieve the default value from
     * @return int
     */
    public function getDefaultValue(?Question $question): int
    {
        if (
            $question !== null
            && isset($question->fields['default_value'])
        ) {
            if (is_int($question->fields['default_value'])) {
                return $question->fields['default_value'];
            } elseif (is_numeric($question->fields['default_value'])) {
                return (int) $question->fields['default_value'];
            }
        }

        return 0;
    }

    #[Override]
    public function renderAdministrationTemplate(?Question $question): string
    {
        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}

            {{ fields.dropdownArrayField(
                'default_value',
                value,
                request_types,
                '',
                {
                    'init'               : init,
                    'no_label'           : true,
                    'display_emptychoice': true
                }
            ) }}
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'init'               => $question != null,
            'value'              => $this->getDefaultValue($question),
            'request_types'     => Ticket::getTypes()
        ]);
    }

    #[Override]
    public function renderEndUserTemplate(Question $question): string
    {
        $template = <<<TWIG
        {% import 'components/form/fields_macros.html.twig' as fields %}

        {{ fields.dropdownArrayField(
            question.getEndUserInputName(),
            value,
            request_types,
            '',
            {
                'no_label'           : true,
                'display_emptychoice': true
            }
        ) }}
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'value'              => $this->getDefaultValue($question),
            'question'           => $question,
            'request_types'      => Ticket::getTypes()
        ]);
    }

    #[Override]
    public function renderAnswerTemplate($answer): string
    {
        $template = <<<TWIG
            <div class="form-control-plaintext">{{ answer }}</div>
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, [
            'answer' => Ticket::getTicketTypeName($answer)
        ]);
    }

    #[Override]
    public function getCategory(): QuestionTypeCategory
    {
        return QuestionTypeCategory::REQUEST_TYPE;
    }
}
