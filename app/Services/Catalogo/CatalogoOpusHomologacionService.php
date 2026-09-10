<?php

namespace App\Services\Catalogo;

use App\Models\CatalogoFamilia;
use App\Models\CatalogoSubfamilia;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Homologación opcional de textos de import/categoría local
 * contra el catálogo global OPUS (familia / subfamilia).
 */
class CatalogoOpusHomologacionService
{
    private ?Collection $familiasByNorm = null;

    /** @var array<int, Collection> */
    private array $subfamiliasByFamilia = [];

    public function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = Str::lower($value);
        $value = Str::ascii($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * @return array{familia_id: ?int, subfamilia_id: ?int, matched: bool, warning: ?string}
     */
    public function match(?string $familiaOrCategoria, ?string $subfamiliaOrSubcategoria = null): array
    {
        $this->boot();

        $famRaw = trim((string) $familiaOrCategoria);
        $subRaw = trim((string) $subfamiliaOrSubcategoria);

        $result = [
            'familia_id' => null,
            'subfamilia_id' => null,
            'matched' => false,
            'warning' => null,
        ];

        if ($famRaw === '' && $subRaw === '') {
            return $result;
        }

        $familiaId = null;
        if ($famRaw !== '') {
            $norm = $this->normalize($famRaw);
            $matches = $this->familiasByNorm->get($norm, collect());
            if ($matches->count() === 1) {
                $familiaId = (int) $matches->first()->id;
            } elseif ($matches->count() > 1) {
                $result['warning'] = "Familia/categoría '{$famRaw}' ambigua en catálogo OPUS";

                return $result;
            } else {
                $result['warning'] = "Sin match OPUS para familia/categoría '{$famRaw}'";
            }
        }

        if ($familiaId === null && $subRaw !== '') {
            // Buscar subfamilia global por nombre si no hubo familia
            $normSub = $this->normalize($subRaw);
            $hits = CatalogoSubfamilia::query()
                ->where('activo', true)
                ->get()
                ->filter(fn ($s) => $this->normalize($s->nombre) === $normSub);

            if ($hits->count() === 1) {
                $sub = $hits->first();
                $result['familia_id'] = (int) $sub->familia_id;
                $result['subfamilia_id'] = (int) $sub->id;
                $result['matched'] = true;
                $result['warning'] = null;

                return $result;
            }
            if ($hits->count() > 1) {
                $result['warning'] = "Subfamilia '{$subRaw}' ambigua en catálogo OPUS";
            } elseif ($result['warning'] === null) {
                $result['warning'] = "Sin match OPUS para subfamilia '{$subRaw}'";
            }

            return $result;
        }

        if ($familiaId === null) {
            return $result;
        }

        $result['familia_id'] = $familiaId;

        if ($subRaw === '') {
            $result['matched'] = true;

            return $result;
        }

        $subs = $this->subfamiliasFor($familiaId);
        $normSub = $this->normalize($subRaw);
        $subMatches = $subs->filter(fn ($s) => $this->normalize($s->nombre) === $normSub);

        if ($subMatches->count() === 1) {
            $result['subfamilia_id'] = (int) $subMatches->first()->id;
            $result['matched'] = true;
            $result['warning'] = null;

            return $result;
        }

        if ($subMatches->count() > 1) {
            $result['warning'] = "Subfamilia '{$subRaw}' ambigua bajo la familia OPUS";
        } else {
            $result['matched'] = true; // familia sí; sub no
            $result['warning'] = "Familia OPUS asignada; sin match de subfamilia '{$subRaw}'";
        }

        return $result;
    }

    private function boot(): void
    {
        if ($this->familiasByNorm !== null) {
            return;
        }

        $this->familiasByNorm = CatalogoFamilia::query()
            ->where('activo', true)
            ->get()
            ->groupBy(fn ($f) => $this->normalize($f->nombre));
    }

    private function subfamiliasFor(int $familiaId): Collection
    {
        if (! isset($this->subfamiliasByFamilia[$familiaId])) {
            $this->subfamiliasByFamilia[$familiaId] = CatalogoSubfamilia::query()
                ->where('familia_id', $familiaId)
                ->where('activo', true)
                ->get();
        }

        return $this->subfamiliasByFamilia[$familiaId];
    }
}
