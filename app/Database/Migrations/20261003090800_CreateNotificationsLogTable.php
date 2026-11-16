<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsLogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'booking_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'channel'    => ['type' => 'ENUM', 'constraint' => ['sms', 'email']],
            'recipient'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'message'    => ['type' => 'TEXT'],
            'status'     => ['type' => 'ENUM', 'constraint' => ['sent', 'failed'], 'default' => 'sent'],
            'sent_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('booking_id');
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('notifications_log', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications_log', true);
    }
}
