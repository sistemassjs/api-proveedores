<?php

namespace App\Services\Proveedor;

use Spatie\PdfToText\Pdf;

class ConstanciaFiscalOCRService
{
  public function extraerDatos(string $path): array
  {
    $texto = Pdf::getText($path);

    // Normalizar texto (importante)
    $texto = preg_replace('/\s+/', ' ', $texto);

    return [
      'rfc' => $this->extraer('/RFC:\s*([A-Z0-9]{12,13})/', $texto),
      'curp' => $this->extraer('/CURP:\s*([A-Z0-9]{18})/', $texto),

      'nombre' => $this->extraer('/Nombre \(s\):\s*([A-Z\s]+)/', $texto),
      'apellido_paterno' => $this->extraer('/Primer Apellido:\s*([A-Z\s]+)/', $texto),
      'apellido_materno' => $this->extraer('/Segundo Apellido:\s*([A-Z\s]+)/', $texto),

      'razon_social' => $this->extraer('/Nombre, denominación o razón social\s*([A-Z\s]+)/', $texto),

      'cp' => $this->extraer('/Código Postal:\s*(\d{5})/', $texto),
      'calle' => $this->extraer('/Nombre de Vialidad:\s*([A-Z\s]+)/', $texto),
      'numero_exterior' => $this->extraer('/Número Exterior:\s*([A-Z0-9\/]+)/', $texto),
      'colonia' => $this->extraer('/Nombre de la Colonia:\s*([A-Z\s\.]+)/', $texto),
      'ciudad' => $this->extraer('/Municipio o Demarcación Territorial:\s*([A-Z\s]+)/', $texto),
      'estado' => $this->extraer('/Entidad Federativa:\s*([A-Z\s]+)/', $texto),

      'regimenes' => $this->extraerRegimenes($texto),
    ];
  }

  private function extraer($regex, $texto)
  {
    preg_match($regex, $texto, $matches);
    return $matches[1] ?? null;
  }

  private function extraerRegimenes($texto)
  {
    preg_match_all('/Régimen\s*(.*?)\s*\d{2}\/\d{2}\/\d{4}/', $texto, $matches);

    return collect($matches[1] ?? [])
      ->map(fn($r) => ['nombre' => trim($r)])
      ->values()
      ->toArray();
  }
}
