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

namespace tests\units\Glpi\Form\QuestionType;

use DbTestCase;
use Glpi\Form\QuestionType\QuestionTypeInterface;
use Glpi\Form\QuestionType\QuestionTypeCategory;
use Glpi\Form\QuestionType\QuestionTypeRequestType;

class QuestionTypesManager extends DbTestCase
{
    /**
     * Test the getQuestionTypes method
     *
     * @return void
     */
    public function testGetDefaultTypeClass(): void
    {
        $manager = \Glpi\Form\QuestionType\QuestionTypesManager::getInstance();
        $default_type = $manager->getDefaultTypeClass();

        $this->string($default_type)->isNotEmpty();

        // Ensure the default type is a valid question type
        $is_question_type = is_a($default_type, QuestionTypeInterface::class, true);
        $this->boolean($is_question_type)->isTrue();

        // Ensure the default type is not an abstract class
        $is_abstract = (new \ReflectionClass($default_type))->isAbstract();
        $this->boolean($is_abstract)->isFalse();

        // Ensure constructor is working
        $question_type_object = new $default_type();
        $this->object($question_type_object);
    }

    /**
     * Test the getQuestionTypes method
     *
     * @return void
     */
    public function testGetCategories(): void
    {
        $manager = \Glpi\Form\QuestionType\QuestionTypesManager::getInstance();
        $categories = $manager->getCategories();

        $expected_categories = [
            QuestionTypeCategory::SHORT_ANSWER,
            QuestionTypeCategory::LONG_ANSWER,
            QuestionTypeCategory::DATE_AND_TIME,
            QuestionTypeCategory::ACTORS,
            QuestionTypeCategory::URGENCY,
            QuestionTypeCategory::REQUEST_TYPE,
            QuestionTypeCategory::FILE,
            QuestionTypeCategory::RADIO,
            QuestionTypeCategory::CHECKBOX,
        ];

        // Manual array comparison, `isEqualTo`  doesn't seem to work properly
        // with an array of enums
        $this->array($categories)->hasSize(count($expected_categories));
        foreach ($categories as $i => $category) {
            $this->object($category)->isEqualTo($expected_categories[$i]);
        }
    }

    /**
     * Data provider for the testGetTypesForCategory method
     *
     * @return iterable
     */
    protected function testGetTypesForCategoryProvider(): iterable
    {
        yield [
            QuestionTypeCategory::SHORT_ANSWER,
            [
                new \Glpi\Form\QuestionType\QuestionTypeShortText(),
                new \Glpi\Form\QuestionType\QuestionTypeEmail(),
                new \Glpi\Form\QuestionType\QuestionTypeNumber(),
            ]
        ];

        yield [
            QuestionTypeCategory::LONG_ANSWER,
            [
                new \Glpi\Form\QuestionType\QuestionTypeLongText(),
            ]
        ];

        yield [
            QuestionTypeCategory::DATE_AND_TIME,
            [
                new \Glpi\Form\QuestionType\QuestionTypeDateTime(),
            ]
        ];

        yield [
            QuestionTypeCategory::ACTORS,
            [
                new \Glpi\Form\QuestionType\QuestionTypeRequester(),
                new \Glpi\Form\QuestionType\QuestionTypeObserver(),
                new \Glpi\Form\QuestionType\QuestionTypeAssignee(),
            ]
        ];

        yield [
            QuestionTypeCategory::URGENCY,
            [
                new \Glpi\Form\QuestionType\QuestionTypeUrgency(),
            ]
        ];

        yield [
            QuestionTypeCategory::REQUEST_TYPE,
            [
                new \Glpi\Form\QuestionType\QuestionTypeRequestType(),
            ]
        ];

        yield [
            QuestionTypeCategory::FILE,
            [
                new \Glpi\Form\QuestionType\QuestionTypeFile(),
            ]
        ];

        yield [
            QuestionTypeCategory::RADIO,
            [
                new \Glpi\Form\QuestionType\QuestionTypeRadio(),
            ]
        ];

        yield [
            QuestionTypeCategory::CHECKBOX,
            [
                new \Glpi\Form\QuestionType\QuestionTypeCheckbox(),
            ]
        ];
    }

    /**
     * Test the getTypesForCategory method
     *
     * @dataProvider testGetTypesForCategoryProvider
     *
     * @param QuestionTypeCategory $category
     * @param array $expected_types
     *
     * @return void
     */
    public function testGetTypesForCategory(
        QuestionTypeCategory $category,
        array $expected_types
    ): void {
        $manager = \Glpi\Form\QuestionType\QuestionTypesManager::getInstance();
        $types = $manager->getTypesForCategory($category);
        $types = array_values($types); // Remove special keys

        $this->array($types)->isEqualTo($expected_types);
    }

    /**
     * This test case ensure all categories are defined by the
     * testGetTypesForCategoryProvider provider.
     *
     * This prevent us from forgetting to update this provider when adding new
     * questions types
     *
     * @return void
     */
    public function testEnsureAllCategoriesAreTested(): void
    {
        $manager = \Glpi\Form\QuestionType\QuestionTypesManager::getInstance();
        $provider_data = iterator_to_array($this->testGetTypesForCategoryProvider());

        $this
            ->array($provider_data)
            ->hasSize(
                count($manager->getCategories()),
                "All categories must be added to the `testGetTypesForCategoryProvider` provider"
            );
    }
}
