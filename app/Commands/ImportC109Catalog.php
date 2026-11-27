<?php

namespace App\Commands;

use App\Libraries\C109Catalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ImportC109Catalog extends BaseCommand
{
    protected $group = 'Circle.ms';
    protected $name = 'circlems:import-c109';
    protected $description = 'Initial C109 import from downloaded event_240 SQLite files; refuses to replace existing rows.';

    public function run(array $params): void
    {
        $catalog = new C109Catalog();
        CLI::write(json_encode($catalog->importInitial()));
        CLI::write(json_encode($catalog->importImages()));
        $maps = $catalog->exportMaps();
        CLI::write('Common images exported: ' . $maps['count']);
    }
}
