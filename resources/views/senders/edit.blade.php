@extends('sendportal::layouts.app')

@section('heading')
    {{ __('Edit Sender') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="card">
                <div class="card-header">{{ __('Edit Sender') }}</div>
                <div class="card-body">
                    @include('senders._form', [
                        'action' => route('senders.update', $sender),
                        'method' => 'put',
                        'sender' => $sender,
                        'submitLabel' => __('Save Changes'),
                    ])
                </div>
            </div>
        </div>
    </div>
@endsection
