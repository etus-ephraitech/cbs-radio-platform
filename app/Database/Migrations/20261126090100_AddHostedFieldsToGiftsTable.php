<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHostedFieldsToGiftsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('gifts', [
            // A picture the vendor serves (a full https address). Null for our own gifts.
            'image_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'synced_at',
            ],
            // The least time the vendor needs to prepare the gift. The customer picks the arrival date on the
            // vendor's checkout; this is only shown as a hint.
            'minimum_delivery_hours' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'image_url',
            ],
            // Units the vendor reported at the last sync. A snapshot, not a reservation; null means not tracked.
            'stock_available' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'minimum_delivery_hours',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('gifts', ['image_url', 'minimum_delivery_hours', 'stock_available']);
    }
}
