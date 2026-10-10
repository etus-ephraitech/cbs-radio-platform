<?php

namespace Tests\Support\Libraries;

use App\Services\PaymentService;

/**
 * PaymentService with the Mobile Money charge under the test's control. The real method is, today, a stub that
 * always fails (the provider integration is waiting on CBS), so this is how the *rest* of online payment (what
 * happens when a charge is accepted) can be tested before that integration exists.
 */
class FakeMobileMoneyPayments extends PaymentService
{
    /** @var array<string, mixed> */
    public array $chargeResult = ['success' => true, 'message' => 'Request sent.'];

    /** @var array<int, array{phone: string, amount: float, reference: string}> */
    public array $charges = [];

    protected function chargeMobileMoney(string $phone, float $amount, string $referenceId): array
    {
        $this->charges[] = ['phone' => $phone, 'amount' => $amount, 'reference' => $referenceId];

        return $this->chargeResult;
    }
}
