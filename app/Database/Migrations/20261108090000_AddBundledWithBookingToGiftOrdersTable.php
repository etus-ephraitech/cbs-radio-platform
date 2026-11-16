<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marks a gift order that was chosen inside the greeting form and is paid for together with that greeting, in one
 * payment, rather than with a payment of its own.
 *
 * booking_id alone can't say this: a gift can also be added to an existing greeting afterwards (gifts?booking_id=),
 * and that one is paid separately. Mixing the two up would either charge for a gift twice or never charge for it.
 */
class AddBundledWithBookingToGiftOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('gift_orders', [
            'bundled_with_booking' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
                'after'      => 'booking_id',
            ],
        ]);

        $this->forge->addKey(['booking_id', 'bundled_with_booking'], false, false, 'gift_orders_bundled_booking');
        $this->forge->processIndexes('gift_orders');
    }

    public function down()
    {
        $this->forge->dropKey('gift_orders', 'gift_orders_bundled_booking', false);
        $this->forge->dropColumn('gift_orders', 'bundled_with_booking');
    }
}
