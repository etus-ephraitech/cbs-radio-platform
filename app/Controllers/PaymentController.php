<?php

namespace App\Controllers;

use App\Services\PaymentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Receives the Mobile Money provider's payment callback. This route must
 * be public (the provider calls it directly, with no browser session) but
 * is protected by a shared-secret check instead of open access.
 *
 * OPEN ITEM: the exact callback payload shape (field names, signature
 * header) depends on which provider CBS confirms — the shared-secret
 * check below is a placeholder pattern (a query-string token) until the
 * real provider's callback-verification method (often an HMAC signature
 * header) is known. Do not go live on the query-token check alone.
 */
class PaymentController extends BaseController
{
    protected PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    public function callback(): ResponseInterface
    {
        $providedToken = $this->request->getGet('token');
        $expectedToken = env('momo.callbackToken');

        if (empty($expectedToken) || ! hash_equals($expectedToken, (string) $providedToken)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Invalid callback token.']);
        }

        $payload = $this->request->getJSON(true) ?? [];

        if (empty($payload['provider_reference']) || empty($payload['status'])) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Malformed callback payload.']);
        }

        $handled = $this->paymentService->handleCallback([
            'provider_reference' => $payload['provider_reference'],
            'status'             => $payload['status'], // expected: 'success' or 'failed'
        ]);

        if (! $handled) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Payment record not found.']);
        }

        return $this->response->setJSON(['received' => true]);
    }
}
