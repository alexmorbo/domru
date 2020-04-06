<?php


namespace Morbo\Domru;

/**
 * @property $accessToken      string
 * @property $finances         array
 * @property $cameras          array
 * @property $subscriberPlaces array
 */
class Registry
{
    /**
     * @var Registry
     */
    private static $instance;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $timers;

    /**
     * @var array
     */
    private $videoStream;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->data = [];
        $this->timers = [];
        $this->videoStream = [];
    }

    public function __set($key, $val)
    {
        $this->data[$key] = $val;
    }

    public function __get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function setTimer($name, $timerData)
    {
        $this->timers[$name] = $timerData;
    }

    public function getTimers(): array
    {
        return $this->timers;
    }

    public function createVideoStream(int $cameraId)
    {
        $this->videoStream[$cameraId] = [
            'createdAt' => time(),
            'raw' => '',
        ];
    }

    public function getVideoStream(int $cameraId = null)
    {
        return $this->videoStream[$cameraId] ?? null;
    }

    public function putRawToVideoStream(int $cameraId, string $raw)
    {
        $this->videoStream[$cameraId]['raw'] .= $raw;
    }

    public function getVideoStreams(): array
    {
        return $this->videoStream;
    }
}