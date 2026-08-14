#!/usr/bin/env bash
#
# ساخت بستهٔ نصب برای هاست اشتراکی.
#
# روی هاست اشتراکی composer نیست، پس `vendor/` باید داخل خود بسته باشد — همان
# چیزی که INSTALL.md به آن ارجاع می‌دهد. خروجی در `dist/bagisto-shop.zip` است
# و همان چیزی است که در Releases گیت‌هاب می‌گذاریم.
#
#   bash bin/build-release.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/dist"
STAGE="$OUT/bagisto-shop"

cd "$ROOT"

# ⚠️ این کار وابستگی‌های dev را از `vendor/` همین پوشه برمی‌دارد. آخر اسکریپت
# دوباره برمی‌گردند، ولی اگر وسط کار قطع شد، یک `composer install` بزنید.
echo "==> وابستگی‌های production"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "==> آماده‌سازی پوشهٔ موقت"
rm -rf "$STAGE" "$OUT/bagisto-shop.zip"
mkdir -p "$STAGE"

# فقط چیزهایی که برای اجرا لازم‌اند. هرچه اینجا نیست عمداً نیست:
# `.env` رمز دارد، `.git` تاریخچه است، `storage/keys` کلید خصوصی مخزن است و
# `Modules/MarketplaceServer` اصلاً مال این بسته نیست.
for item in \
    app artisan bootstrap composer.json composer.lock config database lang \
    Modules packages public resources routes storage vendor \
    .env.example .htaccess INSTALL.md README.md LICENSE
do
    [ -e "$item" ] && cp -R "$item" "$STAGE/" || true
done

echo "==> پاک‌سازی چیزهایی که نباید در بسته باشند"
rm -rf "$STAGE/Modules/MarketplaceServer"
rm -rf "$STAGE/storage/keys"
rm -rf "$STAGE/storage/app/marketplace"
rm -rf "$STAGE/storage/logs"/*.log
rm -rf "$STAGE/storage/framework/cache/data"/* \
       "$STAGE/storage/framework/sessions"/* \
       "$STAGE/storage/framework/views"/* \
       "$STAGE/bootstrap/cache"/*.php
find "$STAGE" -name '.DS_Store' -delete 2>/dev/null || true
find "$STAGE" -name '._*' -delete 2>/dev/null || true

# ساختار پوشه‌ها باید بماند، وگرنه لاراول سر اولین ریکوئست خطا می‌دهد.
for dir in storage/framework/cache/data storage/framework/sessions \
           storage/framework/views storage/logs bootstrap/cache
do
    mkdir -p "$STAGE/$dir"
    touch "$STAGE/$dir/.gitignore"
done

echo "==> بستن zip"
cd "$OUT"
# `-x` روی فراداده‌های مک: بسته نباید با آشغال فایندر پر شود.
zip -qr bagisto-shop.zip bagisto-shop -x '*.DS_Store' -x '__MACOSX/*'
rm -rf "$STAGE"

echo "==> برگرداندن وابستگی‌های dev برای ادامهٔ کار"
cd "$ROOT"
composer install --no-interaction --quiet

echo
echo "آماده شد: dist/bagisto-shop.zip  ($(du -h "$OUT/bagisto-shop.zip" | cut -f1))"
echo "این فایل را در Releases گیت‌هاب بگذارید."
