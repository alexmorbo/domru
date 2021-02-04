<?php

namespace App\Service;

use React\EventLoop\LoopInterface;

/**
 * @property $state     int
 * @property $accounts  array
 * @property $haNetwork array
 * @property $loop      LoopInterface
 */
class AsyncRegistry
{
    const STATE_START = 0;
    const STATE_READY = 1;
    const STATE_LOOP = 2;

    private static ?self $instance = null;

    private array $data;

    private array $tokens;

    private array $fetchData;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->data = [];
        $this->tokens = [];

        $this->state = self::STATE_START;
    }

    public function all(): array
    {
        $data = $this->data;
        foreach ($data['accounts'] as $account => &$accountData) {
            $cameras = [];
            foreach ($this->fetch('cameras', $account) as $camera) {
                $cameras[$camera['ID']] = $camera;
            }
            $subscriberPlaces = $this->fetch('subscriberPlaces', $account);

            if ($subscriberPlaces) {
                foreach ($subscriberPlaces as &$subscriberPlace) {
                    foreach ($subscriberPlace['place']['accessControls'] as $accessControl) {
                        foreach ($cameras as &$camera) {
                            foreach ($camera['ParentGroups'] as $parentGroup) {
                                if ($parentGroup['ID'] === (int)$accessControl['forpostGroupId']) {
                                    $subscriberPlace['cameraId'] = $camera['ID'];
                                    $camera['isSubscriber'] = $subscriberPlace['id'];
                                }
                            }

                            if (!isset($camera['isSubscriber'])) {
                                $subscriberPlace['additionalCameras'][] = $camera['ID'];
                            }
                        }
                    }
                }

                foreach ($subscriberPlaces as &$subscriberPlace) {
                    $subscriberPlace['additionalCameras'] = false;
                    foreach ($cameras as $camera) {
                        if (!isset($camera['isSubscriber'])) {
                            $subscriberPlace['additionalCameras'][] = $camera['ID'];
                        }
                    }

                    if (is_array($subscriberPlace['additionalCameras'])) {
                        $subscriberPlace['additionalCameras'] = array_unique($subscriberPlace['additionalCameras']);
                    }
                }
            }

            $accountData['finances'] = $this->fetch('finances', $account);
            $accountData['profiles'] = $this->fetch('profiles', $account);
            $accountData['cameras'] = $cameras;
            $accountData['subscriberPlaces'] = $subscriberPlaces;
        }

        unset($data['loop']);

        return array_merge(
            $data,
            [
                'tokens' => $this->tokens,
            ]
        );
    }

    public function __set($key, $val)
    {
        $this->data[$key] = $val;
    }

    public function __get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function accountsUpdate(array $deletedAccounts)
    {
        if ($deletedAccounts) {
            foreach ($deletedAccounts as $account) {
                if (isset($this->fetchData[$account])) {
                    unset($this->fetchData[$account]);
                }
                if (isset($this->tokens[$account])) {
                    unset($this->tokens[$account]);
                }
                if (isset($this->data['lastUpdate'][$account])) {
                    unset($this->data['lastUpdate'][$account]);
                }
            }
        }
    }

    public function update(string $key, string $account, array $data)
    {
        $this->data['lastUpdate'][$account][$key] = time();
        $this->fetchData[$account][$key] = $data;
    }

    public function fetch(string $key, string $account): ?array
    {
        return $this->fetchData[$account][$key] ?? null;
    }

    public function setToken(string $account, string $token)
    {
        $this->tokens[$account] = $token;
    }

    public function getToken(string $account): ?string
    {
        return $this->tokens[$account] ?? null;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }
}
