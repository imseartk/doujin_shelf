<?php

if (! function_exists('admin_unlocked')) {
    function admin_unlocked(): bool
    {
        return (bool) session('admin_unlocked');
    }
}
