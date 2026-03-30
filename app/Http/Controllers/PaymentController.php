<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Exceptions\ApiException;
use App\Models\WebhookSuccess;

class PaymentController extends Controller
{
    private function getMollieClient(): MollieApiClient
    {
        $mollie = new MollieApiClient();
        $apiKey = config('services.mollie.use_test')
            ? config('services.mollie.test_api_key')
            : config('services.mollie.api_key');
        $mollie->setApiKey($apiKey);
        return $mollie;
    }

    /**
     * Show payment form
     */
    public function create()
    {
        return view('payment.create');
    }

    /**
     * Process payment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        try {
            $mollie = $this->getMollieClient();

            // Build payment payload
            $redirectUrl = route('payment.return');
            $webhookUrl = route('payment.webhook');
            
            $paymentData = [
                'amount' => [
                    'value' => number_format($validated['amount'], 2, '.', ''),
                    'currency' => config('services.mollie.currency', 'EUR'),
                ],
                'description' => $validated['description'],
                'redirectUrl' => $redirectUrl,
                'locale' => config('services.mollie.locale', 'en_US'),
            ];

            // Only add webhook URL if not localhost (Mollie can't reach localhost)
            if (!str_contains($webhookUrl, '127.0.0.1') && !str_contains($webhookUrl, 'localhost')) {
                $paymentData['webhookUrl'] = $webhookUrl;
            }

            // Log payment creation data for debugging
            Log::info('Creating Mollie payment', [
                'app_url' => config('app.url'),
                'redirect_url' => $redirectUrl,
                'webhook_url' => $webhookUrl,
                'webhook_included' => isset($paymentData['webhookUrl']),
            ]);

            // Create payment with Mollie SDK
            $payment = $mollie->payments->create($paymentData);

            // Store payment ID in session
            session(['mollie_payment_id' => $payment->id]);

            // Redirect to Mollie checkout page
            return redirect($payment->getCheckoutUrl());

        } catch (ApiException $e) {
            Log::error('Mollie API error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Payment error: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Payment error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Payment error: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment return
     */
    public function handleReturn(Request $request)
    {
        try {
            $paymentId = session('mollie_payment_id');
            
            if (!$paymentId) {
                return redirect()->route('payment.create')->with('error', 'No payment found');
            }

            $mollie = $this->getMollieClient();
            $payment = $mollie->payments->get($paymentId);
            $status = $payment->status; // paid, pending, failed, canceled, expired, etc.

            // Status values: paid, pending, failed, canceled, expired, etc.
            if ($status === 'paid') {
                session()->forget('mollie_payment_id');
                return redirect()->route('payment.create')->with('success', 'Payment successful! Payment ID: ' . $paymentId);
            } elseif ($status === 'pending') {
                return redirect()->route('payment.create')->with('warning', 'Payment pending');
            } else {
                return redirect()->route('payment.create')->with('error', 'Payment was not completed. Status: ' . $status);
            }

        } catch (ApiException $e) {
            Log::error('Payment return error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Error processing payment');
        } catch (\Exception $e) {
            Log::error('Payment return error: ' . $e->getMessage());
            return redirect()->route('payment.create')->with('error', 'Error processing payment');
        }
    }

    /**
     * Handle webhook from Mollie
     */
    public function handleWebhook(Request $request)
    {
        try {
            $paymentId = $request->input('id');
            
            if ($paymentId) {
                $mollie = $this->getMollieClient();
                $payment = $mollie->payments->get($paymentId);
                $status = $payment->status;
                
                // Record webhook call in database
                WebhookSuccess::updateOrCreate(
                    ['payment_id' => $paymentId],
                    [
                        'status' => $status,
                        'webhook_data' => $request->all(),
                        'ip_address' => $request->ip(),
                    ]
                );
                
                // Update your database with transaction status
                // Example: Payment::where('mollie_payment_id', $paymentId)->update(['status' => $status]);
                
                Log::info('Mollie webhook received and recorded', [
                    'payment_id' => $paymentId,
                    'status' => $status,
                    'ip' => $request->ip(),
                ]);
            }

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 400);
        }
    }
}
