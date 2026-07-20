<?php

namespace Modules\MFSEssentials\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Entities\ThreadReaction;

class ReactionsController extends Controller
{
    public function toggle(Request $request)
    {
        $threadId = (int) $request->input('thread_id');
        $emoji    = (string) $request->input('emoji', '');

        // Never trust arbitrary client input for the emoji value — only the
        // fixed set of 4 is ever valid, regardless of what's posted.
        if (!in_array($emoji, ThreadReaction::ALLOWED_EMOJI, true)) {
            return response()->json(['error' => 'invalid_emoji'], 422);
        }

        $thread = \App\Thread::find($threadId);
        if (!$thread) {
            return response()->json(['error' => 'thread_not_found'], 404);
        }

        // Reactions must never be possible on customer-facing threads.
        // Enforced here server-side, not just hidden client-side/in the
        // rendering hook.
        if (!$thread->isNote()) {
            return response()->json(['error' => 'not_a_note'], 403);
        }

        $user = Auth::user();

        ThreadReaction::toggle($threadId, $user->id, $emoji);

        $summary = ThreadReaction::summaryFor($threadId, $user->id);

        return response()->json([
            'thread_id'     => $threadId,
            'counts'        => $summary['counts'],
            'active'        => $summary['active'],
            'reactor_names' => $summary['reactor_names'],
        ]);
    }
}
