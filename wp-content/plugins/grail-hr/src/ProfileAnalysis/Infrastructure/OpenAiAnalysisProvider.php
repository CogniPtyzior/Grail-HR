<?php
/**
 * OpenAI adapter. It never logs raw CV text, prompts or full model responses.
 */

declare(strict_types=1);

namespace GrailHr\ProfileAnalysis\Infrastructure;

use GrailHr\ProfileAnalysis\Application\AnalysisNormalizer;
use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Domain\Exception\GrailHrException;
use GrailHr\Shared\Infrastructure\Logging\Logger;

final class OpenAiAnalysisProvider
{
    public function __construct(private readonly SettingsRepository $settings, private readonly Logger $logger)
    {
    }

    public function modelName(): string
    {
        return $this->settings->openAiModel();
    }

    public function promptVersion(): string
    {
        return $this->settings->promptVersion();
    }

    /** @return array<string, mixed> */
    public function analyze(string $cvText): array
    {
        if ($this->settings->openAiApiKey() === '') {
            throw new GrailHrException(
                'L’analyse IA n’est pas configurée. Contactez l’administrateur du site.',
                'grail_hr_openai_not_configured',
                503
            );
        }

        $body = [
            'model' => $this->settings->openAiModel(),
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => "Analyse ce CV et retourne uniquement le JSON demandé.\n\n" . $cvText],
            ],
            'response_format' => $this->responseFormat(),
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->settings->openAiApiKey(),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('openai', 'OpenAI request failed.', ['code' => $response->get_error_code()]);
            throw new GrailHrException('L’analyse IA n’est pas disponible pour le moment.', 'grail_hr_openai_unavailable', 503);
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            $this->logger->error('openai', 'OpenAI returned non-success status.', ['status' => $status]);
            throw new GrailHrException('L’analyse IA n’est pas disponible pour le moment.', 'grail_hr_openai_unavailable', 503);
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $content = (string) ($payload['choices'][0]['message']['content'] ?? '');
        $analysis = json_decode($content, true);

        if (!is_array($analysis)) {
            $this->logger->warning('openai', 'OpenAI response was not valid analysis JSON.', [
                'model' => $this->settings->openAiModel(),
                'finish_reason' => (string) ($payload['choices'][0]['finish_reason'] ?? ''),
                'response_chars' => mb_strlen($content),
            ]);
            throw new GrailHrException('Le résultat de l’analyse est invalide.', 'grail_hr_invalid_analysis_json', 422);
        }

        return $analysis;
    }

    /** @return array<string, mixed> */
    private function responseFormat(): array
    {
        $schemaPath = GRAIL_HR_PLUGIN_DIR . 'resources/schemas/cv-analysis-v1.json';
        $schema = is_readable($schemaPath) ? json_decode((string) file_get_contents($schemaPath), true) : null;

        if (!is_array($schema)) {
            return ['type' => 'json_object'];
        }

        unset($schema['$schema'], $schema['title']);

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'grail_hr_cv_analysis_v1',
                'strict' => true,
                'schema' => $schema,
            ],
        ];
    }

    private function systemPrompt(): string
    {
        $normalizer = new AnalysisNormalizer();

        return "Tu es un assistant RH. Retourne uniquement un JSON strict compatible avec cette structure : "
            . wp_json_encode($normalizer->emptyAnalysis())
            . ". Style concis, professionnel, pas d'invention. Utilise low/medium/high pour les niveaux de confiance.";
    }
}
