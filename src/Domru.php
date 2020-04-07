<?php

namespace Morbo\Domru;

use Closure;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\ResponseException;
use Morbo\Domru\Exceptions\Exception;
use Morbo\Domru\Server\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;

class Domru
{
    const API_REFRESH_SESSION = 'https://myhome.novotelecom.ru/auth/v2/session/refresh';

    const API_FINANCES = 'https://myhome.novotelecom.ru/rest/v1/subscribers/profiles/finances';

    const API_CAMERAS = 'https://myhome.novotelecom.ru/rest/v1/forpost/cameras';

    const API_CAMERA_GET_STREAM = 'https://myhome.novotelecom.ru/rest/v1/forpost/cameras/%d/video?&LightStream=0';

    const API_SUBSCRIBER_PLACES = 'https://myhome.novotelecom.ru/rest/v1/subscriberplaces';

    const API_OPEN_DOOR = 'https://myhome.novotelecom.ru/rest/v1/places/%d/accesscontrols/%d/actions';

    const API_VIDEO_SNAPSHOT = 'https://myhome.novotelecom.ru/rest/v1/places/%d/accesscontrols/%d/videosnapshots';

    const REFRESH_ACCESS_TOKEN_INTERVAL = 60;

    const REFRESH_FINANCES_INTERVAL = 3600;

    const REFRESH_SUBSCRIBER_PLACES_INTERVAL = 3600;

    const TIMER_INITIATE_RETRY = 10;

    const TIMER_GET_TOKEN = 'getToken';

    const TIMER_GET_FINANCES = 'getFinances';

    const TIMER_GET_CAMERAS = 'getCameras';

    const TIMER_GET_SUBSCRIBER_PLACES = 'getSubscriberPlaces';

    const INITIATED_FINANCES = 'finances';

    const INITIATED_CAMERAS = 'cameras';

    const INITIATED_SUBSCRIBER_PLACES = 'subscriberPlaces';


    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var Browser
     */
    private $client;

    /**
     * @var string
     */
    private $refreshToken;

    /**
     * @var string
     */
    private $operatorId;

    /**
     * @var Closure
     */
    private $apiError;

    /**
     * @var
     */
    private $initiated;

    /**
     * @var bool
     */
    private $needServe = true;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->loop = Factory::create();
        $this->client = new Browser($this->loop);
        $this->refreshToken = $_ENV['REFRESH_TOKEN'] ?? null;
        $this->operatorId = $_ENV['OPERATOR_ID'] ?? 2;
        $this->needServe = $_ENV['SERVE'] ?? true;
        $this->initiated = [];

        Registry::getInstance()->accessToken = $_ENV['ACCESS_TOKEN'] ?? null;

        if ($this->operatorId === null) {
            throw new Exception('Empty operatorId');
        }

        if ($this->refreshToken === null && Registry::getInstance()->accessToken === null) {
            throw new Exception('Empty refresh_token or access_token. You need define one of them');
        }

        $this->apiError = function (ResponseException $e) {
            $error = 'Api error: ['.$e->getMessage().'] '.$e->getResponse()->getBody()->getContents();
            $this->logger->error($error);

            return reject($error);
        };
    }

    public function setupLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function run()
    {
        if ($this->needServe) {
            $this->serve()->then(
                function ($log) {
                    $this->logger->info($log);
                },
                function ($error) {
                    $this->logger->critical($error);
                    die;
                }
            );
        }

        if ($this->refreshToken !== null) {
            $this->getToken()->then(
                function () {
                    $this->watchdog();
                }
            );
        } else {
            $this->watchdog();
        }

        $this->loop->run();
    }

    private function watchdog()
    {
        if (empty($this->initiated[self::INITIATED_FINANCES])) {
            $this->getFinances()->then(
                function () {
                    $this->initiated[self::INITIATED_FINANCES] = time();
                },
                function () {
                    $this->loop->addTimer(
                        self::TIMER_INITIATE_RETRY,
                        function () {
                            $this->watchdog();
                        }
                    );
                }
            );
        }

        if (empty($this->initiated[self::INITIATED_CAMERAS])) {
            $this->getCameras()->then(
                function () {
                    $this->initiated[self::INITIATED_CAMERAS] = time();
                },
                function () {
                    $this->loop->addTimer(
                        self::TIMER_INITIATE_RETRY,
                        function () {
                            $this->watchdog();
                        }
                    );
                }
            );
        }


        if (empty($this->initiated[self::INITIATED_SUBSCRIBER_PLACES])) {
            $this->getSubscriberPlaces()->then(
                function () {
                    $this->initiated[self::INITIATED_SUBSCRIBER_PLACES] = time();
                },
                function () {
                    $this->loop->addTimer(
                        self::TIMER_INITIATE_RETRY,
                        function () {
                            $this->watchdog();
                        }
                    );
                }
            );
        }
    }

    private function serve()
    {
        $server = new Server($this);

        return $server->run();
    }

    private function getToken(): PromiseInterface
    {
        $this->logger->debug('Trying to fetch refresh token');

        return $this->client->get(
            self::API_REFRESH_SESSION,
            [
                'Operator' => $this->operatorId,
                'Bearer' => $this->refreshToken,
            ]
        )->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);
                if (!is_array($data) || empty($data['accessToken'])) {
                    return reject('Api error: [HTTP OK] Response json failed');
                }

                Registry::getInstance()->accessToken = $data['accessToken'];
                $this->logger->debug('Access token refresh success');
                Registry::getInstance()->setTimer(
                    self::TIMER_GET_TOKEN,
                    [
                        'initiatedAt' => time(),
                        'timer' => $this->loop->addTimer(
                            self::REFRESH_ACCESS_TOKEN_INTERVAL,
                            function () {
                                $this->getToken();
                            }
                        ),
                    ]
                );

                return resolve($data['accessToken']);
            },
            $this->apiError
        );
    }

    public function getFinances(): PromiseInterface
    {
        $this->logger->debug('Trying to fetch finances');

        return $this->client->get(
            self::API_FINANCES,
            [
                'Operator' => $this->operatorId,
                'Authorization' => 'Bearer '.Registry::getInstance()->accessToken,
            ]
        )->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);
                Registry::getInstance()->finances = $data;

                $this->logger->debug('Finances fetch success');

                Registry::getInstance()->setTimer(
                    self::TIMER_GET_FINANCES,
                    [
                        'initiatedAt' => time(),
                        'timer' => $this->loop->addTimer(
                            self::REFRESH_FINANCES_INTERVAL,
                            function () {
                                $this->getFinances();
                            }
                        ),
                    ]
                );
            },
            $this->apiError
        );
    }

    public function getCameras(): PromiseInterface
    {
        $this->logger->debug('Trying to fetch cameras');

        return $this->client->get(
            self::API_CAMERAS,
            [
                'Operator' => $this->operatorId,
                'Authorization' => 'Bearer '.Registry::getInstance()->accessToken,
            ]
        )->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);
                Registry::getInstance()->cameras = $data['data'];

                $this->logger->debug('Cameras fetch success');

                Registry::getInstance()->setTimer(
                    self::TIMER_GET_CAMERAS,
                    [
                        'initiatedAt' => time(),
                        'timer' => $this->loop->addTimer(
                            self::REFRESH_FINANCES_INTERVAL,
                            function () {
                                $this->getCameras();
                            }
                        ),
                    ]
                );
            },
            $this->apiError
        );
    }

    public function getSubscriberPlaces(): PromiseInterface
    {
        $this->logger->debug('Trying to fetch subscriber places');

        return $this->client->get(
            self::API_SUBSCRIBER_PLACES,
            [
                'Operator' => $this->operatorId,
                'Authorization' => 'Bearer '.Registry::getInstance()->accessToken,
            ]
        )->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);
                Registry::getInstance()->subscriberPlaces = $data['data'];

                $this->logger->debug('Subscriber places fetch success');

                Registry::getInstance()->setTimer(
                    self::TIMER_GET_SUBSCRIBER_PLACES,
                    [
                        'initiatedAt' => time(),
                        'timer' => $this->loop->addTimer(
                            self::REFRESH_SUBSCRIBER_PLACES_INTERVAL,
                            function () {
                                $this->getSubscriberPlaces();
                            }
                        ),
                    ]
                );
            },
            $this->apiError
        );
    }

    private function getPlaceIdAccessControlId(int $placeId = null, int $accessControlId = null): PromiseInterface
    {
        $registry = Registry::getInstance();

        if (!is_array($registry->subscriberPlaces)) {
            return reject('Subscriber places is empty');
        }

        $place = null;

        foreach ($registry->subscriberPlaces as $subscriberPlace) {
            if ($placeId === null) {
                $placeId = $subscriberPlace['place']['id'];
                $accessControlId = $subscriberPlace['place']['accessControls'][0]['id'];
                $place = $subscriberPlace['place'];
                break;
            } else {
                if ($placeId === $subscriberPlace['place']['id']) {
                    $accessControlId = $subscriberPlace['place']['accessControls'][0]['id'];
                    $place = $subscriberPlace['place'];
                    break;
                }
            }
        }

        return resolve(
            [
                'placeId' => $placeId,
                'accessControlId' => $accessControlId,
                'place' => $place,
            ]
        );
    }

    public function openDoor(int $placeId = null, int $accessControlId = null): PromiseInterface
    {
        return $this->getPlaceIdAccessControlId($placeId, $accessControlId)->then(
            function ($new) use (&$placeId, &$accessControlId) {
                $registry = Registry::getInstance();
                $placeId = $new['placeId'];
                $accessControlId = $new['accessControlId'];

                if ($new['place']['accessControls'][0]['allowOpen'] === false) {
                    return reject('Access control allowVideo disabled');
                }

                $this->logger->debug(
                    'Trying to open door for place',
                    ['placeId' => $placeId, 'accessControlId' => $accessControlId]
                );

                return $this->client->post(
                    sprintf(self::API_OPEN_DOOR, $placeId, $accessControlId),
                    [
                        'Operator' => $this->operatorId,
                        'Content-Type' => 'application/json; charset=UTF-8',
                        'Authorization' => 'Bearer '.$registry->accessToken,
                    ],
                    json_encode(['name' => 'accessControlOpen'])
                )->then(
                    function (ResponseInterface $response) {
                        $data = json_decode($response->getBody()->getContents(), true);
                        if (!is_array($data) || !isset($data['data']['status'])) {
                            return reject('Api error: [HTTP OK] Response json failed');
                        }
                        $this->logger->debug('Door opened');

                        return resolve($data['data']);
                    },
                    $this->apiError
                );
            },
            function ($error) {
                return reject($error);
            }
        );
    }

    public function videoSnapshot(int $placeId = null, int $accessControlId = null): PromiseInterface
    {
        return $this->getPlaceIdAccessControlId($placeId, $accessControlId)->then(
            function ($new) use (&$placeId, &$accessControlId) {
                $registry = Registry::getInstance();
                $placeId = $new['placeId'];
                $accessControlId = $new['accessControlId'];

                if ($new['place']['accessControls'][0]['allowVideo'] === false) {
                    return reject('Access control allowVideo disabled');
                }

                $this->logger->debug(
                    'Trying to get video snapshot',
                    ['placeId' => $placeId, 'accessControlId' => $accessControlId]
                );

                return $this->client->get(
                    sprintf(self::API_VIDEO_SNAPSHOT, $placeId, $accessControlId),
                    [
                        'Operator' => $this->operatorId,
                        'Authorization' => 'Bearer '.$registry->accessToken,
                    ]
                )->then(
                    function (ResponseInterface $response) {
                        if ($response->getHeader('Content-Type')[0] !== 'image/jpeg') {
                            return reject('Api error: [HTTP OK] Response image failed');
                        }
                        $this->logger->debug('Snapshot success');

                        return resolve(
                            [
                                'mime' => 'image/jpeg',
                                'content' => $response->getBody()->getContents(),
                            ]
                        );
                    },
                    $this->apiError
                );
            },
            function ($error) {
                return reject($error);
            }
        );
    }

    public function getCameraStream(int $cameraId = null): PromiseInterface
    {
        $registry = Registry::getInstance();

        if (!count($registry->cameras) || !isset($registry->cameras[0]['id'])) {
            reject('There is no avaiable camera for streaming');
        }

        foreach ($registry->cameras as $camera) {
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
            sprintf(self::API_CAMERA_GET_STREAM, $cameraId),
            [
                'Operator' => $this->operatorId,
                'Authorization' => 'Bearer '.$registry->accessToken,
            ]
        )->then(
            function (ResponseInterface $response) use ($cameraId, $registry) {
                $data = json_decode($response->getBody()->getContents(), true);
                if (!is_array($data) || !is_array($data['data']) || empty($data['data']['URL'])) {
                    return reject('Api error: [HTTP OK] Response json failed');
                }

                return resolve($data['data']['URL']);
            },
            $this->apiError
        );
    }
}