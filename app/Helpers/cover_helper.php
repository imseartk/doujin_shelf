<?php

if (! function_exists('covers_hidden')) {
    function covers_hidden(): bool
    {
        $isAdminUnlocked = function_exists('admin_unlocked') && admin_unlocked();

        if (! $isAdminUnlocked && session('hide_covers') === null) {
            return true;
        }

        return (bool) session('hide_covers');
    }
}

if (! function_exists('cover_placeholder_url')) {
    function cover_placeholder_url(): string
    {
        return '/assets/cover-placeholder.svg';
    }
}

if (! function_exists('cover_display_url')) {
    function cover_display_url(?string $coverUrl): string
    {
        $coverUrl = trim((string) $coverUrl);

        if ($coverUrl === '') {
            return '';
        }

        return covers_hidden() ? cover_placeholder_url() : $coverUrl;
    }
}
