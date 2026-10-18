<?php

namespace App\Livewire\Admin;

use App\Models\Affirmation;
use App\Models\AffirmationMedia;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Affirmations extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $title = '', $description = '', $content = '', $content_type = 'text', $author = '', $status = 'draft';
    public $category_id = '';
    public bool $is_premium = false;
    public $duration = null;
    public $thumbnailFile = null;
    public $mediaFile = null;

    public function updatingSearch() { $this->resetPage(); }

    public function create()
    {
        $this->reset(['editingId', 'title', 'description', 'content', 'content_type', 'author', 'status', 'category_id', 'is_premium', 'duration', 'thumbnailFile', 'mediaFile']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id)
    {
        $a = Affirmation::with('media')->findOrFail($id);
        $this->editingId = $a->id;
        $this->title = $a->title;
        $this->description = (string) $a->description;
        $this->content = (string) $a->content;
        $this->content_type = $a->content_type;
        $this->author = (string) $a->author;
        $this->status = $a->status;
        $this->category_id = $a->category_id ?? '';
        $this->is_premium = $a->is_premium;
        $this->duration = $a->media->first()?->duration;
        $this->thumbnailFile = $this->mediaFile = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:190',
            'content_type' => config('higherlife.text_only', true) ? 'required|in:text' : 'required|in:text,audio,video',
            'content' => 'required_if:content_type,text|nullable|string|max:50000',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:draft,published',
            'thumbnailFile' => 'nullable|image|max:4096',
            'mediaFile' => 'nullable|file|max:'.(1024 * 12).'|mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/aac,video/mp4,video/quicktime,video/webm',
        ]);

        $disk = config('espee.media_disk');
        $data = [
            'title' => $this->title, 'description' => $this->description ?: null, 'content' => $this->content ?: null,
            'content_type' => $this->content_type, 'author' => $this->author ?: null, 'status' => $this->status,
            'category_id' => $this->category_id ?: null, 'is_premium' => $this->is_premium,
        ];

        $a = $this->editingId ? Affirmation::findOrFail($this->editingId) : new Affirmation();
        $a->fill($data);
        if ($this->status === 'published' && ! $a->published_at) $a->published_at = now();
        if ($this->thumbnailFile) $a->thumbnail = $this->thumbnailFile->store('thumbnails', $disk);
        $a->save();

        if ($this->content_type !== 'text') {
            $media = $a->media()->first();
            if ($this->mediaFile) {
                $path = $this->mediaFile->store('media/'.$this->content_type, $disk);
                $payload = ['type' => $this->content_type, 'file_url' => $path, 'file_size' => $this->mediaFile->getSize(), 'mime_type' => $this->mediaFile->getMimeType(), 'duration' => $this->duration ?: null];
                $media ? $media->update($payload) : $a->media()->create($payload);
            } elseif ($media) {
                $media->update(['duration' => $this->duration ?: null]);
            }
        }

        $this->showForm = false;
        session()->flash('ok', 'Affirmation saved.');
    }

    public function delete(int $id)
    {
        Affirmation::findOrFail($id)->delete();
        session()->flash('ok', 'Affirmation deleted.');
    }

    public function render()
    {
        $items = Affirmation::with('category')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$this->search}%")->orWhere('content', 'like', "%{$this->search}%")))
            ->latest()->paginate(12);

        return view('livewire.admin.affirmations', ['items' => $items, 'categories' => Category::orderBy('name')->get()]);
    }
}
