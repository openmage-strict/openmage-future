<?php

/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   OpenMage
 * @package    OpenMage_Tests
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace OpenMage\Tests\Unit\Mage\Admin\Helper;

use Mage;
use Mage_Admin_Helper_Variable as Subject;
use OpenMage\Tests\Unit\OpenMageTest;

class VariableTest extends OpenMageTest
{
    public static Subject $subject;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$subject = Mage::helper('admin/variable');
    }

    /**
     * @covers Mage_Admin_Helper_Variable::isPathAllowed()
     * @group Helper
     */
    public function testIsPathAllowed(): void
    {
        static::assertIsBool(self::$subject->isPathAllowed(''));
    }
}
