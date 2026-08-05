@auth()
    @if (auth()->user()->currentWorkspaceId())
        <li class="nav-item {{ request()->is('senders*') ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('senders.index') }}">
                <i class="fa-fw fas fa-paper-plane mr-2"></i><span>{{ __('Senders') }}</span>
            </a>
        </li>
    @endif
@endauth
