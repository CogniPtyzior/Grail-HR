<?php
/**
 * Permission exception for inactive users, missing tokens or insufficient capabilities.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Domain\Exception;

final class PermissionDeniedException extends GrailHrException
{
    public function __construct(string $message = 'Vous ne disposez pas des droits nécessaires.')
    {
        parent::__construct($message, 'grail_hr_forbidden', 403);
    }
}
