<?php

namespace App\View\Components;

use App\Models\PushSubscription;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class PwaControls extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(Request $request)
    {
        $user = $request->user();

        $this->accountId = $user instanceof User ? (string) $user->getKey() : '';
        $this->pushSubscribed = $user instanceof User && PushSubscription::query()
            ->where('user_id', $user->getKey())
            ->where('session_hash', hash('sha256', $request->session()->getId()))
            ->exists();
    }

    public string $accountId;

    public bool $pushSubscribed;

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.pwa-controls');
    }
}
