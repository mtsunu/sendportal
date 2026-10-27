@extends('sendportal::layouts.app')

@section('title', $campaign->name)
@section('heading', $campaign->name)

@push('css')
    <style>
        .main-wrapper { min-width: 0; }
        .recipient-subject { min-width: 160px; }
        .recipient-detail { min-width: 180px; max-width: 260px; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    @include('sendportal::campaigns.reports.partials.nav')

    <form method="GET" action="{{ route('sendportal.campaigns.reports.recipients', $campaign->id) }}" class="mb-4">
        <label for="recipient-email-search">{{ __('Cari email penerima') }}</label>
        <div class="form-row align-items-center">
            <div class="col-sm-6 mb-2 mb-sm-0">
                <input id="recipient-email-search" type="search" name="email" value="{{ $email }}" maxlength="254"
                       class="form-control" placeholder="{{ __('Alamat email atau sebagian alamat') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ __('Cari') }}</button>
                <a href="{{ route('sendportal.campaigns.reports.recipients', $campaign->id) }}" class="btn btn-light">{{ __('Reset') }}</a>
            </div>
        </div>
        @error('email')<div class="text-danger mt-2">{{ $message }}</div>@enderror
    </form>

    <div class="card">
        <div class="card-table table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Subscriber') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Detail') }}</th>
                        <th>{{ __('Delivered') }}</th>
                        <th>{{ __('Opened') }}</th>
                        <th>{{ __('Clicked') }}</th>
                        <th>{{ __('Bounced') }}</th>
                        <th>{{ __('Complained') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                        @php([$deliveryLabel, $deliveryColor] = \App\Services\Ses\RecipientDeliveryStatus::for($message))
                        <tr>
                            <td><a href="{{ route('sendportal.subscribers.show', $message->subscriber_id) }}">{{ $message->recipient_email }}</a></td>
                            <td class="recipient-subject">{{ $message->subject }}</td>
                            <td class="text-nowrap"><span class="badge badge-{{ $deliveryColor }}">{{ __($deliveryLabel) }}</span></td>
                            <td class="recipient-detail">{{ $message->ses_detail ?: '—' }}</td>
                            @foreach(['delivered_at', 'opened_at', 'clicked_at', 'bounced_at', 'complained_at'] as $dateColumn)
                                <td class="text-nowrap">{{ $message->{$dateColumn} ? $message->{$dateColumn}->copy()->timezone(config('app.timezone'))->format('d M Y H:i:s T') : '—' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="9"><p class="empty-table-text">{{ $email !== '' ? __('Tidak ada penerima yang cocok dengan pencarian email.') : __('There are no messages') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('sendportal::layouts.partials.pagination', ['records' => $messages])
@endsection
