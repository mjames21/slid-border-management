<?php

namespace App\Services;

class BorderRegionCatalog
{
    /**
     * District labels used by border-post setup and reporting filters.
     *
     * The value format intentionally matches the seeded border-post records so
     * mobile assignments, dashboard filters, and imported reports speak the
     * same administrative language.
     */
    public function options(): array
    {
        return [
            'WEST - Western Area' => [
                'Western Area Urban / WEST - Western Area',
                'Western Area Rural / WEST - Western Area',
            ],
            'NORTH_WEST - North West Province' => [
                'Kambia / NORTH_WEST - North West Province',
                'Karene / NORTH_WEST - North West Province',
                'Port Loko / NORTH_WEST - North West Province',
            ],
            'NORTH - Northern Province' => [
                'Bombali / NORTH - Northern Province',
                'Falaba / NORTH - Northern Province',
                'Koinadugu / NORTH - Northern Province',
                'Tonkolili / NORTH - Northern Province',
            ],
            'EAST - Eastern Province' => [
                'Kailahun / EAST - Eastern Province',
                'Kenema / EAST - Eastern Province',
                'Kono / EAST - Eastern Province',
            ],
            'SOUTH - Southern Province' => [
                'Bo / SOUTH - Southern Province',
                'Bonthe / SOUTH - Southern Province',
                'Moyamba / SOUTH - Southern Province',
                'Pujehun / SOUTH - Southern Province',
            ],
        ];
    }

    public function values(): array
    {
        return collect($this->options())->flatten()->values()->all();
    }
}
