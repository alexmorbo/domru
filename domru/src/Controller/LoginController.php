<?php

namespace App\Controller;

use App\Service\AccountService;
use App\Service\Domru;
use App\Traits\HttpClientAwareTrait;
use Exception;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/login", name="login_");
 */
class LoginController extends AbstractController
{
    use HttpClientAwareTrait;

    /**
     * @Route("", name="login", methods={"POST", "GET"})
     *
     * @param Request $request
     * @param Domru   $domru
     *
     * @return Response
     */
    public function login(Request $request, Domru $domru): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            //            $accounts = null;
            $accounts = $domru->getAccounts($request->get('phone'), Domru::LOGIN_BY_PHONE);
            //            if ($request->get('phone')) {
            //            } elseif ($request->get('account')) {
            //                $accounts = $domru->getAccounts($request->get('phone'), Domru::LOGIN_BY_ACCOUNT);
            //            }

            if ($accounts) {
                return $this->render(
                    'login-accounts.html.twig',
                    [
                        'hassioIngress' => $this->hassioIngress,
                        'phone'         => $accounts['phone'],
                        'accounts'      => $accounts['accounts'],
                    ]
                );
            } else {
                $error = 'По указанным данным ничего не найдено';
            }
        }

        return $this->render(
            'login.html.twig',
            [
                'hassioIngress' => $this->hassioIngress,
                'error'         => $error,
            ]
        );
    }

    /**
     * @Route("/address/{phone}/{index}", name="address", methods={"GET"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function selectAddress(Request $request, Domru $domru): Response
    {
        try {
            $smsRequest = $domru->requestSmsConfirmation(
                $request->attributes->get('phone'),
                (int)$request->attributes->get('index')
            );

            if (!$smsRequest) {
                throw new Exception("Internal Error");
            }

            return $this->render(
                'login-sms.html.twig',
                [
                    'hassioIngress' => $this->hassioIngress,
                    'phone'         => $request->attributes->get('phone'),
                    'index'         => (int)$request->attributes->get('index'),
                ]
            );
        } catch (Exception $e) {
            return $this->render(
                'login.html.twig',
                [
                    'hassioIngress' => $this->hassioIngress,
                    'error'         => $e->getResponse()->getBody()->getContents(),
                ]
            );
        }
    }

    /**
     * @Route("/sms/{phone}/{index}", name="sms", methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function sms(Request $request, Domru $domru, AccountService $accountService): Response
    {
        try {
            $accounts = $domru->getAccounts($request->attributes->get('phone'), Domru::LOGIN_BY_PHONE);
            $smsRequest = $domru->requestSmsVerification(
                $request->attributes->get('phone'),
                (int)$request->attributes->get('index'),
                (int)$request->get('sms')
            );

            $accountId = $accounts['accounts'][(int)$request->attributes->get('index')]['accountId'];

            $accountService->addAccount(
                [
                    'id'      => $accountId,
                    'uuid'    => mb_strtoupper(Uuid::uuid4()),
                    'phone'   => $accounts['phone'],
                    'address' => $accounts['accounts'][(int)$request->attributes->get('index')],
                    'data'    => $smsRequest,
                ]
            );

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
                    isset($content['accounts'][$accountId]) &&
                    isset($content['accounts'][$accountId]['subscriberPlaces'])
                ) {
                    $redirect = $this->redirectToRoute('index');
                    if ($this->hassioIngress) {
                        $redirect->setTargetUrl($this->hassioIngress.$redirect->getTargetUrl());
                    }

                    return $redirect;
                } else {
                    if ($checks > 10) {
                        throw new Exception('Internal Error');
                    }
                    $checks++;
                    sleep(2);
                }
            }
        } catch (Exception $e) {
            return $this->render(
                'login-sms.html.twig',
                [
                    'hassioIngress' => $this->hassioIngress,
                    'phone'         => $request->attributes->get('phone'),
                    'index'         => (int)$request->attributes->get('index'),
                    'error'         => $e->getResponse()->getBody()->getContents(),
                ]
            );
        }
    }
}
