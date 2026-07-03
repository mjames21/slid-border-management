<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class CountryBoundaryImporter
{
    private const MAX_BOUNDARY_BYTES = 20 * 1024 * 1024;
    private const MAX_FEATURES = 5000;
    private const MAX_ZIP_ENTRIES = 250;

    public function import(Country $country, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $sourceName = $file->getClientOriginalName();

        $geojson = match ($extension) {
            'json', 'geojson' => $this->normalizeGeoJson((string) file_get_contents($file->getRealPath())),
            'zip' => $this->importZip($file),
            'shp' => $this->featureCollectionFromShp((string) file_get_contents($file->getRealPath())),
            default => throw ValidationException::withMessages([
                'boundary_file' => 'Upload a GeoJSON file, a .shp file, or a .zip containing a shapefile.',
            ]),
        };

        $path = "country-boundaries/{$country->code}.geojson";
        Storage::disk('public')->put($path, json_encode($geojson, JSON_UNESCAPED_SLASHES));

        return [
            'boundary_geojson_path' => $path,
            'boundary_source_name' => $sourceName,
            'boundary_source_type' => $extension,
            'boundary_imported_at' => now(),
        ];
    }

    public function readBoundary(?Country $country): ?array
    {
        if (!$country?->boundary_geojson_path || !Storage::disk('public')->exists($country->boundary_geojson_path)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('public')->get($country->boundary_geojson_path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function importZip(UploadedFile $file): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'boundary_file' => 'ZIP shapefile import requires the PHP zip extension.',
            ]);
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['boundary_file' => 'The uploaded ZIP file could not be opened.']);
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw ValidationException::withMessages([
                    'boundary_file' => 'The ZIP contains too many files for a country boundary upload.',
                ]);
            }

            $geoJsonIndexes = [];
            $shapefileIndexes = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);

                if ($this->shouldIgnoreZipEntry($name)) {
                    continue;
                }

                if (preg_match('/\.(geojson|json)$/i', $name)) {
                    $geoJsonIndexes[] = $index;
                    continue;
                }

                if (preg_match('/\.shp$/i', $name)) {
                    $shapefileIndexes[] = $index;
                }
            }

            foreach ($this->prioritizeBoundaryCandidates($geoJsonIndexes, $zip) as $index) {
                return $this->normalizeGeoJson($this->zipEntryContents($zip, $index));
            }

            foreach ($this->prioritizeBoundaryCandidates($shapefileIndexes, $zip) as $index) {
                try {
                    return $this->featureCollectionFromShp($this->zipEntryContents($zip, $index));
                } catch (ValidationException) {
                    continue;
                }
            }
        } finally {
            $zip->close();
        }

        throw ValidationException::withMessages([
            'boundary_file' => 'The ZIP must contain a .shp shapefile or a GeoJSON file.',
        ]);
    }

    private function normalizeGeoJson(string $contents): array
    {
        if (strlen($contents) > self::MAX_BOUNDARY_BYTES) {
            throw ValidationException::withMessages(['boundary_file' => 'The boundary file is too large after decompression.']);
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages(['boundary_file' => 'The boundary file is not valid JSON.']);
        }

        $features = $this->extractPolygonFeatures($decoded);

        if ($features === []) {
            throw ValidationException::withMessages([
                'boundary_file' => 'The boundary must contain Polygon or MultiPolygon geometry.',
            ]);
        }

        if (count($features) > self::MAX_FEATURES) {
            throw ValidationException::withMessages([
                'boundary_file' => 'The boundary contains too many polygon features.',
            ]);
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    private function extractPolygonFeatures(array $geojson): array
    {
        $type = $geojson['type'] ?? null;

        if ($type === 'FeatureCollection') {
            return collect($geojson['features'] ?? [])
                ->filter(fn ($feature) => is_array($feature))
                ->flatMap(fn ($feature) => $this->extractPolygonFeatures($feature))
                ->values()
                ->all();
        }

        if ($type === 'Feature') {
            $geometry = $geojson['geometry'] ?? null;
            if (!is_array($geometry)) {
                return [];
            }

            return collect($this->extractPolygonFeatures($geometry))
                ->map(function (array $feature) use ($geojson) {
                    $feature['properties'] = array_merge($geojson['properties'] ?? [], $feature['properties'] ?? []);

                    return $feature;
                })
                ->all();
        }

        if ($type === 'GeometryCollection') {
            return collect($geojson['geometries'] ?? [])
                ->filter(fn ($geometry) => is_array($geometry))
                ->flatMap(fn ($geometry) => $this->extractPolygonFeatures($geometry))
                ->values()
                ->all();
        }

        if (in_array($type, ['Polygon', 'MultiPolygon'], true) && is_array($geojson['coordinates'] ?? null)) {
            return [[
                'type' => 'Feature',
                'properties' => [],
                'geometry' => [
                    'type' => $type,
                    'coordinates' => $this->closeGeometryRings($geojson['coordinates'], $type),
                ],
            ]];
        }

        return [];
    }

    private function closeGeometryRings(array $coordinates, string $type): array
    {
        if ($type === 'Polygon') {
            return array_map(fn ($ring) => $this->closeRing($ring), $coordinates);
        }

        return array_map(
            fn ($polygon) => array_map(fn ($ring) => $this->closeRing($ring), $polygon),
            $coordinates
        );
    }

    private function closeRing(array $ring): array
    {
        if (count($ring) < 3) {
            return $ring;
        }

        $first = $ring[0];
        $last = $ring[count($ring) - 1];

        if ($first !== $last) {
            $ring[] = $first;
        }

        return $ring;
    }

    private function featureCollectionFromShp(string $binary): array
    {
        if (strlen($binary) > self::MAX_BOUNDARY_BYTES) {
            throw ValidationException::withMessages(['boundary_file' => 'The shapefile is too large after decompression.']);
        }

        if (strlen($binary) < 100) {
            throw ValidationException::withMessages(['boundary_file' => 'The shapefile is too small to be valid.']);
        }

        $features = [];
        $offset = 100;
        $length = strlen($binary);

        while ($offset + 8 <= $length) {
            $contentLengthWords = $this->readUInt32BE($binary, $offset + 4);
            $contentLength = $contentLengthWords * 2;
            $recordOffset = $offset + 8;

            if ($contentLength <= 0 || $recordOffset + $contentLength > $length) {
                break;
            }

            $shapeType = $this->readInt32LE($binary, $recordOffset);
            if (in_array($shapeType, [5, 15, 25], true)) {
                $features[] = $this->polygonFeatureFromRecord($binary, $recordOffset, $contentLength);
            }

            $offset = $recordOffset + $contentLength;
        }

        $features = array_values(array_filter($features));

        if ($features === []) {
            throw ValidationException::withMessages([
                'boundary_file' => 'Only polygon shapefiles are supported for country boundaries.',
            ]);
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    private function zipEntryContents(ZipArchive $zip, int $index): string
    {
        $name = (string) $zip->getNameIndex($index);

        if ($this->isUnsafeZipEntryName($name)) {
            throw ValidationException::withMessages(['boundary_file' => 'The ZIP contains an unsafe file path.']);
        }

        $stat = $zip->statIndex($index);
        $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;

        if ($size <= 0 || $size > self::MAX_BOUNDARY_BYTES) {
            throw ValidationException::withMessages(['boundary_file' => 'The ZIP contains a boundary file that is too large.']);
        }

        $contents = $zip->getFromIndex($index);

        if (!is_string($contents)) {
            throw ValidationException::withMessages(['boundary_file' => 'The ZIP boundary file could not be read.']);
        }

        return $contents;
    }

    private function isUnsafeZipEntryName(string $name): bool
    {
        return str_starts_with($name, '/')
            || str_contains($name, '../')
            || str_contains($name, '..\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $name) === 1;
    }

    private function shouldIgnoreZipEntry(string $name): bool
    {
        $basename = basename($name);

        return str_ends_with($name, '/')
            || str_starts_with($name, '__MACOSX/')
            || $basename === '.DS_Store'
            || str_starts_with($basename, '._');
    }

    /**
     * Real GIS exports often contain several polygon layers. Prefer country or
     * region-level layers before detailed districts, chiefdoms, or sections.
     *
     * @param  array<int, int>  $indexes
     * @return array<int, int>
     */
    private function prioritizeBoundaryCandidates(array $indexes, ZipArchive $zip): array
    {
        usort($indexes, function (int $left, int $right) use ($zip): int {
            $leftName = strtolower((string) $zip->getNameIndex($left));
            $rightName = strtolower((string) $zip->getNameIndex($right));

            return $this->boundaryLayerRank($leftName) <=> $this->boundaryLayerRank($rightName)
                ?: substr_count($leftName, '/') <=> substr_count($rightName, '/')
                ?: $leftName <=> $rightName;
        });

        return $indexes;
    }

    private function boundaryLayerRank(string $name): int
    {
        $baseName = pathinfo(basename($name), PATHINFO_FILENAME);

        return match (true) {
            str_contains($baseName, 'country'),
            str_contains($baseName, 'national'),
            str_contains($baseName, 'boundary') => 0,
            str_contains($baseName, 'region') => 10,
            str_contains($baseName, 'district') => 20,
            str_contains($baseName, 'chiefdom') => 30,
            str_contains($baseName, 'section') => 40,
            default => 50,
        };
    }

    private function polygonFeatureFromRecord(string $binary, int $offset, int $contentLength): ?array
    {
        if ($contentLength < 44) {
            return null;
        }

        $numParts = $this->readInt32LE($binary, $offset + 36);
        $numPoints = $this->readInt32LE($binary, $offset + 40);

        if ($numParts <= 0 || $numPoints <= 0 || $numParts > 10000 || $numPoints > 500000) {
            return null;
        }

        $partsOffset = $offset + 44;
        $pointsOffset = $partsOffset + ($numParts * 4);
        $pointsEnd = $pointsOffset + ($numPoints * 16);

        if ($pointsEnd > $offset + $contentLength) {
            return null;
        }

        $parts = [];
        for ($i = 0; $i < $numParts; $i++) {
            $parts[] = $this->readInt32LE($binary, $partsOffset + ($i * 4));
        }

        $points = [];
        for ($i = 0; $i < $numPoints; $i++) {
            $pointOffset = $pointsOffset + ($i * 16);
            $points[] = [
                $this->readDoubleLE($binary, $pointOffset),
                $this->readDoubleLE($binary, $pointOffset + 8),
            ];
        }

        $polygons = [];
        for ($i = 0; $i < $numParts; $i++) {
            $start = $parts[$i];
            $end = $parts[$i + 1] ?? $numPoints;
            $ring = array_slice($points, $start, $end - $start);

            if (count($ring) >= 3) {
                $polygons[] = [$this->closeRing($ring)];
            }
        }

        if ($polygons === []) {
            return null;
        }

        return [
            'type' => 'Feature',
            'properties' => [],
            'geometry' => count($polygons) === 1
                ? ['type' => 'Polygon', 'coordinates' => $polygons[0]]
                : ['type' => 'MultiPolygon', 'coordinates' => $polygons],
        ];
    }

    private function readUInt32BE(string $binary, int $offset): int
    {
        return unpack('N', substr($binary, $offset, 4))[1];
    }

    private function readInt32LE(string $binary, int $offset): int
    {
        $value = unpack('V', substr($binary, $offset, 4))[1];

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private function readDoubleLE(string $binary, int $offset): float
    {
        return unpack('e', substr($binary, $offset, 8))[1];
    }
}
