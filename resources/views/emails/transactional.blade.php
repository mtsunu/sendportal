@if ($htmlBody !== null)
    {!! $htmlBody !!}
@else
    {!! nl2br(e($body)) !!}
@endif
