<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            // Dotted key from App\Libraries\Settings\Registry, e.g. "payments.mode".
            // (Named setting_key, not key — KEY is a reserved word in MySQL.)
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            // Always stored as text; Registry::cast() turns it back into a bool/int/list.
            // Only a *value an admin chose* lives here — a key with no row means
            // "use the .env value, or the code default".
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'updated_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('setting_key');
        $this->forge->addKey('updated_by');
        // Assumes the starter kit's users.id is INT UNSIGNED, like the other tables here.
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('settings', true);
    }

    public function down()
    {
        $this->forge->dropTable('settings', true);
    }
}
