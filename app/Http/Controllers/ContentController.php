<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Faq;
use App\Models\Store;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function blogs(Request $request, SettingsService $settings): Response
    {
        if (! $settings->bool('feature.blog', true)) {
            abort(404);
        }

        $storeSlug = $request->query('toko');

        $blogs = Blog::with(['store:id,name,slug', 'author:id,username'])
            ->where('is_published', true)
            ->when($storeSlug, fn ($q) => $q->whereHas('store', fn ($s) => $s->where('slug', $storeSlug)))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn ($b) => [
                'title' => $b->title,
                'slug' => $b->slug,
                'excerpt' => $b->excerpt,
                'store' => $b->store?->name,
                'store_slug' => $b->store?->slug,
                'author' => $b->author->username,
                'thumbnail' => $b->thumbnail ? Storage::url($b->thumbnail) : null,
                'published_at' => $b->published_at?->format('d M Y'),
            ]);

        return Inertia::render('Public/Blogs', [
            'blogs' => $blogs,
            'stores' => Store::where('is_active', true)->orderBy('sort_order')->get(['id', 'slug', 'name']),
            'currentStore' => $storeSlug,
        ]);
    }

    public function blogDetail(string $slug, SettingsService $settings): Response
    {
        if (! $settings->bool('feature.blog', true)) {
            abort(404);
        }

        $blog = Blog::with(['store:id,name,slug', 'author:id,username'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return Inertia::render('Public/BlogDetail', [
            'blog' => [
                'title' => $blog->title,
                'slug' => $blog->slug,
                'content' => $blog->content,
                'excerpt' => $blog->excerpt,
                'meta_title' => $blog->meta_title ?: $blog->title,
                'meta_description' => $blog->meta_description ?: $blog->excerpt,
                'thumbnail' => $blog->thumbnail ? Storage::url($blog->thumbnail) : null,
                'store' => $blog->store?->name,
                'store_slug' => $blog->store?->slug,
                'author' => $blog->author->username,
                'published_at' => $blog->published_at?->format('d M Y'),
            ],
        ]);
    }

    public function faqs(SettingsService $settings): Response
    {
        if (! $settings->bool('feature.faq', true)) {
            abort(404);
        }

        $faqs = Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'question', 'answer']);

        return Inertia::render('Public/Faqs', [
            'faqs' => $faqs,
        ]);
    }
}
