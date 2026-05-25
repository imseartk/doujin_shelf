<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Preferences extends BaseController
{
    public function coverPrivacy(): RedirectResponse
    {
        $hideCovers = (string) $this->request->getPost('hide_covers') === '1';
        session()->set('hide_covers', $hideCovers);

        return redirect()->to($this->safeReturnTo((string) $this->request->getPost('return_to')));
    }

    private function safeReturnTo(string $path): string
    {
        if ($path === '' || str_starts_with($path, '//') || ! str_starts_with($path, '/')) {
            return '/books';
        }

        return $path;
    }
}
