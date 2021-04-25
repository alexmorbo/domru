<?php

namespace App\Controller;

use App\Service\AccountService;
use App\Service\AsyncRegistry;
use App\Service\Domru;
use App\Traits\HttpClientAwareTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RemoveController extends AbstractController
{
    use HttpClientAwareTrait;

    /**
     * @Route("/remove", name="remove_account", methods={"POST"})
     *
     * @param AccountService $accountService
     * @param Request        $request
     *
     * @return Response
     */
    public function login(AccountService $accountService, Request $request): Response
    {
        $error = null;

        if ($request->getMethod() != 'POST') {
            return $this->goToMainPage();
        }

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
        } catch (Exception $e) {
            return $this->goToMainPage();
        }

        $accountId = (int)$request->request->get('accountId');

        if (!in_array($accountId, array_keys($api['accounts']))) {
            return $this->goToMainPage();
        }

        if ($accountService->removeAccount($accountId)) {
            $checks = 0;
            while (true) {
                $response = $this->getHttp()->request(
                    'GET',
                    'http://127.0.0.1/api'
                );
                $content = json_decode($response->getBody()->getContents(), true);
                if (
                    isset($content['accounts']) &&
                    is_array($content['accounts']) &&
                    isset($content['accounts'][$accountId])
                ) {
                    if ($checks > 10) {
                        throw new Exception('Internal Error');
                    }
                    $checks++;
                    sleep(2);
                } else {
                    $redirect = $this->redirectToRoute('index');
                    if ($this->hassioIngress) {
                        $redirect->setTargetUrl($this->hassioIngress.$redirect->getTargetUrl());
                    }

                    return $redirect;
                }
            }
        }

        return $this->goToMainPage();
    }

    private function goToMainPage(): Response
    {
        $redirect = $this->redirectToRoute('index');
        if ($this->hassioIngress) {
            $redirect->setTargetUrl($this->hassioIngress.$redirect->getTargetUrl());
        }

        return $redirect;
    }

}
