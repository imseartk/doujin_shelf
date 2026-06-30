<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCirclemsCutUrlToCircles extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('webcatalog_cut_url', 'circles')) {
            return;
        }

        $this->forge->addColumn('circles', [
            'webcatalog_cut_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 1000,
                'null'       => true,
                'after'      => 'webcatalog_circle_id',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('webcatalog_cut_url', 'circles')) {
            $this->forge->dropColumn('circles', 'webcatalog_cut_url');
        }
    }
}
