<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AffirmationResource;
use App\Models\Affirmation;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $cats = Category::where('is_active', true)
            ->withCount(['affirmations as affirmations_count' => fn ($q) => $q->published()])
            ->orderBy('name')->get();

        return response()->json(['data' => $cats->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'description' => $c->description, 'affirmations_count' => $c->affirmations_count,
        ])]);
    }

    public function affirmations(Request $request, Category $category)
    {
        $items = Affirmation::published()->with(['category', 'media'])->where('category_id', $category->id)->latest('published_at')->paginate(20);
        return AffirmationResource::collection($items);
    }
}
