<?php

namespace App\Support;

/**
 * Resuelve la vista Blade usada para generar el PDF del presupuesto (DomPDF).
 *
 * @see PresupuestoPdfDocumentConfig
 */
class PresupuestoPdfTemplate
{
    public static function viewName(): string
    {
        return PresupuestoPdfDocumentConfig::defaults()->viewName();
    }
}
