<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateModerationLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'booking_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'moderator_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'action'        => ['type' => 'ENUM', 'constraint' => ['approved', 'rejected', 'flagged']],
            'notes'         => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('booking_id');
        $this->forge->addKey('moderator_id');
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('moderator_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('moderation_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('moderation_logs', true);
    }
}
