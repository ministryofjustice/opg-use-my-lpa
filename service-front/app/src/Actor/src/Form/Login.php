<?php

declare(strict_types=1);

namespace Actor\Form;

use Common\Form\AbstractForm;
use Common\Validator\EmailAddressValidator;
use Mezzio\Csrf\CsrfGuardInterface;
use Laminas\Filter\StringToLower;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\NotEmpty;
use Laminas\Filter\StringTrim;

class Login extends AbstractForm implements InputFilterProviderInterface
{
    const FORM_NAME = 'login';

    /**
     * Error codes
     * @const string
     */
    const INVALID_LOGIN = 'invalidLogin';

    /**
     * Error messages
     * @var array
     */
    protected array $messageTemplates = [
        self::INVALID_LOGIN => 'Email and password combination not recognised. Please try signing in again below or create an account',
        self::NOT_SAME => 'Security validation failed. Please try again.'
    ];



    public function __construct(CsrfGuardInterface $csrfGuard)
    {
        parent::__construct(self::FORM_NAME, $csrfGuard);

        $this->add([
            'name' => 'email',
            'type' => 'Text',
        ]);

        $this->add([
            'name' => 'password',
            'type' => 'Password',
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        return [
            'email' => [
                'required'   => true,
                'filters'    => [
                    [
                        'name' => StringToLower::class,
                    ],
                    [
                        'name' => StringTrim::class,

                    ],
                ],
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages'           => [
                                NotEmpty::IS_EMPTY => 'Enter your email address',
                            ],
                        ],
                    ],
                    [
                        'name'                   => EmailAddressValidator::class,
                        'break_chain_on_failure' => true,
                    ]
                ],
            ],
            'password' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages' => [
                                NotEmpty::IS_EMPTY => 'Enter your password',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
