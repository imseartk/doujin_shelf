<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCutImageUrlToC108Circles extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('c108_circles') || $this->db->fieldExists('cut_image_url', 'c108_circles')) {
            return;
        }

        $this->forge->addColumn('c108_circles', [
            'cut_image_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'circlems_portal_url',
            ],
        ]);
    }

    public function down(): void
    {
        if (! $this->db->tableExists('c108_circles') || ! $this->db->fieldExists('cut_image_url', 'c108_circles')) {
            return;
        }

        $this->forge->dropColumn('c108_circles', 'cut_image_url');
    }
}
