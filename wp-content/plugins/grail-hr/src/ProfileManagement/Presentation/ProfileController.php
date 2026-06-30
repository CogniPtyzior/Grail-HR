<?php
/**
 * REST controller for profile tables, details, manual creation, CV creation and replacement.
 */

declare(strict_types=1);

namespace GrailHr\ProfileManagement\Presentation;

use GrailHr\CvIntake\Application\AnalyzeProfileFromPdfService;
use GrailHr\CvIntake\Application\AnalysisRateLimiter;
use GrailHr\IdentityAccess\Infrastructure\AuthMiddleware;
use GrailHr\ProfileManagement\Application\ProfileService;
use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Infrastructure\Rest\RestErrorMapper;
use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;
use WP_REST_Request;

final class ProfileController
{
    public function __construct(
        private readonly ProfileService $profiles,
        private readonly AnalyzeProfileFromPdfService $analyzer,
        private readonly AuthMiddleware $auth,
        private readonly RestErrorMapper $errors,
        private readonly SettingsRepository $settings,
        private readonly AnalysisRateLimiter $rateLimiter
    ) {
    }

    public function registerRoutes(): void
    {
        $public = '__return_true';

        register_rest_route('grail-hr/v1', '/profiles', [
            'methods' => 'GET',
            'permission_callback' => $public,
            'callback' => [$this, 'list'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'createManual'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/from-cv', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'fromCv'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => $public,
            'callback' => [$this, 'get'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)', [
            'methods' => 'PUT',
            'permission_callback' => $public,
            'callback' => [$this, 'update'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)/replace-analysis', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'replaceAnalysis'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)/validate', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'validate'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)/archive', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'archive'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)/reopen', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'reopen'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)/restore', [
            'methods' => 'POST',
            'permission_callback' => $public,
            'callback' => [$this, 'restore'],
        ]);
        register_rest_route('grail-hr/v1', '/profiles/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'permission_callback' => $public,
            'callback' => [$this, 'delete'],
        ]);
        register_rest_route('grail-hr/v1', '/analysis/status', [
            'methods' => 'GET',
            'permission_callback' => $public,
            'callback' => [$this, 'analysisStatus'],
        ]);
    }


    public function analysisStatus(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::ANALYZE_CV);

            return rest_ensure_response([
                'openai_configured' => $this->settings->openAiApiKey() !== '',
                'analyses_per_hour' => $this->settings->analysesPerHour(),
                'remaining_analyses' => $this->rateLimiter->remaining($user->ID),
            ]);
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function list(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::VIEW_PROFILES);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);

            return rest_ensure_response($this->profiles->list($request->get_params(), $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function createManual(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::EDIT_PROFILES);
            $id = $this->profiles->createManual((array) $request->get_json_params(), $user->ID);

            return rest_ensure_response($this->profiles->get($id, $user->ID, false));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function fromCv(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::ANALYZE_CV);
            $file = $_FILES['cv'] ?? [];
            $title = sanitize_text_field((string) $request->get_param('title'));
            $id = $this->analyzer->createProfileFromPdf($file, $user->ID, $title);

            return rest_ensure_response($this->profiles->get($id, $user->ID, false));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function get(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::VIEW_PROFILES);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);

            return rest_ensure_response($this->profiles->get(absint($request['id']), $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function update(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::EDIT_PROFILES);
            $id = absint($request['id']);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->updateAnalysisForUser($id, (array) $request->get_json_params(), $user->ID, $canSeeAll);

            return rest_ensure_response($this->profiles->get($id, $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function replaceAnalysis(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::ANALYZE_CV);
            $id = absint($request['id']);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->assertCanAccess($id, $user->ID, $canSeeAll);
            $this->analyzer->replaceAnalysis($id, $_FILES['cv'] ?? [], $user->ID);

            return rest_ensure_response($this->profiles->get($id, $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function validate(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::VALIDATE_PROFILES);
            $id = absint($request['id']);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->validate($id, $user->ID, $canSeeAll);

            return rest_ensure_response($this->profiles->get($id, $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function archive(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::ARCHIVE_PROFILES);
            $id = absint($request['id']);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->archive($id, $user->ID, $canSeeAll);

            return rest_ensure_response($this->profiles->get($id, $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function reopen(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::EDIT_PROFILES);
            $id = absint($request['id']);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->reopen($id, $user->ID, $canSeeAll);

            return rest_ensure_response($this->profiles->get($id, $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }


    public function restore(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::ARCHIVE_PROFILES);
            $id = absint($request['id']);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->restore($id, $user->ID, $canSeeAll);

            return rest_ensure_response($this->profiles->get($id, $user->ID, $canSeeAll));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function delete(WP_REST_Request $request): mixed
    {
        try {
            $user = $this->auth->requireUser($request, CapabilityRegistrar::DELETE_PROFILES);
            $canSeeAll = user_can($user, CapabilityRegistrar::MANAGE_PROFILES);
            $this->profiles->delete(absint($request['id']), $user->ID, $canSeeAll);

            return rest_ensure_response(['ok' => true]);
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }
}
