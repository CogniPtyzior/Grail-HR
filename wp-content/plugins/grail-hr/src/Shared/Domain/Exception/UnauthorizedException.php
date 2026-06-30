<?php
/**
 * Authentication exception for missing, expired or invalid bearer sessions.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Domain\Exception;

final class UnauthorizedException extends GrailHrException
{
    public function __construct(string $message = 'Votre session a expiré. Veuillez vous reconnecter.')
    {
        parent::__construct($message, 'grail_hr_unauthorized', 401);
    }
}
