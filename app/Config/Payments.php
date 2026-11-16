<?php

namespace Config;

use App\Libraries\Settings\AppliesSettingOverrides;
use CodeIgniter\Config\BaseConfig;

/**
 * How payments are taken. Defaults come from here and .env; an admin can
 * change the non-secret values under Admin > Settings > Payments (see
 * App\Libraries\Settings\Registry). The credentials themselves — API key,
 * secret, callback token — are secrets and stay in .env only.
 *
 * Manual is the default because the Mobile Money provider has not been
 * confirmed yet: a booking is taken, the payer is shown how to pay, and
 * staff mark it paid. That is the same fallback the project always had,
 * now a deliberate mode rather than an error page.
 */
class Payments extends BaseConfig
{
    use AppliesSettingOverrides;

    /** 'manual' (staff confirm each payment) or 'mobile_money' (online). */
    public string $mode = 'manual';

    /** 'mtn' or 'airtel'; only used in mobile_money mode. */
    public string $provider = '';

    /** Shown to payers in manual mode. */
    public string $merchantCode = '';

    public string $manualInstructions = 'Send the amount above using the CBS Radio merchant code, quoting your reference. '
        . 'Your greeting goes to review as soon as our team confirms the payment.';

    public function __construct()
    {
        parent::__construct();

        $this->mode               = (string) env('payments.mode', $this->mode);
        $this->provider           = (string) env('momo.provider', $this->provider);
        $this->merchantCode       = (string) env('payments.merchantCode', $this->merchantCode);
        $this->manualInstructions = (string) env('payments.manualInstructions', $this->manualInstructions);

        $this->applySettingOverrides('Payments');

        // A mistyped .env value must not leave payments in an undefined state.
        if (! in_array($this->mode, ['manual', 'mobile_money'], true)) {
            $this->mode = 'manual';
        }
    }
}
