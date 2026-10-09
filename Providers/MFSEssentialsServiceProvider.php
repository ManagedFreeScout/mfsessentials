<?php

namespace Modules\MFSEssentials\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Entities\ThreadReaction;
use Modules\MFSEssentials\Services\ThreadConverter;
use Modules\MFSEssentials\Services\LicenseService;

defined('MFSESSENTIALS_MODULE') || define('MFSESSENTIALS_MODULE', 'mfsessentials');

class MFSEssentialsServiceProvider extends ServiceProvider
{
    const MODULE_ALIAS = 'mfsessentials';

    protected $defer = false;

    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerLicenseHooks();
        $this->registerAssets();
        $this->registerReactionsHook();
        $this->registerConvertHooks();
    }

    public function register()
    {
        $this->app->singleton(LicenseService::class, function () {
            return new LicenseService();
        });
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', self::MODULE_ALIAS);
    }

    /**
     * Licence (card #194, 1.3.0): a settings section under Manage → Settings → MFSEssentials,
     * the licence shown on FreeScout's Manage → Modules page, and a re-check every 6 hours.
     * The modules.* filters below only affect what FreeScout shows; the real gate is
     * LicenseService::isLicensed(), checked by every feature hook and controller.
     * Rutger, 8 Oct 2026: no licence, an expired subscription or a licence that could not be
     * checked for 14 days = every feature off, also right after updating from a free version.
     */
    protected function registerLicenseHooks()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[self::MODULE_ALIAS] = [
                'title'       => __('MFSEssentials'),
                'icon'        => 'lock',
                'order'       => 310,
                'description' => __('Licence for the MFSEssentials module.'),
            ];
            return $sections;
        }, 15);

        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section === self::MODULE_ALIAS) {
                $settings['license_status'] = app(LicenseService::class)->getLicenseStatus();
            }
            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section === self::MODULE_ALIAS) {
                $params['license_status'] = app(LicenseService::class)->getLicenseStatus();
            }
            return $params;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section === self::MODULE_ALIAS ? self::MODULE_ALIAS . '::settings.mfsessentials' : $view;
        }, 20, 2);

        \Eventy::addFilter('modules.show_license', function ($show, $module) {
            return (isset($module['alias']) && $module['alias'] === self::MODULE_ALIAS) ? true : $show;
        }, 20, 2);

        \Eventy::addFilter('modules.license_info', function ($license_info, $module_alias) {
            if ($module_alias !== self::MODULE_ALIAS) {
                return $license_info;
            }
            $status = app(LicenseService::class)->getLicenseStatus();
            return [
                'license'      => $status['license_key'] ?? '',
                'activated'    => $status['valid'] ?? false,
                'status'       => $status['status'] ?? 'inactive',
                'expires_at'   => $status['expires_at'] ?? null,
                'license_type' => $status['license_type'] ?? null,
            ];
        }, 20, 2);

        \Eventy::addFilter('module.requires_license', function ($requires, $module) {
            return (isset($module['alias']) && $module['alias'] === self::MODULE_ALIAS) ? true : $requires;
        }, 20, 2);

        // Re-check every 6 hours (same interval as MSTeamsFS, card #232 F5), so an expired or
        // revoked subscription switches the features off without anyone opening the settings.
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->call(function () {
                $service = app(LicenseService::class);
                $status  = $service->getLicenseStatus();
                if (!empty($status['license_key'])) {
                    $service->validateLicense($status['license_key']);
                }
            })->cron('0 */6 * * *');
            return $schedule;
        }, 20, 1);
    }

    protected function registerViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', self::MODULE_ALIAS);
    }

    protected function registerAssets()
    {
        // Mirrors CfsAssistServiceProvider::boot() exactly -- Module::getPublicPath(),
        // not asset() (asset() has no such helper, FreeScout_Development_Notes.md §3.4).
        // Licence gate (card #194): no licence = none of the CSS/JS is loaded, which also
        // switches off the code-block wrap fix (pure CSS, nothing else to gate).
        \Eventy::addFilter('stylesheets', function ($items) {
            if (!LicenseService::isLicensed()) {
                return $items;
            }
            $items[] = \Module::getPublicPath(self::MODULE_ALIAS) . '/css/module.css';
            return $items;
        }, 20, 1);

        \Eventy::addFilter('javascripts', function ($items) {
            if (!LicenseService::isLicensed()) {
                return $items;
            }
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
            if (!$thread->isNote() || !LicenseService::isLicensed()) {
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
            if (!LicenseService::isLicensed() || !Auth::check() || !ThreadConverter::userCanConvert(Auth::user(), $thread)) {
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
            if (!ThreadConverter::isConverted($thread) || !LicenseService::isLicensed()) {
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
