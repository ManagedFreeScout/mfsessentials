<?php

namespace Modules\MFSEssentials\Http\Controllers;

use App\Thread;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Services\LicenseService;
use Modules\MFSEssentials\Services\ThreadConverter;

class ConvertController extends Controller
{
    /**
     * Convert an email to a note (direction=note) or a converted note back
     * to an email (direction=email). Answers in fsAjax() format.
     */
    public function convert(Request $request)
    {
        // Licence gate (card #194), server-side too, not only by hiding the menu item.
        if (!LicenseService::isLicensed()) {
            return response()->json(['status' => 'error', 'msg' => __('MFS Essentials has no active licence.')]);
        }

        $thread = Thread::find((int) $request->input('thread_id'));
        if (!$thread) {
            return response()->json(['status' => 'error', 'msg' => __('Thread not found')]);
        }

        $user = Auth::user();
        if (!ThreadConverter::userCanConvert($user, $thread)) {
            return response()->json(['status' => 'error', 'msg' => __('Not enough permissions')]);
        }

        // The menu only offers valid conversions, but enforce it here too.
        if ($request->input('direction') === 'email') {
            if (!ThreadConverter::isConverted($thread)) {
                return response()->json(['status' => 'error', 'msg' => __('This note was not converted from an email')]);
            }
            ThreadConverter::convertToEmail($thread, $user);
        } else {
            if (!ThreadConverter::canConvertToNote($thread)) {
                return response()->json(['status' => 'error', 'msg' => __('Only emails can be converted to a note')]);
            }
            ThreadConverter::convertToNote($thread, $user);
        }

        return response()->json(['status' => 'success']);
    }
}
