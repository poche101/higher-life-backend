<?php

namespace App\Livewire\Admin;

use App\Models\AppNotification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Notifications extends Component
{
    public string $title = '', $body = '', $type = 'new_content';
    public ?string $scheduled_at = null;

    public function send()
    {
        $this->validate(['title' => 'required|string|max:120', 'body' => 'required|string|max:500', 'type' => 'required|in:new_content,announcement,premium,collection']);

        AppNotification::create([
            'title' => $this->title, 'body' => $this->body, 'type' => $this->type, 'status' => 'scheduled',
            'scheduled_at' => $this->scheduled_at ? \Carbon\Carbon::parse($this->scheduled_at) : now(),
        ]);

        $this->reset(['title', 'body', 'scheduled_at']);
        session()->flash('ok', 'Notification scheduled. The scheduler delivers it within a minute.');
    }

    public function render()
    {
        return view('livewire.admin.notifications', ['items' => AppNotification::latest()->limit(20)->get()]);
    }
}
