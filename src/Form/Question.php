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

namespace Glpi\Form;

use CommonDBChild;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Form\QuestionType\QuestionTypeInterface;
use Glpi\Form\QuestionType\QuestionTypesManager;
use Log;
use Override;
use ReflectionClass;

/**
 * Question of a given helpdesk form's section
 */
final class Question extends CommonDBChild implements BlockInterface
{
    public static $itemtype = Section::class;
    public static $items_id = 'forms_sections_id';

    public $dohistory = true;

    #[Override]
    public static function getTypeName($nb = 0)
    {
        return _n('Question', 'Questions', $nb);
    }

    #[Override]
    public function post_addItem()
    {
        // Report logs to the parent form
        $this->logCreationInParentForm();
    }

    #[Override]
    public function post_updateItem($history = true)
    {
        // Report logs to the parent form
        $this->logUpdateInParentForm($history);
    }

    #[Override]
    public function post_deleteFromDB()
    {
        // Report logs to the parent form
        $this->logDeleteInParentForm();
    }

    public function displayBlockForEditor(): void
    {
        TemplateRenderer::getInstance()->display('pages/admin/form/form_question.html.twig', [
            'form'                   => $this->getForm(),
            'question'               => $this,
            'question_type'          => $this->getQuestionType(),
            'question_types_manager' => QuestionTypesManager::getInstance(),
            'section'                => $this->getItem(),
            'can_update'             => $this->getForm()->canUpdate(),
        ]);
    }

    /**
     * Get type object for the current object.
     *
     * @return QuestionTypeInterface|null
     */
    public function getQuestionType(): ?QuestionTypeInterface
    {
        $type = $this->fields['type'] ?? "";

        if (
            !is_a($type, QuestionTypeInterface::class, true)
            || (new ReflectionClass($type))->isAbstract()
        ) {
            return null;
        }

        return new $type();
    }

    /**
     * Get the extra datas for the question.
     *
     * @return ?array
     */
    public function getExtraDatas(): ?array
    {
        if (!isset($this->fields['extra_data'])) {
            return null;
        }

        return json_decode($this->fields['extra_data'] ?? "[]", true);
    }

    /**
     * Get the parent form of this question
     *
     * @return Form
     */
    public function getForm(): Form
    {
        return $this->getItem()->getItem();
    }

    public function getEndUserInputName(): string
    {
        return (new EndUserInputNameProvider())->getEndUserInputName($this);
    }

    public function prepareInputForAdd($input)
    {
        $this->prepareInput($input);
        return parent::prepareInputForUpdate($input);
    }

    public function prepareInputForUpdate($input)
    {
        $this->prepareInput($input);
        return parent::prepareInputForUpdate($input);
    }

    private function prepareInput(&$input)
    {
        $question_type = $this->getQuestionType();

        // The question type can be null when the question is created
        // We need to instantiate the question type to format and validate attributes
        if (
            $question_type === null
            && isset($input['type'])
            && class_exists($input['type'])
        ) {
            $question_type = new $input['type']();
        }

        if ($question_type) {
            if (isset($input['default_value'])) {
                $input['default_value'] = $question_type->formatDefaultValueForDB($input['default_value']);
            }

            $extra_data = $input['extra_data'] ?? [];
            if (is_string($extra_data)) {
                if (empty($extra_data)) {
                    $extra_data = [];
                } else {
                    // Decode extra data as JSON
                    $extra_data = json_decode($extra_data, true);
                }
            }

            $is_extra_data_valid = $question_type->validateExtraDataInput($extra_data);

            if (!$is_extra_data_valid) {
                throw new \InvalidArgumentException("Invalid extra data for question");
            }

            // Prepare extra data
            $extra_data = $question_type->prepareExtraData($extra_data);

            // Save extra data as JSON
            if (!empty($extra_data)) {
                $input['extra_data'] = json_encode($extra_data);
            }
        }
    }

    /**
     * Manually update logs of the parent form item
     *
     * @return void
     */
    protected function logCreationInParentForm(): void
    {
        if ($this->input['_no_history'] ?? false) {
            return;
        }

        $form = $this->getForm();
        $changes = [
            '0',
            '',
            $this->getHistoryNameForItem($form, 'add'),
        ];

        // Report logs to the parent form
        Log::history(
            $form->getID(),
            $form->getType(),
            $changes,
            $this->getType(),
            static::$log_history_add
        );

        parent::post_addItem();
    }

    /**
     * Manually update logs of the parent form item
     *
     * @param bool $history
     *
     * @return void
     */
    protected function logUpdateInParentForm($history = true): void
    {
        if ($this->input['_no_history'] ?? false) {
            return;
        }

        $form = $this->getForm();

        $oldvalues = $this->oldvalues;
        unset($oldvalues[static::$itemtype]);
        unset($oldvalues[static::$items_id]);

        foreach (array_keys($oldvalues) as $field) {
            if (in_array($field, $this->getNonLoggedFields())) {
                continue;
            }
            $changes = $this->getHistoryChangeWhenUpdateField($field);
            if ((!is_array($changes)) || (count($changes) != 3)) {
                continue;
            }

            Log::history(
                $form->getID(),
                $form->getType(),
                $changes,
                $this->getType(),
                static::$log_history_update
            );
        }

        parent::post_updateItem($history);
    }

    /**
     * Manually update logs of the parent form item
     *
     * @return void
     */
    protected function logDeleteInParentForm(): void
    {
        if ($this->input['_no_history'] ?? false) {
            return;
        }

        $form = $this->getForm();
        $changes = [
            '0',
            '',
            $this->getHistoryNameForItem($form, 'delete'),
        ];

        Log::history(
            $form->getID(),
            $form->getType(),
            $changes,
            $this->getType(),
            static::$log_history_delete
        );

        parent::post_deleteFromDB();
    }
}
