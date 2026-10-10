<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGiftOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // A gift can stand alone ("Send a Gift") or ride along with a booking —
            // nullable so both flows share this one table.
            'booking_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'gift_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],

            'sender_name'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'sender_phone'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'recipient_name'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'recipient_phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],

            'quantity' => ['type' => 'INT', 'constraint' => 3, 'default' => 1],
            'amount'   => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'UGX'],

            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending_payment', 'paid', 'fulfilled', 'cancelled'],
                'default'    => 'pending_payment',
            ],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('booking_id');
        $this->forge->addKey('gift_id');
        $this->forge->addKey('status');

        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('gift_id', 'gifts', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('gift_orders', true);
    }

    public function down()
    {
        $this->forge->dropTable('gift_orders', true);
    }
}
