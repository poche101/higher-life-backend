<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AffirmationResource;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['type' => 'nullable|in:text,audio,video']);

        $q = $request->user()->favorites()->published()->with(['category', 'media'])->orderByPivot('created_at', 'desc');
        if ($request->type) $q->where('content_type', $request->type);

        return AffirmationResource::collection($q->paginate(30));
    }
}
