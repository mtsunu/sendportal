@extends('sendportal::layouts.app')

@section('heading')
    {{ __('Add Sender') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="card">
                <div class="card-header">{{ __('Add Sender') }}</div>
                <div class="card-body">
                    @include('senders._form', [
                        'action' => route('senders.store'),
                        'submitLabel' => __('Save Sender'),
                    ])
                </div>
            </div>
        </div>
    </div>
@endsection
