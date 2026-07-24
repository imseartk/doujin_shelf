<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('admin');

        if (admin_unlocked()) {
            return null;
        }

        $method = strtolower($request->getMethod());
        $path = trim($request->getUri()->getPath(), '/');

        if ($this->isPublicRoute($method, $path)) {
            return null;
        }

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON([
                'message' => 'admin required',
                'csrf' => csrf_hash(),
            ]);
        }

        if ($method !== 'get') {
            return redirect()->to('/manage')->with('error', '請先解鎖管理功能。');
        }

        return redirect()->to('/manage?return_to=' . rawurlencode('/' . $path));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function isPublicRoute(string $method, string $path): bool
    {
        if (str_starts_with($path, 'api/app/')) {
            return true;
        }

        if (ENVIRONMENT === 'production' && $method === 'get' && $path === 'circlems') {
            return true;
        }

        if ($method === 'get' && ($path === '' || $path === 'books' || $path === 'manage')) {
            return true;
        }

        if ($method === 'post' && ($path === 'manage' || $path === 'preferences/cover-privacy')) {
            return true;
        }

        return false;
    }
}
