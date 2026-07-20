<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class ChangeMfsessentialsEmojiColumnCollation extends Migration
{
    public $timestamps = false;

    /**
     * utf8mb4_unicode_ci (FreeScout's table-wide default collation) has no
     * real collation weights for supplementary-plane codepoints -- every
     * emoji used by this feature is above U+FFFF -- and treats them all as
     * equal under `=`. Confirmed live (2026-07-21) via direct equality
     * checks: '👍' = '👀' = '🎉' all returned 1 under utf8mb4_unicode_ci,
     * while '✅' (a 3-byte, BMP codepoint) correctly returned 0 against all
     * three. That equality backs both the UNIQUE constraint and
     * ThreadReaction::toggle()'s ->where('emoji', $emoji) lookup, so it
     * collided all three distinct reactions into one. utf8mb4_bin (byte-
     * exact binary comparison) is the semantically correct collation for an
     * identity-match column like this -- confirmed via the same live test:
     * all four emoji compare distinct from each other under utf8mb4_bin,
     * and each still compares equal to itself.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE mfsessentials_thread_reactions MODIFY emoji VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    /**
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE mfsessentials_thread_reactions MODIFY emoji VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }
}
