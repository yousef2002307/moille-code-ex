<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Exceptions\ApiException;

class PaymentApiController extends Controller
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
     * Create payment and return payment URL
     */
    public function createPayment(Request $request): JsonResponse
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
                'redirectUrl' => route('api.payment.callback'),
                'locale' => config('services.mollie.locale', 'en_US'),
            ];

            // Only add webhook URL if not localhost (Mollie can't reach localhost)
            $webhookUrl = route('api.payment.webhook');
            if (!str_contains($webhookUrl, '127.0.0.1') && !str_contains($webhookUrl, 'localhost')) {
                $paymentData['webhookUrl'] = $webhookUrl;
            }

            // Create payment with Mollie SDK
            $payment = $mollie->payments->create($paymentData);

            // Return payment URL to frontend
            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'payment_url' => $payment->getCheckoutUrl(),
            ], 200);

        } catch (ApiException $e) {
            Log::error('Payment API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment error: ' . $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment error: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle payment callback from Mollie (redirect from payment page)
     */
    public function handleCallback(Request $request)
    {
        try {
            // Mollie sends 'id' parameter in callback
            $paymentId = $request->input('id');
            
            if (!$paymentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment found',
                ], 400);
            }

            $mollie = $this->getMollieClient();
            $payment = $mollie->payments->get($paymentId);
            $status = $payment->status; // paid, pending, failed, canceled, expired, etc.

            // Status values: paid, pending, failed, canceled, expired, expired_pending_payment, etc.
            if ($status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'payment_id' => $paymentId,
                    'status' => 'approved',
                    'mollie_status' => $status,
                ], 200);
            } elseif ($status === 'pending') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment pending',
                    'payment_id' => $paymentId,
                    'status' => 'pending',
                    'mollie_status' => $status,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not completed',
                    'payment_id' => $paymentId,
                    'status' => 'failed',
                    'mollie_status' => $status,
                ], 400);
            }

        } catch (ApiException $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing payment',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error processing payment',
            ], 400);
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
        ]);

        try {
            $mollie = $this->getMollieClient();
            $payment = $mollie->payments->get($validated['order_id']);
            $status = $payment->status; // paid, pending, failed, canceled, expired, etc.

            $statusMap = [
                'paid' => 'approved',
                'pending' => 'pending',
                'failed' => 'failed',
                'canceled' => 'cancelled',
                'expired' => 'expired',
                'expired_pending_payment' => 'expired',
            ];

            return response()->json([
                'success' => true,
                'payment_id' => $validated['order_id'],
                'status' => $statusMap[$status] ?? 'unknown',
                'mollie_status' => $status,
            ], 200);

        } catch (ApiException $e) {
            Log::error('Payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status',
            ], 400);
        }
    }

    /**
     * Handle webhook from Mollie
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            // Mollie sends 'id' parameter in webhook
            $paymentId = $request->input('id');
            
            if ($paymentId) {
                $mollie = $this->getMollieClient();
                $payment = $mollie->payments->get($paymentId);
                $status = $payment->status;
                
                // Update your database with transaction status
                // Example: Payment::where('mollie_payment_id', $paymentId)->update(['status' => $status]);
                
                Log::info('Payment webhook received', [
                    'payment_id' => $paymentId,
                    'status' => $status,
                ]);
            }

            return response()->json(['status' => 'ok'], 200);

        } catch (\Exception $e) {
            Log::error('Payment webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 400);
        }
    }
}
