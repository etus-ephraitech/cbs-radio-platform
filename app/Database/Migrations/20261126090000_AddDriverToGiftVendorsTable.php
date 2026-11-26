<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDriverToGiftVendorsTable extends Migration
{
    public function up()
    {
        // Which integration talks to this vendor. 'generic' is the assumed products/orders REST shape; 'katale' is
        // Katale Plus: a catalogue we mirror, and a hosted checkout where the customer pays Katale.
        $this->forge->addColumn('gift_vendors', [
            'driver' => [
                'type'       => 'ENUM',
                'constraint' => ['generic', 'katale'],
                'default'    => 'generic',
                'null'       => false,
                'after'      => 'slug',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('gift_vendors', 'driver');
    }
}
