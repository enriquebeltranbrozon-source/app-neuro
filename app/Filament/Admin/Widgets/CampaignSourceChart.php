<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class CampaignSourceChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Distribución por Fuente de Adquisición';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $sources = Lead::selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->toArray();

        $labels = array_map(fn($source) => match($source) {
            'google_ads'       => 'Google Ads',
            'meta_ads'         => 'Meta / Facebook Ads',
            'whatsapp_directo' => 'WhatsApp Directo',
            'web_organico'     => 'Web Orgánico',
            'referido'         => 'Referido',
            default            => ucfirst($source),
        }, array_keys($sources));

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data'  => array_values($sources),
                    'backgroundColor' => [
                        '#4285F4', // Google Blue
                        '#1877F2', // Meta Blue
                        '#25D366', // WhatsApp Green
                        '#10B981', // Emerald
                        '#F59E0B', // Amber
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}