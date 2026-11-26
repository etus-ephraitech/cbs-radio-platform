<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Says, per package, whether the booking includes a voice note, so the booking form offers the recorder only to
 * people whose package has one (and the server refuses an upload for one that does not). Until now the form showed
 * it to everyone.
 *
 * Packages that already mention a voice note in their name or what they include keep it, so nothing that has been
 * sold changes; every other package, and every new one, starts without it, and staff tick it under Admin > Packages.
 */
class AddIncludesVoiceNoteToRateTiersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('rate_tiers', [
            'includes_voice_note' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
                'after'      => 'includes_on_air',
            ],
        ]);

        $this->db->query(
            "UPDATE rate_tiers SET includes_voice_note = 1 WHERE LOWER(package_level) LIKE '%voice%' OR LOWER(COALESCE(features, '')) LIKE '%voice%'"
        );
    }

    public function down()
    {
        $this->forge->dropColumn('rate_tiers', 'includes_voice_note');
    }
}
