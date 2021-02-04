<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

trait HttpClientAwareTrait
{

    private ?ClientInterface $http = null;

    /**
     * @param ClientInterface $http
     *
     * @return self
     */
    public function setHttp(ClientInterface $http): self
    {
        $this->http = $http;

        return $this;
    }

    /**
     * @return ClientInterface
     */
    public function getHttp(): ClientInterface
    {
        return $this->http ??= new Client();
    }

}
