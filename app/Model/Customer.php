<?php

namespace App\Model;

use Webkul\Customer\Models\Customer as BaseCustomer;

/**
 * مشتری فروشگاه با قرارداد کاربرِ هستهٔ Elementor/Blog.
 *
 * نوار ادمینِ فرانت و ویجت دیدگاه‌ها روی کاربرِ لاگین‌شدهٔ گاردِ پیش‌فرض
 * `isAdmin()` و `full_name` صدا می‌زنند. در بجیستو گاردِ پیش‌فرض `customer`
 * است، پس مدل مشتری هم باید همین دو را داشته باشد وگرنه صفحهٔ فروشگاه برای
 * مشتری لاگین‌شده خطا می‌دهد.
 *
 * در config/auth.php به‌عنوان مدلِ provider «customers» ثبت شده است.
 */
class Customer extends BaseCustomer
{
    /** مشتری هرگز ادمین نیست – نوار ادمین برایش رندر نمی‌شود */
    public function isAdmin(): bool
    {
        return false;
    }

    /** نام کامل – بجیستو خودش accessor «name» را از نام و نام خانوادگی می‌سازد */
    public function getFullNameAttribute(): string
    {
        return (string) $this->name;
    }
}
