<?php

namespace App\Livewire\Admin;

use App\Models\Affirmation;
use App\Models\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Collections extends Component
{
    public ?int $editingId = null;
    public string $title = '', $description = '', $status = 'draft';
    public bool $is_premium = false;
    public array $selected = [];

    public function edit(int $id)
    {
        $c = Collection::with('affirmations')->findOrFail($id);
        $this->editingId = $c->id; $this->title = $c->title; $this->description = (string) $c->description;
        $this->status = $c->status; $this->is_premium = $c->is_premium;
        $this->selected = $c->affirmations->pluck('id')->map(fn ($i) => (string) $i)->all();
    }

    public function resetForm() { $this->reset(['editingId', 'title', 'description', 'selected']); $this->status = 'draft'; $this->is_premium = false; }

    public function save()
    {
        $this->validate(['title' => 'required|string|max:190', 'status' => 'required|in:draft,published']);

        $c = $this->editingId ? Collection::findOrFail($this->editingId) : new Collection();
        $c->fill(['title' => $this->title, 'description' => $this->description ?: null, 'status' => $this->status, 'is_premium' => $this->is_premium])->save();

        $sync = [];
        foreach (array_values($this->selected) as $i => $id) $sync[(int) $id] = ['position' => $i + 1];
        $c->affirmations()->sync($sync);

        $this->resetForm();
    }

    public function delete(int $id) { Collection::findOrFail($id)->delete(); }

    public function render()
    {
        return view('livewire.admin.collections', [
            'collections' => Collection::withCount('affirmations')->latest()->get(),
            'allAffirmations' => Affirmation::orderBy('title')->get(['id', 'title']),
        ]);
    }
}
