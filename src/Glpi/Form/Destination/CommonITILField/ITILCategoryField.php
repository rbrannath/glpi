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

namespace Glpi\Form\Destination\CommonITILField;

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\JsonFieldInterface;
use Glpi\Form\AnswersSet;
use Glpi\Form\Destination\AbstractConfigField;
use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypeItem;
use Glpi\Form\QuestionType\QuestionTypeItemDropdown;
use Glpi\Form\QuestionType\QuestionTypeITILCategory;
use InvalidArgumentException;
use ITILCategory;
use Override;

class ITILCategoryField extends AbstractConfigField
{
    #[Override]
    public static function getKey(): string
    {
        return 'itilcategory';
    }

    #[Override]
    public function getLabel(): string
    {
        return _n('ITIL category', 'ITIL categories', 1);
    }

    #[Override]
    public function getConfigClass(): string
    {
        return ITILCategoryFieldConfig::class;
    }

    #[Override]
    public function renderConfigForm(
        Form $form,
        JsonFieldInterface $config,
        string $input_name,
        array $display_options
    ): string {
        if (!$config instanceof ITILCategoryFieldConfig) {
            throw new InvalidArgumentException("Unexpected config class");
        }

        $twig = TemplateRenderer::getInstance();
        return $twig->render('pages/admin/form/itil_config_fields/itilcategory.html.twig', [
            // Possible configuration constant that will be used to to hide/show additional fields
            'CONFIG_SPECIFIC_VALUE'  => ITILCategoryFieldStrategy::SPECIFIC_VALUE->value,
            'CONFIG_SPECIFIC_ANSWER' => ITILCategoryFieldStrategy::SPECIFIC_ANSWER->value,

            // General display options
            'options' => $display_options,

            // Main config field
            'main_config_field' => [
                'label'           => $this->getLabel(),
                'value'           => $config->getStrategy()->value,
                'input_name'      => $input_name . "[" . ITILCategoryFieldConfig::STRATEGY . "]",
                'possible_values' => $this->getMainConfigurationValuesforDropdown(),
            ],

            // Specific additional config for SPECIFIC_ANSWER strategy
            'specific_value_extra_field' => [
                'empty_label'     => __("Select an ITIL category..."),
                'value'           => $config->getSpecificITILCategoryID() ?? 0,
                'input_name'      => $input_name . "[" . ITILCategoryFieldConfig::ITILCATEGORY_ID . "]",
            ],

            // Specific additional config for SPECIFIC_VALUE strategy
            'specific_answer_extra_field' => [
                'empty_label'     => __("Select a question..."),
                'value'           => $config->getSpecificQuestionId(),
                'input_name'      => $input_name . "[" . ITILCategoryFieldConfig::QUESTION_ID . "]",
                'possible_values' => $this->getITILCategoryQuestionsValuesForDropdown($form),
            ],
        ]);
    }

    #[Override]
    public function applyConfiguratedValueToInputUsingAnswers(
        JsonFieldInterface $config,
        array $input,
        AnswersSet $answers_set
    ): array {
        if (!$config instanceof ITILCategoryFieldConfig) {
            throw new InvalidArgumentException("Unexpected config class");
        }

        // Compute value according to strategy
        $itilcategory_id = $config->getStrategy()->getITILCategory($config, $answers_set);

        // Do not edit input if invalid value was found
        if (!ITILCategory::getById($itilcategory_id)) {
            return $input;
        }

        // Apply value
        $input['itilcategories_id'] = $itilcategory_id;
        return $input;
    }

    #[Override]
    public function getDefaultConfig(Form $form): ITILCategoryFieldConfig
    {
        return new ITILCategoryFieldConfig(
            ITILCategoryFieldStrategy::LAST_VALID_ANSWER
        );
    }

    private function getMainConfigurationValuesforDropdown(): array
    {
        $values = [];
        foreach (ITILCategoryFieldStrategy::cases() as $strategies) {
            $values[$strategies->value] = $strategies->getLabel();
        }
        return $values;
    }

    private function getITILCategoryQuestionsValuesForDropdown(Form $form): array
    {
        $values = [];
        $questions = $form->getQuestionsByType(QuestionTypeItemDropdown::class);

        foreach ($questions as $question) {
            // Only keep questions that are ITIL categories
            if ((new QuestionTypeItemDropdown())->getDefaultValueItemtype($question) !== ITILCategory::getType()) {
                continue;
            }

            $values[$question->getId()] = $question->fields['name'];
        }

        return $values;
    }

    #[Override]
    public function getWeight(): int
    {
        return 30;
    }
}