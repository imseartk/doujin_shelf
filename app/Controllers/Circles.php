<?php

namespace App\Controllers;

use App\Libraries\CirclemsClient;
use App\Models\CircleModel;
use App\Models\CirclemsTokenModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class Circles extends BaseController
{
    private const PRIORITY_OPTIONS = [
        'normal' => '普通',
        'high' => '優先',
        'must' => '必看',
    ];

    public function index(): string
    {
        $q = trim((string) $this->request->getGet('q'));
        $tracked = trim((string) $this->request->getGet('tracked'));
        $priority = trim((string) $this->request->getGet('priority'));
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = 30;
        $db = db_connect();

        $builder = $db->table('circles c')
            ->select('c.*')
            ->select('COUNT(b.id) AS book_count', false)
            ->select("SUM(CASE WHEN b.status = 'wishlist' THEN 1 ELSE 0 END) AS wishlist_count", false)
            ->join('books b', 'b.circle_id = c.id', 'left');

        if ($q !== '') {
            $builder->groupStart()
                ->like('c.name', $q)
                ->orLike('c.name_kana', $q)
                ->orLike('c.note', $q)
                ->groupEnd();
        }

        if ($tracked === '1' || $tracked === '0') {
            $builder->where('c.is_tracked', (int) $tracked);
        }

        if ($priority !== '' && array_key_exists($priority, self::PRIORITY_OPTIONS)) {
            $builder->where('c.priority', $priority);
        }

        $countBuilder = $db->table('circles c')->select('COUNT(*) AS total', false);
        if ($q !== '') {
            $countBuilder->groupStart()
                ->like('c.name', $q)
                ->orLike('c.name_kana', $q)
                ->orLike('c.note', $q)
                ->groupEnd();
        }
        if ($tracked === '1' || $tracked === '0') {
            $countBuilder->where('c.is_tracked', (int) $tracked);
        }
        if ($priority !== '' && array_key_exists($priority, self::PRIORITY_OPTIONS)) {
            $countBuilder->where('c.priority', $priority);
        }

        $total = (int) $countBuilder->get()->getRow('total');
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $circles = $builder
            ->groupBy('c.id')
            ->orderBy('c.is_tracked', 'DESC')
            ->orderBy("FIELD(c.priority, 'must', 'high', 'normal')", '', false)
            ->orderBy('c.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return view('circles/index', [
            'circles' => $circles,
            'q' => $q,
            'tracked' => $tracked,
            'priority' => $priority,
            'priorityOptions' => self::PRIORITY_OPTIONS,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function circlems(int $id): string
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return view('errors/html/error_404');
        }

        $q = trim((string) ($this->request->getGet('q') ?: $circle['name']));
        $eventId = (int) $this->request->getGet('event_id');
        $page = max(1, (int) $this->request->getGet('page'));
        $events = [];
        $latestEventId = null;
        $candidates = [];
        $error = null;

        try {
            $client = new CirclemsClient();
            $token = $this->currentCirclemsToken();
            if (! $token) {
                throw new RuntimeException('尚未連線 Circle.ms。');
            }

            $token = $this->refreshCirclemsTokenIfNeeded($token, $client);
            $eventList = $client->eventList((string) $token['access_token']);
            $events = $this->eventOptions($eventList);
            $latestEventId = $this->latestEventId($eventList);
            if ($eventId <= 0) {
                $eventId = $latestEventId ?? 0;
            }
            if ($eventId <= 0) {
                throw new RuntimeException('Circle.ms event list did not include a latest event id.');
            }

            if ($q !== '') {
                $result = $client->queryCircle((string) $token['access_token'], $eventId, $q, $page);
                $candidates = $this->circlemsCandidates($result);
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('circles/circlems', [
            'circle' => $circle,
            'q' => $q,
            'eventId' => $eventId,
            'events' => $events,
            'latestEventId' => $latestEventId,
            'page' => $page,
            'candidates' => $candidates,
            'error' => $error,
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $circleModel = new CircleModel();
        if (! $circleModel->find($id)) {
            return redirect()->to('/circles')->with('error', '找不到這個社團。');
        }

        $circleModel->update($id, [
            'name_kana' => $this->nullablePost('name_kana'),
            'priority' => $this->priorityPost(),
            'pixiv_url' => $this->nullablePost('pixiv_url'),
            'twitter_url' => $this->nullablePost('twitter_url'),
            'website_url' => $this->nullablePost('website_url'),
            'booth_url' => $this->nullablePost('booth_url'),
            'melonbooks_url' => $this->nullablePost('melonbooks_url'),
            'toranoana_url' => $this->nullablePost('toranoana_url'),
            'note' => $this->nullablePost('note'),
        ]);

        return redirect()->to($this->safeReturnTo())->with('message', '已更新社團資料。');
    }

    public function toggleTrack(int $id): ResponseInterface
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'not found',
                'csrf' => csrf_hash(),
            ]);
        }

        $isTracked = empty($circle['is_tracked']) ? 1 : 0;
        $circleModel->update($id, ['is_tracked' => $isTracked]);

        return $this->response->setJSON([
            'is_tracked' => $isTracked,
            'label' => $isTracked ? '追蹤中' : '未追蹤',
            'csrf' => csrf_hash(),
        ]);
    }

    public function bindCirclems(int $id): RedirectResponse
    {
        $circleModel = new CircleModel();
        $circle = $circleModel->find($id);
        if (! $circle) {
            return redirect()->to('/circles')->with('error', '找不到這個社團。');
        }

        $circlemsId = trim((string) $this->request->getPost('circlems_id'));
        if ($circlemsId === '') {
            return redirect()->to('/circles/' . $id . '/circlems')->with('error', '缺少 Circle.ms 社團 ID。');
        }

        $data = [
            'webcatalog_circle_id' => $circlemsId,
        ];

        $cutUrl = trim((string) $this->request->getPost('webcatalog_cut_url'));
        if ($cutUrl !== '') {
            $data['webcatalog_cut_url'] = $cutUrl;
        }

        if ((string) $this->request->getPost('import_social') === '1') {
            foreach ($this->circlemsSocialFieldsFromPost() as $field => $value) {
                if ($value !== null && empty($circle[$field])) {
                    $data[$field] = $value;
                }
            }

            $nameKana = trim((string) $this->request->getPost('name_kana'));
            if ($nameKana !== '' && empty($circle['name_kana'])) {
                $data['name_kana'] = $nameKana;
            }
        }

        $circleModel->update($id, $data);

        return redirect()->to('/circles/' . $id . '/circlems')->with('message', '已綁定 Circle.ms 社團。');
    }

    private function priorityPost(): string
    {
        $priority = (string) $this->request->getPost('priority');
        return array_key_exists($priority, self::PRIORITY_OPTIONS) ? $priority : 'normal';
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function safeReturnTo(): string
    {
        $returnTo = (string) $this->request->getPost('return_to');
        if ($returnTo === '' || str_starts_with($returnTo, '//') || ! str_starts_with($returnTo, '/circles')) {
            return '/circles';
        }

        return $returnTo;
    }

    private function currentCirclemsToken(): ?array
    {
        return (new CirclemsTokenModel())
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function refreshCirclemsTokenIfNeeded(array $token, CirclemsClient $client): array
    {
        if (! $this->isCirclemsTokenExpired($token)) {
            return $token;
        }

        $tokenResponse = $client->refreshToken((string) $token['refresh_token']);
        $this->storeCirclemsToken($tokenResponse, $client);

        return $this->currentCirclemsToken() ?? $token;
    }

    private function storeCirclemsToken(array $tokenResponse, CirclemsClient $client): void
    {
        $data = [
            'access_token' => (string) ($tokenResponse['access_token'] ?? ''),
            'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? ''),
            'expires_at' => $client->tokenExpiresAt($tokenResponse),
            'scope' => isset($tokenResponse['scope']) ? (string) $tokenResponse['scope'] : null,
            'last_error' => null,
        ];

        if ($data['access_token'] === '' || $data['refresh_token'] === '') {
            throw new RuntimeException('Circle.ms token response missing token values.');
        }

        $model = new CirclemsTokenModel();
        $current = $model->orderBy('id', 'DESC')->first();
        if ($current) {
            $model->update((int) $current['id'], $data);
            return;
        }

        $model->insert($data);
    }

    private function isCirclemsTokenExpired(?array $token): bool
    {
        if (! $token || empty($token['expires_at'])) {
            return true;
        }

        return strtotime((string) $token['expires_at']) <= time() + 300;
    }

    private function latestEventId(array $eventList): ?int
    {
        $candidates = [
            $eventList['response']['LatestEventId'] ?? null,
            $eventList['response']['latestEventId'] ?? null,
            $eventList['response']['latest_event_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function eventOptions(array $eventList): array
    {
        $list = $eventList['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $latestEventId = $this->latestEventId($eventList);
        $events = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $eventId = (int) ($row['EventId'] ?? $row['eventId'] ?? $row['event_id'] ?? 0);
            if ($eventId <= 0 || ($latestEventId !== null && $eventId > $latestEventId)) {
                continue;
            }

            $eventNo = (int) ($row['EventNo'] ?? $row['eventNo'] ?? $row['event_no'] ?? 0);
            $events[] = [
                'eventId' => $eventId,
                'label' => $eventNo > 0 ? 'C' . $eventNo . ' / Event ' . $eventId : 'Event ' . $eventId,
            ];
        }

        usort($events, static fn (array $a, array $b): int => $b['eventId'] <=> $a['eventId']);

        return $events;
    }

    private function circlemsCandidates(array $result): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $candidates = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stores = $this->circlemsStores($row['onlinestore'] ?? []);
            $candidates[] = [
                'wcid' => (string) ($row['wcid'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'name_kana' => (string) ($row['name_kana'] ?? ''),
                'circlems_id' => (string) ($row['circlemsId'] ?? ''),
                'genre' => (string) ($row['genre'] ?? ''),
                'cut_url' => (string) ($row['cut_url'] ?? $row['cut_web_url'] ?? $row['cut_base_url'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'tag' => (string) ($row['tag'] ?? ''),
                'website_url' => $this->nullableString($row['url'] ?? null),
                'pixiv_url' => $this->nullableString($row['pixiv_url'] ?? null),
                'twitter_url' => $this->nullableString($row['twitter_url'] ?? null),
                'booth_url' => $stores['booth_url'] ?? null,
                'melonbooks_url' => $stores['melonbooks_url'] ?? null,
                'toranoana_url' => $stores['toranoana_url'] ?? null,
                'stores' => $stores['links'],
            ];
        }

        return $candidates;
    }

    private function circlemsStores(mixed $stores): array
    {
        $links = [];
        $fields = [];
        if (! is_array($stores)) {
            return ['links' => [], 'booth_url' => null, 'melonbooks_url' => null, 'toranoana_url' => null];
        }

        foreach ($stores as $store) {
            if (! is_array($store)) {
                continue;
            }

            $name = trim((string) ($store['name'] ?? ''));
            $link = trim((string) ($store['link'] ?? ''));
            if ($name === '' || $link === '') {
                continue;
            }

            $links[] = ['name' => $name, 'link' => $link];
            $lowerName = mb_strtolower($name, 'UTF-8');
            if (str_contains($lowerName, 'booth')) {
                $fields['booth_url'] = $link;
            } elseif (str_contains($lowerName, 'メロン') || str_contains($lowerName, 'melon')) {
                $fields['melonbooks_url'] = $link;
            } elseif (str_contains($lowerName, 'とら') || str_contains($lowerName, 'tora')) {
                $fields['toranoana_url'] = $link;
            }
        }

        return [
            'links' => $links,
            'booth_url' => $fields['booth_url'] ?? null,
            'melonbooks_url' => $fields['melonbooks_url'] ?? null,
            'toranoana_url' => $fields['toranoana_url'] ?? null,
        ];
    }

    private function circlemsSocialFieldsFromPost(): array
    {
        return [
            'website_url' => $this->nullablePost('website_url'),
            'pixiv_url' => $this->nullablePost('pixiv_url'),
            'twitter_url' => $this->nullablePost('twitter_url'),
            'booth_url' => $this->nullablePost('booth_url'),
            'melonbooks_url' => $this->nullablePost('melonbooks_url'),
            'toranoana_url' => $this->nullablePost('toranoana_url'),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
