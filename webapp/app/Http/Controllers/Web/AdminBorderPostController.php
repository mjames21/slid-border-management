<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Models\BorderPost;
use App\Models\Country;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminBorderPostController extends Controller
{
    use ResolvesTenantScope;

    public function index(Request $request): View
    {
        $selectedCountry = $this->selectedCountryCode($request);

        $borderPosts = BorderPost::query()
            ->with('country')
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->orderBy('country_code')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.border-posts.index', [
            'borderPosts' => $borderPosts,
            'countries' => $this->countriesForUser($request),
            'filters' => ['country_code' => $selectedCountry],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.border-posts.form', [
            'borderPost' => new BorderPost(['is_active' => true, 'allowed_radius_meters' => 250]),
            'countries' => $this->countriesForUser($request),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateBorderPost($request);
        $this->assertCanAccessCountry($request, $validated['country_code']);

        $borderPost = BorderPost::query()->create($validated);

        $audit->record('admin.border_post_created', $request->user(), $borderPost, metadata: [
            'code' => $borderPost->code,
            'digital_address' => $borderPost->digital_address,
            'country_code' => $borderPost->country_code,
        ], request: $request);

        return redirect()->route('admin.border-posts.index')->with('status', 'Border post created.');
    }

    public function edit(Request $request, BorderPost $borderPost): View
    {
        $this->assertCanAccessRecordCountry($request, $borderPost);

        return view('admin.border-posts.form', [
            'borderPost' => $borderPost,
            'countries' => $this->countriesForUser($request),
        ]);
    }

    public function update(Request $request, BorderPost $borderPost, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateBorderPost($request, $borderPost);
        $this->assertCanAccessRecordCountry($request, $borderPost);
        $this->assertCanAccessCountry($request, $validated['country_code']);

        $borderPost->fill($validated)->save();

        $audit->record('admin.border_post_updated', $request->user(), $borderPost, metadata: [
            'code' => $borderPost->code,
            'digital_address' => $borderPost->digital_address,
            'country_code' => $borderPost->country_code,
        ], request: $request);

        return redirect()->route('admin.border-posts.index')->with('status', 'Border post updated.');
    }

    private function validateBorderPost(Request $request, ?BorderPost $borderPost = null): array
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:3', 'exists:countries,code'],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('border_posts', 'code')->ignore($borderPost),
            ],
            'digital_address' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('border_posts', 'digital_address')->ignore($borderPost),
            ],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'allowed_radius_meters' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['country_code'] = strtoupper($validated['country_code']);
        $validated['digital_address'] = $this->normalizeDigitalAddress(
            $validated['digital_address'] ?? '',
            $validated['country_code'],
            $validated['code']
        );
        $validated['region'] = $validated['region'] ?? null;
        $validated['latitude'] = $validated['latitude'] ?? null;
        $validated['longitude'] = $validated['longitude'] ?? null;
        $validated['allowed_radius_meters'] = $validated['allowed_radius_meters'] ?? null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    private function normalizeDigitalAddress(string $value, string $countryCode, string $postCode): string
    {
        $address = trim($value);

        if ($address === '') {
            $address = "{$countryCode}-BP-{$postCode}";
        }

        // Keep addresses stable, readable, and QR/API friendly across deployments.
        return Str::upper(Str::slug($address, '-'));
    }

}
