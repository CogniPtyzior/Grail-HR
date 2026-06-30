<?php
/**
 * Validation exception used when a request or analysis payload is not acceptable.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Domain\Exception;

final class ValidationException extends GrailHrException
{
    /** @param array<string, string> $fieldErrors */
    public function __construct(string $message, private readonly array $fieldErrors = [])
    {
        parent::__construct($message, 'grail_hr_validation_error', 422);
    }

    /** @return array<string, string> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
