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
}