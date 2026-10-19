<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-info">
            <strong>{{ __('MFS Essentials') }}</strong> &mdash; {{ __('Fixed panels, split view, status filters, sortable and adjustable columns, editor extras, an emoji and symbol picker, note reactions, converting an email to an internal note, and code blocks that wrap.') }}
            {{ __('A licence is a yearly subscription for one FreeScout installation, for all your agents.') }}
            <a href="{{ config('mfsessentials.buy_url') }}" target="_blank" rel="noopener">{{ __('Buy or renew a licence') }}</a>
        </div>
    </div>
</div>

{{-- Licence panel (card #194) --}}
@include('mfsessentials::settings.partials.license')

{{-- Feature switches (1.4.0) --}}
@include('mfsessentials::settings.partials.features')
