<?php

namespace Modules\MFSEssentials\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Entities\ThreadReaction;
use Modules\MFSEssentials\Services\ThreadConverter;

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
        $this->registerConvertHooks();
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
            $items[] = \Module::getPublicPath(self::MODULE_ALIAS) . '/js/mfsessentials-convert.js';
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

    /**
     * Convert a single email to a note and back (card freescout-modules #261).
     * The work itself is in Services/ThreadConverter.php.
     */
    protected function registerConvertHooks()
    {
        // Menu items in each thread's top-right dropdown (same hook as
        // TicketTranslator's "Translate"). Confirmation texts travel as data
        // attributes so they stay translatable; mfsessentials-convert.js
        // shows them and posts the conversion.
        \Eventy::addAction('thread.menu', function ($thread) {
            if (!Auth::check() || !ThreadConverter::userCanConvert(Auth::user(), $thread)) {
                return;
            }

            if (ThreadConverter::canConvertToNote($thread)) {
                $confirm = __('Convert this email to an internal note? Clients will no longer see it in the history of later replies or in the customer portal.');
                if ($thread->type == \App\Thread::TYPE_MESSAGE) {
                    $confirm .= ' '.__('This reply has already been sent; converting it cannot recall that email.');
                }
                if ($thread->first) {
                    $confirm .= ' '.__('Note: this is the first message of the conversation.');
                }
                $label = __('Convert to note');
                $direction = 'note';
            } elseif (ThreadConverter::isConverted($thread)) {
                $confirm = __('Convert this note back to an email? Clients will see it again in the history of later replies and in the customer portal.');
                $label = __('Convert back to email');
                $direction = 'email';
            } else {
                return;
            }

            echo '<li><a href="#" class="mfsessentials-convert-trigger" role="button"'
                .' data-direction="'.$direction.'"'
                .' data-confirm="'.e($confirm).'"'
                .' data-ok="'.e($label).'"'
                .' data-url="'.e(route('mfsessentials.thread.convert')).'">'
                .e($label).'</a></li>';
        }, 20, 1);

        // Banner on a converted note: who converted it, when, original sender.
        \Eventy::addAction('thread.before_body', function ($thread, $loop, $threads, $conversation, $mailbox) {
            if (!ThreadConverter::isConverted($thread)) {
                return;
            }

            echo view(self::MODULE_ALIAS . '::partials.convert-banner', [
                'banner' => ThreadConverter::bannerData($thread),
            ])->render();
        }, 20, 5);

        // Activity lines ("Rutger converted an email to a note").
        \Eventy::addFilter('thread.action_text', function ($did_this, $thread) {
            if ($thread->type != \App\Thread::TYPE_LINEITEM) {
                return $did_this;
            }
            if ($thread->action_type == ThreadConverter::ACTION_TYPE_CONVERTED_TO_NOTE) {
                return __(':person converted an email to a note');
            }
            if ($thread->action_type == ThreadConverter::ACTION_TYPE_CONVERTED_TO_EMAIL) {
                return __(':person converted a note back to an email');
            }

            return $did_this;
        }, 20, 2);
    }
}
