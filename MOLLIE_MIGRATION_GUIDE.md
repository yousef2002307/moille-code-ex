# Migration Guide: PayNL to Mollie Payment Gateway

## Overview
This project has been successfully migrated from **Pay.nl** to **Mollie** payment gateway. All payment processing functionality now uses Mollie's API instead of Pay.nl.

## Changes Made

### 1. Configuration Updates

#### `config/services.php`
- Replaced PayNL configuration block with Mollie configuration
- New Mollie settings:
  - `api_key`: Your live Mollie API key
  - `test_api_key`: Your test Mollie API key
  - `profile_id`: Your Mollie Profile ID
  - `use_test`: Boolean to toggle between test and live mode
  - `currency`: Payment currency (default: EUR)
  - `locale`: Payment locale (default: en_US)
  - `webhook_url`: Webhook endpoint URL

#### `.env` File
- Removed all PayNL environment variables:
  - `PAYNL_SERVICE_ID`
  - `PAYNL_SECRET`
  - `PAYNL_API_TOKEN`
  - `PAYNL_TOKEN_CODE`
  - `PAYNL_TEST_MODE`
  - `PAYNL_CURRENCY`
  - `PAYNL_LANGUAGE`

- Added new Mollie environment variables:
  ```
  MOLLIE_API_KEY=live_DnvGqFN4dPMvtRkg62RGgnsMjHWBE8
  MOLLIE_TEST_API_KEY=test_zbmnVAd5Thr9uQxuDD5tBuhT8HAyUF
  MOLLIE_PROFILE_ID=pfl_MXUNsXKjCp
  MOLLIE_USE_TEST=true
  MOLLIE_CURRENCY=EUR
  MOLLIE_LOCALE=en_US
  ```

### 2. Controller Updates

#### `app/Http/Controllers/PaymentController.php`
**Changes:**
- Removed PayNL SDK imports (`PayNL\Sdk\*`)
- Added Laravel HTTP client import (`Illuminate\Support\Facades\Http`)
- Replaced `getConfig()` method with `getMollieApiKey()`
- Updated session key from `paynl_order_id` to `mollie_order_id`

**Method Updates:**
1. **`store()` - Create Payment**
   - Now uses HTTP POST to `https://api.mollie.com/v2/orders`
   - Amount converted and formatted correctly for Mollie API
   - Returns checkout URL from Mollie response
   - Uses Bearer token authentication with API key

2. **`handleReturn()` - Handle Callback**
   - Fetches order status from Mollie using GET request
   - Checks for `'paid'` status instead of status code `100`
   - Updated error messages to reflect Mollie statuses

3. **`handleWebhook()` - Process Webhook**
   - Updated to handle Mollie webhook format
   - Orders ID parameter from `orderid` changed to `id`
   - Added logging of Mollie webhook events

#### `app/Http/Controllers/PaymentApiController.php`
**Changes:**
- Same import and method updates as PaymentController
- Identical Mollie API integration in all methods
- API responses now return Mollie status codes instead of PayNL codes

**Method Updates:**
1. **`createPayment()` - API endpoint to create payments**
   - Returns JSON response with `order_id` and `payment_url`
   - Uses Mollie order creation endpoint

2. **`handleCallback()` - API callback handler**
   - Returns status mapping: `'paid'` → `'approved'`, `'pending'` → `'pending'`, etc.
   - Includes both mapped status and raw Mollie status in response

3. **`checkStatus()` - API status check**
   - Maps Mollie statuses to simpler format:
     - `'paid'` → `'approved'`
     - `'pending'` → `'pending'`
     - `'failed'` → `'failed'`
     - `'canceled'` → `'cancelled'`
     - `'expired'` → `'expired'`

4. **`handleWebhook()` - API webhook handler**
   - Receives order ID from Mollie webhook
   - Fetches order details for status update

### 3. Session Management
- Changed session key for storing order IDs:
  - **Before:** `session('paynl_order_id')`
  - **After:** `session('mollie_order_id')`

### 4. API Response Format

#### Before (PayNL)
```json
{
  "success": true,
  "order_id": "...",
  "payment_url": "...",
  "status_code": 100
}
```

#### After (Mollie)
```json
{
  "success": true,
  "order_id": "...",
  "payment_url": "...",
  "status": "paid",
  "mollie_status": "paid"
}
```

## Mollie Payment Flow

1. **Create Payment Order**
   - POST `/v2/orders` with amount, description, redirect URL
   - Mollie returns order ID and checkout URL

2. **User Redirected to Mollie**
   - User redirected to checkout URL
   - User completes payment on Mollie's hosted payment page

3. **Callback/Webhook**
   - After payment, user redirected to your `redirectUrl`
   - Mollie sends webhook to your `webhookUrl`

4. **Query Status**
   - Use `GET /v2/orders/{orderId}` to get current status
   - Status values: `open`, `pending`, `authorized`, `paid`, `canceled`, `failed`, `expired`, etc.

## Mollie Status Codes Reference

| Status | Meaning |
|--------|---------|
| `open` | Order created, awaiting payment |
| `pending` | Payment initiated, awaiting confirmation |
| `authorized` | Payment authorized (awaiting capture) |
| `paid` | Payment successfully completed ✓ |
| `canceled` | Order canceled by user/system |
| `failed` | Payment failed |
| `expired` | Payment expired without completion |

## Authentication

All API calls use **Bearer token authentication** with the Mollie API key:

```php
'Authorization' => 'Bearer ' . $apiKey
```

## Testing

### Enable Test Mode
To use test credentials, set in `.env`:
```
MOLLIE_USE_TEST=true
```

### Test Credentials Provided
- **Test API Key:** `test_zbmnVAd5Thr9uQxuDD5tBuhT8HAyUF`
- **Profile ID:** `pfl_MXUNsXKjCp`

### Test Payments
In test mode, you can use test card numbers from [Mollie Documentation](https://docs.mollie.com/guides/testing)

## Important Notes

### 1. Amount Formatting
- Mollie requires amounts as strings with 2 decimal places
- Example: `€12.50` should be sent as `"12.50"`

### 2. Webhook Security
- Verify webhook authenticity using Mollie's signature headers
- Always query Mollie API to confirm payment status
- Add webhook URL to your Mollie dashboard

### 3. Error Handling
- Mollie API errors return HTTP status codes
- Check response body for detailed error messages
- All errors are logged for debugging

### 4. Currency & Locale
- Default currency is EUR (configurable)
- Locale affects payment form language (en_US by default)
- Update in `.env` or `config/services.php` as needed

## Database Considerations

If you have existing payment records, update any references:
- `paynl_order_id` column → `mollie_order_id`
- Status code mapping (100 → "paid", etc.)

Example migration:
```php
Schema::table('payments', function (Blueprint $table) {
    $table->renameColumn('paynl_order_id', 'mollie_order_id');
});
```

## API Documentation

For complete Mollie API documentation, visit:
- [Mollie Orders API](https://docs.mollie.com/reference/v2/orders-api/overview)
- [Mollie Getting Started](https://docs.mollie.com/docs/getting-started)
- [Mollie Payment Methods](https://docs.mollie.com/guides/handling-payments/overview)

## Troubleshooting

### "Unauthorized" Error
- Verify API key is correct in `.env`
- Ensure `MOLLIE_USE_TEST` matches the API key type (test vs live)

### "Invalid request" Error
- Check amount format (must be string with 2 decimals)
- Verify currency code is valid

### Webhook Not Received
- Check webhook URL is publicly accessible
- Verify webhook URL is configured in your code
- Check Mollie dashboard webhook settings

### Order Not Found
- Ensure order ID is correct
- Verify using correct API key (live vs test)

## Next Steps

1. **Optional:** Remove PayNL SDK dependency from `composer.json` if no longer needed:
   ```bash
   composer remove paynl/php-sdk
   ```

2. **Test:** Thoroughly test payment flow with Mollie test credentials

3. **Go Live:** When ready, update to live credentials:
   - Set `MOLLIE_USE_TEST=false` in `.env`
   - Update `MOLLIE_API_KEY` with your live key

4. **Monitor:** Set up error logging and webhook monitoring in Mollie dashboard

---

**Migration Date:** March 2026
**Framework:** Laravel 12
**Payment Gateway:** Mollie V2 API
