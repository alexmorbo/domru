<?php

namespace App\Controller;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    use LoggerAwareTrait;

    protected ?string $hassioIngress = null;

    public function __construct(RequestStack $requestStack, LoggerInterface $logger)
    {
        $this->hassioIngress = $requestStack->getCurrentRequest()->server->get('HTTP_X_INGRESS_PATH');
        $this->logger = $logger;
    }
}
