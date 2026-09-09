<?php

namespace App\Services\PerfilPublico;

/**
 * Temas visuales para la página pública de perfil de empresa.
 * Paletas alineadas al catálogo de presupuestos (mismas keys conocidas).
 */
final class PerfilPublicoThemeService
{
    public const DEFAULT_THEME_KEY = 'corporativo';

    /**
     * @var array<string, array{name: string, key: string, description: string, variables: array<string, string>}>
     */
    private const THEMES = [
        'corporativo' => [
            'name' => 'Azul Institucional',
            'key' => 'corporativo',
            'description' => 'Claro y profesional para presentaciones B2B.',
            'variables' => [
                'color-bg' => '#f4f9f9',
                'color-card' => '#ffffff',
                'color-primary' => '#2563eb',
                'color-primary-dark' => '#1d4ed8',
                'color-primary-soft' => '#eff6ff',
                'color-heading' => '#1e3a5f',
                'color-text' => '#2f3640',
                'color-muted' => '#64748b',
                'color-border' => '#e2e8f0',
            ],
        ],
        'finanzas-confianza' => [
            'name' => 'Finanzas y Confianza',
            'key' => 'finanzas-confianza',
            'description' => 'Verde sólido para transmitir confianza.',
            'variables' => [
                'color-bg' => '#f4f9f9',
                'color-card' => '#ffffff',
                'color-primary' => '#047857',
                'color-primary-dark' => '#065f46',
                'color-primary-soft' => '#ecfdf5',
                'color-heading' => '#064e3b',
                'color-text' => '#2f3640',
                'color-muted' => '#64748b',
                'color-border' => '#e2e8f0',
            ],
        ],
        'tecnologia-digital' => [
            'name' => 'Tecnología Digital',
            'key' => 'tecnologia-digital',
            'description' => 'Acento contemporáneo para equipos digitales.',
            'variables' => [
                'color-bg' => '#f4f9f9',
                'color-card' => '#ffffff',
                'color-primary' => '#0891b2',
                'color-primary-dark' => '#0e7490',
                'color-primary-soft' => '#ecfeff',
                'color-heading' => '#155e75',
                'color-text' => '#2f3640',
                'color-muted' => '#64748b',
                'color-border' => '#e2e8f0',
            ],
        ],
        'premium-lujo' => [
            'name' => 'Premium',
            'key' => 'premium-lujo',
            'description' => 'Tonos cálidos y sobrios para una imagen premium.',
            'variables' => [
                'color-bg' => '#faf8f5',
                'color-card' => '#ffffff',
                'color-primary' => '#92400e',
                'color-primary-dark' => '#78350f',
                'color-primary-soft' => '#fffbeb',
                'color-heading' => '#451a03',
                'color-text' => '#2f3640',
                'color-muted' => '#78716c',
                'color-border' => '#e7e5e4',
            ],
        ],
        'reporte-ejecutivo' => [
            'name' => 'Ejecutivo',
            'key' => 'reporte-ejecutivo',
            'description' => 'Grises y acento marcado para un look ejecutivo.',
            'variables' => [
                'color-bg' => '#f8fafc',
                'color-card' => '#ffffff',
                'color-primary' => '#334155',
                'color-primary-dark' => '#1e293b',
                'color-primary-soft' => '#f1f5f9',
                'color-heading' => '#0f172a',
                'color-text' => '#2f3640',
                'color-muted' => '#64748b',
                'color-border' => '#e2e8f0',
            ],
        ],
        'caterpillar' => [
            'name' => 'Caterpillar',
            'key' => 'caterpillar',
            'description' => 'Amarillo CAT (#FFCD11) y negro CAT (#1D1D1B) para imagen industrial.',
            'variables' => [
                'color-bg' => '#fafaf9',
                'color-card' => '#ffffff',
                'color-primary' => '#ffcd11',
                'color-primary-dark' => '#e0b800',
                'color-primary-soft' => '#fff8d6',
                'color-heading' => '#1d1d1b',
                'color-text' => '#1d1d1b',
                'color-muted' => '#4a4a47',
                'color-border' => '#e7e5e4',
            ],
        ],
    ];

    /**
     * @return list<array{name: string, key: string, description: string, variables: array<string, string>}>
     */
    public function getThemes(): array
    {
        return array_values(self::THEMES);
    }

    public function getDefaultThemeKey(): string
    {
        return self::DEFAULT_THEME_KEY;
    }

    public function resolveThemeKey(?string $key): string
    {
        $key = trim((string) $key);
        if ($key !== '' && isset(self::THEMES[$key])) {
            return $key;
        }

        return self::DEFAULT_THEME_KEY;
    }

    /**
     * @return array{name: string, key: string, description: string, variables: array<string, string>}
     */
    public function getTheme(string $key): array
    {
        $resolved = $this->resolveThemeKey($key);

        return self::THEMES[$resolved];
    }

    public function isValidThemeKey(?string $key): bool
    {
        $key = trim((string) $key);

        return $key !== '' && isset(self::THEMES[$key]);
    }
}
