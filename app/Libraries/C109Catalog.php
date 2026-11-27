<?php

namespace App\Libraries;

use App\Controllers\Circlems;

final class C109Catalog extends Circlems
{
    protected string $catalogTable = 'c109_circles';

    public function importInitial(): array
    {
        $path = WRITEPATH . 'circlems/catalogs/event_240/webcatalog_text.db';
        $sqlite = new \SQLite3($path, SQLITE3_OPEN_READONLY);
        $event = (int) $sqlite->querySingle('SELECT comiketNo FROM ComiketInfoWC LIMIT 1');
        $sqlite->close();
        if ($event !== 109) {
            throw new \RuntimeException('Expected the official C109 catalog.');
        }
        if (db_connect()->table($this->catalogTable)->countAllResults() !== 0) {
            throw new \RuntimeException('C109 is already populated; initial import will not overwrite bindings or notices.');
        }
        // The initial catalog is fixed and modest in size. Avoid repeating the full SQLite join per page.
        return $this->importC108Rows(240, 0, 100000);
    }

    public function importImages(): array
    {
        return $this->importC108ImageCatalog(240, 1, false, false);
    }

    public function exportMaps(): array
    {
        return $this->exportCommonImages(240, 1);
    }

    protected function localCircleIdsForCatalog($db): array
    {
        $ids = ['circlems' => [], 'name' => []];
        $candidates = ['circlems' => [], 'name' => []];
        foreach ($db->table('circles')->select('id, name, webcatalog_circle_id')->get()->getResultArray() as $r) {
            $name = mb_strtolower(trim($r['name']), 'UTF-8');
            $candidates['name'][$name][(int)$r['id']] = true;
            if (!empty($r['webcatalog_circle_id'])) $candidates['circlems'][(string)$r['webcatalog_circle_id']][(int)$r['id']] = true;
        }
        foreach ($db->table('c108_circles')->select('circle_id, circlems_id')->where('circle_id IS NOT NULL', null, false)->get()->getResultArray() as $r) {
            if (!empty($r['circlems_id'])) $candidates['circlems'][(string)$r['circlems_id']][(int)$r['circle_id']] = true;
        }
        foreach ($candidates as $kind => $keys) {
            foreach ($keys as $key => $matches) {
                if ($key !== '' && count($matches) === 1) $ids[$kind][$key] = array_key_first($matches);
            }
        }
        return $ids;
    }
}
