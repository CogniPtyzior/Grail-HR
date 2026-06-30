<?php
/**
 * Central repository for plugin options. It keeps option keys stable and hides WordPress option details from services.
 */

declare(strict_types=1);

namespace GrailHr\Settings\Infrastructure;

final class SettingsRepository
{
    private const OPTION = 'grail_hr_settings';

    /** @return array<string, mixed> */
    public function all(): array
    {
        $defaults = $this->defaults();
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
    }

    public function installDefaults(): void
    {
        if (get_option(self::OPTION, null) === null) {
            add_option(self::OPTION, $this->defaults(), '', false);
        }
    }

    public function openAiApiKey(): string
    {
        $constant = defined('GRAIL_HR_OPENAI_API_KEY') ? (string) constant('GRAIL_HR_OPENAI_API_KEY') : '';

        if ($constant !== '') {
            return $constant;
        }

        return (string) ($this->all()['openai_api_key'] ?? '');
    }

    public function openAiModel(): string
    {
        return (string) ($this->all()['openai_model'] ?? 'gpt-4.1-mini');
    }

    public function promptVersion(): string
    {
        return (string) ($this->all()['prompt_version'] ?? 'cv-analysis-v1');
    }

    public function maxPdfSizeBytes(): int
    {
        return 5 * 1024 * 1024;
    }

    public function analysesPerHour(): int
    {
        return max(1, (int) ($this->all()['analyses_per_hour'] ?? 10));
    }

    public function isDevCorsEnabled(): bool
    {
        return (bool) ($this->all()['dev_cors_enabled'] ?? true);
    }

    /** @param array<string, mixed> $input */
    public function save(array $input): void
    {
        $current = $this->all();
        $current['openai_api_key'] = isset($input['openai_api_key']) ? sanitize_text_field((string) $input['openai_api_key']) : '';

        $modelChoice = sanitize_text_field((string) ($input['openai_model_choice'] ?? $input['openai_model'] ?? $current['openai_model']));
        $customModel = sanitize_text_field((string) ($input['openai_model_custom'] ?? ''));
        $selectedModel = $modelChoice === 'custom' && $customModel !== '' ? $customModel : $modelChoice;
        $current['openai_model'] = $selectedModel !== '' ? $selectedModel : 'gpt-4.1-mini';

        $current['prompt_version'] = sanitize_key((string) ($input['prompt_version'] ?? $current['prompt_version']));
        $current['analyses_per_hour'] = max(1, absint($input['analyses_per_hour'] ?? $current['analyses_per_hour']));
        $current['dev_cors_enabled'] = !empty($input['dev_cors_enabled']);

        update_option(self::OPTION, $current, false);
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'openai_api_key' => '',
            'openai_model' => 'gpt-4.1-mini',
            'prompt_version' => 'cv-analysis-v1',
            'analyses_per_hour' => 10,
            'dev_cors_enabled' => true,
        ];
    }
}
