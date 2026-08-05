<form action="{{ $action }}" method="post">
    @csrf

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ __('We couldn\'t save this sender. Check the highlighted fields and try again.') }}
        </div>
    @endif

    <div class="form-group">
        <label for="sender-label">{{ __('Label') }}</label>
        <input id="sender-label" type="text" name="label" value="{{ old('label') }}"
               class="form-control @error('label') is-invalid @enderror" maxlength="255" required>
        <small class="form-text text-muted">{{ __('A recognizable name for this sender.') }}</small>
        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label for="sender-from-name">{{ __('From Name') }}</label>
        <input id="sender-from-name" type="text" name="from_name" value="{{ old('from_name') }}"
               class="form-control @error('from_name') is-invalid @enderror" maxlength="255" required>
        <small class="form-text text-muted">{{ __('The name recipients will see.') }}</small>
        @error('from_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
        <label for="sender-from-email">{{ __('From Email') }}</label>
        <input id="sender-from-email" type="email" name="from_email" value="{{ old('from_email') }}"
               class="form-control @error('from_email') is-invalid @enderror" maxlength="255" required>
        <small class="form-text text-muted">{{ __('The email address recipients will see.') }}</small>
        @error('from_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="d-flex align-items-center">
        <button type="submit" class="btn btn-primary mr-2">{{ $submitLabel }}</button>
        <a href="{{ route('senders.index') }}">{{ __('Return to Senders') }}</a>
    </div>
</form>
