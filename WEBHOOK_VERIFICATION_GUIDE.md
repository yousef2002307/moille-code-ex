# Webhook Verification Guide

## Overview
A webhook success tracking system has been implemented to monitor and verify all incoming Mollie webhooks.

## What Was Created

### 1. Database Table: `webhook_success`
```sql
- id: Primary key
- payment_id: Unique Mollie payment ID
- status: Payment status (paid, pending, failed, etc.)
- webhook_data: Full webhook payload (JSON)
- ip_address: Source IP of webhook call
- created_at: Timestamp of webhook receipt
- updated_at: Timestamp of last update
```

### 2. Model: `WebhookSuccess`
Location: [app/Models/WebhookSuccess.php](app/Models/WebhookSuccess.php)

Handles database interactions with proper JSON casting.

### 3. Updated Controllers
Both webhook handlers now automatically log successful calls:
- [app/Http/Controllers/PaymentController.php](app/Http/Controllers/PaymentController.php) - Web routes
- [app/Http/Controllers/PaymentApiController.php](app/Http/Controllers/PaymentApiController.php) - API routes

### 4. Artisan Command
```bash
php artisan app:check-webhooks
```

## How to Verify Webhooks

### Method 1: Database Query
Check records directly in the database:

```bash
php artisan tinker
```

Then in Tinker:
```php
use App\Models\WebhookSuccess;

// Get all webhooks
WebhookSuccess::all();

// Get last 5 webhooks
WebhookSuccess::latest()->limit(5)->get();

// Get specific payment webhook
WebhookSuccess::where('payment_id', 'tr_xxx')->first();

// Get all paid webhooks
WebhookSuccess::where('status', 'paid')->get();

// Count total webhooks
WebhookSuccess::count();
```

### Method 2: Artisan Command (Recommended)
View recent webhooks in a formatted table:

```bash
# Show last 10 webhooks (default)
php artisan app:check-webhooks

# Show last 20 webhooks
php artisan app:check-webhooks --limit=20

# Show last 50 webhooks
php artisan app:check-webhooks --limit=50
```

Output:
```
=== Recent Webhook Calls ===

┌────┬──────────────┬────────┬─────────────┬──────────────────────┐
│ ID │ Payment ID   │ Status │ IP Address  │ Received At          │
├────┼──────────────┼────────┼─────────────┼──────────────────────┤
│ 1  │ tr_WDqYK6vll │ paid   │ 1.2.3.4     │ 2026-03-30 09:15:30  │
│ 2  │ tr_abc123def │ pending│ 1.2.3.4     │ 2026-03-30 09:10:15  │
└────┴──────────────┴────────┴─────────────┴──────────────────────┘

Total webhook records: 2

=== Latest Webhook Details ===
Payment ID: tr_WDqYK6vll
Status: paid
IP Address: 1.2.3.4
Received At: 2026-03-30 09:15:30
Data: {...webhook payload...}
```

### Method 3: Check Logs
Log file: `storage/logs/laravel.log`

Look for entries like:
```
[2026-03-30 09:15:30] local.INFO: Mollie webhook received and recorded {"payment_id":"tr_WDqYK6vll","status":"paid","ip":"1.2.3.4"}
```

### Method 4: Laravel Tinker CLI
Interactive checking with Tinker:

```bash
php artisan tinker
```

```php
use App\Models\WebhookSuccess;

// Get latest webhook
$webhook = WebhookSuccess::latest()->first();

// View details
echo $webhook->payment_id;      // tr_WDqYK6vll
echo $webhook->status;           // paid
echo $webhook->ip_address;       // 1.2.3.4
print_r($webhook->webhook_data); // Full webhook data
```

## What Gets Recorded

When Mollie sends a webhook, the following is automatically recorded:

```php
[
    'payment_id'   => 'tr_WDqYK6vll',
    'status'       => 'paid',              // Current payment status
    'webhook_data' => [                    // Full request payload
        'id' => 'tr_WDqYK6vll',
        ...REST OF MOLLIE DATA...
    ],
    'ip_address'   => '1.2.3.4',          // Mollie's server IP
    'created_at'   => '2026-03-30 09:15:30'
]
```

## Testing Webhooks

### Local Development with ngrok

1. **Start ngrok**
   ```bash
   ngrok http 8000
   ```

2. **Update APP_URL in .env**
   ```env
   APP_URL=https://your-ngrok-url.ngrok-free.app
   ```

3. **Clear config cache**
   ```bash
   php artisan config:cache
   ```

4. **Create a test payment** at `/payment` route

5. **Complete the payment** using test card

6. **Check webhooks**
   ```bash
   php artisan app:check-webhooks
   ```

### What to Look For

After completing a test payment, you should see:
- ✅ Status changes: `pending` → `paid`
- ✅ Multiple webhook records for same payment_id (one per status change)
- ✅ IP address from Mollie servers
- ✅ Webhook data containing payment details

## Troubleshooting

### Webhooks Not Received

1. **Check ngrok tunnel is active**
   ```bash
   # Make sure ngrok is running
   ngrok http 8000
   ```

2. **Verify APP_URL is correct**
   ```bash
   php artisan config:cache
   php artisan config:clear
   ```

3. **Check webhook URL format**
   - Should be: `https://your-ngrok-url/payment/webhook` (web route)
   - Or: `https://your-ngrok-url/api/payment/webhook` (API route)

4. **Check Mollie webhook settings**
   - Go to: https://www.mollie.com/dashboard/developers/webhooks
   - Verify URL is reachable

5. **Check logs**
   ```bash
   tail -f storage/logs/laravel.log
   # Look for webhook-related entries
   ```

### Payment Status Not Updating

1. **Verify payment creation succeeded**
   ```bash
   php artisan tinker
   use App\Models\WebhookSuccess;
   WebhookSuccess::pluck('payment_id'); // Should show payment IDs
   ```

2. **Check Mollie payment status directly**
   - Access payment via Mollie dashboard
   - Verify payment status matches database

## Database Queries

```php
// Count webhooks by status
WebhookSuccess::selectRaw('status, count(*) as count')
    ->groupBy('status')
    ->get();

// Get webhooks from last hour
WebhookSuccess::where('created_at', '>', now()->subHour())->get();

// Get failed webhooks
WebhookSuccess::where('status', 'failed')->get();

// Get webhooks from specific IP
WebhookSuccess::where('ip_address', '1.2.3.4')->get();

// Get all unique payment IDs
WebhookSuccess::distinct()->pluck('payment_id');
```

## Production Considerations

### Before Going Live

1. **Update webhook URL** in Mollie Dashboard
   - Change from ngrok URL to production domain
   - Ensure production domain is HTTPS

2. **Monitor webhook table**
   - Set up periodic checks: `php artisan app:check-webhooks`
   - Monitor logs for errors
   - Set up alerts for failed status

3. **Database backups**
   - Regular backups of webhook_success table
   - Helps with debugging payment issues

4. **Log retention**
   - Set up log rotation
   - Archive old webhook records

## Useful Commands

```bash
# View recent webhooks
php artisan app:check-webhooks

# View 50 recent webhooks
php artisan app:check-webhooks --limit=50

# Interactive shell for complex queries
php artisan tinker

# Clear config cache (after changing APP_URL)
php artisan config:cache

# View recent logs
tail -f storage/logs/laravel.log

# Tail logs with grep filter
tail -f storage/logs/laravel.log | grep webhook
```

## API Endpoint for Webhook Status

You can also create an API endpoint to check webhook status:

```php
// In routes/api.php
Route::get('/webhooks/status/{paymentId}', function ($paymentId) {
    $webhook = \App\Models\WebhookSuccess::where('payment_id', $paymentId)->latest()->first();
    return response()->json($webhook ?? ['error' => 'Not found']);
});
```

Then access: `GET https://your-domain/api/webhooks/status/tr_WDqYK6vll`

## Summary

✅ **Webhook tracking system is now active!**

- All webhooks are automatically logged to `webhook_success` table
- Use `php artisan app:check-webhooks` to view recent webhooks
- Webhooks include full payload, status, and IP information
- Production-ready and tested with ngrok

---

**Dashboard Command:**
```bash
php artisan app:check-webhooks
```

**This is your go-to command for verifying webhook activity!**
