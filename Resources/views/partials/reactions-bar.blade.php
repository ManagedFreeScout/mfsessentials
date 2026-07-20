<div class="mfsessentials-reactions-bar" data-thread-id="{{ $thread->id }}">
    @foreach ($counts as $emoji => $count)
        <span class="mfsessentials-reaction{{ !empty($active[$emoji]) ? ' active' : '' }}" data-emoji="{{ $emoji }}"
              @if (!empty($reactorNames[$emoji])) data-toggle="tooltip" data-placement="top" title="{{ implode(', ', $reactorNames[$emoji]) }}" @endif>
            <span class="mfsessentials-reaction-emoji">{{ $emoji }}</span>
            <span class="mfsessentials-reaction-count">{{ $count }}</span>
        </span>
    @endforeach
</div>
