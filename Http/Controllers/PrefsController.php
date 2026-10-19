<?php

namespace Modules\MFSEssentials\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Services\Features;
use Modules\MFSEssentials\Services\LicenseService;

class PrefsController extends Controller
{
    /**
     * Save one preference of the logged-in agent (1.4.0): split view on/off, divider position,
     * column widths, hidden statuses. key + value (JSON); Features::cleanPref() validates both.
     */
    public function save(Request $request)
    {
        if (!LicenseService::isLicensed()) {
            return response()->json(['status' => 'error', 'msg' => 'not_licensed'], 403);
        }

        $key = (string) $request->input('key', '');
        if (!in_array($key, Features::PREF_KEYS, true)) {
            return response()->json(['status' => 'error', 'msg' => 'unknown_key'], 422);
        }
        $value = json_decode((string) $request->input('value', 'null'), true);

        if (!Features::setPref(Auth::user()->id, $key, $value)) {
            return response()->json(['status' => 'error', 'msg' => 'invalid_value'], 422);
        }

        return response()->json(['status' => 'success', 'prefs' => (object) Features::prefs(Auth::user()->id)]);
    }
}
