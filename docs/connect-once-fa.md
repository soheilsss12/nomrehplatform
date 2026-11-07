# راهنمای اتصال یک‌بارهٔ تشخیص — بدون دانش فنی

این فایل را دنبال کنید. سه دقیقه وقت می‌گیرد. هیچ چیزی روی هاست تغییر نمی‌کند و چیزی حذف نمی‌شود.

---

## ۱) یک کلید SSH بسازید (deploy key)

روی **کامپیوتر خودتان** (نه هاست)، در Plesk:

Plesk → **Websites & Domains** → `nomreh.arounidea.com` → **SSH Keys** (یا در پنل هاست: **SSH Access** → Manage keys → **Add Key**) → **Generate** / **Add**.

اگر Plesk این گزینه را ندارد، بگویید — راه دوم GitHub وجود دارد ولی طولانی‌تر است.

بعد از ساخت، **کلید خصوصی** (Private key / `id_rsa`) را دانلود کنید؛ یک فایل متنی است که با `-----BEGIN` شروع می‌شود.

> اگر Plesk فقط به شما اجازهٔ «Authorize key» می‌دهد و کلید خصوصی را نشان نمی‌دهد، دوباره بگویید؛ آن حالت راه دیگری دارد.

---

## ۲) سه secret در GitHub بسازید

GitHub → ریپوی `soheilsss12/nomrehplatform` → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

| Name | Value |
|---|---|
| `HOST_SSH_KEY` | محتوای کامل فایل کلید خصوصی (از `-----BEGIN` تا آخر) |
| `HOST_SSH_USER` | نام کاربری SSH همان صفحهٔ Plesk (مثلاً `root` یا نام دامنه) |
| `HOST_SSH_FINGERPRINT` | (اختیاری) خروجی `ssh-keyscan nomreh.arounidea.com` |

⚠️ رمز عبور Plesk را **نگذارید**. فقط کلید.

---

## ۳) workflow را اجرا کنید

GitHub → تب **Actions** → `host-diagnose` → **Run workflow** → **Run workflow**

فقط یک بار. چیزی در حلقه نیست.

---

## ۴) نتیجه را برای من بفرستید

بعد از پایان، روی همان اجرا کلیک کنید و کل متن لاگ را کپی/اسکرین‌شات کنید. مهم‌ترین خطوط:

- `### KAYA_TCP` → `TCP_OK` یا `TCP_TIMEOUT`
- `### EGRESS_IP` → IP خروجی
- `### IFACES` → آدرس‌های روی سرور
- `### KAYA_TLS` → `Connected` یا `SSL_connect` خراب

اگر جایی خطا داد، همان خطا را بفرستید. مهم‌ترین حالت: `kex_exchange_identification: Connection closed` ⇒ SSH از این‌جا بسته است و باید راه دومی برویم.

---

## نکتهٔ امنیتی

کلید فقط برای همین تشخیص است. بعد از تمام شدن کار، در Plesk آن کلید را **Revoke/Delete** کنید و secret را هم در GitHub پاک کنید. کلیدها را در چت نفرستید.
