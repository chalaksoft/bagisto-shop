# کلید عمومی مخزن

فایل `repository.pub` باید کلید **عمومی** مخزن ماژول‌ها باشد. مسیرش در
`Config/marketplace.php` زیر کلید `public_key` تعریف شده.

هر بستهٔ zip روی سایت مخزن با کلید **خصوصی** امضا می‌شود و کلاینت با همین کلید
عمومی امضا را بررسی می‌کند. بدون امضای معتبر، نصب متوقف می‌شود — نه با هشدار،
بلکه با توقف کامل.

## کلید خصوصی هرگز اینجا نمی‌آید

کلید خصوصی فقط روی سرور مخزن می‌ماند. اگر داخل این ریپو بیاید، هر کسی که به
کد دسترسی دارد می‌تواند بستهٔ دلخواه امضا کند و کل زنجیرهٔ اعتماد بی‌معنا
می‌شود.

## ساخت جفت‌کلید

روی سرور مخزن:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out repository.key
openssl rsa -in repository.key -pubout -out repository.pub
```

`repository.key` روی همان سرور می‌ماند (با دسترسی ۶۰۰) و `repository.pub` کنار
همین فایل در ریپوی فروشگاه قرار می‌گیرد.

## امضای یک بسته

```bash
openssl dgst -sha256 -sign repository.key -out package.sig package.zip
base64 -w0 package.sig
```

خروجی base64 همان چیزی است که مخزن در هدر `X-Package-Signature` برمی‌گرداند.

## تا وقتی مخزن راه نیفتاده

فاز ۲ فقط نصب از فایل محلی است و `MARKETPLACE_REQUIRE_SIGNATURE` پیش‌فرض خاموش
است. **پیش از راه‌اندازی روی پروداکشن** باید روشن شود:

```
MARKETPLACE_REQUIRE_SIGNATURE=true
```
