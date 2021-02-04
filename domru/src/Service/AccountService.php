<?php

declare(strict_types=1);

namespace App\Service;

use App\Traits\HttpClientAwareTrait;

class AccountService
{
    use HttpClientAwareTrait;

    private string $path;

    public function __construct()
    {
        $this->path = '/share/domru/accounts';
    }

    public function getAccounts(): array
    {
        $pathFolder = dirname($this->path);

        if (!is_dir($pathFolder)) {
            mkdir($pathFolder, 0777, true);
        }

        if (!file_exists($this->path)) {
            return [];
        }

        $rows = explode("\n", file_get_contents($this->path));

        $accounts = [];
        foreach ($rows as $row) {
            if (!trim($row)) {
                continue;
            }

            $data = explode('|', $row, 2);
            $accounts[$data[0]] = json_decode($data[1], true);
        }

        return $accounts;
    }

    public function addAccount($accountData): bool
    {
        $accounts = $this->getAccounts();

        $key = $accountData['id'];
        $accounts[$key] = $accountData;

        $data = [];
        foreach ($accounts as $key => $account) {
            $data[] = $key.'|'.json_encode($account, JSON_UNESCAPED_UNICODE);
        }

        return file_put_contents($this->path, implode("\n", $data)) ? true : false;
    }

    public function fetchApi()
    {
        $response = $this->getHttp()->request('GET', 'http://127.0.0.1:8080/api?events=true');
        $content = $response->getBody()->getContents();

        return json_decode($content, true);
    }
}
