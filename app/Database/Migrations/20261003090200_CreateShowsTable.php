<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShowsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100], // e.g. "Morning Express", "Evening Drive"
            'start_time'  => ['type' => 'TIME'],
            'end_time'    => ['type' => 'TIME'],
            // Comma-separated day numbers (1=Mon..7=Sun), or null if daily
            'days_of_week' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('shows');
    }

    public function down()
    {
        $this->forge->dropTable('shows');
    }
}
