<?php

namespace Modules\MFSEssentials\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Entities\ThreadReaction;

defined('MFSESSENTIALS_MODULE') || define('MFSESSENTIALS_MODULE', 'mfsessentials');

class MFSEssentialsServiceProvider extends ServiceProvider
{
    const MODULE_ALIAS = 'mfsessentials';

    protected $defer = false;

    public function boot()
    {
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerAssets();
        $this->registerReactionsHook();
    }

    public function register()
    {
        //
    }

    protected function registerViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', self::MODULE_ALIAS);
    }

    protected function registerAssets()
    {
        // Mirrors CfsAssistServiceProvider::boot() exactly -- Module::getPublicPath(),
        // not asset() (asset() has no such helper, FreeScout_Development_Notes.md §3.4).
        \Eventy::addFilter('stylesheets', function ($items) {
            $items[] = \Module::getPublicPath(self::MODULE_ALIAS) . '/css/module.css';
            return $items;
        }, 20, 1);

        \Eventy::addFilter('javascripts', function ($items) {
            $items[] = \Module::getPublicPath(self::MODULE_ALIAS) . '/js/mfsessentials-editor.js';
            $items[] = \Module::getPublicPath(self::MODULE_ALIAS) . '/js/mfsessentials-reactions.js';
            return $items;
        }, 20, 1);
    }

    protected function registerReactionsHook()
    {
        // thread.meta fires unconditionally for every thread type
        // (resources/views/conversations/partials/thread.blade.php), so the
        // isNote() gate must be the first line -- same pattern already
        // proven in this project's own MSTeamsFSServiceProvider hooks.
        \Eventy::addAction('thread.meta', function ($thread, $loop, $threads, $conversation, $mailbox) {
            if (!$thread->isNote()) {
                return;
            }

            $userId  = Auth::check() ? Auth::user()->id : 0;
            $summary = ThreadReaction::summaryFor($thread->id, $userId);

            echo view(self::MODULE_ALIAS . '::partials.reactions-bar', [
                'thread'       => $thread,
                'counts'       => $summary['counts'],
                'active'       => $summary['active'],
                'reactorNames' => $summary['reactor_names'],
            ])->render();
        }, 20, 5);
    }
}
