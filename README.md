# Higher Life — Laravel 12 backend

REST API (Sanctum) + Livewire admin panel. This folder is an **overlay**: create a fresh Laravel 12 app and copy these files over it.

## Setup
```bash
composer create-project laravel/laravel higher-life-api
cd higher-life-api
php artisan install:api                 # Sanctum + routes/api.php (answer "yes" to migrations)
composer require livewire/livewire

# copy this overlay into the project (overwrite when asked): app/ bootstrap/ config/ database/ resources/ routes/
```
In `.env` configure MySQL, Redis (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`), then add:
```
ESPEE_DRIVER=espee           # espee (Pay with Espees button, production) | transfer (manual fallback) | fake (local only)
ESPEE_BUTTON_ID=695bc9cfc64c3fc1adbce8ba3b559465   # id inside Espeespaybutton("...") from the Espees Merchant dashboard
TRIAL_DAYS=7                 # free trial for every new account (0 = off)
ESPEE_API_KEY=               # developer-console token, used only to confirm payments (not the merchant ID / wallet address)
ESPEE_TRANSFER_CODE=z5project  # only used by the transfer fallback (placeholder)
ESPEE_CURRENCY=ESP
KINGSCHAT_CLIENT_ID=        # from the KingsChat developer console
MEDIA_DISK=public            # "s3" for Cloudflare R2 / S3 / DigitalOcean Spaces
```
```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan queue:work          # separate terminal
php artisan schedule:work       # separate terminal (production: cron "* * * * * php artisan schedule:run")
```
- Admin panel: `/admin` — login `admin@higherlife.app` / `ChangeMe123!` (**change it immediately**).
- API base: `/api/v1`.

## Access model
Subscription required: after sign-up a user can only reach auth, profile, subscription and payment endpoints.
Every affirmation, category-content, collection, favorite and progress route sits behind the `premium` middleware and
returns HTTP 402 until the user has an active subscription (admins bypass it). The app routes 402s to the paywall.
The per-item "Premium" checkbox in the admin panel no longer changes access.

## Payments — "Pay with Espees" button
Higher Life uses the **Pay with Espees Button** generated in the Espees Merchant dashboard (merchant.espees.org → Payment Integration → Pay with Espees Button).

Product on the dashboard: **Higher Life Monthly Subscription** · SKU **HLIFE-MONTHLY-001** · **1 Espee**
Success URL `https://hlife.christembassylz5.org/payment/success` · Fail URL `https://hlife.christembassylz5.org/payment/failed`

Flow
1. The app calls `POST /api/v1/subscription/subscribe`; Laravel creates a pending payment and returns a **signed** link to `/pay/{reference}`.
2. The app opens that page in the phone's browser. It shows the Espees button (`button.js` + `Espeespaybutton("<button id>")`) and remembers which payment this browser started (encrypted cookie).
3. The user logs in to their Espees wallet and pays. Espees sends the browser to the Success URL (or Fail URL).
4. Laravel links the returned `payment_ref` to the pending payment (the column is **unique**: one Espees payment can never unlock two accounts) and asks Espees to confirm it:
   `POST https://api.espees.org/v2/payment/confirm/` with `{ "payment_ref": … }` and header `x-api-key`.
5. The subscription is activated **only** when Espees answers `APPROVED` with product SKU `HLIFE-MONTHLY-001` and price equal to the plan price. Any mismatch goes to the admin "Awaiting confirmation" tab.

The success/fail pages never activate anything by themselves, and neither does the app. The app polls `POST /payments/verify` (every 5 s while waiting), and `payments:reverify` re-checks linked-but-unconfirmed payments every 2 minutes.
If a user pays but Espees does not hand us a `payment_ref` (or they return in a different browser), the app offers **"Paid but still waiting?"** (`POST /payments/submit`), which parks the payment for an admin to approve.

`.env` (production)
```
APP_URL=https://hlife.christembassylz5.org
ESPEE_DRIVER=espee
ESPEE_BUTTON_ID=695bc9cfc64c3fc1adbce8ba3b559465   # the id inside Espeespaybutton("…") from the Espees dashboard
ESPEE_API_KEY=              # developer-console token — used only for Confirm Payment (not the merchant ID or wallet address)
# optional: ESPEE_BUTTON_SCRIPT, ESPEE_PRODUCT_SKU, ESPEE_PRODUCT_NAME
```
To swap in another button later, change `ESPEE_BUTTON_ID` (and `ESPEE_BUTTON_SCRIPT` if Espees gives a different script URL), then `php artisan config:cache`.
`ESPEE_DRIVER=transfer` keeps the manual fallback; `fake` is for local testing only.

## Content: text only
For now only **text** affirmations are served (config `CONTENT_TEXT_ONLY=true`). Existing video/audio rows stay in the database, hidden everywhere. Set `CONTENT_TEXT_ONLY=false` to bring them back.
Run `php artisan migrate` after updating (adds `transactions.gateway_ref`).

## Media on R2/S3
Set the `s3` disk in `config/filesystems.php`/`.env` and `MEDIA_DISK=s3`. Audio/video are served via short-lived signed URLs.
The admin form uploads through Livewire (default limit ~12MB). For larger videos upload straight to the bucket and store the key.

## Push notifications
`app/Services/PushService.php` currently logs. Install `kreait/laravel-firebase` and send a CloudMessage there.

## KingsChat sign-in
`POST /api/v1/auth/kingschat {code}` exchanges the one-time code from the KingsChat app at KingsChat's token endpoint, loads the profile,
then finds or creates the user. **Verify `KINGSCHAT_PROFILE_URL` and the profile field names against KingsChat's developer docs** —
`KingsChatService::profileFromCode()` reads several likely field names, but I couldn't confirm the exact response.
KingsChat users without an email get a placeholder `@kingschat.invalid` address.

## Verify everything
```bash
php artisan test
```
`HigherLifeFlowTest` (fake driver) covers sign-up, the paywall (402 until subscribed), fake-gateway payment + server-side verification, content, favorites, progress/streaks, collections, profile, device token, cancel, and KingsChat sign-in (with the KingsChat API mocked).

`TransferPaymentTest` covers the manual transfer flow: instructions, proof submission, reuse of references, admin approve/reject, and access control.


## Free trial
Every new account gets `TRIAL_DAYS` (default 7) days of full access to the affirmations (`users.trial_ends_at`).
When it ends the API answers 402 and the app shows the paywall. Paying during the trial starts the paid month when the trial ends.
Run `php artisan migrate` to add the column; existing never-subscribed users get a 7-day trial from migration time.
