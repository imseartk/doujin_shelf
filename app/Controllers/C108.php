<?php

namespace App\Controllers;

use App\Libraries\CirclemsClient;
use App\Models\CirclemsTokenModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class C108 extends BaseController
{
    private const PER_PAGE = 100;

    public function index(): string
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $day = trim((string) $this->request->getGet('day'));
        $relation = trim((string) $this->request->getGet('relation'));
        $priority = trim((string) $this->request->getGet('priority'));
        $page = max(1, (int) $this->request->getGet('page'));

        $builder = $this->baseBuilder($db);
        $this->applyFilters($builder, $q, $day, $relation, $priority);

        $countBuilder = $this->baseBuilder($db, true);
        $this->applyFilters($countBuilder, $q, $day, $relation, $priority);

        $total = (int) $countBuilder->get()->getRow('total');
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = $builder
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.map_id', 'ASC')
            ->orderBy('c108.block_id', 'ASC')
            ->orderBy('c108.space_no', 'ASC')
            ->orderBy('c108.space_no_sub', 'ASC')
            ->limit(self::PER_PAGE, $offset)
            ->get()
            ->getResultArray();

        return view('c108/index', [
            'rows' => $rows,
            'summary' => $this->summary($db),
            'unreadNotices' => $this->unreadNotices($db),
            'q' => $q,
            'day' => $day,
            'relation' => $relation,
            'priority' => $priority,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages,
            'perPage' => self::PER_PAGE,
        ]);
    }

    public function readNotice(int $id): ResponseInterface
    {
        if ($id > 0) {
            db_connect()->table('c108_circles')
                ->where('id', $id)
                ->update(['update_read' => 1]);
        }

        return redirect()->back()->with('message', '已標記通知為已讀。');
    }

    public function readAllNotices(): ResponseInterface
    {
        db_connect()->table('c108_circles')
            ->where('update_read', 0)
            ->where('update_notice_text IS NOT NULL', null, false)
            ->update(['update_read' => 1]);

        return redirect()->back()->with('message', '已標記所有 C108 通知為已讀。');
    }

    public function map(): string
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $day = trim((string) $this->request->getGet('day'));
        $map = trim((string) $this->request->getGet('map'));
        $relation = trim((string) $this->request->getGet('relation'));
        $priority = trim((string) $this->request->getGet('priority'));
        $hasFilters = $q !== '' || $day !== '' || $map !== '' || $relation !== '' || $priority !== '';

        if ($relation === '') {
            $relation = 'all';
        }
        if (! $hasFilters) {
            $day = '1';
            $map = 'E123';
            $relation = 'all';
        }

        $requestedDay = $day;
        $maps = $this->mapOptions($db);
        if ($q !== '') {
            $targetMap = $this->firstMatchingMap($db, $q, $day, $relation, $priority);
            if ($targetMap === null && $day !== '') {
                $targetMap = $this->firstMatchingMap($db, $q, '', $relation, $priority);
            }
            if ($targetMap !== null) {
                $day = (string) $targetMap['day'];
                $map = (string) $targetMap['map_filename'];
                $requestedDay = $day;
            }
        }

        if ($maps !== []) {
            $selected = $this->selectedMap($maps, $requestedDay, $map);
            $day = (string) $selected['day'];
            $map = (string) $selected['map_filename'];
        }

        $rows = [];
        $image = null;
        $positionRows = [];
        if ($day !== '' && $map !== '') {
            $builder = $this->baseBuilder($db)
                ->where('c108.day', (int) $day)
                ->where('c108.map_filename', $map)
                ->where('c108.xpos2 IS NOT NULL', null, false)
                ->where('c108.ypos2 IS NOT NULL', null, false);
            $this->applyFilters($builder, $q, '', $relation, $priority);

            $rows = $builder
                ->orderBy('c.is_tracked', 'DESC')
                ->orderBy("FIELD(c.priority, 'must', 'high', 'normal')", '', false)
                ->orderBy('c108.block_id', 'ASC')
                ->orderBy('c108.space_no', 'ASC')
                ->orderBy('c108.space_no_sub', 'ASC')
                ->get()
                ->getResultArray();

            $image = $this->mapImage($day, $map);
            $positionRows = $this->mapPositionRows($db, $day, $map);
        }

        return view('c108/map', [
            'rows' => $rows,
            'positionRows' => $positionRows,
            'maps' => $maps,
            'image' => $image,
            'q' => $q,
            'day' => $day,
            'map' => $map,
            'relation' => $relation,
            'priority' => $priority,
        ]);
    }

    public function exportMap(): string
    {
        $db = db_connect();
        $day = trim((string) $this->request->getGet('day'));
        $map = trim((string) $this->request->getGet('map'));
        $relation = trim((string) $this->request->getGet('relation'));
        $priority = trim((string) $this->request->getGet('priority'));

        if ($day === '') {
            $day = '1';
        }
        if ($relation === '') {
            $relation = 'known';
        }
        if ($relation === 'all') {
            $relation = 'known';
        }

        $maps = $this->mapOptions($db);
        if ($maps !== []) {
            $selected = $this->selectedMap($maps, $day, $map);
            $day = (string) $selected['day'];
            $map = (string) $selected['map_filename'];
        }

        $rows = [];
        $image = null;
        $positionRows = [];
        if ($day !== '' && $map !== '') {
            $builder = $this->baseBuilder($db)
                ->where('c108.day', (int) $day)
                ->where('c108.map_filename', $map)
                ->where('c108.xpos2 IS NOT NULL', null, false)
                ->where('c108.ypos2 IS NOT NULL', null, false);
            $this->applyFilters($builder, '', '', $relation, $priority);

            $rows = $builder
                ->orderBy('c.is_tracked', 'DESC')
                ->orderBy("FIELD(c.priority, 'must', 'high', 'normal')", '', false)
                ->orderBy('c108.block_id', 'ASC')
                ->orderBy('c108.space_no', 'ASC')
                ->orderBy('c108.space_no_sub', 'ASC')
                ->get()
                ->getResultArray();

            $image = $this->mapImage($day, $map);
            $positionRows = $this->mapPositionRows($db, $day, $map);
        }

        return view('c108/export_map', [
            'rows' => $rows,
            'positionRows' => $positionRows,
            'maps' => $maps,
            'image' => $image,
            'day' => $day,
            'map' => $map,
            'relation' => $relation,
            'priority' => $priority,
        ]);
    }

    public function works(int $wcid): ResponseInterface
    {
        if ($wcid <= 0) {
            return $this->response->setStatusCode(404)->setJSON(['message' => '找不到攤位。']);
        }

        $row = db_connect()->table('c108_circles')
            ->select('event_id, wcid')
            ->where('wcid', $wcid)
            ->get()
            ->getRowArray();

        if (! $row) {
            return $this->response->setStatusCode(404)->setJSON(['message' => '找不到攤位。']);
        }

        $token = $this->currentCirclemsToken();
        if (! $token) {
            return $this->response->setStatusCode(422)->setJSON(['message' => '尚未連線 Circle.ms。']);
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshCirclemsTokenIfNeeded($token, $client);
            $items = [];
            $seenCount = 0;
            $maxCount = null;

            for ($page = 1; $page <= 20; $page++) {
                $result = $client->favoriteWorks((string) $token['access_token'], (int) $row['event_id'], $page, $wcid);
                $response = $result['response'] ?? [];
                $pageItems = is_array($response['list'] ?? null) ? $response['list'] : [];
                $seenCount += count($pageItems);

                if (isset($response['maxcount'])) {
                    $maxCount = (int) $response['maxcount'];
                }

                foreach ($pageItems as $item) {
                    if (is_array($item) && (int) ($item['wcid'] ?? 0) === $wcid) {
                        $items[] = $item;
                    }
                }

                if ($pageItems === [] || ($maxCount !== null && $seenCount >= $maxCount)) {
                    break;
                }
            }
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(502)->setJSON(['message' => $exception->getMessage()]);
        }

        return $this->response->setJSON([
            'items' => array_map([$this, 'formatWorkItem'], $items),
        ]);
    }

    public function circle(int $wcid): ResponseInterface
    {
        if ($wcid <= 0) {
            return $this->response->setStatusCode(404)->setJSON(['message' => '找不到攤位。']);
        }

        $row = db_connect()->table('c108_circles')
            ->select('event_id, wcid')
            ->where('wcid', $wcid)
            ->get()
            ->getRowArray();

        if (! $row) {
            return $this->response->setStatusCode(404)->setJSON(['message' => '找不到攤位。']);
        }

        $token = $this->currentCirclemsToken();
        if (! $token) {
            return $this->response->setStatusCode(422)->setJSON(['message' => '尚未連線 Circle.ms。']);
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshCirclemsTokenIfNeeded($token, $client);
            $result = $client->circleDetail((string) $token['access_token'], $wcid, (int) $row['event_id']);
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(502)->setJSON(['message' => $exception->getMessage()]);
        }

        $circle = $this->circleFromResponse($result);

        return $this->response->setJSON([
            'circle' => [
                'image_url' => (string) ($circle['cut_url'] ?? $circle['cut_web_url'] ?? $circle['cut_base_url'] ?? ''),
            ],
        ]);
    }

    private function baseBuilder($db, bool $countOnly = false)
    {
        $builder = $db->table('c108_circles c108')
            ->join('circles c', $this->circleJoinCondition(), 'left');

        if ($countOnly) {
            return $builder->select('COUNT(*) AS total', false);
        }

        return $builder
            ->select('c108.*')
            ->select('c.id AS local_circle_id, c.name AS local_circle_name, c.is_tracked, c.priority, c.note, c.webcatalog_cut_url')
            ->select($this->ownedBookSelect(0) . ' AS owned_book_1_title', false)
            ->select($this->ownedBookSelect(0, 'cover_url') . ' AS owned_book_1_cover', false)
            ->select($this->ownedBookSelect(1) . ' AS owned_book_2_title', false)
            ->select($this->ownedBookSelect(1, 'cover_url') . ' AS owned_book_2_cover', false);
    }

    private function ownedBookSelect(int $offset, string $column = 'title'): string
    {
        $column = $column === 'cover_url' ? 'cover_url' : 'title';

        return "(SELECT b.{$column} FROM books b WHERE b.circle_id = c.id AND b.status = 'owned' ORDER BY b.updated_at DESC, b.id DESC LIMIT 1 OFFSET {$offset})";
    }

    private function applyFilters($builder, string $q, string $day, string $relation, string $priority): void
    {
        if ($q !== '') {
            $builder->groupStart()
                ->like('c108.circle_name', $q)
                ->orLike('c108.circle_kana', $q)
                ->orLike('c108.pen_name', $q)
                ->orLike('c108.position_label', $q)
                ->orLike('c.note', $q)
                ->groupEnd();
        }

        if ($day === '1' || $day === '2') {
            $builder->where('c108.day', (int) $day);
        }

        if ($relation === 'known') {
            $builder->where('c.id IS NOT NULL', null, false);
        } elseif ($relation === 'tracked') {
            $builder->where('c.is_tracked', 1);
        } elseif ($relation === 'unknown') {
            $builder->where('c.id IS NULL', null, false);
        }

        if (in_array($priority, ['normal', 'high', 'must'], true)) {
            $builder->where('c.priority', $priority);
        }
    }

    private function summary($db): array
    {
        $row = $db->table('c108_circles c108')
            ->select('COUNT(*) AS total', false)
            ->select('SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) AS known_count', false)
            ->select('SUM(CASE WHEN c.is_tracked = 1 THEN 1 ELSE 0 END) AS tracked_count', false)
            ->join('circles c', $this->circleJoinCondition(), 'left')
            ->get()
            ->getRowArray();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'known' => (int) ($row['known_count'] ?? 0),
            'tracked' => (int) ($row['tracked_count'] ?? 0),
        ];
    }

    private function unreadNotices($db): array
    {
        return $this->baseBuilder($db)
            ->where('c108.update_read', 0)
            ->where('c108.update_notice_text IS NOT NULL', null, false)
            ->orderBy('c108.update_detected_at', 'DESC')
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.position_label', 'ASC')
            ->limit(20)
            ->get()
            ->getResultArray();
    }

    private function mapOptions($db): array
    {
        return $db->table('c108_circles c108')
            ->select('c108.day, c108.map_filename, c108.map_name')
            ->select('COUNT(*) AS total_count', false)
            ->select('SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) AS known_count', false)
            ->select('SUM(CASE WHEN c.is_tracked = 1 THEN 1 ELSE 0 END) AS tracked_count', false)
            ->join('circles c', $this->circleJoinCondition(), 'left')
            ->where('c108.map_filename IS NOT NULL', null, false)
            ->groupBy('c108.day, c108.map_filename, c108.map_name')
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.map_filename', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function firstMatchingMap($db, string $q, string $day, string $relation, string $priority): ?array
    {
        $builder = $this->baseBuilder($db)
            ->where('c108.map_filename IS NOT NULL', null, false)
            ->where('c108.xpos2 IS NOT NULL', null, false)
            ->where('c108.ypos2 IS NOT NULL', null, false);

        $this->applyFilters($builder, $q, $day, $relation, $priority);

        $row = $builder
            ->orderBy('c.is_tracked', 'DESC')
            ->orderBy("FIELD(c.priority, 'must', 'high', 'normal')", '', false)
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.map_id', 'ASC')
            ->orderBy('c108.block_id', 'ASC')
            ->orderBy('c108.space_no', 'ASC')
            ->orderBy('c108.space_no_sub', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (! $row || empty($row['map_filename'])) {
            return null;
        }

        return $row;
    }

    private function circleJoinCondition(): string
    {
        return 'c.id = c108.circle_id';
    }

    private function selectedMap(array $maps, string $day, string $map): array
    {
        foreach ($maps as $row) {
            if ((string) $row['day'] === $day && (string) $row['map_filename'] === $map) {
                return $row;
            }
        }

        if ($day === '1' || $day === '2') {
            foreach ($maps as $row) {
                if ((string) $row['day'] === $day) {
                    return $row;
                }
            }
        }

        foreach ($maps as $row) {
            if ((string) $row['day'] === '1' && (string) $row['map_filename'] === 'E123') {
                return $row;
            }
        }

        return $maps[0];
    }

    private function mapImage(string $day, string $map): ?array
    {
        $fileName = 'LWMP' . $day . $map . '.png';
        $relativePath = 'uploads/circlems/event_230/image1/common/' . $fileName;
        $path = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($path)) {
            return null;
        }

        $size = getimagesize($path) ?: [0, 0];

        return [
            'file' => $fileName,
            'url' => '/' . $relativePath,
            'width' => (int) $size[0],
            'height' => (int) $size[1],
            'marker_offset_x' => 20,
            'marker_offset_y' => 20,
        ];
    }

    public static function markerPosition(array $row, array $image): array
    {
        return self::adjustMarkerPosition(self::baseMarkerPosition($row, $image), strtolower((string) ($row['space_no_sub'] ?? '')), 'x');
    }

    public static function markerPositions(array $rows, array $image): array
    {
        $spaceMap = [];
        $geometryRows = ! empty($image['position_rows']) && is_array($image['position_rows']) ? $image['position_rows'] : $rows;
        foreach ($geometryRows as $row) {
            $spaceNo = (int) ($row['space_no'] ?? 0);
            if ($spaceNo <= 0) {
                continue;
            }

            $groupKey = self::markerGroupKey($row);
            $position = self::baseMarkerPosition($row, $image);
            $spaceMap[$groupKey][$spaceNo] ??= $position;
        }

        $componentAxes = self::componentAxes($geometryRows, $image);
        $positions = [];
        foreach ($rows as $index => $row) {
            $groupKey = self::markerGroupKey($row);
            $spaceNo = (int) ($row['space_no'] ?? 0);
            $basePosition = self::baseMarkerPosition($row, $image);
            $basePosition = self::overrideBaseMarkerPosition($row, $basePosition, $spaceMap[$groupKey] ?? []);
            $axis = $componentAxes[self::positionKey($basePosition)] ?? self::splitAxis($spaceMap[$groupKey] ?? [], $spaceNo);
            $axis = self::overrideAxis($row, $axis);
            $positions[$index] = self::adjustMarkerPosition(
                $basePosition,
                strtolower((string) ($row['space_no_sub'] ?? '')),
                $axis
            );
        }

        return $positions;
    }

    private function mapPositionRows($db, string $day, string $map): array
    {
        return $db->table('c108_circles')
            ->select('day, map_filename, block_id, block_name, space_no, xpos2, ypos2')
            ->where('day', (int) $day)
            ->where('map_filename', $map)
            ->where('xpos2 IS NOT NULL', null, false)
            ->where('ypos2 IS NOT NULL', null, false)
            ->orderBy('block_id', 'ASC')
            ->orderBy('space_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    private static function markerGroupKey(array $row): string
    {
        return implode(':', [
            (string) ($row['day'] ?? ''),
            (string) ($row['map_filename'] ?? ''),
            (string) ($row['block_id'] ?? ''),
            (string) ($row['block_name'] ?? ''),
        ]);
    }

    private static function baseMarkerPosition(array $row, array $image): array
    {
        return [
            'left' => (int) ($row['xpos2'] ?? 0) + (int) ($image['marker_offset_x'] ?? 0),
            'top' => (int) ($row['ypos2'] ?? 0) + (int) ($image['marker_offset_y'] ?? 0),
        ];
    }

    private static function overrideBaseMarkerPosition(array $row, array $position, array $spaces): array
    {
        $map = (string) ($row['map_filename'] ?? '');
        $block = (string) ($row['block_name'] ?? '');
        $positionLabel = (string) ($row['position_label'] ?? '');
        $spaceNo = (int) ($row['space_no'] ?? 0);

        if (
            $map !== 'E123'
            || ! self::blockMatches($block, $positionLabel, 'ア')
            || ! in_array($spaceNo, [30, 31, 32], true)
        ) {
            return $position;
        }

        foreach ([33, 34, 35] as $referenceSpaceNo) {
            if (! isset($spaces[$referenceSpaceNo])) {
                return $position;
            }
        }

        $direction = (int) $spaces[33]['left'] >= (int) $spaces[34]['left'] ? 1 : -1;
        $boothStep = max(1, abs((int) $spaces[33]['left'] - (int) $spaces[34]['left']));
        $gapStep = max($boothStep, abs((int) $spaces[34]['left'] - (int) $spaces[35]['left']));
        $top = (int) round(((int) $spaces[33]['top'] + (int) $spaces[34]['top'] + (int) $spaces[35]['top']) / 3);

        $leftBySpaceNo = [
            32 => (int) $spaces[33]['left'] + ($direction * $gapStep),
            31 => (int) $spaces[33]['left'] + ($direction * ($gapStep + $boothStep)),
            30 => (int) $spaces[33]['left'] + ($direction * ($gapStep + ($boothStep * 2))),
        ];

        return [
            'left' => max(0, $leftBySpaceNo[$spaceNo]),
            'top' => max(0, $top),
        ];
    }

    private static function splitAxis(array $spaces, int $spaceNo): string
    {
        if (! isset($spaces[$spaceNo])) {
            return 'x';
        }

        $current = $spaces[$spaceNo];
        $nearest = null;
        foreach ($spaces as $otherSpaceNo => $position) {
            if ((int) $otherSpaceNo === $spaceNo) {
                continue;
            }

            $spaceDistance = abs((int) $otherSpaceNo - $spaceNo);
            $distance = abs((int) $position['left'] - (int) $current['left'])
                + abs((int) $position['top'] - (int) $current['top']);

            if ($distance <= 0) {
                continue;
            }

            if (
                $nearest === null
                || $spaceDistance < $nearest['spaceDistance']
                || ($spaceDistance === $nearest['spaceDistance'] && $distance < $nearest['distance'])
            ) {
                $nearest = [
                    'spaceDistance' => $spaceDistance,
                    'distance' => $distance,
                    'position' => $position,
                ];
            }
        }

        if ($nearest === null) {
            return 'x';
        }

        $dx = abs((int) $nearest['position']['left'] - (int) $current['left']);
        $dy = abs((int) $nearest['position']['top'] - (int) $current['top']);

        if ($dy > $dx) {
            return self::isLaneEdge($spaces, $current, 'y') ? 'x' : 'y';
        }

        return 'x';
    }

    private static function overrideAxis(array $row, string $axis): string
    {
        $map = (string) ($row['map_filename'] ?? '');
        $block = (string) ($row['block_name'] ?? '');
        $positionLabel = (string) ($row['position_label'] ?? '');
        $spaceNo = (int) ($row['space_no'] ?? 0);

        foreach (self::axisOverrides() as $override) {
            if ((string) $override['map'] !== $map) {
                continue;
            }
            if (! self::blockMatches($block, $positionLabel, (string) $override['block'])) {
                continue;
            }
            if (! in_array($spaceNo, $override['spaces'], true)) {
                continue;
            }

            return (string) $override['axis'];
        }

        return $axis;
    }

    private static function axisOverrides(): array
    {
        return [
            ['map' => 'E123', 'block' => 'ア', 'spaces' => array_merge(range(1, 22), range(74, 95)), 'axis' => 'y'],
            ['map' => 'E123', 'block' => 'ア', 'spaces' => range(23, 73), 'axis' => 'x'],
            ['map' => 'E7', 'block' => 'A', 'spaces' => array_merge(range(1, 18), range(35, 48)), 'axis' => 'y'],
            ['map' => 'E7', 'block' => 'A', 'spaces' => range(19, 34), 'axis' => 'x'],
            ['map' => 'S12', 'block' => 'a', 'spaces' => array_merge(range(5, 20), range(45, 50)), 'axis' => 'y'],
            ['map' => 'S12', 'block' => 'a', 'spaces' => array_merge(range(1, 4), range(21, 44), range(51, 54)), 'axis' => 'x'],
            ['map' => 'W12', 'block' => 'め', 'spaces' => array_merge(range(16, 39), range(66, 73)), 'axis' => 'y'],
            ['map' => 'W12', 'block' => 'め', 'spaces' => array_merge(range(1, 15), range(40, 61)), 'axis' => 'x'],
            ['map' => 'W12', 'block' => 'あ', 'spaces' => array_merge(range(22, 39), range(66, 73)), 'axis' => 'y'],
            ['map' => 'W12', 'block' => 'あ', 'spaces' => array_merge(range(1, 15), range(40, 61)), 'axis' => 'x'],
        ];
    }

    private static function blockMatches(string $actual, string $positionLabel, string $expected): bool
    {
        $actual = trim($actual);
        $positionLabel = trim($positionLabel);
        $expected = trim($expected);

        if (preg_match('/^[A-Za-z]$/', $expected) === 1) {
            $actual = self::normalizeAsciiLetters($actual);
            $positionLabel = self::normalizeAsciiLetters($positionLabel);

            return $actual === $expected
                || preg_match('/(?:^|[\s　])' . preg_quote($expected, '/') . '\s*\d+/u', $positionLabel) === 1;
        }

        return $actual === $expected
            || str_contains($actual, $expected)
            || str_contains($positionLabel, $expected);
    }

    private static function normalizeAsciiLetters(string $value): string
    {
        return strtr($value, [
            'Ａ' => 'A',
            'Ｂ' => 'B',
            'Ｃ' => 'C',
            'Ｄ' => 'D',
            'Ｅ' => 'E',
            'Ｆ' => 'F',
            'Ｇ' => 'G',
            'Ｈ' => 'H',
            'Ｉ' => 'I',
            'Ｊ' => 'J',
            'Ｋ' => 'K',
            'Ｌ' => 'L',
            'Ｍ' => 'M',
            'Ｎ' => 'N',
            'Ｏ' => 'O',
            'Ｐ' => 'P',
            'Ｑ' => 'Q',
            'Ｒ' => 'R',
            'Ｓ' => 'S',
            'Ｔ' => 'T',
            'Ｕ' => 'U',
            'Ｖ' => 'V',
            'Ｗ' => 'W',
            'Ｘ' => 'X',
            'Ｙ' => 'Y',
            'Ｚ' => 'Z',
            'ａ' => 'a',
            'ｂ' => 'b',
            'ｃ' => 'c',
            'ｄ' => 'd',
            'ｅ' => 'e',
            'ｆ' => 'f',
            'ｇ' => 'g',
            'ｈ' => 'h',
            'ｉ' => 'i',
            'ｊ' => 'j',
            'ｋ' => 'k',
            'ｌ' => 'l',
            'ｍ' => 'm',
            'ｎ' => 'n',
            'ｏ' => 'o',
            'ｐ' => 'p',
            'ｑ' => 'q',
            'ｒ' => 'r',
            'ｓ' => 's',
            'ｔ' => 't',
            'ｕ' => 'u',
            'ｖ' => 'v',
            'ｗ' => 'w',
            'ｘ' => 'x',
            'ｙ' => 'y',
            'ｚ' => 'z',
        ]);
    }

    private static function componentAxes(array $rows, array $image): array
    {
        $points = [];
        foreach ($rows as $row) {
            $spaceNo = (int) ($row['space_no'] ?? 0);
            if ($spaceNo <= 0 || self::isManualAxisBlock($row)) {
                continue;
            }

            $position = self::baseMarkerPosition($row, $image);
            $points[] = ['left' => $position['left'], 'top' => $position['top']];
        }

        $components = self::positionComponents($points, 64);
        $axes = [];
        foreach ($components as $component) {
            if (count($component) < 4) {
                continue;
            }

            $lefts = array_column($component, 'left');
            $tops = array_column($component, 'top');
            $minLeft = min($lefts);
            $maxLeft = max($lefts);
            $minTop = min($tops);
            $maxTop = max($tops);
            $width = max(1, $maxLeft - $minLeft);
            $height = max(1, $maxTop - $minTop);
            $edge = 24;

            foreach ($component as $point) {
                if ($height >= $width) {
                    $axis = ((int) $point['top'] <= $minTop + $edge || (int) $point['top'] >= $maxTop - $edge) ? 'x' : 'y';
                } else {
                    $axis = ((int) $point['left'] <= $minLeft + $edge || (int) $point['left'] >= $maxLeft - $edge) ? 'y' : 'x';
                }

                $axes[self::positionKey($point)] = $axis;
            }
        }

        return $axes;
    }

    private static function isManualAxisBlock(array $row): bool
    {
        return (string) ($row['map_filename'] ?? '') === 'E123'
            && self::blockMatches((string) ($row['block_name'] ?? ''), (string) ($row['position_label'] ?? ''), 'ア');
    }

    private static function positionComponents(array $points, int $threshold): array
    {
        $components = [];
        $visited = [];
        foreach ($points as $index => $point) {
            if (isset($visited[$index])) {
                continue;
            }

            $queue = [$index];
            $visited[$index] = true;
            $component = [];
            while ($queue !== []) {
                $currentIndex = array_pop($queue);
                $current = $points[$currentIndex];
                $component[] = $current;

                foreach ($points as $nextIndex => $next) {
                    if (isset($visited[$nextIndex])) {
                        continue;
                    }

                    $distance = abs((int) $current['left'] - (int) $next['left']) + abs((int) $current['top'] - (int) $next['top']);
                    if ($distance > $threshold) {
                        continue;
                    }

                    $visited[$nextIndex] = true;
                    $queue[] = $nextIndex;
                }
            }

            $components[] = $component;
        }

        return $components;
    }

    private static function positionKey(array $position): string
    {
        return (int) $position['left'] . ':' . (int) $position['top'];
    }

    private static function isLaneEdge(array $spaces, array $current, string $axis): bool
    {
        $positions = [];
        foreach ($spaces as $position) {
            if ($axis === 'y') {
                if (abs((int) $position['left'] - (int) $current['left']) <= 12) {
                    $positions[] = (int) $position['top'];
                }
            } elseif (abs((int) $position['top'] - (int) $current['top']) <= 12) {
                $positions[] = (int) $position['left'];
            }
        }

        if (count($positions) < 2) {
            return false;
        }

        $currentValue = $axis === 'y' ? (int) $current['top'] : (int) $current['left'];

        return abs($currentValue - min($positions)) <= 4 || abs($currentValue - max($positions)) <= 4;
    }

    private static function adjustMarkerPosition(array $position, string $spaceSub, string $axis): array
    {
        $left = (int) $position['left'];
        $top = (int) $position['top'];

        if ($axis === 'y') {
            if ($spaceSub === 'a') {
                $top += 7;
            } elseif ($spaceSub === 'b') {
                $top -= 7;
            }
        } elseif ($spaceSub === 'a') {
            $left -= 9;
        } elseif ($spaceSub === 'b') {
            $left += 9;
        }

        return ['left' => max(0, $left), 'top' => max(0, $top), 'axis' => $axis];
    }

    private function currentCirclemsToken(): ?array
    {
        return (new CirclemsTokenModel())
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function refreshCirclemsTokenIfNeeded(array $token, CirclemsClient $client): array
    {
        $expiresAt = strtotime((string) ($token['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt > time() + 300) {
            return $token;
        }

        if (empty($token['refresh_token'])) {
            throw new RuntimeException('Circle.ms token 已過期，且沒有 refresh token。');
        }

        $tokenResponse = $client->refreshToken((string) $token['refresh_token']);
        $data = [
            'access_token' => (string) ($tokenResponse['access_token'] ?? ''),
            'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? ''),
            'expires_at' => $client->tokenExpiresAt($tokenResponse),
            'scope' => isset($tokenResponse['scope']) ? (string) $tokenResponse['scope'] : null,
        ];

        (new CirclemsTokenModel())->insert($data);

        return array_merge($token, $data);
    }

    private function formatWorkItem(array $row): array
    {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'image_url' => (string) ($row['image_url'] ?? ''),
            'introduction' => (string) ($row['introduction'] ?? ''),
            'new_book' => (int) ($row['new_book'] ?? 0),
            'price' => isset($row['price']) ? (int) $row['price'] : null,
            'page' => (int) ($row['page'] ?? 0),
            'size' => (string) ($row['size'] ?? ''),
            'r18' => (int) ($row['r18'] ?? 0),
            'update_date' => (string) ($row['update_date'] ?? ''),
        ];
    }

    private function circleFromResponse(array $result): array
    {
        $circle = $result['response']['circle'] ?? null;
        if (is_array($circle)) {
            return $circle;
        }

        $list = $result['response']['list'] ?? null;
        if (is_array($list) && isset($list[0]) && is_array($list[0])) {
            return $list[0];
        }

        return [];
    }
}
