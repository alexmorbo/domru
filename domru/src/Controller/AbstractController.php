<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    protected ?string $hassioIngress = null;

    public function __construct(RequestStack $requestStack)
    {
        $this->hassioIngress = $requestStack->getCurrentRequest()->server->get('HTTP_X_INGRESS_PATH');
    }
}
