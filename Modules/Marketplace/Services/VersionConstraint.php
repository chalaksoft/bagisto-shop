<?php

namespace Modules\Marketplace\Services;

/**
 * تطبیق نسخه با قید‌های سادهٔ `module.json` مثل `>=2.0.0` یا `^8.2`.
 *
 * عمداً composer/semver را نمی‌آورد: مسیر پایه باید روی هاست اشتراکیِ بدون
 * composer کار کند و تنها قیدهایی که واقعاً در مانیفست‌ها می‌نویسیم همین چند
 * شکل ساده‌اند.
 *
 * قیدهای جداشده با کاما «و» منطقی‌اند: `>=2.0.0,<3.0.0`
 */
class VersionConstraint
{
    public static function satisfies(string $version, ?string $constraint): bool
    {
        if (blank($constraint) || $constraint === '*') {
            return true;
        }

        foreach (preg_split('/\s*,\s*/', trim($constraint)) as $part) {
            if (! static::matchesSingle($version, $part)) {
                return false;
            }
        }

        return true;
    }

    protected static function matchesSingle(string $version, string $constraint): bool
    {
        if (! preg_match('/^(>=|<=|!=|>|<|\^|~|=)?\s*v?(.+)$/', trim($constraint), $matches)) {
            return false;
        }

        [$operator, $target] = [$matches[1] ?: '=', trim($matches[2])];

        /**
         * `^2.3` یعنی از 2.3 تا قبل از 3.0 — و `~2.3.1` یعنی تا قبل از 2.4.
         * هر دو به یک بازه ترجمه می‌شوند تا مقایسه یک‌جور بماند.
         */
        if (in_array($operator, ['^', '~'], true)) {
            $segments = explode('.', $target);

            $upper = $operator === '^' || count($segments) < 2
                ? ((int) $segments[0] + 1).'.0.0'
                : $segments[0].'.'.((int) $segments[1] + 1).'.0';

            return version_compare($version, $target, '>=')
                && version_compare($version, $upper, '<');
        }

        return version_compare($version, $target, $operator === '=' ? '==' : $operator);
    }
}
