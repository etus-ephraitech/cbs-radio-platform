<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShowSlotsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'show_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'slot_date'    => ['type' => 'DATE'],
            // Caps how many bookings can air in this show on this date, so one
            // popular slot can't be overbooked.
            'capacity'     => ['type' => 'INT', 'constraint' => 5, 'default' => 20],
            'booked_count' => ['type' => 'INT', 'constraint' => 5, 'default' => 0],
            'is_open'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('show_id');
        $this->forge->addUniqueKey(['show_id', 'slot_date']);
        $this->forge->addForeignKey('show_id', 'shows', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('show_slots');
    }

    public function down()
    {
        $this->forge->dropTable('show_slots');
    }
}
