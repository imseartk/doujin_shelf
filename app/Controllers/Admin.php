<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class Admin extends BaseController
{
    public function manage(): string
    {
        return view('admin/manage', [
            'returnTo' => $this->safeReturnTo((string) $this->request->getGet('return_to')),
            'configured' => $this->passwordHash() !== '',
        ]);
    }

    public function unlock(): RedirectResponse
    {
        $passwordHash = $this->passwordHash();
        $passcode = (string) $this->request->getPost('passcode');

        if ($passwordHash === '') {
            return redirect()->to('/manage')->with('error', '尚未設定管理暗碼。');
        }

        if (! password_verify($passcode, $passwordHash)) {
            return redirect()->to('/manage')->with('error', '暗碼不正確。');
        }

        session()->regenerate();
        session()->set('admin_unlocked', true);

        return redirect()->to($this->safeReturnTo((string) $this->request->getPost('return_to')))->with('message', '管理功能已解鎖。');
    }

    private function passwordHash(): string
    {
        return trim((string) env('admin.passwordHash', ''));
    }

    private function safeReturnTo(string $path): string
    {
        if ($path === '' || str_starts_with($path, '//') || ! str_starts_with($path, '/')) {
            return '/books';
        }

        if (str_starts_with($path, '/manage')) {
            return '/books';
        }

        return $path;
    }
}
