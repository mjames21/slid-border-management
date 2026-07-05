<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Models\BorderPost;
use App\Models\Country;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    use ResolvesTenantScope;

    public function index(Request $request): View
    {
        $selectedCountry = $this->selectedCountryCode($request);

        $users = User::query()
            ->with(['country', 'borderPost.country'])
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'countries' => $this->countriesForUser($request),
            'filters' => ['country_code' => $selectedCountry],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.create', [
            'borderPosts' => BorderPost::query()
                ->where('is_active', true)
                ->when($this->selectedCountryCode($request), fn ($query, string $countryCode) => $query->where('country_code', $countryCode))
                ->orderBy('country_code')
                ->orderBy('name')
                ->get(),
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(10)->mixedCase()->numbers(), 'confirmed'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'border_post_id' => ['required', 'exists:border_posts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $borderPost = BorderPost::query()->findOrFail($validated['border_post_id']);
        $this->assertCanAccessRecordCountry($request, $borderPost);

        $user = DB::transaction(function () use ($validated, $borderPost) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'is_admin' => false,
                'country_code' => $borderPost->country_code,
                'border_post_id' => $validated['border_post_id'],
                'role' => $validated['role'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            $team = Team::forceCreate([
                'user_id' => $user->id,
                'name' => "{$user->name}'s Workspace",
                'personal_team' => true,
            ]);

            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });

        $audit->record('admin.mobile_user_created', $request->user(), $user->borderPost, $user, [
            'role' => $user->role,
            'email' => $user->email,
        ], $request);

        return redirect()->route('admin.users.index')->with('status', 'Border user created successfully.');
    }

    public function setupQr(Request $request, User $user): View
    {
        abort_if($user->is_admin, 404);
        $this->assertCanAccessRecordCountry($request, $user);

        return view('admin.users.setup-qr', [
            'user' => $user->load(['country', 'borderPost']),
            'defaultServerUrl' => $this->defaultServerUrl($request),
            'setup' => null,
        ]);
    }

    public function generateSetupQr(Request $request, User $user, AuditLogger $audit): View
    {
        abort_if($user->is_admin, 404);
        $this->assertCanAccessRecordCountry($request, $user);

        $validated = $request->validate([
            'server_url' => ['required', 'url', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $temporaryPassword = $this->temporaryPassword();
        $serverUrl = $this->normalizedServerUrl($validated['server_url']);
        $deviceName = trim((string) ($validated['device_name'] ?? ''));

        if ($this->isLoopbackHost((string) parse_url($serverUrl, PHP_URL_HOST))) {
            throw ValidationException::withMessages([
                'server_url' => 'Use a phone-reachable IP address or domain, not localhost, 127.0.0.1, or 0.0.0.0.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'is_active' => true,
        ])->save();

        // A setup QR resets mobile credentials, so old app tokens must stop working.
        $user->tokens()->delete();

        $payload = [
            'type' => 'slid_mobile_setup',
            'version' => 1,
            'serverUrl' => $serverUrl,
            'email' => $user->email,
            'password' => $temporaryPassword,
            'deviceName' => $deviceName,
        ];

        $audit->record('admin.mobile_user_setup_qr_generated', $request->user(), $user->borderPost, $user, [
            'email' => $user->email,
            'server_url' => $serverUrl,
        ], $request);

        return view('admin.users.setup-qr', [
            'user' => $user->load(['country', 'borderPost']),
            'defaultServerUrl' => $serverUrl,
            'setup' => [
                'payload' => $payload,
                'payloadJson' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'qrSvg' => $this->qrSvg(json_encode($payload, JSON_UNESCAPED_SLASHES)),
                'temporaryPassword' => $temporaryPassword,
            ],
        ]);
    }

    private function roles(): array
    {
        return [
            'border_officer' => 'Border Officer',
            'border_supervisor' => 'Border Supervisor',
            'regional_supervisor' => 'Regional Supervisor',
        ];
    }

    private function defaultServerUrl(Request $request): string
    {
        $port = $request->getPort();
        $host = $request->getHost();
        $scheme = $request->getScheme();
        $configuredHost = config('app.mobile_setup_host');

        if (is_string($configuredHost) && trim($configuredHost) !== '') {
            return $this->serverUrlFromHost(trim($configuredHost), $scheme, $port);
        }

        if ($this->isLoopbackHost($host)) {
            $host = $this->detectLanIpAddress() ?: $host;
        }

        return $this->serverUrlFromHost($host, $scheme, $port);
    }

    private function serverUrlFromHost(string $host, string $scheme, int $port): string
    {
        if (Str::startsWith($host, ['http://', 'https://'])) {
            return $this->normalizedServerUrl($host);
        }

        $portSuffix = in_array($port, [80, 443], true) ? '' : ":{$port}";

        return "{$scheme}://{$host}{$portSuffix}/";
    }

    private function isLoopbackHost(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    private function detectLanIpAddress(): ?string
    {
        $candidates = array_filter(array_merge(
            [$this->outboundInterfaceIp()],
            $this->hostnameIps(),
            $this->shellDetectedIps()
        ));

        $privateIp = collect($candidates)->first(fn (string $ip) => $this->isPrivateIpv4($ip));

        return $privateIp ?: collect($candidates)->first(fn (string $ip) => $this->isUsableIpv4($ip));
    }

    private function outboundInterfaceIp(): ?string
    {
        $socket = @stream_socket_client('udp://8.8.8.8:80', $errorCode, $errorMessage, 0.2);
        if (!$socket) {
            return null;
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return is_string($name) ? Str::before($name, ':') : null;
    }

    private function hostnameIps(): array
    {
        $ips = @gethostbynamel((string) gethostname());

        return is_array($ips) ? $ips : [];
    }

    private function shellDetectedIps(): array
    {
        // Shell-based network probing is only a local developer convenience.
        // Production deployments should use APP_URL or MOBILE_SETUP_HOST.
        if (!app()->environment('local') || !function_exists('shell_exec')) {
            return [];
        }

        $outputs = [
            @shell_exec('hostname -I 2>/dev/null'),
            @shell_exec('ipconfig getifaddr en0 2>/dev/null'),
            @shell_exec('ipconfig getifaddr en1 2>/dev/null'),
            @shell_exec('ifconfig 2>/dev/null'),
        ];

        return collect($outputs)
            ->filter()
            ->flatMap(fn (string $output) => Str::of($output)->matchAll('/\b(?:\d{1,3}\.){3}\d{1,3}\b/')->all())
            ->filter(fn (string $ip) => $this->isUsableIpv4($ip))
            ->unique()
            ->values()
            ->all();
    }

    private function isUsableIpv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !Str::startsWith($ip, ['127.', '169.254.', '0.']);
    }

    private function isPrivateIpv4(string $ip): bool
    {
        if (!$this->isUsableIpv4($ip)) {
            return false;
        }

        $long = ip2long($ip);

        return $this->ipInRange($long, '10.0.0.0', '10.255.255.255')
            || $this->ipInRange($long, '172.16.0.0', '172.31.255.255')
            || $this->ipInRange($long, '192.168.0.0', '192.168.255.255');
    }

    private function ipInRange(int|false $ip, string $start, string $end): bool
    {
        if ($ip === false) {
            return false;
        }

        return $ip >= ip2long($start) && $ip <= ip2long($end);
    }

    private function normalizedServerUrl(string $serverUrl): string
    {
        return Str::of($serverUrl)->trim()->finish('/')->toString();
    }

    private function temporaryPassword(): string
    {
        return 'SLID-'.Str::upper(Str::random(4)).'-'.random_int(1000, 9999);
    }

    private function qrSvg(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(320),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
