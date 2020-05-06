<?php

declare(strict_types=1);

namespace Actor\Handler;

use Actor\Form\ChangeEmail;
use App\Exception\ForbiddenException;
use Common\Exception\ApiException;
use Common\Handler\AbstractHandler;
use Common\Handler\CsrfGuardAware;
use Common\Handler\Traits\CsrfGuard;
use Common\Handler\Traits\User;
use Common\Handler\UserAware;
use Common\Service\Email\EmailClient;
use Common\Service\User\UserService;
use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ChangeEmailHandler extends AbstractHandler implements CsrfGuardAware, UserAware
{
    use CsrfGuard;
    use User;

    /** @var UserService */
    private $userService;

    /** @var EmailClient */
    private $emailClient;

    /** @var ServerUrlHelper */
    private $serverUrlHelper;

    /**
     * ChangeEmailHandler constructor.
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
        EmailClient $emailClient,
        AuthenticationInterface $authenticator,
        ServerUrlHelper $serverUrlHelper
    ) {
        parent::__construct($renderer, $urlHelper);

        $this->userService = $userService;
        $this->emailClient = $emailClient;
        $this->serverUrlHelper = $serverUrlHelper;

        $this->setAuthenticator($authenticator);
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $form = new ChangeEmail($this->getCsrfGuard($request));

        $user = $this->getUser($request);

        if ($request->getMethod() === 'POST') {
            $form->setData($request->getParsedBody());

            if ($form->isValid()) {
                $formData = $form->getData();

                $newEmail = $formData['new_email_address'];
                $password = $formData['current_password'];

                if ($user->getDetails()['Email'] === $newEmail) {
                    $form->addErrorMessage(ChangeEmail::INVALID_EMAIL, 'new_email_address');
                }

                try {
                    $this->userService->changeEmail($user->getIdentity(), $newEmail, $password);
                } catch (ApiException $ex)  {
                    if ($ex->getCode() === StatusCodeInterface::STATUS_FORBIDDEN) {
                        $form->addErrorMessage(ChangeEmail::INVALID_PASSWORD, 'current_password');
                    }
                }
            }
        }

        return new HtmlResponse($this->renderer->render('actor::change-email', [
            'form' => $form->prepare(),
            'user' => $user
        ]));
    }
}
