<?php

namespace Modules\Marketplace\Tests\Unit;

use Modules\Marketplace\Services\VersionConstraint;
use PHPUnit\Framework\TestCase;

class VersionConstraintTest extends TestCase
{
    public function test_empty_constraint_accepts_everything(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', null));
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', ''));
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', '*'));
    }

    public function test_comparison_operators(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', '>=2.0.0'));
        $this->assertFalse(VersionConstraint::satisfies('1.9.0', '>=2.0.0'));
        $this->assertTrue(VersionConstraint::satisfies('8.2.0', '>=8.2'));
        $this->assertFalse(VersionConstraint::satisfies('8.1.9', '>=8.2'));
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', '<3.0.0'));
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', '=2.3.7'));
        $this->assertFalse(VersionConstraint::satisfies('2.3.7', '2.3.6'));
    }

    /**
     * چند قید جداشده با کاما «و» منطقی‌اند — یعنی نسخه باید همه را برآورده کند.
     */
    public function test_comma_separated_constraints_are_all_required(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', '>=2.0.0,<3.0.0'));
        $this->assertFalse(VersionConstraint::satisfies('3.1.0', '>=2.0.0,<3.0.0'));
    }

    public function test_caret_allows_minor_bumps_but_not_major(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('2.9.9', '^2.3'));
        $this->assertFalse(VersionConstraint::satisfies('3.0.0', '^2.3'));
        $this->assertFalse(VersionConstraint::satisfies('2.2.0', '^2.3'));
    }

    public function test_tilde_allows_patch_bumps_but_not_minor(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('2.3.9', '~2.3.1'));
        $this->assertFalse(VersionConstraint::satisfies('2.4.0', '~2.3.1'));
    }

    public function test_leading_v_is_ignored(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('2.3.7', '>=v2.0.0'));
    }
}
