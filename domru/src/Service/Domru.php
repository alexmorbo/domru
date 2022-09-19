<?php

namespace App\Service;

use App\Traits\HttpClientAwareTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class Domru
{
    use HttpClientAwareTrait;
    use LoggerAwareTrait;

    private Cache $cache;

    private AccountService $accountService;

    private Browser $client;

    private ?AsyncRegistry $registry = null;

    private ?string $asyncUserAgent = 'iPhone13,3 | iOS 14.7.1 | erth | 6.9.3 (build 3) | _ | %s | %s';

    public const LOGIN_BY_PHONE = 'phone';

    public const LOGIN_BY_ACCOUNT = 'account';

    public const API_AUTH_LOGIN = 'https://api-mh.ertelecom.ru/auth/v2/login/%s';

    public const API_AUTH_CONFIRMATION = 'https://api-mh.ertelecom.ru/auth/v2/confirmation/%s';

    public const API_AUTH_CONFIRMATION_SMS = 'https://api-mh.ertelecom.ru/auth/v2/auth/%s/confirmation';

    public const API_USER_AGENT = 'myHomeErth/3 CFNetwork/1240.0.4 Darwin/20.6.0';

    public const API_REFRESH_SESSION = 'https://api-mh.ertelecom.ru/auth/v2/session/refresh';

    public const API_PROFILES = 'https://api-mh.ertelecom.ru/rest/v1/subscribers/profiles';

    public const API_FINANCES = 'https://api-mh.ertelecom.ru/rest/v1/subscribers/profiles/finances';

    public const API_CAMERAS = 'https://api-mh.ertelecom.ru/rest/v1/forpost/cameras';

    public const API_SUBSCRIBER_PLACES = 'https://api-mh.ertelecom.ru/rest/v1/subscriberplaces';

    public const API_OPEN_DOOR = 'https://api-mh.ertelecom.ru/rest/v1/places/%d/accesscontrols/%d/actions';

    public const API_CAMERA_GET_STREAM = 'https://api-mh.ertelecom.ru/rest/v1/forpost/cameras/%d/video?';

    public const API_CAMERA_GET_SNAPSHOT = 'https://api-mh.ertelecom.ru/rest/v1/forpost/cameras/%d/snapshots?';

    public const API_EVENTS = 'https://api-mh.ertelecom.ru/rest/v1/places/%d/events?allowExtentedActions=true';

    public const REFRESH_ACCESS_TOKEN_INTERVAL = 60;

    public const REFRESH_FINANCES_INTERVAL = 3600;

    public const REFRESH_PROFILES_INTERVAL = 3600;

    public const REFRESH_SUBSCRIBER_PLACES_INTERVAL = 3600;

    public const REFRESH_CAMERAS_INTERVAL = 3600;

    public const REFRESH_EVENTS_INTERVAL = 300;

    public function __construct(LoggerInterface $logger, Cache $cache, AccountService $accountService)
    {
        $this->logger = $logger;
        $this->logger->debug('Initiate Domru');
        $this->cache = $cache;
        $this->accountService = $accountService;
    }

    private function apiError(string $account, \Exception $e)
    {
        try {
            $error = '['.$account.'] Api error: ['.$e->getMessage().'] Contents: '.$e->getResponse()->getBody()->getContents();
            $this->logger->error($error);

            return reject($error);
        } catch (\Throwable $e) {
            dd($e);
        }
    }

    public function getAccounts(string $phone, string $loginType): ?array
    {
        $data = $this->cache->get('accounts');
        if (!$data) {
            $response = $this->getHttp()->request(
                'GET',
                sprintf(self::API_AUTH_LOGIN, $phone),
                [
                    'headers' => [
                        'User-Agent' => self::API_USER_AGENT,
                    ],
                ]
            );
            $content = $response->getBody()->getContents();

            $this->logger->debug(__METHOD__.' | Headers', $response->getHeaders());
            $this->logger->debug(__METHOD__.' | Content', [$content]);

            if ($loginType === self::LOGIN_BY_PHONE) {
                $accounts = json_decode($content, true);
            } else {
                /**
                 * @TODO Auth by account id + pass
                 */
                $accounts = null;
            }
            if ($accounts) {
                $data = [
                    'phone'    => $phone,
                    'accounts' => $accounts,
                ];
                $this->cache->set('accounts', $data, 600);
            }
        }

        return $data;
    }

    public function requestSmsConfirmation(string $phone, int $index): bool
    {
        $accounts = $this->getAccounts($phone, Domru::LOGIN_BY_PHONE);
        $headers = [
            'Host'            => parse_url(self::API_AUTH_CONFIRMATION, PHP_URL_HOST),
            'Content-Type'    => 'application/json',
            'User-Agent'      => self::API_USER_AGENT,
            'Connection'      => 'keep-alive',
            'Accept'          => '*/*',
            'Accept-Language' => 'en-us',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Authorization'   => '',
        ];
        $data = [
            'accountId'    => $accounts['accounts'][$index]['accountId'],
            'address'      => $accounts['accounts'][$index]['address'],
            'operatorId'   => $accounts['accounts'][$index]['operatorId'],
            'placeId'      => $accounts['accounts'][$index]['placeId'],
            'subscriberId' => $accounts['accounts'][$index]['subscriberId'],
        ];

        $this->logger->debug(__METHOD__.' | Send', ['headers' => $headers, 'data' => $data]);
        $response = $this->getHttp()->request(
            'POST',
            sprintf(self::API_AUTH_CONFIRMATION, $phone),
            [
                'headers' => $headers,
                'body'    => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]
        );

        $content = $response->getBody()->getContents();
        $this->logger->debug(__METHOD__.' | Headers', $response->getHeaders());
        $this->logger->debug(__METHOD__.' | Content', [$content]);

        return true;
    }

    public function requestSmsVerification(string $phone, int $index, int $code): ?array
    {
        $accounts = $this->getAccounts($phone, Domru::LOGIN_BY_PHONE);
        $address = $accounts['accounts'][$index];
        $headers = [
            'Host'            => parse_url(self::API_AUTH_CONFIRMATION_SMS, PHP_URL_HOST),
            'Content-Type'    => 'application/json',
            'User-Agent'      => self::API_USER_AGENT,
            'Connection'      => 'keep-alive',
            'Accept'          => '*/*',
            'Accept-Language' => 'en-us',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Authorization'   => '',
        ];
        $data = [
            'accountId'    => $address['accountId'],
            'confirm1'     => (string)$code,
            'login'        => $phone,
            'operatorId'   => (int)$address['operatorId'],
            'subscriberId' => $address['subscriberId'],
        ];

        $this->logger->debug(__METHOD__.' | Send', ['headers' => $headers, 'data' => $data]);
        $response = $this->getHttp()->request(
            'POST',
            sprintf(self::API_AUTH_CONFIRMATION_SMS, $phone),
            [
                'headers' => $headers,
                'body'    => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]
        );

        $content = $response->getBody()->getContents();
        $this->logger->debug(__METHOD__.' | Headers', $response->getHeaders());
        $this->logger->debug(__METHOD__.' | Content', [$content]);

        $this->cache->clear();

        return json_decode($content, true);
    }

    public function async()
    {
        $this->client = new Browser($this->registry->loop);

        /**
         * Accounts cache
         */
        $this->registry->loop->addPeriodicTimer(
            5,
            function () {
                $savedAccounts = array_keys($this->registry->accounts);
                $this->registry->accounts = $this->accountService->getAccounts();
                $newAcounts = array_diff(array_keys($this->registry->accounts), $savedAccounts);
                $deletedAccounts = array_diff($savedAccounts, array_keys($this->registry->accounts));

                $this->registry->accountsUpdate($deletedAccounts);
                if ($newAcounts) {
                    $this->refreshTokens()
                        ->then(
                            function () use ($newAcounts) {
                                $promises = [];
                                foreach ($newAcounts as $account) {
                                    $promises[] = $this->fetchData(self::API_SUBSCRIBER_PLACES, 'subscriberPlaces', $account);
                                    $promises[] = $this->fetchData(self::API_FINANCES, 'finances', $account);
                                    $promises[] = $this->fetchData(self::API_PROFILES, 'profiles', $account);
                                    $promises[] = $this->fetchData(self::API_CAMERAS, 'cameras', $account);
                                }

                                return all($promises);
                            }
                        );
                }
            }
        );

        $this->refreshTokens()
            ->then(
                function () {
                    $this->registry->state = AsyncRegistry::STATE_READY;
                    $this->registry->loop->addPeriodicTimer(
                        self::REFRESH_ACCESS_TOKEN_INTERVAL,
                        fn() => $this->refreshTokens()
                    );
                }
            )
            ->then(fn() => $this->watchdog());
    }

    public function setupRegistry(AsyncRegistry $registry)
    {
        $this->registry = $registry;
        $this->registry->accounts = $this->accountService->getAccounts();
    }

    private function watchdog()
    {
        $promises = [
            $this->fetchData(self::API_FINANCES, 'finances')->then(
                function () {
                    $this->logger->debug('Watchdog for finances complete');
                    $this->registry->loop->addPeriodicTimer(
                        self::REFRESH_FINANCES_INTERVAL,
                        fn() => $this->fetchData(self::API_FINANCES, 'finances')
                    );
                }
            ),
            $this->fetchData(self::API_PROFILES, 'profiles')->then(
                function () {
                    $this->logger->debug('Watchdog for profiles complete');
                    $this->registry->loop->addPeriodicTimer(
                        self::REFRESH_PROFILES_INTERVAL,
                        fn() => $this->fetchData(self::API_PROFILES, 'profiles')
                    );
                }
            ),
            $this->fetchData(self::API_CAMERAS, 'cameras')->then(
                function () {
                    $this->logger->debug('Watchdog for cameras complete');
                    $this->registry->loop->addPeriodicTimer(
                        self::REFRESH_CAMERAS_INTERVAL,
                        fn() => $this->fetchData(self::API_CAMERAS, 'cameras')
                    );
                }
            ),
            $this->fetchData(self::API_SUBSCRIBER_PLACES, 'subscriberPlaces')->then(
                function () {
                    $this->logger->debug('Watchdog for subscriberPlaces complete');
                    $this->registry->loop->addPeriodicTimer(
                        self::REFRESH_SUBSCRIBER_PLACES_INTERVAL,
                        fn() => $this->fetchData(self::API_SUBSCRIBER_PLACES, 'subscriberPlaces')
                    );
                }
            ),
        ];

        return all($promises)
            ->then(fn() => $this->registry->state = AsyncRegistry::STATE_LOOP);
    }

    private function refreshTokens(): PromiseInterface
    {
        $promises = [];
        foreach ($this->registry->accounts as $account => $accountData) {
            $promises[$account] = $this->client->get(
                self::API_REFRESH_SESSION,
                [
                    'User-Agent' => sprintf($this->asyncUserAgent, $accountData['data']['operatorId'], $accountData['uuid']),
                    'Operator'   => $accountData['data']['operatorId'],
                    'Bearer'     => $accountData['data']['refreshToken'],
                ]
            )
                ->then(
                    function (ResponseInterface $response) use ($account, $accountData) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        if (!is_array($data) || empty($data['accessToken'])) {
                            return reject('Api error ['.$account.']: [HTTP OK] Response json failed');
                        }

                        $this->logger->debug('['.$account.'] Access token refresh success');

                        return resolve($data['accessToken']);
                    },
                    function (ResponseException $e) use ($account) {
                        $this->apiError($account, $e);

                        return resolve(false);
                    }
                );
        }

        return all($promises)->then(
            function (array $refreshedAccountsTokens) {
                foreach ($refreshedAccountsTokens as $account => $token) {
                    if ($token) {
                        $this->registry->setToken($account, $token);
                    }
                }
            }
        );
    }

    private function fetchData(string $apiUrl, string $storageKey, string $forcedAccount = null): PromiseInterface
    {
        $promises = [];
        $tokensForFetch = $this->registry->getTokens();

        if ($forcedAccount && isset($tokensForFetch[$forcedAccount])) {
            $tokensForFetch = [
                $forcedAccount => $tokensForFetch[$forcedAccount],
            ];
        }

        $this->logger->debug('Fetching '.$storageKey.' for accounts', array_keys($tokensForFetch));

        foreach ($tokensForFetch as $account => $token) {
            $this->logger->debug('['.$account.'] Trying to fetch: '.$storageKey);
            $promises[$account] = $this->client->get(
                $apiUrl,
                [
                    'Operator'      => $this->registry->accounts[$account]['data']['operatorId'],
                    'User-Agent'    => sprintf(
                        $this->asyncUserAgent,
                        $this->registry->accounts['data']['operatorId'],
                        $this->registry->accounts[$account]['uuid']
                    ),
                    'Authorization' => 'Bearer '.$token,
                ]
            )->then(
                function (ResponseInterface $response) use ($account, $storageKey, $apiUrl) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    $this->logger->debug('['.$account.'] Fetching success: '.$storageKey);

                    return resolve($data);
                },
                function (ResponseException $e) use ($account) {
                    $this->apiError($account, $e);

                    return resolve(false);
                }
            );
        }

        return all($promises)->then(
            function (array $refreshedAccountsTokens) use ($storageKey) {
                foreach ($refreshedAccountsTokens as $account => $data) {
                    if ($data) {
                        if (isset($data['data'])) {
                            $this->registry->update($storageKey, $account, $data['data']);
                        } else {
                            $this->registry->update($storageKey, $account, $data);
                        }
                    }
                }
            }
        );
    }

    private function getPlaceIdAccessControlId(string $account, int $cameraId): PromiseInterface
    {
        $all = $this->registry->all();
        $accountData = $all['accounts'][$account];
        $subscriberPlaces = $accountData['subscriberPlaces'] ?? null;
        if (!is_array($subscriberPlaces)) {
            return reject('Subscriber places is empty');
        }

        $useAccessControl = $placeId = $accessControlId = null;

        foreach ($subscriberPlaces as $subscriberPlace) {
            foreach ($subscriberPlace['place']['accessControls'] as $accessControl) {
                if (isset($accessControl['cameraId']) && $accessControl['cameraId'] === $cameraId) {
                    $placeId = $subscriberPlace['place']['id'];
                    $accessControlId = $accessControl['id'];
                    $useAccessControl = $subscriberPlace['place'];
                    break 2;
                }
            }
        }

        if (!$placeId || !$accessControlId || !$useAccessControl) {
            return reject('Wrong parameters');
        }

        return resolve(
            [
                'placeId'         => $placeId,
                'accessControlId' => $accessControlId,
                'accessControl'   => $useAccessControl,
            ]
        );
    }

    private function getPlaceId(string $account, int $placeId = null): PromiseInterface
    {
        $subscriberPlaces = $this->registry->fetch('subscriberPlaces', $account);
        if (!is_array($subscriberPlaces)) {
            return reject('Subscriber places is empty');
        }

        $place = null;

        foreach ($subscriberPlaces as $subscriberPlace) {
            if ($placeId === null) {
                $placeId = $subscriberPlace['place']['id'];
                $place = $subscriberPlace['place'];
                break;
            } else {
                if ($placeId === $subscriberPlace['place']['id']) {
                    $place = $subscriberPlace['place'];
                    break;
                }
            }
        }

        if (!$placeId || !$place) {
            return reject('Wrong parameters');
        }

        return resolve(
            [
                'placeId' => $placeId,
                'place'   => $place,
            ]
        );
    }

    public function openDoor(string $account, int $cameraId): PromiseInterface
    {
        if ($this->registry->state !== AsyncRegistry::STATE_LOOP) {
            return reject('Api not ready');
        }

        return $this->getPlaceIdAccessControlId($account, $cameraId)
            ->then(
                function ($use) use ($account) {
                    if ($use['accessControl']['allowOpen'] === false) {
                        return reject('Access control allowOpen disabled');
                    }

                    $this->logger->debug(
                        'Trying to open door for place',
                        ['placeId' => $use['placeId'], 'accessControlId' => $use['accessControlId']]
                    );

                    return $this->client->post(
                        sprintf(self::API_OPEN_DOOR, $use['placeId'], $use['accessControlId']),
                        [
                            'Operator'      => $this->registry->accounts[$account]['data']['operatorId'],
                            'Content-Type'  => 'application/json',
                            'User-Agent'    => sprintf(
                                $this->asyncUserAgent,
                                $this->registry->accounts['data']['operatorId'],
                                $this->registry->accounts[$account]['uuid']
                            ),
                            'Authorization' => 'Bearer '.$this->registry->getToken($account),
                        ],
                        json_encode(['name' => 'accessControlOpen'])
                    )->then(
                        function (ResponseInterface $response) use ($account) {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (!is_array($data) || !isset($data['data']['status'])) {
                                return reject('['.$account.'] Api error: [HTTP OK] Response json failed');
                            }
                            $this->logger->debug('Door opened');

                            return resolve($data['data']);
                        },
                        function (ResponseException $e) use ($account) {
                            $this->apiError($account, $e);

                            return resolve(
                                [
                                    'status'       => false,
                                    'errorCode'    => $e->getCode(),
                                    'errorMessage' => $e->getMessage(),
                                ]
                            );
                        }
                    );
                },
                function ($error) {
                    return reject($error);
                }
            );
    }

    public function cameraSnapshot(string $account, int $cameraId = null): PromiseInterface
    {
        if ($this->registry->state !== AsyncRegistry::STATE_LOOP) {
            return reject('Api not ready');
        }

        $cameras = $this->registry->fetch('cameras', $account);

        if (!count($cameras) || !isset($cameras[0]['ID'])) {
            return reject('There is no available camera for streaming');
        }

        $cameraToUse = null;
        foreach ($cameras as $camera) {
            if ($cameraId && (int)$camera['ID'] === $cameraId) {
                // Необходимая камера
                break;
            }
            if ($cameraId === null) {
                $cameraId = (int)$camera['ID'];
                break;
            }
        }

        return $this->client->get(
            sprintf(self::API_CAMERA_GET_SNAPSHOT, $cameraId),
            [
                'Operator'      => $this->registry->accounts[$account]['data']['operatorId'],
                'Content-Type'  => 'application/json',
                'User-Agent'    => sprintf(
                    $this->asyncUserAgent,
                    $this->registry->accounts['data']['operatorId'],
                    $this->registry->accounts[$account]['uuid']
                ),
                'Authorization' => 'Bearer '.$this->registry->getToken($account),
            ]
        )->then(
            function (ResponseInterface $response) use ($account) {
                if ($response->getHeader('Content-Type')[0] !== 'image/jpeg') {
                    $this->logger->warning('Bad response headers from Domru');
                }

                $this->logger->debug('Snapshot success');

                return resolve(
                    [
                        'mime'    => 'image/jpeg',
                        'content' => $response->getBody()->getContents(),
                    ]
                );
            },
            function (ResponseException $e) use ($account) {
                $this->apiError($account, $e);

                return resolve(
                    [
                        'status'       => false,
                        'errorCode'    => $e->getCode(),
                        'errorMessage' => $e->getMessage(),
                    ]
                );
            }
        );
    }

    public function cameraStream(string $account, int $cameraId = null, int $timestamp = null): PromiseInterface
    {
        if ($this->registry->state !== AsyncRegistry::STATE_LOOP) {
            return reject('Api not ready');
        }

        $cameras = $this->registry->fetch('cameras', $account);

        if (!count($cameras) || !isset($cameras[0]['ID'])) {
            return reject('There is no available camera for streaming');
        }

        $cameraToUse = null;
        foreach ($cameras as $camera) {
            if ($cameraId && (int)$camera['ID'] === $cameraId) {
                // Необходимая камера
                $cameraToUse = $camera;
                break;
            }
            if ($cameraId === null) {
                $cameraId = (int)$camera['ID'];
                $cameraToUse = $camera;
                break;
            }
        }

        $url = sprintf(self::API_CAMERA_GET_STREAM, $cameraId);
        $httpQuery = [
            'LightStream' => 0,
        ];
        if ($timestamp) {
            $httpQuery['TS'] = $timestamp;
            $httpQuery['TZ'] = $cameraToUse['TimeZone'];
        }

        return $this->client->get(
            $url.http_build_query($httpQuery),
            [
                'Operator'      => $this->registry->accounts[$account]['data']['operatorId'],
                'User-Agent'    => sprintf(
                    $this->asyncUserAgent,
                    $this->registry->accounts['data']['operatorId'],
                    $this->registry->accounts[$account]['uuid']
                ),
                'Authorization' => 'Bearer '.$this->registry->getToken($account),
            ]
        )->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);

                if (!is_array($data) || !is_array($data['data']) || empty($data['data']['URL'])) {
                    return reject('Api error: [HTTP OK] Response json failed');
                }

                return resolve($data['data']['URL']);
            },
            function (ResponseException $e) use ($account) {
                $this->apiError($account, $e);

                return resolve(
                    [
                        'status'       => false,
                        'errorCode'    => $e->getCode(),
                        'errorMessage' => $e->getMessage(),
                    ]
                );
            }
        );
    }

    public function events(string $account, int $placeId = null, int $limit = null): PromiseInterface
    {
        if ($this->registry->state !== AsyncRegistry::STATE_LOOP) {
            return reject('Api not ready');
        }

        return $this->getPlaceId($account, $placeId)
            ->then(
                function ($use) use ($account, $limit) {
                    $this->logger->debug(
                        'Trying to fetch events for place',
                        ['placeId' => $use['placeId']]
                    );

                    return $this->client->get(
                        sprintf(self::API_EVENTS, $use['placeId']),
                        [
                            'Operator'      => $this->registry->accounts[$account]['data']['operatorId'],
                            'Content-Type'  => 'application/json',
                            'User-Agent'    => sprintf(
                                $this->asyncUserAgent,
                                $this->registry->accounts['data']['operatorId'],
                                $this->registry->accounts[$account]['uuid']
                            ),
                            'Authorization' => 'Bearer '.$this->registry->getToken($account),
                        ]
                    )->then(
                        function (ResponseInterface $response) use ($account, $limit) {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (!is_array($data) || !isset($data['data'])) {
                                return reject('['.$account.'] Api error: [HTTP OK] Response json failed');
                            };

                            if ($limit) {
                                $returnData = [];
                                foreach ($data['data'] as $i => $row) {
                                    if ($i >= $limit) {
                                        break;
                                    }

                                    $returnData[] = $row;
                                }

                                return resolve($returnData);
                            } else {
                                return resolve($data['data']);
                            }
                        },
                        function (ResponseException $e) use ($account) {
                            $this->apiError($account, $e);

                            return resolve(
                                [
                                    'status'       => false,
                                    'errorCode'    => $e->getCode(),
                                    'errorMessage' => $e->getMessage(),
                                ]
                            );
                        }
                    );
                },
                function ($error) {
                    return reject($error);
                }
            );
    }
}
