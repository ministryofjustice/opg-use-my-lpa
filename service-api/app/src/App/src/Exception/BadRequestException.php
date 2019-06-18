<?php

namespace App\Exception;

use Fig\Http\Message\StatusCodeInterface;
use Throwable;

/**
 * Class BadRequestException
 * @package App\Exception
 */
class BadRequestException extends AbstractApiException
{
    /**
     * @var int
     */
    protected $code = StatusCodeInterface::STATUS_BAD_REQUEST;

    /**
     * BadRequestException constructor.
     *
     * @param string $message
     * @param string $title
     * @param array $additionalData
     * @param Throwable|null $previous
     */
    public function __construct(string $message = null, string $title = 'Bad Request', array $additionalData = [], Throwable $previous = null)
    {
        parent::__construct($message, $title, $additionalData, $previous);
    }
}
