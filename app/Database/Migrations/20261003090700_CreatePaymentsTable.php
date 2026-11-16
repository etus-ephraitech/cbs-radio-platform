<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],

            // Exactly one of these two should be set — enforced in the application
            // layer (a CHECK constraint isn't portable across MySQL versions we
            // might deploy on, so this is validated in the Payment service).
            'booking_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'gift_order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],

            // Phase 1: single Mobile Money provider. Kept as a string (not enum)
            // so a second provider can be added later without a migration.
            'provider'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'provider_reference' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],

            'amount'   => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'UGX'],

            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'success', 'failed'],
                'default'    => 'pending',
            ],

            // Admin manual-override fallback, per the agreed scope: a payment
            // hiccup should never block a booking from airing.
            'marked_paid_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],

            'paid_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('booking_id');
        $this->forge->addKey('gift_order_id');
        $this->forge->addKey('status');
        $this->forge->addKey('provider_reference');

        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('gift_order_id', 'gift_orders', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('marked_paid_by', 'users', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('payments', true);
    }

    public function down()
    {
        $this->forge->dropTable('payments', true);
    }
}
