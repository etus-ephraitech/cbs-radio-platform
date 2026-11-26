<?php

namespace App\Commands;

use App\Services\HostedCheckoutService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Asks each partner shop about the purchases that are still moving: checkouts waiting to be paid, and paid
 * purchases whose delivery is not finished.
 *
 * Usage:
 *   php spark gifts:reconcile
 *
 * Run it on a schedule (every 10 minutes is plenty). The shop sends no webhook, so a customer who paid and never
 * returned to our site is only found this way. Each run asks about at most 25 purchases, longest-unchecked first,
 * to stay inside the shop's request limit.
 */
class GiftsReconcile extends BaseCommand
{
    protected $group       = 'CBS Radio';
    protected $name        = 'gifts:reconcile';
    protected $description = 'Checks pending and in-delivery partner shop purchases with the shop.';

    public function run(array $params)
    {
        $result = (new HostedCheckoutService())->reconcile();

        CLI::write("Checked {$result['checked']} purchase(s): {$result['paid']} newly paid, {$result['changed']} changed.", 'green');
    }
}
