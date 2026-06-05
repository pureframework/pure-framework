<?php

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

declare(strict_types=1);

namespace PureFramework\Tests\Unit;

use PureFramework\Form;
use PureFramework\Tests\Support\NameConstraintEntity;
use PureFramework\Tests\TestCase;

final class FormTest extends TestCase
{
	public function testValidateRequiredField(): void
	{
		$form = new Form();
		$form->addField('name', 'Name', [], [new NameConstraintEntity()]);
		$form->setValues(['name' => '']);
		$this->assertFalse($form->validate());

		$form->setValues(['name' => 'valid']);
		$this->assertTrue($form->validate());
	}
}
