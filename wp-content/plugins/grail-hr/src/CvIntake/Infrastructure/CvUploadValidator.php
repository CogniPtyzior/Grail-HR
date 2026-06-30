<?php
/**
 * Validates CV upload constraints: PDF only and 5 MB maximum.
 */

declare(strict_types=1);

namespace GrailHr\CvIntake\Infrastructure;

use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Domain\Exception\ValidationException;

final class CvUploadValidator
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /** @param array<string, mixed> $file */
    public function validate(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Le fichier PDF n’a pas pu être reçu correctement.');
        }

        if ((int) ($file['size'] ?? 0) > $this->settings->maxPdfSizeBytes()) {
            throw new ValidationException('Le fichier est trop volumineux. La taille maximale est de 5 Mo.');
        }

        $name = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $checked = wp_check_filetype_and_ext((string) ($file['tmp_name'] ?? ''), $name, ['pdf' => 'application/pdf']);
        $detectedExtension = strtolower((string) ($checked['ext'] ?? ''));
        $detectedType = (string) ($checked['type'] ?? '');

        if ($extension !== 'pdf' || $detectedExtension !== 'pdf' || $detectedType !== 'application/pdf') {
            throw new ValidationException('Format non supporté. Importez un fichier PDF uniquement.');
        }
    }
}
