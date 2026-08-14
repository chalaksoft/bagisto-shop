<?php

namespace Modules\Marketplace\Services;

use RuntimeException;

/**
 * هر دلیلی که بستهٔ ماژول رد می‌شود — پیامش مستقیم به ادمین نشان داده می‌شود،
 * پس باید فارسی و قابل‌فهم باشد، نه متن فنی کتابخانه.
 */
class PackageException extends RuntimeException
{
}
