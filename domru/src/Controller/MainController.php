<?php

namespace App\Controller;

use App\Service\AccountService;
use App\Service\AsyncRegistry;
use App\Traits\HttpClientAwareTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    use HttpClientAwareTrait;

    /**
     * @Route("/", name="index")
     */
    public function index(AccountService $accountService): Response
    {
        $api = $error = null;

        try {
            $api = $accountService->fetchApi();

            if (!$api || !is_array($api)) {
                throw new Exception('Api daemon error, try again later');
            }

            if ($api['state'] < AsyncRegistry::STATE_LOOP) {
                throw new Exception('Api not ready, please try a little bit later');
            }

            if (!count($api['accounts'])) {
                $redirect = $this->redirectToRoute('login_login');
                if ($this->hassioIngress) {
                    $redirect->setTargetUrl($this->hassioIngress.$redirect->getTargetUrl());
                }

                return $redirect;
            }
        } catch (GuzzleException $e) {
            $error = 'Api недоступно';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $hostIp = 'host-ip';
        if (isset($api['haNetwork']['interfaces'][0]['ipv4']['address'][0])) {
            $hostIp = explode('/', $api['haNetwork']['interfaces'][0]['ipv4']['address'][0])[0];
        }

        return $this->render(
            'home.html.twig',
            [
                'hassioIngress' => $this->hassioIngress,
                'api'           => $api,
                'hostIp'        => $hostIp,
                'validEvents'   => [
                    'accessControlCallMissed',
                    'accessControlCallAccepted',
                ],
                'error' => $error,
            ]
        );
    }
}
