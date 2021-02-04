<?php

namespace App\Service;

use App\Kernel;

class Cache
{
    private string $path;

    public function __construct(Kernel $kernel)
    {
        $this->path = $kernel->getProjectDir().'/var/app_cache';
    }

    public function get(string $key)
    {
        $data = json_decode(file_get_contents($this->path), true);

        if (!isset($data[$key])) {
            return null;
        }

        if ($data[$key]['ttl'] < time()) {
            return null;
        }

        return $data[$key]['value'];
    }

    public function set(string $key, $val, int $time = 60): int
    {
        $data = json_decode(file_get_contents($this->path), true);

        $data[$key] = ['ttl' => time() + $time, 'value' => $val];

        return file_put_contents($this->path, json_encode($data));
    }

    public function clear()
    {
        file_put_contents($this->path, '');
    }
}
