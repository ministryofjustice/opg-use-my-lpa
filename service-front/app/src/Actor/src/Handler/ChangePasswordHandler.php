<?php

declare(strict_types=1);

namespace Actor\Handler;

use Actor\Form\PasswordChange;
use Common\Exception\ApiException;
use Common\Handler\AbstractHandler;
use Common\Handler\CsrfGuardAware;
use Common\Handler\Traits\CsrfGuard;
use Common\Handler\Traits\User;
use Common\Handler\UserAware;
use Common\Service\User\UserService;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Authentication\AuthenticationInterface;
use Zend\Expressive\Helper\ServerUrlHelper;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Expressive\Template\TemplateRendererInterface;

/**
 * Class ChangePasswordHandler
 *
 * @package Actor\Handler
 * @codeCoverageIgnore
 */
class ChangePasswordHandler extends AbstractHandler implements CsrfGuardAware, UserAware
{
    use CsrfGuard;
    use User;

    /** @var UserService */
    private $userService;

    /** @var ServerUrlHelper */
    private $serverUrlHelper;

    /**
     * PasswordResetPageHandler constructor.
     *
     * @codeCoverageIgnore
     *
     * @param TemplateRendererInterface $renderer
     * @param UrlHelper $urlHelper
     * @param UserService $userService
     * @param AuthenticationInterface $authenticator
     * @param ServerUrlHelper $serverUrlHelper
     */
    public function __construct(
        TemplateRendererInterface $renderer,
        UrlHelper $urlHelper,
        UserService $userService,
        AuthenticationInterface $authenticator,
        ServerUrlHelper $serverUrlHelper
    ) {
        parent::__construct($renderer, $urlHelper);

        $this->userService = $userService;
        $this->serverUrlHelper = $serverUrlHelper;

        $this->setAuthenticator($authenticator);
    }


    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $form = new PasswordChange($this->getCsrfGuard($request));

        $user = $this->getUser($request);

        if ($request->getMethod() === 'POST') {
            $form->setData($request->getParsedBody());

            if ($form->isValid()) {
                $data = $form->getData();

                try {
                    $this->userService->changePassword($user->getIdentity(), $data['current_password'], $data['new_password']);

                    //  Redirect to the dashboard screen with success flash message
                    return $this->redirectToRoute('your-details');
                } catch (ApiException $e) {
                    if ($e->getCode() === StatusCodeInterface::STATUS_FORBIDDEN) {
                        $form->addErrorMessage(PasswordChange::INVALID_PASSWORD, 'current_password');
                    }
                }
            }
        }

        return new HtmlResponse($this->renderer->render('actor::password-change', [
            'form' => $form->prepare()
        ]));
    }
}