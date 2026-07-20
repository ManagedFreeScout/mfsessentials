<?php

namespace Modules\MFSEssentials\Entities;

use Illuminate\Database\Eloquent\Model;

class ThreadReaction extends Model
{
    protected $table = 'mfsessentials_thread_reactions';

    public $timestamps = false;

    protected $fillable = ['thread_id', 'user_id', 'emoji', 'created_at'];

    const ALLOWED_EMOJI = ["\u{1F44D}", "\u{2705}", "\u{1F440}", "\u{1F389}"];

    /**
     * Toggle a single user's reaction on a thread: insert if absent, delete
     * if present. Returns true if the reaction is now active, false if it
     * was just removed.
     *
     * @param int $threadId
     * @param int $userId
     * @param string $emoji
     * @return bool
     */
    public static function toggle($threadId, $userId, $emoji)
    {
        $existing = self::where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        self::create([
            'thread_id'  => $threadId,
            'user_id'    => $userId,
            'emoji'      => $emoji,
            'created_at' => now(),
        ]);
        return true;
    }

    /**
     * Counts per emoji for a thread, which of those the given user has
     * active, and the display names of everyone who reacted with each emoji
     * (for tooltips). Always returns every allowed emoji (zero-filled), so
     * the bar renders a consistent set of buttons regardless of existing
     * data.
     *
     * @param int $threadId
     * @param int $userId
     * @return array{counts: array<string,int>, active: array<string,bool>, reactor_names: array<string,string[]>}
     */
    public static function summaryFor($threadId, $userId)
    {
        $rows = self::where('thread_id', $threadId)->get(['emoji', 'user_id']);

        $counts     = array_fill_keys(self::ALLOWED_EMOJI, 0);
        $active     = array_fill_keys(self::ALLOWED_EMOJI, false);
        $reactorIds = array_fill_keys(self::ALLOWED_EMOJI, []);

        foreach ($rows as $row) {
            if (!array_key_exists($row->emoji, $counts)) {
                continue;
            }
            $counts[$row->emoji]++;
            $reactorIds[$row->emoji][] = (int) $row->user_id;
            if ((int) $row->user_id === (int) $userId) {
                $active[$row->emoji] = true;
            }
        }

        // One query for every reactor's name across all four emoji, rather
        // than a query per emoji.
        $allUserIds = array_unique(array_merge([], ...array_values($reactorIds)));
        $users = $allUserIds ? \App\User::whereIn('id', $allUserIds)->get()->keyBy('id') : collect();

        $reactorNames = [];
        foreach ($reactorIds as $emoji => $ids) {
            $reactorNames[$emoji] = collect($ids)
                ->map(function ($id) use ($users) {
                    return $users->has($id) ? $users[$id]->getFullName() : null;
                })
                ->filter()
                ->values()
                ->all();
        }

        return ['counts' => $counts, 'active' => $active, 'reactor_names' => $reactorNames];
    }
}
