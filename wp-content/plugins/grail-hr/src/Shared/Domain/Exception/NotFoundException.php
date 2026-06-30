<?php
/**
 * Not-found exception for resources hidden from the current user or absent from storage.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Domain\Exception;

final class NotFoundException extends GrailHrException
{
    public function __construct(string $message = 'La ressource demandée est introuvable.')
    {
        parent::__construct($message, 'grail_hr_not_found', 404);
    }
}
