<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Faq;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'blogs');

        // Blog Filters & Sorting
        $q = $request->query('q');
        $storeId = $request->query('store');
        $status = $request->query('status');
        $sort = in_array($request->query('sort'), ['title', 'store', 'published_at', 'status', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $blogsQuery = Blog::with(['store:id,name', 'author:id,username'])
            ->when($storeId === 'general', fn ($w) => $w->whereNull('store_id'))
            ->when($storeId && $storeId !== 'general', fn ($w) => $w->where('store_id', $storeId))
            ->when($status === 'published', fn ($w) => $w->where('is_published', true))
            ->when($status === 'draft', fn ($w) => $w->where('is_published', false))
            ->when($q, function ($query, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('title', 'like', $like)
                      ->orWhere('excerpt', 'like', $like)
                      ->orWhere('content', 'like', $like);
                });
            });

        if ($sort === 'title') {
            $blogsQuery->orderBy('title', $dir);
        } elseif ($sort === 'published_at') {
            $blogsQuery->orderBy('published_at', $dir);
        } elseif ($sort === 'status') {
            $blogsQuery->orderBy('is_published', $dir);
        } else {
            $blogsQuery->orderBy('created_at', $dir);
        }

        $blogs = $blogsQuery->get()->map(fn ($b) => [
            'id' => $b->id,
            'title' => $b->title,
            'slug' => $b->slug,
            'store' => $b->store?->name ?? 'Umum',
            'store_id' => $b->store_id,
            'author' => $b->author?->username ?? 'Admin',
            'is_published' => $b->is_published,
            'published_at' => $b->published_at?->format('d M Y H:i'),
            'created_at' => $b->created_at?->format('d M Y H:i'),
            'thumbnail' => $b->thumbnail ? Storage::url($b->thumbnail) : null,
        ]);

        // FAQ Filters & Sorting
        $faqQ = $request->query('faq_q');
        $faqStatus = $request->query('faq_status');
        $faqSort = in_array($request->query('faq_sort'), ['sort_order', 'question', 'status', 'created_at'], true)
            ? $request->query('faq_sort')
            : 'sort_order';
        $faqDir = $request->query('faq_dir') === 'desc' ? 'desc' : 'asc';

        $faqsQuery = Faq::query()
            ->when($faqStatus === 'active', fn ($w) => $w->where('is_active', true))
            ->when($faqStatus === 'inactive', fn ($w) => $w->where('is_active', false))
            ->when($faqQ, function ($query, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('question', 'like', $like)
                      ->orWhere('answer', 'like', $like);
                });
            });

        if ($faqSort === 'question') {
            $faqsQuery->orderBy('question', $faqDir);
        } elseif ($faqSort === 'status') {
            $faqsQuery->orderBy('is_active', $faqDir);
        } elseif ($faqSort === 'created_at') {
            $faqsQuery->orderBy('created_at', $faqDir);
        } else {
            $faqsQuery->orderBy('sort_order', $faqDir)->orderBy('id');
        }

        $faqs = $faqsQuery->get()->map(fn ($f) => [
            'id' => $f->id,
            'question' => $f->question,
            'answer' => $f->answer,
            'sort_order' => $f->sort_order,
            'is_active' => $f->is_active,
            'created_at' => $f->created_at?->format('d M Y H:i'),
        ]);

        $stores = Store::orderBy('sort_order')->get(['id', 'name']);

        $stats = [
            'total_blogs' => Blog::count(),
            'published_blogs' => Blog::where('is_published', true)->count(),
            'draft_blogs' => Blog::where('is_published', false)->count(),
            'total_faqs' => Faq::count(),
            'active_faqs' => Faq::where('is_active', true)->count(),
            'inactive_faqs' => Faq::where('is_active', false)->count(),
        ];

        return Inertia::render('Admin/Blogs', [
            'blogs' => $blogs,
            'faqs' => $faqs,
            'stores' => $stores,
            'stats' => $stats,
            'filters' => [
                'tab' => $tab,
                'q' => $q ?? '',
                'store' => $storeId ?? '',
                'status' => $status ?? '',
                'sort' => $sort,
                'dir' => $dir,
                'faq_q' => $faqQ ?? '',
                'faq_status' => $faqStatus ?? '',
                'faq_sort' => $faqSort,
                'faq_dir' => $faqDir,
            ],
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
