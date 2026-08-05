@extends('sendportal::layouts.app')

@section('heading')
    {{ __('Senders') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-10 offset-lg-1">
            @if (session('success'))
                <div class="alert alert-success" role="alert">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h2 color-purple-500 mb-0">{{ __('Senders') }}</h1>
                <a class="btn btn-primary" href="{{ route('senders.create') }}">{{ __('Add Sender') }}</a>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Saved Senders') }}</div>

                @if ($senders->isEmpty())
                    <div class="card-body">
                        <h2 class="h5">{{ __('No saved senders yet') }}</h2>
                        <p class="mb-0">{{ __('Add a sender to reuse its From Name and From Email when creating campaigns.') }}</p>
                    </div>
                @else
                    <div class="card-table table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>{{ __('Label') }}</th>
                                <th>{{ __('From Name') }}</th>
                                <th>{{ __('From Email') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($senders as $sender)
                                <tr>
                                    <td>{{ $sender->label }}</td>
                                    <td>{{ $sender->from_name }}</td>
                                    <td>{{ $sender->from_email }}</td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('senders.edit', $sender) }}">{{ __('Edit Sender') }}</a>
                                        <form class="d-inline" action="{{ route('senders.destroy', $sender) }}" method="post" onsubmit="return confirm('Are you sure you want to delete this sender? This action cannot be undone.')">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Delete Sender') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
