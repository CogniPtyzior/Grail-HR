<?php
/**
 * Base exception carrying a safe public message and an internal error code.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Domain\Exception;

use RuntimeException;

class GrailHrException extends RuntimeException
{
    public function __construct(
        string $publicMessage,
        private readonly string $publicCode = 'grail_hr_error',
        int $statusCode = 400,
        ?RuntimeException $previous = null
    ) {
        parent::__construct($publicMessage, $statusCode, $previous);
    }

    public function publicCode(): string
    {
        return $this->publicCode;
    }

    public function statusCode(): int
    {
        return $this->getCode() > 0 ? $this->getCode() : 400;
    }
}
