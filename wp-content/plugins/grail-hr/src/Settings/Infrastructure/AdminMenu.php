<?php
/**
 * WordPress admin pages for Grail HR.
 *
 * The admin area exposes configuration, diagnostics, exports, logs and access-management entry points.
 */

declare(strict_types=1);

namespace GrailHr\Settings\Infrastructure;

use GrailHr\ProfileManagement\Infrastructure\ProfileRepository;
use GrailHr\Shared\Infrastructure\Logging\Logger;
use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;

final class AdminMenu
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ProfileRepository $profiles,
        private readonly Logger $logger
    ) {
    }

    public function register(): void
    {
        add_menu_page('Grail HR', 'Grail HR', CapabilityRegistrar::USE_APP, 'grail-hr', [$this, 'dashboard'], 'dashicons-id', 58);
        add_submenu_page('grail-hr', 'Tableau de bord', 'Tableau de bord', CapabilityRegistrar::USE_APP, 'grail-hr', [$this, 'dashboard']);
        add_submenu_page('grail-hr', 'Réglages IA', 'Réglages IA', CapabilityRegistrar::MANAGE_SETTINGS, 'grail-hr-settings', [$this, 'settings']);
        add_submenu_page(
            'grail-hr',
            'Accès utilisateurs',
            'Accès utilisateurs',
            CapabilityRegistrar::MANAGE_USERS,
            'grail-hr-users',
            [$this, 'users']
        );
        add_submenu_page('grail-hr', 'Exports', 'Exports', CapabilityRegistrar::EXPORT_DATA, 'grail-hr-exports', [$this, 'exports']);
        add_submenu_page('grail-hr', 'Logs', 'Logs', CapabilityRegistrar::VIEW_LOGS, 'grail-hr-logs', [$this, 'logs']);
        add_submenu_page('grail-hr', 'Diagnostic', 'Diagnostic', CapabilityRegistrar::VIEW_LOGS, 'grail-hr-diagnostic', [$this, 'diagnostic']);
    }

    public function dashboard(): void
    {
        echo '<div class="wrap"><h1>Grail HR</h1>';
        echo '<p>Application interne d’analyse de CV assistée par IA.</p>';
        echo '<p>Le CV PDF source et le texte brut extrait ne sont pas conservés après analyse.</p>';
        echo '</div>';
    }

    public function profiles(): void
    {
        $rows = $this->profiles->exportRows(true);
        echo '<div class="wrap"><h1>Profils CV</h1>';
        echo '<p>Vue administrative synthétique. Le travail quotidien se fait dans l’application Nuxt.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Profil</th><th>Statut</th><th>Analyse</th><th>Mis à jour</th></tr></thead><tbody>';

        foreach (array_slice($rows, 0, 50) as $row) {
            echo '<tr><td>' . esc_html((string) $row['post_title']) . '</td>';
            echo '<td>' . esc_html((string) $row['review_status']) . '</td>';
            echo '<td>' . esc_html((string) $row['analysis_status']) . '</td>';
            echo '<td>' . esc_html((string) $row['updated_at']) . '</td></tr>';
        }

        if ($rows === []) {
            echo '<tr><td colspan="4">Aucun profil pour le moment.</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function settings(): void
    {
        if (!current_user_can(CapabilityRegistrar::MANAGE_SETTINGS)) {
            wp_die('Accès refusé.');
        }

        if (isset($_POST['grail_hr_settings_nonce']) && wp_verify_nonce($_POST['grail_hr_settings_nonce'], 'grail_hr_save_settings')) {
            $this->settings->save(wp_unslash($_POST));
            echo '<div class="notice notice-success"><p>Réglages enregistrés.</p></div>';
        }

        if (isset($_POST['grail_hr_test_openai_nonce']) && wp_verify_nonce($_POST['grail_hr_test_openai_nonce'], 'grail_hr_test_openai')) {
            $this->renderOpenAiTestNotice();
        }

        $settings = $this->settings->all();
        $currentModel = (string) $settings['openai_model'];
        $knownModels = $this->modelOptions();
        $isCustomModel = !array_key_exists($currentModel, $knownModels);

        echo '<div class="wrap"><h1>Réglages IA Grail HR</h1>';
        echo '<style>.grail-hr-admin-actions{display:flex;gap:12px;align-items:center;margin-top:18px}.grail-hr-admin-actions form{margin:0}.grail-hr-model-custom{margin-top:8px}@media(max-width:782px){.grail-hr-admin-actions{align-items:stretch;flex-direction:column}.grail-hr-admin-actions .button{width:100%;text-align:center}}</style>';
        echo '<form id="grail_hr_settings_form" method="post">';
        wp_nonce_field('grail_hr_save_settings', 'grail_hr_settings_nonce');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="openai_api_key">Clé OpenAI</label></th><td>';
        echo '<input id="openai_api_key" name="openai_api_key" type="password" class="regular-text" autocomplete="off" ';
        echo 'value="' . esc_attr((string) $settings['openai_api_key']) . '"> ';
        echo '<button type="button" class="button" onclick="const f=document.getElementById(\'openai_api_key\');';
        echo 'f.type=f.type===\'password\'?\'text\':\'password\';">Afficher / masquer</button>';
        echo '<p class="description">Cette clé est utilisée uniquement côté serveur. ';
        echo 'Elle n’est jamais exposée dans l’application Nuxt.</p></td></tr>';
        echo '<tr><th scope="row"><label for="openai_model_choice">Modèle</label></th><td>';
        echo '<select id="openai_model_choice" name="openai_model_choice">';

        foreach ($knownModels as $model => $label) {
            echo '<option value="' . esc_attr($model) . '" ' . selected($currentModel, $model, false) . '>' . esc_html($label) . '</option>';
        }

        echo '<option value="custom" ' . selected($isCustomModel, true, false) . '>Modèle personnalisé</option>';
        echo '</select>';
        echo '<input id="openai_model_custom" name="openai_model_custom" type="text" class="regular-text grail-hr-model-custom" placeholder="ex. gpt-4.1-mini" value="' . esc_attr($isCustomModel ? $currentModel : '') . '">';
        echo '<p class="description">Choisissez un modèle recommandé ou indiquez un identifiant personnalisé si votre compte OpenAI requiert un modèle spécifique.</p>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="analyses_per_hour">Analyses par utilisateur par heure</label></th><td>';
        echo '<input id="analyses_per_hour" name="analyses_per_hour" type="number" min="1" ';
        echo 'value="' . esc_attr((string) $settings['analyses_per_hour']) . '"></td></tr>';
        echo '<tr><th scope="row">CORS dev Nuxt</th><td><label>';
        echo '<input name="dev_cors_enabled" type="checkbox" value="1" ' . checked(!empty($settings['dev_cors_enabled']), true, false) . '> ';
        echo 'Autoriser localhost:3000 en dev</label></td></tr>';
        echo '</tbody></table></form>';
        echo '<div class="grail-hr-admin-actions">';
        echo '<button type="submit" form="grail_hr_settings_form" class="button button-primary">Mettre à jour les réglages</button>';
        echo '<form method="post">';
        wp_nonce_field('grail_hr_test_openai', 'grail_hr_test_openai_nonce');
        echo '<button type="submit" class="button button-secondary">Tester la connexion OpenAI</button>';
        echo '</form></div></div>';
    }

    public function users(): void
    {
        echo '<div class="wrap"><h1>Accès utilisateurs Grail HR</h1>';
        echo '<p>Les accès Grail HR se gèrent dans la fiche utilisateur WordPress, section “Accès Grail HR”.</p>';
        echo '<p>Utilisez le filtre “Accès Grail HR” sur la liste des utilisateurs pour distinguer les managers, recruteurs et comptes sans accès actif.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('users.php')) . '">Ouvrir la liste des utilisateurs</a></p></div>';
    }

    public function exports(): void
    {
        if (!current_user_can(CapabilityRegistrar::EXPORT_DATA)) {
            wp_die('Accès refusé.');
        }

        if (isset($_POST['grail_hr_export_nonce']) && wp_verify_nonce($_POST['grail_hr_export_nonce'], 'grail_hr_export')) {
            $this->downloadExport(sanitize_key((string) ($_POST['format'] ?? 'json')));
        }

        echo '<div class="wrap"><h1>Exports Grail HR</h1>';
        echo '<p>Les exports excluent les profils supprimés et ne contiennent jamais les CV sources, textes bruts, tokens ou clés API.</p>';
        echo '<form method="post">';
        wp_nonce_field('grail_hr_export', 'grail_hr_export_nonce');
        echo '<select name="format"><option value="json">JSON complet</option><option value="csv">CSV résumé</option></select> ';
        submit_button('Télécharger l’export', 'primary', 'submit', false);
        echo '</form></div>';
    }

    public function logs(): void
    {
        echo '<div class="wrap"><h1>Logs Grail HR</h1>';
        echo '<p>Les logs ne doivent contenir ni CV, ni texte extrait, ni token, ni clé OpenAI.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Niveau</th><th>Canal</th><th>Message</th><th>Détails</th></tr></thead><tbody>';

        foreach ($this->logger->recent(200) as $record) {
            echo '<tr><td>' . esc_html((string) ($record['time'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($record['level'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($record['channel'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($record['message'] ?? '')) . '</td>';
            echo '<td><code class="grail-hr-log-context">' . esc_html($this->formatLogContext($record['context'] ?? [])) . '</code></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function diagnostic(): void
    {
        $privateBase = WP_CONTENT_DIR . '/grail-hr-private';
        echo '<div class="wrap"><h1>Diagnostic Grail HR</h1><ul>';
        echo '<li>Version plugin : ' . esc_html(GRAIL_HR_VERSION) . '</li>';
        echo '<li>PHP : ' . esc_html(PHP_VERSION) . '</li>';
        echo '<li>WordPress : ' . esc_html(get_bloginfo('version')) . '</li>';
        echo '<li>OpenAI configuré : ' . ($this->settings->openAiApiKey() !== '' ? 'oui' : 'non') . '</li>';
        echo '<li>Stockage privé accessible : ' . (is_dir($privateBase) && is_writable($privateBase) ? 'oui' : 'non') . '</li>';
        echo '<li>pdftotext disponible : ' . (trim((string) shell_exec('command -v pdftotext 2>/dev/null')) !== '' ? 'oui' : 'non') . '</li>';
        echo '</ul></div>';
    }


    /** @param mixed $context */
    private function formatLogContext(mixed $context): string
    {
        if (!is_array($context) || $context === []) {
            return '';
        }

        $safe = [];
        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (preg_match('/key|token|secret|password|cv|text|prompt/i', $key) === 1) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
                continue;
            }

            $safe[$key] = '[complexe]';
        }

        if ($safe === []) {
            return '';
        }

        return (string) wp_json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function renderOpenAiTestNotice(): void
    {
        if ($this->settings->openAiApiKey() === '') {
            echo '<div class="notice notice-warning"><p>OpenAI n’est pas configuré. Renseignez la clé puis réessayez.</p></div>';
            return;
        }

        $model = rawurlencode($this->settings->openAiModel());
        $response = wp_remote_get('https://api.openai.com/v1/models/' . $model, [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $this->settings->openAiApiKey()],
        ]);

        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>Connexion OpenAI impossible pour le moment.</p></div>';
            return;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status >= 200 && $status < 300) {
            echo '<div class="notice notice-success"><p>Connexion OpenAI réussie pour le modèle configuré.</p></div>';
            return;
        }

        echo '<div class="notice notice-error"><p>Le modèle ou la clé OpenAI n’a pas pu être validé.</p></div>';
    }

    /** @return array<string, string> */
    private function modelOptions(): array
    {
        return [
            'gpt-4.1-mini' => 'GPT-4.1 mini — recommandé',
            'gpt-4.1' => 'GPT-4.1 — qualité supérieure',
            'gpt-4o-mini' => 'GPT-4o mini — économique',
            'gpt-5-mini' => 'GPT-5 mini — moderne et équilibré',
            'gpt-5' => 'GPT-5 — plus puissant',
        ];
    }

    private function downloadExport(string $format): void
    {
        $rows = $this->profiles->exportRows(true);

        if ($format === 'csv') {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="grail-hr-export.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['profile_id', 'name', 'email', 'primary_job', 'seniority', 'status', 'updated_at']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['profile_post_id'],
                    $row['post_title'],
                    $row['candidate_email'],
                    $row['primary_job_label'],
                    $row['seniority_level'],
                    $row['review_status'],
                    $row['updated_at'],
                ]);
            }

            exit;
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="grail-hr-export.json"');
        echo wp_json_encode(['schema_version' => '1.0', 'exported_at' => gmdate('c'), 'profiles' => $rows], JSON_PRETTY_PRINT);
        exit;
    }
}
