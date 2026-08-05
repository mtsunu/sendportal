<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\SenderStoreRequest;
use App\Http\Requests\Workspaces\SenderUpdateRequest;
use App\Models\Sender;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SendersController extends Controller
{
    public function index(Request $request): ViewContract
    {
        $workspace = $request->user()->currentWorkspace();

        return view('senders.index', [
            'senders' => $workspace->senders()->latest()->get(),
        ]);
    }

    public function create(): ViewContract
    {
        return view('senders.create');
    }

    public function store(SenderStoreRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        $workspace->senders()->create($request->validated());

        return redirect()
            ->route('senders.index')
            ->with('success', __('Sender saved successfully.'));
    }

    public function edit(Request $request, int $senderId): ViewContract
    {
        return view('senders.edit', [
            'sender' => $this->senderFor($request, $senderId),
        ]);
    }

    public function update(SenderUpdateRequest $request, int $senderId): RedirectResponse
    {
        $this->senderFor($request, $senderId)->update($request->validated());

        return redirect()
            ->route('senders.index')
            ->with('success', __('Sender updated successfully.'));
    }

    public function destroy(Request $request, int $senderId): RedirectResponse
    {
        $this->senderFor($request, $senderId)->delete();

        return redirect()
            ->route('senders.index')
            ->with('success', __('Sender deleted successfully.'));
    }

    private function senderFor(Request $request, int $senderId): Sender
    {
        return $request->user()->currentWorkspace()->senders()->findOrFail($senderId);
    }
}
