<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\SenderStoreRequest;
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
}
