<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Blogs', [
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'blogs' => Blog::with(['store:id,name', 'author:id,username'])
                ->latest()
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'slug' => $b->slug,
                    'store' => $b->store?->name ?? 'Umum',
                    'author' => $b->author->username,
                    'is_published' => $b->is_published,
                    'published_at' => $b->published_at?->format('d M Y H:i'),
                    'thumbnail' => $b->thumbnail ? Storage::url($b->thumbnail) : null,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'thumbnail' => ['nullable', 'image', 'max:3072'],
            'is_published' => ['boolean'],
        ]);

        $slug = Str::slug($data['title']);
        $originalSlug = $slug;
        $count = 1;
        while (Blog::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-" . (++$count);
        }
        $data['slug'] = $slug;
        $data['author_id'] = $request->user()->id;

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('blogs', 'public');
        }

        if (! empty($data['is_published'])) {
            $data['published_at'] = now();
        }

        Blog::create($data);

        return back()->with('success', 'Artikel blog berhasil dibuat.');
    }

    public function toggle(Blog $blog): RedirectResponse
    {
        $willPublish = ! $blog->is_published;
        $blog->update([
            'is_published' => $willPublish,
            'published_at' => $willPublish ? ($blog->published_at ?? now()) : $blog->published_at,
        ]);

        return back()->with('success', 'Status publikasi artikel diubah.');
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        $blog->delete();

        return back()->with('success', 'Artikel blog dihapus.');
    }
}
