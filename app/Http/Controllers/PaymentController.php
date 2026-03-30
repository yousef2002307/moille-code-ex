<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Exceptions\ApiException;

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
            $paymentData = [
                'amount' => [
                    'value' => number_format($validated['amount'], 2, '.', ''),
                    'currency' => config('services.mollie.currency', 'EUR'),
                ],
                'description' => $validated['description'],
                'redirectUrl' => route('payment.return'),
                'locale' => config('services.mollie.locale', 'en_US'),
            ];

            // Only add webhook URL if not localhost (Mollie can't reach localhost)
            $webhookUrl = route('payment.webhook');
            if (!str_contains($webhookUrl, '127.0.0.1') && !str_contains($webhookUrl, 'localhost')) {
                $paymentData['webhookUrl'] = $webhookUrl;
            }

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
                
                // Update your database with transaction status
                // Example: Payment::where('mollie_payment_id', $paymentId)->update(['status' => $status]);
                
                Log::info('Mollie webhook received', [
                    'payment_id' => $paymentId,
                    'status' => $status,
                ]);
            }

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 400);
        }
    }
}
