<?php
/**
 * Converts exceptions into user-friendly REST errors while logging internal context safely.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Infrastructure\Rest;

use GrailHr\Shared\Domain\Exception\GrailHrException;
use GrailHr\Shared\Domain\Exception\ValidationException;
use GrailHr\Shared\Infrastructure\Logging\Logger;
use WP_Error;
use Throwable;

final class RestErrorMapper
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function map(Throwable $exception): WP_Error
    {
        if ($exception instanceof GrailHrException) {
            $data = ['status' => $exception->statusCode()];

            if ($exception instanceof ValidationException) {
                $data['fields'] = $exception->fieldErrors();
            }

            return new WP_Error($exception->publicCode(), $exception->getMessage(), $data);
        }

        $this->logger->error('rest', 'Unhandled REST exception.', ['exception' => $exception::class]);

        return new WP_Error(
            'grail_hr_server_error',
            'Une erreur est survenue. Veuillez réessayer ou contacter l’administrateur du site.',
            ['status' => 500]
        );
    }
}
