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

namespace tests\units\Glpi\Form\AnswersHandler;

use DbTestCase;
use Glpi\Form\Question;
use Glpi\Tests\FormBuilder;
use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypeEmail;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeNumber;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Tests\FormTesterTrait;
use User;

class AnswersHandler extends DbTestCase
{
    use FormTesterTrait;

    /**
     * Data provider for testSaveAnswers
     *
     * @return iterable
     */
    protected function testSaveAnswersProvider(): iterable
    {
        $this->login();
        $users_id = getItemByTypeName(User::class, TU_USER, true);

        // Fist form
        $builder = new FormBuilder("Form 1");
        $builder
            ->addQuestion("First name", QuestionTypeShortText::class)
            ->addQuestion("Last name", QuestionTypeShortText::class)
            ->addQuestion("Age", QuestionTypeNumber::class)
            ->addQuestion("Thoughts about GLPI", QuestionTypeLongText::class)
        ;
        $form_1 = $this->createForm($builder);

        // Submit first answer
        yield [
            'form'     => $form_1,
            'users_id' => $users_id,
            'answers'  => [
                $this->getQuestionId($form_1, "First name") => "John",
                $this->getQuestionId($form_1, "Last name") => "Doe",
                $this->getQuestionId($form_1, "Age") => 78,
                $this->getQuestionId($form_1, "Thoughts about GLPI") => "I love GLPI!!!"
            ],
            'expected_set' => [
                'forms_forms_id' => $form_1->getID(),
                'users_id'       => $users_id,
                'name'           => "Form 1 #1",
                'index'          => 1,
                'answers'        => [
                    [
                        'question' => $this->getQuestionId($form_1, "First name"),
                        'value'    => "John",
                        'label'    => "First name",
                        'type'     => QuestionTypeShortText::class,
                    ],
                    [
                        'question' => $this->getQuestionId($form_1, "Last name"),
                        'value'    => "Doe",
                        'label'    => "Last name",
                        'type'     => QuestionTypeShortText::class,
                    ],
                    [
                        'question' => $this->getQuestionId($form_1, "Age"),
                        'value'    => 78,
                        'label'    => "Age",
                        'type'     => QuestionTypeNumber::class,
                    ],
                    [
                        'question' => $this->getQuestionId($form_1, "Thoughts about GLPI"),
                        'value'    => "I love GLPI!!!",
                        'label'    => "Thoughts about GLPI",
                        'type'     => QuestionTypeLongText::class,
                    ],
                ],
            ]
        ];

        // Submit second answer
        yield [
            'form'     => $form_1,
            'users_id' => $users_id,
            'answers'  => [
                $this->getQuestionId($form_1, "First name") => "John",
                $this->getQuestionId($form_1, "Last name") => "Smith",
                $this->getQuestionId($form_1, "Age") => 19,
                $this->getQuestionId($form_1, "Thoughts about GLPI") => "GLPI is incredible"
            ],
            'expected_set' => [
                'forms_forms_id' => $form_1->getID(),
                'users_id'       => $users_id,
                'name'           => "Form 1 #2", // Increased to #2
                'index'          => 2,           // Increased to #2
                'answers'        => [
                    [
                        'question' => $this->getQuestionId($form_1, "First name"),
                        'value'    => "John",
                        'label'    => "First name",
                        'type'     => QuestionTypeShortText::class,
                    ],
                    [
                        'question' => $this->getQuestionId($form_1, "Last name"),
                        'value'    => "Smith",
                        'label'    => "Last name",
                        'type'     => QuestionTypeShortText::class,
                    ],
                    [
                        'question' => $this->getQuestionId($form_1, "Age"),
                        'value'    => 19,
                        'label'    => "Age",
                        'type'     => QuestionTypeNumber::class,
                    ],
                    [
                        'question' => $this->getQuestionId($form_1, "Thoughts about GLPI"),
                        'value'    => "GLPI is incredible",
                        'label'    => "Thoughts about GLPI",
                        'type'     => QuestionTypeLongText::class,
                    ],
                ],
            ]
        ];

        // Second form
        $builder = new FormBuilder("Form 2");
        $builder
            ->addQuestion("Contact email", QuestionTypeEmail::class)
        ;
        $form_2 = $this->createForm($builder);

        yield [
            'form'     => $form_2,
            'users_id' => $users_id,
            'answers'  => [
                $this->getQuestionId($form_2, "Contact email") => "glpi@teclib.com",
            ],
            'expected_set' => [
                'forms_forms_id' => $form_2->getID(),
                'users_id'       => $users_id,
                'name'           => "Form 2 #1", // Back to #1 since this is a different form
                'index'          => 1,
                'answers'        => [
                    [
                        'question' => $this->getQuestionId($form_2, "Contact email"),
                        'value'    => "glpi@teclib.com",
                        'label'    => "Contact email",
                        'type'     => QuestionTypeEmail::class,
                    ],
                ],
            ]
        ];
    }

    /**
     * Test the saveAnswers method
     *
     * @dataProvider testSaveAnswersProvider
     *
     * @param Form  $form         The form to save answers for
     * @param array $answers      The answers to save
     * @param int   $users_id     The user id
     * @param array $expected_set The expected answers set
     *
     * @return void
     */
    public function testSaveAnswers(
        Form $form,
        array $answers,
        int $users_id,
        array $expected_set
    ): void {
        $handler = \Glpi\Form\AnswersHandler\AnswersHandler::getInstance();
        $answer_set = $handler->saveAnswers($form, $answers, $users_id);

        foreach ($expected_set as $field => $value) {
            $this
                ->variable($value)
                ->isEqualTo($answer_set->fields[$field])
            ;
        }

        // The `createDestinations` part of the `saveAnswers` method is tested
        // by each possible destinations type in their own test file
    }

    /**
     * Data provider for testPrepareAnswersForDisplay method
     *
     * @return iterable
     */
    protected function testPrepareAnswersForDisplayProvider(): iterable
    {
        $this->login();
        $users_id = getItemByTypeName(User::class, TU_USER, true);

        // Build form
        $builder = new FormBuilder("Form 1");
        $builder
            ->addQuestion("First name", QuestionTypeShortText::class)
            ->addQuestion("Last name", QuestionTypeShortText::class)
            ->addQuestion("Age", QuestionTypeNumber::class)
        ;
        $form_1 = $this->createForm($builder);

        // Register an answer
        $handler = \Glpi\Form\AnswersHandler\AnswersHandler::getInstance();
        $answers_set = $handler->saveAnswers($form_1, [
            $this->getQuestionId($form_1, "First name") => "Frédéric",
            $this->getQuestionId($form_1, "Last name") => "Chopin",
            $this->getQuestionId($form_1, "Age") => 39,
        ], $users_id);

        // First test: ensure type is replaced by its matching class
        yield [
            'answers' => $answers_set->fields['answers'],
            'expected_prepared_answers' => [
                [
                    'question' => $this->getQuestionId($form_1, "First name"),
                    'value'    => "Frédéric",
                    'label'    => "First name",
                    'type'     => new QuestionTypeShortText(),
                ],
                [
                    'question' => $this->getQuestionId($form_1, "Last name"),
                    'value'    => "Chopin",
                    'label'    => "Last name",
                    'type'     => new QuestionTypeShortText(),
                ],
                [
                    'question' => $this->getQuestionId($form_1, "Age"),
                    'value'    => 39,
                    'label'    => "Age",
                    'type'     => new QuestionTypeNumber(),
                ],
            ],
        ];

        // Build form
        $builder = new FormBuilder("Form 2");
        $builder
            ->addQuestion("Valid question", QuestionTypeShortText::class)
            ->addQuestion("Invalid question", "Not a question type")
        ;
        $form_2 = $this->createForm($builder);

        // We can't use `getQuestionId` to get an invalid question id
        $invalid_questions_id = getItemByTypeName(Question::class, "Invalid question", true);

        // Register an answer
        $handler = \Glpi\Form\AnswersHandler\AnswersHandler::getInstance();
        $answers_set = $handler->saveAnswers($form_2, [
            $this->getQuestionId($form_2, "Valid question") => "Valid answer",
            $invalid_questions_id => "Invalid answer",
        ], $users_id);

        $error =  "Unknown question: $invalid_questions_id";
        $this->when(
            function () {
                // Second test, ensure invalid types are dropped
                yield [
                    'answers' => $answers_set->fields['answers'],
                    'expected_prepared_answers' => [
                        [
                            'question' => $this->getQuestionId($form_2, "Valid question"),
                            'value'    => "Valid answer",
                            'label'    => "Valid question",
                            'type'     => new QuestionTypeShortText(),
                        ],
                    ],
                ];
            }
        )->error()->withType(E_USER_WARNING)->exists()->withMessage($error);
    }

    /**
     * Test the prepareAnswersForDisplay method
     *
     * @dataProvider testPrepareAnswersForDisplayProvider
     *
     * @param array $answers                   The answers to prepare
     * @param array $expected_prepared_answers The expected prepared answers
     *
     * @return void
     */
    public function testPrepareAnswersForDisplay(
        array $answers,
        array $expected_prepared_answers
    ): void {
        $handler = \Glpi\Form\AnswersHandler\AnswersHandler::getInstance();
        $prepared_answers = $handler->prepareAnswersForDisplay($answers);

        $this
            ->array($prepared_answers)
            ->isEqualTo($expected_prepared_answers)
        ;
    }
}
