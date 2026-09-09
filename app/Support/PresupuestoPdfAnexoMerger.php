<?php

namespace App\Support;

use App\Models\Presupuesto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Une el PDF del presupuesto (DomPDF) con anexos PDF y estampa encabezado de anexo.
 */
final class PresupuestoPdfAnexoMerger
{
    public static function unirSiHayAnexos(Presupuesto $presupuesto, string $mainBinary): string
    {
        $presupuesto->loadMissing('anexosPdf');

        /** @var Collection<int, \App\Models\PresupuestoAnexoPdf> $anexos */
        $anexos = $presupuesto->anexosPdf;
        if ($anexos->isEmpty()) {
            return $mainBinary;
        }

        $tmpMain = tempnam(sys_get_temp_dir(), 'pres_main_');
        if ($tmpMain === false) {
            return $mainBinary;
        }

        file_put_contents($tmpMain, $mainBinary);

        try {
            return self::mergeDesdePrincipal($presupuesto, $tmpMain, $anexos);
        } catch (\Throwable $e) {
            Log::warning('No fue posible unir anexos PDF al presupuesto', [
                'presupuesto_id' => $presupuesto->id,
                'error' => $e->getMessage(),
            ]);

            return $mainBinary;
        } finally {
            if (is_file($tmpMain)) {
                @unlink($tmpMain);
            }
        }
    }

    /**
     * @param  Collection<int, \App\Models\PresupuestoAnexoPdf>  $anexos
     */
    private static function mergeDesdePrincipal(Presupuesto $presupuesto, string $mainAbsPath, Collection $anexos): string
    {
        $pdf = new PresupuestoAnexoFpdi();
        $pdf->SetAutoPageBreak(false);

        $mainPages = $pdf->setSourceFile($mainAbsPath);
        for ($i = 1; $i <= $mainPages; $i++) {
            self::importarPaginaSinEstampado($pdf, $mainAbsPath, $i);
        }

        foreach ($anexos as $anexo) {
            $abs = PresupuestoAnexoPdfArchivoResponse::archivoAbsoluto($anexo->archivo_path);
            if ($abs === null || ! is_file($abs)) {
                continue;
            }

            $total = max(1, (int) ($anexo->paginas ?: 1));
            PresupuestoAnexoPdfStorage::prepararParaImportacionFpdi($abs);
            try {
                $anexoPages = $pdf->setSourceFile($abs);
                $total = max(1, (int) $anexoPages);
            } catch (\Throwable) {
                continue;
            }

            for ($page = 1; $page <= $total; $page++) {
                self::importarPaginaSinEstampado($pdf, $abs, $page);
                if ($anexo->mostrar_estampado ?? true) {
                    PresupuestoPdfAnexoEstampado::aplicar($pdf, $presupuesto, $anexo, $page, $total);
                }
            }
        }

        return $pdf->Output('S');
    }

    private static function importarPaginaSinEstampado(PresupuestoAnexoFpdi $pdf, string $sourcePath, int $pageNumber): void
    {
        $pdf->setSourceFile($sourcePath);
        $tpl = $pdf->importPage($pageNumber);
        $size = $pdf->getTemplateSize($tpl);
        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }
}
