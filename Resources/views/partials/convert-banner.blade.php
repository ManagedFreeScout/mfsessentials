<div class="mfsessentials-converted-banner">
    <i class="glyphicon glyphicon-eye-close"></i>
    @if ($banner['is_reply'])
        {{ __('Converted from a reply by :by :when. Originally sent by :from. Clients no longer see it in later replies or the portal.', ['by' => $banner['by'], 'when' => App\User::dateDiffForHumansWithHours($banner['at']), 'from' => $banner['from']]) }}
    @else
        {{ __('Converted from an email by :by :when. Originally from :from. Clients no longer see it in later replies or the portal.', ['by' => $banner['by'], 'when' => App\User::dateDiffForHumansWithHours($banner['at']), 'from' => $banner['from']]) }}
    @endif
</div>
