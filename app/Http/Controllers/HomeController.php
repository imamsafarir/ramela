<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Faq;
use App\Models\Store;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(SettingsService $settings): Response
    {
        $stores = Store::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'tagline']);

        $recentBlogs = [];
        if ($settings->bool('feature.blog', true)) {
            $recentBlogs = Blog::with('store:id,name')
                ->where('is_published', true)
                ->latest('published_at')
                ->take(3)
                ->get()
                ->map(fn ($b) => [
                    'title' => $b->title,
                    'slug' => $b->slug,
                    'excerpt' => $b->excerpt,
                    'store' => $b->store?->name,
                    'thumbnail' => $b->thumbnail ? \Illuminate\Support\Facades\Storage::url($b->thumbnail) : null,
                    'published_at' => $b->published_at?->format('d M Y'),
                ]);
        }

        $faqs = [];
        if ($settings->bool('feature.faq', true)) {
            $faqs = Faq::where('is_active', true)
                ->orderBy('sort_order')
                ->take(6)
                ->get(['id', 'question', 'answer']);
        }

        return Inertia::render('Welcome', [
            'stores' => $stores,
            'recentBlogs' => $recentBlogs,
            'faqs' => $faqs,
        ]);
    }
}
