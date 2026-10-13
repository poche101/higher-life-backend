<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Categories extends Component
{
    public ?int $editingId = null;
    public string $name = '', $description = '';
    public bool $is_active = true;

    public function edit(int $id)
    {
        $c = Category::findOrFail($id);
        $this->editingId = $c->id; $this->name = $c->name; $this->description = (string) $c->description; $this->is_active = $c->is_active;
    }

    public function resetForm() { $this->reset(['editingId', 'name', 'description']); $this->is_active = true; }

    public function save()
    {
        $this->validate(['name' => 'required|string|max:80']);
        $slug = Str::slug($this->name);

        $c = $this->editingId ? Category::findOrFail($this->editingId) : new Category();
        $c->fill(['name' => $this->name, 'slug' => $slug, 'description' => $this->description ?: null, 'is_active' => $this->is_active])->save();
        $this->resetForm();
    }

    public function delete(int $id) { Category::findOrFail($id)->delete(); }

    public function render()
    {
        return view('livewire.admin.categories', ['categories' => Category::withCount('affirmations')->orderBy('name')->get()]);
    }
}
