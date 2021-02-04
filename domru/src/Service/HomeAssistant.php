<?php

namespace App\Service;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use Throwable;
use function React\Promise\reject;

class HomeAssistant
{
    use LoggerAwareTrait;

    private ?AsyncRegistry $registry = null;

    public const API_NETWORK_INFO = 'http://supervisor/network/info';

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->debug('Initiate HomeAssistant');
    }

    private function apiError(Exception $e)
    {
        try {
            $error = '[HA] Api error: ['.$e->getMessage().'] '.$e->getResponse()->getBody()->getContents();
            $this->logger->error($error);

            return reject($error);
        } catch (Throwable $e) {
            dd($e);
        }
    }

    public function setupRegistry(AsyncRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function async($haToken)
    {
        $this->logger->debug(__METHOD__.' | Run');

        $this->client = new Browser($this->registry->loop);
        $this->client->get(
            self::API_NETWORK_INFO,
            [
                'Authorization' => sprintf('Bearer %s', $haToken),
            ]
        )
            ->then(
                function (ResponseInterface $response) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    if (!is_array($data) || !isset($data['result']) || $data['result'] !== 'ok') {
                        return reject('Api error [HA]: [HTTP OK] Response json failed');
                    }

                    $this->logger->debug('[HA] Network info fetching complete');
                    $this->registry->haNetwork = $data['data'];
                },
                function (ResponseException $e) {
                    $this->apiError($e);
                }
            );
    }
}
