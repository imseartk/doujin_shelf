<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateC109Circles extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('c109_circles')) {
            $this->db->query('CREATE TABLE c109_circles LIKE c108_circles');
            $this->db->tableExists('c109_circles', false);
        }
    }

    public function down()
    {
        $this->forge->dropTable('c109_circles', true);
    }
}
