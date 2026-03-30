# Mollie Migration - Quick Setup & Testing Checklist

## ✅ What's Been Done

- [x] Updated `config/services.php` - PayNL config replaced with Mollie config
- [x] Updated `.env` - All Mollie credentials and settings added
- [x] Migrated `PaymentController.php` - Using Mollie HTTP API
- [x] Migrated `PaymentApiController.php` - Using Mollie HTTP API
- [x] Updated session keys - `paynl_order_id` → `mollie_order_id`
- [x] Updated payment flow - Uses Mollie checkout redirect
- [x] Added error handling - Mollie API error management

## 🔍 Current Credentials (TEST MODE)

```
API Key (Test):    test_zbmnVAd5Thr9uQxuDD5tBuhT8HAyUF
API Key (Live):    live_DnvGqFN4dPMvtRkg62RGgnsMjHWBE8
Profile ID:        pfl_MXUNsXKjCp
Test Mode:         Enabled (MOLLIE_USE_TEST=true)
Currency:          EUR
```

## 🚀 Before You Deploy

### 1. Test Payment Flow
- [ ] Create a test payment via `/payment` route
- [ ] Verify redirect to Mollie checkout page
- [ ] Test payment completion with test card
- [ ] Verify return callback is processed
- [ ] Check webhook reception in Mollie dashboard

### 2. Test API Endpoints
- [ ] POST `/api/payment/create` - Create payment
- [ ] GET `/api/payment/status?order_id=...` - Check status
- [ ] GET `/api/payment/callback` - Test callback handling
- [ ] POST `/api/payment/webhook` - Test webhook handling

### 3. Database Integration (If Needed)
- [ ] Create `payments` table if not exists:
  ```php
  Schema::create('payments', function (Blueprint $table) {
      $table->id();
      $table->string('mollie_order_id')->unique();
      $table->decimal('amount', 10, 2);
      $table->string('description');
      $table->string('status')->default('open'); // open, pending, paid, failed, canceled
      $table->timestamps();
  });
  ```
- [ ] Update webhook handler to save/update payment records

### 4. Webhook Configuration
- [ ] Add webhook URL to Mollie Dashboard
  - Go to: https://www.mollie.com/dashboard/developers/webhooks
  - Add webhook endpoint: `https://yourdomain.com/api/payment/webhook`
  - Or for web routes: `https://yourdomain.com/payment/webhook`

### 5. Testing with Test Credentials
- [ ] Use test payment cards provided by Mollie
- [ ] Verify API error responses are handled correctly
- [ ] Test all status codes (paid, pending, failed, canceled)

## 🔄 Test Card Numbers

For testing in Mollie test mode, use:

| Card Type | Number |
|-----------|--------|
| Visa | 4111 1111 1111 1111 |
| Mastercard | 5555 5555 5555 4444 |
| iDEAL | Use mock bank in payment form |

Expiry: Any future date (e.g., 12/25)
CVC: Any 3 digits (e.g., 123)

[Full list →](https://docs.mollie.com/guides/testing)

## 🔐 Going Live

When ready to use live payments:

### 1. Update Environment
```env
MOLLIE_USE_TEST=false
MOLLIE_API_KEY=live_DnvGqFN4dPMvtRkg62RGgnsMjHWBE8
```

### 2. Verify Webhook
- [ ] Update webhook URL pointing to production domain
- [ ] Test webhook with real Mollie test transactions
- [ ] Monitor webhook logs

### 3. Payment Methods
- [ ] Configure payment methods in Mollie dashboard
- [ ] Enable iDEAL, cards, or other methods as needed
- [ ] The code uses 'ideal' as default method

### 4. SSL Certificate
- [ ] Ensure your domain has valid SSL certificate
- [ ] Mollie requires HTTPS for webhooks

## 📝 Code Reference

### Create Payment
```php
// Via form
POST /payment
  - amount: 12.50
  - description: "Test payment"

// Via API
POST /api/payment/create
  - amount: 12.50
  - description: "Test payment"
```

### Check Status
```php
GET /api/payment/status?order_id=tr_WDqYK6vllg
```

### Response Example
```json
{
  "success": true,
  "order_id": "tr_WDqYK6vllg",
  "status": "paid",
  "mollie_status": "paid"
}
```

## 🐛 Troubleshooting

### Payment creation fails with "Unauthorized"
- Verify API key in `.env` is correct
- Check that `MOLLIE_USE_TEST` matches API key type (test vs live)
- Reload environment: Clear config cache if using `php artisan config:cache`

### Webhook not received
- Ensure webhook URL is publicly accessible (not localhost)
- Check Mollie dashboard webhook logs
- Verify webhook route in `routes/api.php` or `routes/web.php`
- Test webhook delivery from Mollie dashboard

### Order not found errors
- Verify order ID format (usually starts with `tr_`)
- Check you're using correct API key (test vs live)
- Ensure order exists in Mollie (correct workspace)

### CORS or redirect issues
- If frontend and backend are different domains, verify CORS is configured
- Checkout URL must include full domain for redirect

## 📚 Documentation

- [Mollie Orders API Docs](https://docs.mollie.com/reference/v2/orders-api/overview)
- [Mollie Getting Started](https://docs.mollie.com/docs/getting-started)
- [Mollie Testing Guide](https://docs.mollie.com/guides/testing)
- [Mollie Webhooks](https://docs.mollie.com/guides/handling-status-changes)

## 💡 Next Steps

1. **Test thoroughly** with test credentials
2. **Set up database** to track payments
3. **Configure webhooks** in Mollie dashboard
4. **Monitor logs** for any issues
5. **Deploy to staging** first
6. **Switch to live** credentials when ready

---

**Status:** ✅ All code changes complete - Ready for testing
**Last Updated:** March 2026
