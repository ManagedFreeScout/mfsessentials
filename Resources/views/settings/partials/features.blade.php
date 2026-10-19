{{-- Feature switches (1.4.0, card freescout-modules #340). Saved by core's settings form;
     every switch defaults to on. Agents' own choices (split view, column widths, hidden
     statuses) are remembered per agent and need no settings. --}}
@php
    $mfseFeatureHelp = [
        'mfsessentials_fixed_panels'   => __('The top menu, the folders, the ticket list header and, on a conversation, the toolbar, title and customer panel stay in place while you scroll.'),
        'mfsessentials_split_view'     => __('A "Split view" button above the ticket list: the list stays on the left and the conversation opens on the right. Each agent switches it on for themselves.'),
        'mfsessentials_status_filters' => __('Active / Pending / Closed checkboxes above the ticket list. Each agent\'s choice is remembered.'),
        'mfsessentials_list_columns'   => __('Sort the ticket list by Customer and Assigned To, and drag the column borders to change their width. Each agent\'s widths are remembered.'),
        'mfsessentials_editor_extras'  => __('In the reply and note editor: Tab / Shift+Tab for sub-lists, indent and outdent, strikethrough, alignment, undo and redo, a horizontal line and fullscreen.'),
        'mfsessentials_emoji'          => __('An emoji and symbol picker in the reply and note editor.'),
        'mfsessentials_reactions'      => __('Emoji reactions on notes, so colleagues can respond without writing a note.'),
        'mfsessentials_convert'        => __('Convert a single email in a conversation to an internal note, and back.'),
    ];
@endphp

<div class="row">
    <div class="col-xs-12">
        <div class="panel panel-default">
            <div class="panel-heading">{{ __('Features') }}</div>
            <div class="panel-body">
                <form class="form-horizontal" method="POST" action="" id="mfsessentials-features-form">
                    {{ csrf_field() }}

                    @foreach (\Modules\MFSEssentials\Services\Features::SWITCHES as $mfseOption => $mfseLabel)
                        <div class="form-group">
                            <label for="{{ $mfseOption }}" class="col-sm-3 control-label">{{ __($mfseLabel) }}</label>
                            <div class="col-sm-8">
                                <div class="controls">
                                    <div class="onoffswitch-wrap">
                                        <div class="onoffswitch">
                                            <input type="checkbox" name="settings[{{ $mfseOption }}]" value="1" id="{{ $mfseOption }}" class="onoffswitch-checkbox" @if (old('settings.'.$mfseOption, $settings[$mfseOption] ?? true)) checked="checked" @endif>
                                            <label class="onoffswitch-label" for="{{ $mfseOption }}"></label>
                                        </div>
                                    </div>
                                </div>
                                <p class="form-help">{{ $mfseFeatureHelp[$mfseOption] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach

                    <div class="form-group margin-top">
                        <div class="col-sm-8 col-sm-offset-3">
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                            <p class="form-help margin-top-10">{{ __('These switches apply to all agents. Within a feature, each agent\'s own choices (split view on or off, column widths, which statuses are shown) are remembered for that agent only. The code-block fix (long lines wrap) is always on with a valid licence.') }}</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
