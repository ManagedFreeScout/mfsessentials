<?php

namespace Modules\MFSEssentials\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MFSEssentials\Services\LicenseService;

class MFSEssentialsController extends Controller
{
    protected $licenseService;

    public function __construct()
    {
        $this->licenseService = null;
    }

    protected function getLicenseService()
    {
        if ($this->licenseService === null) {
            $this->licenseService = app(LicenseService::class);
        }
        return $this->licenseService;
    }

    /**
     * Handles this module's own settings-page license form (Activate/Deactivate
     * buttons in Resources/views/settings/partials/license.blade.php).
     */
    public function manageLicense(Request $request)
    {
        $licenseService = $this->getLicenseService();
        $licenseStatus = $licenseService->getLicenseStatus();

        if ($licenseStatus['status'] === 'no_table') {
            return response()->json([
                'status' => 'error',
                'message' => __('License table does not exist. Please run migrations first.')
            ]);
        }

        $action = $request->input('action');
        $licenseKey = $request->input('license_key');

        if (empty($licenseKey) && $action !== 'deactivate') {
            return response()->json([
                'status' => 'error',
                'message' => __('License key is required.')
            ]);
        }

        switch ($action) {
            case 'activate':
                $result = $licenseService->activateLicense($licenseKey);
                break;
            case 'deactivate':
                $licenseStatus = $licenseService->getLicenseStatus();
                $licenseKey = $licenseStatus['license_key'] ?? $licenseKey;
                $result = $licenseService->deactivateLicense($licenseKey);
                break;
            default:
                return response()->json([
                    'status' => 'error',
                    'message' => __('Invalid action.')
                ]);
        }

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => __($result['message']),
        ]);
    }

    /**
     * FreeScout core's own generic "Manage Modules" page license button
     * (module.requires_license/modules.license_info filters) posts here --
     * separate entry point from this module's own settings-page form above,
     * same as MSTeamsFS's own handleModuleLicenseAction.
     */
    public function handleModuleLicenseAction(Request $request)
    {
        $action = $request->input('action');
        $moduleAlias = $request->input('module_alias');
        $licenseKey = $request->input('license_key');

        if ($moduleAlias !== 'mfsessentials') {
            return response()->json([
                'success' => false,
                'message' => __('Invalid module')
            ]);
        }

        if (empty($licenseKey) && $action !== 'deactivate') {
            return response()->json([
                'success' => false,
                'message' => __('License key is required')
            ]);
        }

        $result = $this->getLicenseService()->performAction($action, $licenseKey);

        return response()->json($result);
    }
}
