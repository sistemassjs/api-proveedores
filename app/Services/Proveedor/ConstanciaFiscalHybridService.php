<?php

namespace App\Services\Proveedor;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Spatie\PdfToText\Pdf;

class ConstanciaFiscalHybridService
{
  public function extraerDatos(string $pdfPath): array
  {
    Log::info("Intentando extraer datos fiscales de: $pdfPath");

    $texto = $this->extraerTextoPDF($pdfPath);
    $texto = $this->limpiarTexto($texto);

    Log::info('Texto limpiado:', ['texto' => $texto]);

    // 🔹 Separar bloques correctamente
    $bloques = $this->separarBloques($texto);

    Log::info('Bloques extraídos:', ['bloques' => $bloques]);

    $identidadTexto = $bloques['identidad'] ?? '';
    $domicilioTexto = $bloques['domicilio'] ?? '';

    // Fallback: algunos formatos del SAT no delimitan bien los bloques.
    $identidad = $this->normalizarCamposTexto(
      $this->extraerIdentidad($identidadTexto !== '' ? $identidadTexto : $texto)
    );
    $domicilio = $this->normalizarCamposTexto(
      $this->extraerDomicilio($domicilioTexto !== '' ? $domicilioTexto : $texto)
    );
    $regimenes = $this->extraerRegimenes($texto);

    Log::info('Identidad extraída:', ['identidad' => $identidad]);
    Log::info('Domicilio extraído:', ['domicilio' => $domicilio]);
    Log::info('Regímenes extraídos:', ['regimenes' => $regimenes]);

    return [
      ...$identidad,
      ...$domicilio,
      'regimenes' => $regimenes,
      'pais' => 'México'
    ];
  }

  // =============================
  // 📄 PDF
  // =============================
  private function extraerTextoPDF(string $path): string
  {
    try {
      $parser = new Parser();
      $pdf = $parser->parseFile($path);
      $text = $pdf->getText();

      if (trim($text)) return $text;
    } catch (\Throwable $e) {
    }

    return Pdf::getText($path);
  }

  // =============================
  // 🧼 LIMPIEZA
  // =============================
  private function limpiarTexto(string $texto): string
  {
    // separar palabras pegadas tipo "Nombrede"
    $texto = preg_replace('/([a-z])([A-Z])/', '$1 $2', $texto);

    // limpiar caracteres invisibles
    $texto = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $texto);

    // espacios
    $texto = preg_replace('/\s+/', ' ', $texto);

    return trim($texto);
  }

  // =============================
  // 🧱 BLOQUES
  // =============================
  private function separarBloques(string $texto): array
  {
    return [
      'identidad' => $this->matchBlock(
        '/Datos de Identificación del Contribuyente:?(.*?)(?:Datos del domicilio registrado|Domicilio registrado)/si',
        $texto
      ),
      'domicilio' => $this->matchBlock(
        '/(?:Datos del domicilio registrado(?:\s+en\s+el\s+RFC)?|Domicilio registrado):?(.*?)(?:Actividades Económicas|Regímenes|Características fiscales)/si',
        $texto
      ),
    ];
  }

  private function matchBlock($regex, $texto): string
  {
    preg_match($regex, $texto, $m);
    return trim($m[1] ?? '');
  }

  // =============================
  // 👤 IDENTIDAD
  // =============================
  private function extraerIdentidad(string $texto): array
  {
    return [
      'rfc' => $this->match('/RFC:\s*([A-Z0-9]+)/', $texto),
      'curp' => $this->match('/CURP:\s*([A-Z0-9]+)/', $texto),

      'nombre' => $this->match('/Nombre(?:\(s\)|s?)\s*:\s*(.+?)(?=\s*Primer\s*Apellido:|\s*PrimerApellido:|\s*CURP:|\s*RFC:|\s*Fecha\s*inicio\s*de\s*operaciones:|\s*Fechainiciodeoperaciones:|$)/u', $texto),
      'primer_apellido' => $this->match('/Primer\s*Apellido\s*:\s*(.+?)(?=\s*Segundo\s*Apellido:|\s*SegundoApellido:|\s*CURP:|\s*RFC:|\s*Fecha\s*inicio\s*de\s*operaciones:|\s*Fechainiciodeoperaciones:|$)/u', $texto),
      'segundo_apellido' => $this->match('/Segundo\s*Apellido\s*:\s*(.+?)(?=\s*CURP:|\s*RFC:|\s*Datos\s*del\s*domicilio\s*registrado|\s*Domicilio\s*registrado|\s*Fecha\s*inicio\s*de\s*operaciones:|\s*Fechainiciodeoperaciones:|\s*Estatus\s*en\s*el\s*padr[oó]n:|\s*Estatusenelpadr[oó]n:|\s*Nombre\s*Comercial:|\s*NombreComercial:|$)/u', $texto),

      'razon_social' => null,
    ];
  }

  // =============================
  // 🏠 DOMICILIO
  // =============================
  private function extraerDomicilio(string $texto): array
  {
    return [
      'codigo_postal' => $this->match('/Código Postal:\s*(\d{5})/', $texto),
      'tipo_vialidad' => $this->match('/(?:Tipo\s*de\s*Vialidad|TipodeVialidad)\s*:\s*(.+?)(?=\s*(?:Nombre\s*de\s*Vialidad|Nombrede\s*Vialidad):|\s*(?:N[uú]mero\s*Exterior|N[uú]meroExterior):|$)/u', $texto),
      'nombre_vialidad' => $this->match('/(?:Nombre\s*de\s*Vialidad|Nombrede\s*Vialidad)\s*:\s*(.+?)(?=\s*(?:N[uú]mero\s*Exterior|N[uú]meroExterior):|\s*(?:N[uú]mero\s*Interior|N[uú]meroInterior):|\s*(?:Colonia|NombredelaColonia):|$)/u', $texto),

      'numero_exterior' => $this->match('/(?:N[uú]mero\s*Exterior|N[uú]meroExterior)\s*:\s*([A-Z0-9\/\-]+)/u', $texto),
      'numero_interior' => $this->match('/(?:N[uú]mero\s*Interior|N[uú]meroInterior)\s*:\s*([A-Z0-9\/\-]+)/u', $texto),

      'colonia' => $this->match('/(?:Colonia|NombredelaColonia)\s*:\s*(.+?)(?=\s*(?:Localidad|Nombre\s*de\s*la\s*Localidad|NombredelaLocalidad):|\s*(?:Municipio|NombredelMunicipiooDemarcaci[oó]nTerritorial):|\s*(?:Entidad\s*Federativa|Nombre\s*de\s*la\s*Entidad\s*Federativa):|$)/u', $texto),
      'localidad' => $this->match('/(?:Localidad|Nombre\s*de\s*la\s*Localidad|NombredelaLocalidad)\s*:\s*(.+?)(?=\s*(?:Municipio|NombredelMunicipiooDemarcaci[oó]nTerritorial):|\s*(?:Entidad\s*Federativa|Nombre\s*de\s*la\s*Entidad\s*Federativa):|$)/u', $texto),
      'municipio_delegacion' => $this->match('/(?:Municipio.*?|NombredelMunicipiooDemarcaci[oó]nTerritorial)\s*:\s*(.+?)(?=\s*(?:Entidad\s*Federativa|Nombre\s*de\s*la\s*Entidad\s*Federativa):|\s*Entre\s*Calle:|\s*EntreCalle:|\s*Y\s*Calle:|$)/u', $texto),
      'entidad_federativa' => $this->match('/(?:Entidad\s*Federativa|Nombre\s*de\s*la\s*Entidad\s*Federativa)\s*:\s*(.+?)(?=\s*Entre\s*Calle:|\s*EntreCalle:|\s*Y\s*Calle:|\s*C[oó]digo\s*Postal:|$)/u', $texto),

      'entre_calle' => $this->match('/(?:Entre\s*Calle|EntreCalle)\s*:\s*(.+?)(?=\s*(?:Y\s*Calle|YCalle):|$)/u', $texto),
      'y_calle' => $this->match('/(?:Y\s*Calle|YCalle)\s*:\s*(.+?)(?=\s*Caracter[ií]sticas\s*fiscales:|\s*Reg[ií]menes:|$)/u', $texto),

      // normalizados
      'calle' => $this->match('/(?:Nombre\s*de\s*Vialidad|Nombrede\s*Vialidad)\s*:\s*(.+?)(?=\s*(?:N[uú]mero\s*Exterior|N[uú]meroExterior):|\s*(?:N[uú]mero\s*Interior|N[uú]meroInterior):|\s*(?:Colonia|NombredelaColonia):|$)/u', $texto),
      'ciudad' => $this->match('/(?:Municipio.*?|NombredelMunicipiooDemarcaci[oó]nTerritorial)\s*:\s*(.+?)(?=\s*(?:Entidad\s*Federativa|Nombre\s*de\s*la\s*Entidad\s*Federativa):|\s*Entre\s*Calle:|\s*EntreCalle:|\s*Y\s*Calle:|$)/u', $texto),
      'estado' => $this->match('/(?:Entidad\s*Federativa|Nombre\s*de\s*la\s*Entidad\s*Federativa)\s*:\s*(.+?)(?=\s*Entre\s*Calle:|\s*EntreCalle:|\s*Y\s*Calle:|\s*C[oó]digo\s*Postal:|$)/u', $texto),
    ];
  }

  // =============================
  // 💼 REGÍMENES (FIX REAL)
  // =============================
  private function extraerRegimenes(string $texto): array
  {
    $bloqueRegimenes = $this->matchBlock(
      '/(?:Regímenes:|Régimenes:|Régimen fiscal)(.*?)(?:Obligaciones:|Actividades Económicas|Datos informativos|$)/isu',
      $texto
    );

    $fuente = $bloqueRegimenes !== '' ? $bloqueRegimenes : $texto;

    $matches = [];
    preg_match_all(
      '/R[eé]gimen\s*:?\s*(.+?)\s+Fecha\s*Inicio\s*:?\s*(\d{2}\/\d{2}\/\d{4})(?:\s+Fecha\s*Fin\s*:?\s*(\d{2}\/\d{2}\/\d{4})?)?/iu',
      $fuente,
      $matches,
      PREG_SET_ORDER
    );

    // Fallback para tablas donde viene "Régimen de ... 01/01/2016"
    if (empty($matches)) {
      preg_match_all(
        '/R[eé]gimen(?:\s+de)?\s+(.+?)\s+(\d{2}\/\d{2}\/\d{4})(?=\s|$)/iu',
        $fuente,
        $matches,
        PREG_SET_ORDER
      );
    }

    $regimenes = [];

    foreach ($matches as $m) {
      $nombre = $this->normalizarNombreRegimen($m[1] ?? '');

      if ($nombre === '') {
        continue;
      }

      $clave = $this->mapClaveRegimen($nombre);

      $regimenes[] = [
        'clave' => $clave,
        'nombre' => $nombre,
        'fecha_alta' => $this->normalizeFechaAlta($m[2] ?? null),
      ];
    }

    return $regimenes;
  }

  private function normalizeFechaAlta(?string $fecha): ?string
  {
    if ($fecha === null || trim($fecha) === '') {
      return null;
    }
    $raw = trim($fecha);
    foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
      $dt = \DateTime::createFromFormat('!'.$format, $raw);
      if ($dt instanceof \DateTime) {
        return $dt->format('Y-m-d');
      }
    }

    return $raw;
  }

  private function normalizarNombreRegimen(string $nombre): string
  {
    $nombre = trim($nombre);

    // Algunos PDFs mezclan encabezados de columnas en el nombre del régimen.
    $nombre = preg_replace('/\bFecha\s+Inicio\b/iu', ' ', $nombre);
    $nombre = preg_replace('/\bInicioFecha\s+Fin\b/iu', ' ', $nombre);
    $nombre = preg_replace('/\bInicioFechaFin\b/iu', ' ', $nombre);
    $nombre = preg_replace('/\bFecha\s+Fin\b/iu', ' ', $nombre);
    $nombre = preg_replace('/\bRégimen\s+de\b/iu', ' ', $nombre);
    $nombre = preg_replace('/\bRégimen\b/iu', ' ', $nombre);
    $nombre = preg_replace('/^(de|del|la|las|los)\s+/iu', '', $nombre);
    $nombre = preg_replace('/\bAm[aá]stardareld[ií]a.*$/iu', ' ', $nombre);
    $nombre = preg_replace('/\bA\s+m[aá]s\s+tardar\s+el\s+d[ií]a.*$/iu', ' ', $nombre);
    $nombre = preg_replace('/\s+/', ' ', $nombre);
    $nombre = trim($nombre);

    // Descartar nombres claramente ruidosos.
    if (mb_strlen($nombre) < 4) {
      return '';
    }

    if (preg_match('/^(fech|estatus|nombrecomercial|obligaciones?)$/iu', str_replace(' ', '', $nombre))) {
      return '';
    }

    $nombre = preg_replace('/\s+/u', ' ', $nombre);

    return trim($nombre);
  }

  private function mapClaveRegimen(string $nombre): ?string
  {
    $map = [
      'General de Ley Personas Morales' => '601',
      'Personas Morales con Fines no Lucrativos' => '603',
      'Sueldos y Salarios' => '605',
      'Arrendamiento' => '606',
      'Enajenación o Adquisición de Bienes' => '607',
      'Demás ingresos' => '608',
      'Residentes en el Extranjero' => '610',
      'Dividendos' => '611',
      'Actividades Empresariales y Profesionales' => '612',
      'Actividades Empresariales y Profesales' => '612',
      'intereses' => '614',
      'premios' => '615',
      'Sin obligaciones fiscales' => '616',
      'Sociedades Cooperativas' => '620',
      'Incorporación Fiscal' => '621',
      'RIF' => '621',
      'Agrícolas, Ganaderas, Silvícolas y Pesqueras' => '622',
      'Grupos de Sociedades' => '623',
      'Coordinados' => '624',
      'Plataformas Tecnológicas' => '625',
      'Simplificado de Confianza' => '626',
      'RESICO' => '626',
    ];

    foreach ($map as $k => $v) {
      if (stripos($nombre, $k) !== false) {
        return $v;
      }
    }

    return null;
  }

  // =============================
  // 🔍 HELPER
  // =============================
  private function match($regex, $texto)
  {
    preg_match($regex, $texto, $m);
    if (!isset($m[1])) {
      return null;
    }

    return $this->limpiarValorExtraido($m[1]);
  }

  private function limpiarValorExtraido(string $valor): ?string
  {
    $valor = preg_replace('/Página\s*\[\d+\]\s*de\s*\[\d+\]/iu', ' ', $valor);
    $valor = preg_replace('/\bFechainiciodeoperaciones:.*$/iu', ' ', $valor);
    $valor = preg_replace('/\bEstatusenelpadr[oó]n:.*$/iu', ' ', $valor);
    $valor = preg_replace('/\bFechade[uú]ltimocambiodeestado:.*$/iu', ' ', $valor);
    $valor = preg_replace('/\bNombreComercial:.*$/iu', ' ', $valor);

    // Etiquetas OCR pegadas que suelen contaminar al final.
    $valor = preg_replace('/\bNombredela\b.*$/iu', ' ', $valor);
    $valor = preg_replace('/\bNombredel\b.*$/iu', ' ', $valor);
    $valor = preg_replace('/\bYCalle:.*$/iu', ' ', $valor);
    $valor = preg_replace('/\bEntreCalle:.*$/iu', ' ', $valor);

    // Conservar espacios internos; solo limpiar bordes.
    $valor = trim($valor);

    return $valor !== '' ? $valor : null;
  }

  private function normalizarCamposTexto(array $datos): array
  {
    $camposTexto = [
      'nombre',
      'primer_apellido',
      'segundo_apellido',
      'tipo_vialidad',
      'nombre_vialidad',
      'colonia',
      'localidad',
      'municipio_delegacion',
      'entidad_federativa',
      'entre_calle',
      'y_calle',
      'calle',
      'ciudad',
      'estado',
    ];

    foreach ($camposTexto as $campo) {
      if (!isset($datos[$campo]) || !is_string($datos[$campo])) {
        continue;
      }

      $datos[$campo] = $this->humanizarTextoPegado($datos[$campo], $campo);
    }

    return $datos;
  }

  private function humanizarTextoPegado(string $valor, string $campo): string
  {
    $valor = trim($valor);

    if ($valor === '' || preg_match('/\s/u', $valor)) {
      return $valor;
    }

    // Prefijos comunes de domicilio que suelen venir pegados.
    $valor = preg_replace('/^(FRACC\.)([A-ZÁÉÍÓÚÑ])/u', '$1 $2', $valor);
    $valor = preg_replace('/^(CALLE|AVENIDA|AV|PROLONGACION|PRIVADA|BLVD|BOULEVARD|COLONIA|LOCALIDAD|MUNICIPIO|CIUDAD|BARRIO|EJIDO)([A-ZÁÉÍÓÚÑ])/u', '$1 $2', $valor);
    $valor = preg_replace('/^(LOS|LAS|SAN|SANTA)([A-ZÁÉÍÓÚÑ])/u', '$1 $2', $valor);

    // Nombres compuestos frecuentes cuando OCR/PDF los pega.
    if ($campo === 'nombre') {
      $valor = preg_replace('/^(JOSE|JOSÉ|MARIA|MARÍA|LUIS|JULIO|JUAN|MIGUEL|CARLOS|ANA|ANGEL|ÁNGEL)([A-ZÁÉÍÓÚÑ]{3,})$/u', '$1 $2', $valor);
    }

    return $valor;
  }
}
