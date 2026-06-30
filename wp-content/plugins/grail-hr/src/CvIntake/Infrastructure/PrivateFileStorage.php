<?php
/**
 * Private temporary file storage. Files are named with GUIDs and never preserve the original filename on disk.
 */

declare(strict_types=1);

namespace GrailHr\CvIntake\Infrastructure;

use GrailHr\Shared\Domain\Exception\GrailHrException;

final class PrivateFileStorage
{
    public function __construct(private readonly string $tmpDir)
    {
    }

    /** @param array<string, mixed> $file */
    public function storeUploadedPdf(array $file): string
    {
        if (!is_dir($this->tmpDir)) {
            wp_mkdir_p($this->tmpDir);
            file_put_contents($this->tmpDir . '/index.php', "<?php\n// Silence is golden.\n", LOCK_EX);
            file_put_contents($this->tmpDir . '/.htaccess', "Deny from all\n", LOCK_EX);
        }

        $target = $this->tmpDir . '/' . wp_generate_uuid4() . '.pdf';

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new GrailHrException('Le fichier PDF n’a pas pu être préparé pour l’analyse.', 'grail_hr_upload_failed', 400);
        }

        return $target;
    }

    public function deleteQuietly(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
