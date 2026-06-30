<?php
/**
 * Central plugin composition root.
 *
 * This class wires WordPress hooks to small services. It intentionally avoids business logic so that contexts remain testable and readable.
 */

declare(strict_types=1);

namespace GrailHr;

use GrailHr\CvIntake\Application\AnalyzeProfileFromPdfService;
use GrailHr\CvIntake\Application\AnalysisRateLimiter;
use GrailHr\CvIntake\Infrastructure\CvUploadValidator;
use GrailHr\CvIntake\Infrastructure\PdfTextExtractor;
use GrailHr\CvIntake\Infrastructure\PrivateFileStorage;
use GrailHr\IdentityAccess\Application\AuthService;
use GrailHr\IdentityAccess\Infrastructure\AuthMiddleware;
use GrailHr\IdentityAccess\Infrastructure\TokenService;
use GrailHr\IdentityAccess\Infrastructure\UserAccessManager;
use GrailHr\IdentityAccess\Presentation\AuthController;
use GrailHr\ProfileAnalysis\Application\AnalysisNormalizer;
use GrailHr\ProfileAnalysis\Application\AnalysisValidator;
use GrailHr\ProfileAnalysis\Infrastructure\OpenAiAnalysisProvider;
use GrailHr\ProfileManagement\Application\ProfileService;
use GrailHr\ProfileManagement\Infrastructure\ProfilePostType;
use GrailHr\ProfileManagement\Infrastructure\ProfileRepository;
use GrailHr\ProfileManagement\Presentation\ProfileController;
use GrailHr\Settings\Infrastructure\AdminMenu;
use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Infrastructure\Activation\Activator;
use GrailHr\Shared\Infrastructure\Logging\Logger;
use GrailHr\Shared\Infrastructure\Maintenance\MaintenanceTasks;
use GrailHr\Shared\Infrastructure\Rest\CorsHandler;
use GrailHr\Shared\Infrastructure\Rest\RestErrorMapper;
use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct(private readonly string $pluginFile)
    {
    }

    /**
     * Boots the plugin once. WordPress may load plugin files multiple times in test contexts, so booting is guarded.
     */
    public static function boot(string $pluginFile): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self($pluginFile);
        self::$instance->register();
    }

    private function register(): void
    {
        $settings = new SettingsRepository();
        $logger = new Logger($this->privatePath('logs'));
        $repository = new ProfileRepository($settings, $logger);
        $tokenService = new TokenService();
        $authMiddleware = new AuthMiddleware($tokenService);
        $errorMapper = new RestErrorMapper($logger);

        $profileService = new ProfileService($repository, new AnalysisValidator(), new AnalysisNormalizer(), $logger);
        $analysisProvider = new OpenAiAnalysisProvider($settings, $logger);
        $analyzeService = new AnalyzeProfileFromPdfService(
            $profileService,
            new PrivateFileStorage($this->privatePath('tmp')),
            new CvUploadValidator($settings),
            new PdfTextExtractor(),
            $analysisProvider,
            $logger,
            new AnalysisRateLimiter($settings)
        );

        register_activation_hook($this->pluginFile, [new Activator($settings, $logger), 'activate']);
        register_deactivation_hook($this->pluginFile, [new Activator($settings, $logger), 'deactivate']);

        add_action('init', [$this, 'ensureInstalledActiveTheme'], 1);
        add_action('init', [new ProfilePostType(), 'register']);
        add_action('init', [new CapabilityRegistrar(), 'registerCapabilities']);
        add_action('admin_menu', [new AdminMenu($settings, $repository, $logger), 'register']);
        (new UserAccessManager($tokenService))->register();
        add_action('rest_api_init', [new CorsHandler($settings), 'register']);
        add_action('rest_api_init', [new AuthController(new AuthService($tokenService), $errorMapper), 'registerRoutes']);
        $profileController = new ProfileController(
            $profileService,
            $analyzeService,
            $authMiddleware,
            $errorMapper,
            $settings,
            new AnalysisRateLimiter($settings)
        );
        add_action('rest_api_init', [$profileController, 'registerRoutes']);

        $maintenance = new MaintenanceTasks();
        add_action('grail_hr_cleanup_tmp', [$maintenance, 'cleanupTemporaryFiles']);
        add_action('grail_hr_cleanup_exports', [$maintenance, 'cleanupExports']);
        add_action('grail_hr_cleanup_logs', [$maintenance, 'cleanupLogs']);
        add_action('grail_hr_cleanup_expired_tokens', [$maintenance, 'cleanupExpiredTokens']);
    }


    /**
     * If WordPress still points to a deleted custom theme, switch to an installed default theme.
     * This keeps the admin/backend usable while Grail HR remains a plugin-driven Nuxt application.
     */
    public function ensureInstalledActiveTheme(): void
    {
        $activeTheme = wp_get_theme();

        if ($activeTheme->exists()) {
            return;
        }

        foreach (['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree'] as $stylesheet) {
            $fallback = wp_get_theme($stylesheet);

            if ($fallback->exists()) {
                switch_theme($stylesheet);
                return;
            }
        }
    }

    private function privatePath(string $child): string
    {
        return WP_CONTENT_DIR . '/grail-hr-private/' . trim($child, '/');
    }
}
