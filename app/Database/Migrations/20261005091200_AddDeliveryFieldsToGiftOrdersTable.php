<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveryFieldsToGiftOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('gift_orders', [
            // Deliberately generic (not a structured address) so this
            // covers both a physical delivery address and a digital
            // delivery detail (an airtime number, an e-voucher email) —
            // whichever the vendor's gift actually needs. Revisit as a
            // structured field once a real vendor and gift type is
            // confirmed and this proves too loose in practice.
            'delivery_details' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'recipient_phone',
            ],
            // The vendor's own confirmation reference for this order,
            // returned by VendorGiftClientInterface::submitOrder().
            'vendor_order_ref' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'status',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('gift_orders', ['delivery_details', 'vendor_order_ref']);
    }
}
