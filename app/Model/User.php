<?php

namespace App\Model;

use Illuminate\Support\Facades\Storage;
use Webkul\User\Models\Admin;

/**
 * کاربر ادمین بجیستو با قرارداد کاربرِ هستهٔ Elementor/Blog.
 *
 * هسته (که با laravel_clinic مشترک است) نویسندهٔ نوشته‌ها و کاربر نوار ادمین
 * را App\Model\User می‌شناسد و از آن `full_name`، `avatar` و `isAdmin()`
 * می‌خواهد. جدول ادمین‌های بجیستو `name` و `image` دارد، پس این کلاس فقط همان
 * نام‌ها را روی مدل خود بجیستو نگاشت می‌کند.
 *
 * در config/auth.php به‌عنوان مدلِ provider «admins» ثبت شده تا
 * auth('admin')->user() هم همین نمونه باشد.
 */
class User extends Admin
{
    /**
     * جدول باید صریح باشد: نام کلاس User است و قرارداد الوکوئنت آن را به
     * جدول `users` می‌برد، در حالی که ادمین‌های بجیستو در `admins` هستند.
     */
    protected $table = 'admins';

    /** نام کامل – معادل ستون name جدول admins */
    public function getFullNameAttribute(): string
    {
        return (string) $this->attributes['name'];
    }

    /** مسیر آواتار روی دیسک – معادل ستون image جدول admins */
    public function getAvatarAttribute(): ?string
    {
        return $this->attributes['image'] ?? null;
    }

    /**
     * هر کاربرِ جدول admins ادمین است؛ تفکیک دسترسی‌ها در بجیستو با نقش‌ها
     * (Bouncer/ACL) انجام می‌شود نه با این پرچم.
     */
    public function isAdmin(): bool
    {
        return true;
    }

    /** آدرس عمومی آواتار (نوار ادمین و ویجت «دربارهٔ نویسنده») */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::url($this->avatar) : null;
    }
}
