<?php


namespace Morbo\Domru\Server;


use Exception;
use Morbo\Domru\Domru;
use Morbo\Domru\Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Http\Response;
use React\Promise\PromiseInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use function React\Promise\reject;
use function React\Promise\resolve;

class Server
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Domru
     */
    private $domru;

    /**
     * @var RouteCollection
     */
    private $routes;

    /**
     * @var array
     */
    private $videoStream;

    public function __construct(Domru $domru)
    {
        $this->domru = $domru;
        $this->loop = $domru->getLoop();
        $this->logger = $domru->getLogger();

        $this->setupRoutes();
    }

    private function setupRoutes()
    {
        $this->routes = new RouteCollection();

        /**
         * Main full info
         */
        $this->routes->add(
            'full_info',
            new Route(
                '/',
                [
                    '_controller' => self::class,
                    '_method' => 'fullInfo',
                ]
            )
        );

        /**
         * Door opening
         */
        $this->routes->add(
            'open_door',
            new Route(
                '/open/{placeId}/{accessControlId}',
                [
                    '_controller' => self::class,
                    '_method' => 'openDoor',
                    'placeId' => null,
                    'accessControlId' => null,
                ]
            )
        );

        /**
         * Video snapshot
         */
        $this->routes->add(
            'video_snapshot',
            new Route(
                '/video/snapshot/{placeId}/{accessControlId}',
                [
                    '_controller' => self::class,
                    '_method' => 'videoSnapshot',
                    'placeId' => null,
                    'accessControlId' => null,
                ]
            )
        );

        /**
         * Video stream
         */
        $this->routes->add(
            'video_stream',
            new Route(
                '/video/stream/{cameraId}',
                [
                    '_controller' => self::class,
                    '_method' => 'videoStream',
                    'cameraId' => null,
                ]
            )
        );
    }

    public function run(): PromiseInterface
    {
        try {
            $server = new \React\Http\Server(
                function (ServerRequestInterface $request) {
                    try {
                        $response = $this->handleRequest($request);
                        if ($response instanceof PromiseInterface) {
                            return $response->then(
                                function ($response) {
                                    return $response;
                                },
                                function (ResponseInterface $response) {
                                    return $response;
                                }
                            );
                        }

                        return $response;
                    } catch (ResourceNotFoundException $e) {
                        return $this->error('404 not found', 404);
                    } catch (Exception $e) {
                        return $this->error($e->getMessage(), 500);
                    }
                }
            );

            $server->on(
                'error',
                function (Exception $e) {
                    $this->logger->critical(
                        $e->getMessage(),
                        [
                            'code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]
                    );
                }
            );

            $socket = new \React\Socket\Server('0.0.0.0:8080', $this->loop);
            $server->listen($socket);

            return resolve('Listening on '.str_replace('tcp:', 'http:', $socket->getAddress()));
        } catch (Exception $e) {
            return reject($e->getMessage());
        }
    }

    private function handleRequest(ServerRequestInterface $request)
    {
        $context = new RequestContext(
            '',
            $request->getMethod(),
            $request->getUri()->getHost(),
            $request->getUri()->getScheme(),
            80,
            443,
            $request->getUri()->getPath(),
            $request->getUri()->getQuery()
        );
        $matcher = new UrlMatcher($this->routes, $context);
        $parameters = $matcher->match($request->getUri()->getPath());

        $this->logger->debug('HTTP request run', ['parameters' => $parameters]);

        $result = call_user_func([$parameters['_controller'], $parameters['_method']]);

        if ($result instanceof Response) {
            return $result;
        } elseif ($result instanceof PromiseInterface) {
            return $result->then(
                function ($response) {
                    return resolve($response);
                },
                function ($errorResponse) {
                    return reject($errorResponse);
                }
            );
        } else {
            return $this->error('Internal Error');
        }
    }

    private function json(array $data = [], int $statusCode = 200): Response
    {
        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );
    }

    private function error(string $message, int $statusCode = 400): Response
    {
        return $this->json(['error' => $message], $statusCode);
    }

    private function image(string $imageData, string $mime = 'image/jpeg'): Response
    {
        return new Response(
            200,
            ['Content-Type' => $mime],
            $imageData
        );
    }

    private function memoryConvert($size)
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).' '.$unit[$i];
    }

    private function fullInfo(): Response
    {
        $registry = Registry::getInstance();
        $memory = memory_get_usage(true);

        $body = [
            'accessToken' => $registry->accessToken,
            'memoryHuman' => $this->memoryConvert($memory),
            'memory' => $memory,
            'finances' => $registry->finances,
            'cameras' => $registry->cameras,
            'subscriberPlaces' => $registry->subscriberPlaces,
            'timers' => array_map(
        function ($name, $timerData) {
            /** @var TimerInterface $timer */
            $timer = $timerData['timer'];

            return [
                'name' => $name,
                'initiatedAt' => $timerData['initiatedAt'],
                'interval' => $timer->getInterval(),
            ];
        },
        array_keys($registry->getTimers()),
        $registry->getTimers()
    ),
        ];

        return $this->json($body);
    }

    private function openDoor(int $placeId = null, int $accessControlId = null): PromiseInterface
    {
        return $this->domru->openDoor($placeId, $accessControlId)->then(
            function ($data) {
                return resolve($this->json($data));
            },
            function ($error) {
                return reject($this->error($error));
            }
        );
    }

    private function videoSnapshot(int $placeId = null, int $accessControlId = null): PromiseInterface
    {
        return $this->domru->videoSnapshot($placeId, $accessControlId)->then(
            function ($data) {
                return resolve(
                    $this->image($data['content'], $data['mime'])
                );
            },
            function ($error) {
                return reject($this->error($error));
            }
        );
    }

    private function videoStream(int $cameraId = null): PromiseInterface
    {
        return $this->domru->getCameraStream($cameraId)->then(
            function ($cameraId) {
//                return new Response(
//                    200,
//                    ['Content-Type' => 'video/x-flv'],
//                    Registry::getInstance()->getVideoStream($cameraId)['raw']
//                );
                return $this->json(['url' => $cameraId]);
            },
            function ($error) {
                return reject($this->error($error));
            }
        );
    }
}