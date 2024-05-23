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

namespace Glpi\Tests;

use Glpi\Form\AccessControl\FormAccessControl;
use Glpi\Form\AccessControl\FormAccessControlManager;
use Glpi\Form\Comment;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use Glpi\Form\Tag\Tag;
use Glpi\Tests\FormBuilder;

/**
 * Helper trait to tests helpdesk form related features
 * Should only be used on DbTestCase classes as it calls some of its methods.
 */
trait FormTesterTrait
{
    /**
     * Helper method to help creating complex forms using the FormBuilder class.
     *
     * @param FormBuilder $builder RuleConfiguration
     *
     * @return Form Created form
     */
    protected function createForm(FormBuilder $builder): Form
    {
        // Create form
        $form = $this->createItem(Form::class, [
            'name'                  => $builder->getName(),
            'entities_id'           => $builder->getEntitiesId(),
            'is_recursive'          => $builder->getIsRecursive(),
            'is_active'             => $builder->getIsActive(),
            'header'                => $builder->getHeader(),
            'is_draft'              => $builder->getIsDraft(),
            '_do_not_init_sections' => true, // We will handle sections ourselves
        ]);

        foreach ($builder->getSections() as $section_data) {
            // Create section
            $section = $this->createItem(Section::class, [
                'forms_forms_id' => $form->getID(),
                'name'           => $section_data['name'],
                'description'    => $section_data['description'],
            ]);

            // Create questions
            foreach ($section_data['questions'] as $question_data) {
                $this->createItem(Question::class, [
                    'forms_sections_id' => $section->getID(),
                    'name'              => $question_data['name'],
                    'type'              => $question_data['type'],
                    'is_mandatory'      => $question_data['is_mandatory'],
                    'default_value'     => $question_data['default_value'],
                    'extra_data'        => $question_data['extra_data'],
                ]);
            }

            // Create comments
            foreach ($section_data['comments'] as $comment_data) {
                $this->createItem(Comment::class, [
                    'forms_sections_id' => $section->getID(),
                    'name'              => $comment_data['name'],
                    'description'       => $comment_data['description'],
                ]);
            }
        }

        // Create destinations
        foreach ($builder->getDestinations() as $itemtype => $destinations) {
            foreach ($destinations as $destination_data) {
                $this->createItem(FormDestination::class, [
                    'forms_forms_id' => $form->getID(),
                    'itemtype'       => $itemtype,
                    'name'           => $destination_data['name'],
                    'config'         => $destination_data['config'],
                ], ['config']);
            }
        }

        // Create access controls
        foreach ($builder->getAccessControls() as $strategy_class => $config) {
            $this->createItem(FormAccessControl::class, [
                'forms_forms_id' => $form->getID(),
                'strategy'       => $strategy_class,
                '_config'        => $config,
                'is_active'      => true,
            ]);
        }

        // Reload form
        $form->getFromDB($form->getID());

        return $form;
    }

    /**
     * Helper method to access the ID of a question for a given form.
     *
     * @param Form        $form          Given form
     * @param string      $question_name Question name to look for
     * @param string|null $section_name  Optional section name, might be needed if
     *                                   multiple sections have questions with the
     *                                   same names.
     *
     * @return int The ID of the question
     */
    protected function getQuestionId(
        Form $form,
        string $question_name,
        string $section_name = null,
    ): int {
        // Make sure form is up to date
        $form->getFromDB($form->getID());

        // Get questions
        $questions = $form->getQuestions();

        if ($section_name === null) {
            // Search by name
            $filtered_questions = array_filter(
                $questions,
                fn($question) => $question->fields['name'] === $question_name
            );

            $question = array_pop($filtered_questions);
            return $question->getID();
        } else {
            // Find section
            $sections = $form->getSections();
            $filtered_sections = array_filter(
                $sections,
                fn($section) => $section->fields['name'] === $section_name
            );
            $section = array_pop($filtered_sections);

            // Search by name AND section
            $filtered_questions = array_filter(
                $questions,
                fn($question) => $question->fields['name'] === $question_name
                    && $question->fields['forms_sections_id'] === $section->getID()
            );
            $this->array($filtered_questions)->hasSize(1);
            $question = array_pop($filtered_questions);
            return $question->getID();
        }
    }

    /**
     * Helper method to access the ID of a section for a given form.
     *
     * @param Form        $form         Given form
     * @param string      $section_name Section name to look for
     *
     * @return int The ID of the section
     */
    protected function getSectionId(
        Form $form,
        string $section_name,
    ): int {
        // Make sure form is up to date
        $form->getFromDB($form->getID());

        // Get sections
        $sections = $form->getSections();

        // Search by name
        $filtered_sections = array_filter(
            $sections,
            fn($section) => $section->fields['name'] === $section_name
        );

        $section = array_pop($filtered_sections);
        return $section->getID();
    }

    /**
     * Helper method to access the ID of a comment for a given form.
     *
     * @param Form        $form         Given form
     * @param string      $comment_name Comment name to look for
     * @param string|null $section_name Optional section name, might be needed if
     *
     * @return int The ID of the comment
     */
    protected function getCommentId(
        Form $form,
        string $comment_name,
        string $section_name = null,
    ): int {
        // Make sure form is up to date
        $form->getFromDB($form->getID());

        // Get comments
        $comments = $form->getComments();

        if ($section_name === null) {
            // Search by name
            $filtered_comments = array_filter(
                $comments,
                fn($comment) => $comment->fields['name'] === $comment_name
            );

            $comment = array_pop($filtered_comments);
            return $comment->getID();
        } else {
            // Find section
            $sections = $form->getSections();
            $filtered_sections = array_filter(
                $sections,
                fn($section) => $section->fields['name'] === $section_name
            );
            $section = array_pop($filtered_sections);

            // Search by name AND section
            $filtered_comments = array_filter(
                $comments,
                fn($comment) => $comment->fields['name'] === $comment_name
                    && $comment->fields['forms_sections_id'] === $section->getID()
            );
            $this->array($filtered_comments)->hasSize(1);
            $comment = array_pop($filtered_comments);
            return $comment->getID();
        }
    }

    /**
     * Get the given access control object of a form.
     *
     * @param Form   $form         Form to get the access control from
     * @param string $control_type Type of access control to get
     *
     * @return FormAccessControl
     */
    protected function getAccessControl(
        Form $form,
        string $control_type
    ): FormAccessControl {
        $controls = $form->getAccessControls();

        $controls = array_filter(
            $controls,
            fn($control) => $control->fields['strategy'] === $control_type
        );

        $control = array_pop($controls);
        return $control;
    }

    protected function getTagByName(array $tags, string $name): Tag
    {
        return current(array_filter(
            $tags,
            fn($tag) => $tag->label === $name,
        ));
    }
}
