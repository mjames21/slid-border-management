<?php

namespace Database\Seeders;

use App\Models\BorderPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BorderPostSeeder extends Seeder
{
    /**
     * Seed Sierra Leone's current SLID operational border-post master list.
     *
     * These records were ported from the earlier SIMIS border-post master data.
     * Keep this seeder idempotent: it must be safe to run during local setup,
     * demo refreshes, and production rollout rehearsals without creating duplicates.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->sierraLeoneBorderPosts() as $post) {
                BorderPost::query()->updateOrCreate(
                    ['code' => $post['code']],
                    [
                        'country_code' => 'SLE',
                        'digital_address' => $this->digitalAddress('SLE', $post['code']),
                        'name' => $post['name'],
                        'region' => $this->regionLabel($post),
                        'latitude' => $post['latitude'] ?? null,
                        'longitude' => $post['longitude'] ?? null,
                        'allowed_radius_meters' => null,
                        'is_active' => true,
                    ]
                );
            }

            $this->removeLegacyFalabaPlaceholder();
        });
    }

    /**
     * The type is preserved in the code suffix until the Laravel schema gets
     * dedicated type/district/province columns.
     *
     * @return array<int, array{code: string, name: string, type: string, district: string, region_code: string, province: string}>
     */
    private function sierraLeoneBorderPosts(): array
    {
        return [
            [
                'code' => 'LUN-AIR',
                'name' => 'Freetown International Airport',
                'type' => 'Air',
                'district' => 'Port Loko',
                'region_code' => 'NORTH_WEST',
                'province' => 'North West Province',
            ],
            [
                'code' => 'QEQ-SEA',
                'name' => 'Queen Elizabeth II Quay',
                'type' => 'Sea',
                'district' => 'Western Area Urban',
                'region_code' => 'WEST',
                'province' => 'Western Area',
            ],
            [
                'code' => 'SBY-SEA',
                'name' => "Susan's Bay",
                'type' => 'Sea',
                'district' => 'Western Area Urban',
                'region_code' => 'WEST',
                'province' => 'Western Area',
            ],
            [
                'code' => 'GBM-LND',
                'name' => 'Gbalamuya',
                'type' => 'Land',
                'district' => 'Kambia',
                'region_code' => 'NORTH_WEST',
                'province' => 'North West Province',
            ],
            [
                'code' => 'SNY-LND',
                'name' => 'Sanya',
                'type' => 'Land',
                'district' => 'Bombali',
                'region_code' => 'NORTH',
                'province' => 'Northern Province',
            ],
            [
                'code' => 'DGL-LND',
                'name' => 'Dogolaya',
                'type' => 'Land',
                'district' => 'Koinadugu',
                'region_code' => 'NORTH',
                'province' => 'Northern Province',
            ],
            [
                'code' => 'NJB-LND',
                'name' => 'Njagbema',
                'type' => 'Land',
                'district' => 'Kono',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
            ],
            [
                'code' => 'FAL-LND',
                'name' => 'Falaba',
                'type' => 'Land',
                'district' => 'Falaba',
                'region_code' => 'NORTH',
                'province' => 'Northern Province',
            ],
            [
                'code' => 'KNU-LND',
                'name' => 'Koinukura',
                'type' => 'Land',
                'district' => 'Falaba',
                'region_code' => 'NORTH',
                'province' => 'Northern Province',
            ],
            [
                'code' => 'YEN-LND',
                'name' => 'Yenga',
                'type' => 'Land',
                'district' => 'Kailahun',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
                'latitude' => 8.4938,
                'longitude' => -10.3314,
            ],
            [
                'code' => 'JDM-LND',
                'name' => 'Jendema',
                'type' => 'Land',
                'district' => 'Pujehun',
                'region_code' => 'SOUTH',
                'province' => 'Southern Province',
            ],
            [
                'code' => 'KOI-LND',
                'name' => 'Koindu',
                'type' => 'Land',
                'district' => 'Kailahun',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
            ],
            [
                'code' => 'BEN-LND',
                'name' => 'Bendu',
                'type' => 'Land',
                'district' => 'Kailahun',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
            ],
            [
                'code' => 'BAI-LND',
                'name' => 'Bailu',
                'type' => 'Land',
                'district' => 'Kailahun',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
            ],
            [
                'code' => 'BAD-LND',
                'name' => 'Baidu',
                'type' => 'Land',
                'district' => 'Kailahun',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
            ],
            [
                'code' => 'DAW-LND',
                'name' => 'Dawa',
                'type' => 'Land',
                'district' => 'Kailahun',
                'region_code' => 'EAST',
                'province' => 'Eastern Province',
            ],
        ];
    }

    private function regionLabel(array $post): string
    {
        return "{$post['district']} / {$post['region_code']} - {$post['province']}";
    }

    private function removeLegacyFalabaPlaceholder(): void
    {
        $legacyPost = BorderPost::query()->where('code', 'FAL_FALABA')->first();
        $replacementPost = BorderPost::query()->where('code', 'BEN-LND')->first();

        if (! $legacyPost || ! $replacementPost) {
            return;
        }

        // Preserve user access when the initial MVP placeholder is removed.
        User::query()
            ->where('border_post_id', $legacyPost->id)
            ->update(['border_post_id' => $replacementPost->id]);

        $hasOperationalHistory = DB::table('mobile_devices')
            ->where('border_post_id', $legacyPost->id)
            ->exists()
            || DB::table('mobile_submissions')
                ->where('border_post_id', $legacyPost->id)
                ->exists();

        if ($hasOperationalHistory) {
            $legacyPost->forceFill([
                'is_active' => false,
                'region' => 'Legacy MVP placeholder - retained for audit history',
                'digital_address' => $legacyPost->digital_address ?: $this->digitalAddress($legacyPost->country_code ?: 'SLE', $legacyPost->code),
            ])->save();

            return;
        }

        $legacyPost->delete();
    }

    private function digitalAddress(string $countryCode, string $postCode): string
    {
        return strtoupper(str_replace('_', '-', "{$countryCode}-BP-{$postCode}"));
    }
}
