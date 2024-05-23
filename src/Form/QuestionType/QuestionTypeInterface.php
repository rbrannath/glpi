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

use Glpi\Form\Question;

/**
 * Interface that must be implemented by all available questions types
 */
interface QuestionTypeInterface
{
    public function __construct();

    /**
     * Load the required JS files for this question type.
     * This method is called when the form editor page is loaded.
     *
     * @return array List of JS files to load.
     */
    public function loadJavascriptFiles(): array;

    /**
     * Format the default value for the database.
     * This method is called before saving the question.
     *
     * @param mixed $value The default value to format.
     * @return string
     */
    public function formatDefaultValueForDB(mixed $value): ?string;

    /**
     * Validate the input for extra data of the question.
     * This method is called before saving the question.
     *
     * @param array $input The input data to validate.
     *
     * @return bool
     */
    public function validateExtraDataInput(array $input): bool;

    /**
     * Prepare the answer for the end user.
     * This method is called before saving the answer.
     *
     * @param Question $question The question data.
     * @param mixed $answer The raw answer data.
     * @return mixed The prepared answer data.
     */
    public function prepareEndUserAnswer(Question $question, mixed $answer): mixed;

    /**
     * Prepare the extra data for the question.
     * This method is called before saving the question.
     *
     * @param array $input The input data to prepare.
     * @return array The prepared extra data.
     */
    public function prepareExtraData(array $input): array;

    /**
     * Render the administration template for the given question.
     * This template is used on the form editor page.
     *
     * @param Question|null $question Given question's data. May be null for a new question.
     *
     * @return string
     */
    public function renderAdministrationTemplate(?Question $question): string;

    /**
     * Render the administration options template for the given question.
     * This template is used on the form editor page.
     *
     * @param Question|null $question Given question's data. May be null for a new question.
     *
     * @return string
     */
    public function renderAdministrationOptionsTemplate(?Question $question): string;

    /**
     * Render the end up user template for a given question.
     * This template is used when rendered forms are displayed to users.
     *
     * @param Question $question Given question's data.
     *
     * @return string
     */
    public function renderEndUserTemplate(Question $question): string;

    /**
     * Render the given answer.
     * This template is used when rendering answers for a form.
     *
     * @param mixed $answer Given raw answer data.
     *
     * @return string
     */
    public function renderAnswerTemplate($answer): string;

    /**
     * Get the name of this questions type.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the category of this question type.
     *
     * @return QuestionTypeCategory
     */
    public function getCategory(): QuestionTypeCategory;

    /**
     * Get the weight of this question type.
     * The weight is used to sort question types in a category.
     *
     * @return int
     */
    public function getWeight(): int;
}
