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
        }

        return view('c108/map', [
            'rows' => $rows,
            'maps' => $maps,
            'image' => $image,
            'q' => $q,
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
            $result = $client->favoriteWorks((string) $token['access_token'], (int) $row['event_id'], 1, $wcid);
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(502)->setJSON(['message' => $exception->getMessage()]);
        }

        $response = $result['response'] ?? [];
        $items = is_array($response['list'] ?? null) ? $response['list'] : [];
        $items = array_values(array_filter($items, static function ($item) use ($wcid): bool {
            return is_array($item) && (int) ($item['wcid'] ?? 0) === $wcid;
        }));

        return $this->response->setJSON([
            'items' => array_map([$this, 'formatWorkItem'], $items),
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
}
