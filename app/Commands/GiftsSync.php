<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\GiftVendorSyncService;

/**
 * Pulls every active vendor's gift catalog into the gifts table.
 *
 * Usage:
 *   php spark gifts:sync
 *
 * Intended to run on a schedule (cron), not on a user-facing request —
 * a vendor API can be slow or temporarily down, and a failed sync should
 * never affect someone browsing the gift catalog.
 */
class GiftsSync extends BaseCommand
{
    protected $group       = 'CBS Radio';
    protected $name        = 'gifts:sync';
    protected $description = 'Syncs every active vendor\'s gift catalog into the gifts table.';

    public function run(array $params)
    {
        $service = new GiftVendorSyncService();
        $result  = $service->syncAll();

        CLI::write("Synced {$result['synced']} vendor gift(s).", 'green');

        if (! empty($result['errors'])) {
            CLI::newLine();
            CLI::write('Errors:', 'yellow');

            foreach ($result['errors'] as $error) {
                CLI::write(' - ' . $error, 'red');
            }
        }
    }
}
