<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVendorCheckoutsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'vendor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // The secret in the visitor's own link back to the status page. Without it, ids could be guessed.
            'public_token' => ['type' => 'CHAR', 'constraint' => 32],
            // Our own reference for the purchase (shown to the customer and quoted to the vendor's support).
            'purchase_reference' => ['type' => 'VARCHAR', 'constraint' => 40],
            // Sent as Idempotency-Key, so that a retry after a timeout never creates a second checkout.
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            // JSON: the cart lines exactly as sent (gift_id, inventory_id, quantity) plus title and estimate.
            'items'          => ['type' => 'TEXT'],
            'estimate_total' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'currency'       => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'UGX'],
            'state'          => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'open', 'in_checkout', 'awaiting_payment', 'paid', 'cancelled', 'expired', 'failed'],
                'default'    => 'draft',
            ],
            'paid'              => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'session_id'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'checkout_url'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'expires_at'        => ['type' => 'DATETIME', 'null' => true],
            'vendor_order_id'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'vendor_reference'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'final_amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            // JSON: the vendor's order and the delivery progress of each gift, as last read.
            'order_details'     => ['type' => 'TEXT', 'null' => true],
            'last_error'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'last_checked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('public_token');
        $this->forge->addUniqueKey('purchase_reference');
        $this->forge->addUniqueKey('idempotency_key');
        $this->forge->addKey('state');
        $this->forge->addKey('session_id');
        $this->forge->addForeignKey('vendor_id', 'gift_vendors', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('vendor_checkouts');
    }

    public function down()
    {
        $this->forge->dropTable('vendor_checkouts');
    }
}
