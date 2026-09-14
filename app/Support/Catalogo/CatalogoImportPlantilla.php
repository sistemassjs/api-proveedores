<?php

namespace App\Support\Catalogo;

class CatalogoImportPlantilla
{
    public const VERSION = '1.0';

    public const FECHA = '2026-09-09';

    /** Tamaño máximo de CSV en upload masivo (KB). ~50k filas típicas caben en 50 MB. */
    public const MAX_UPLOAD_KB = 51200;

    public const PREVIEW_ROWS_DEFAULT = 100;

    public const PREVIEW_ROWS_MAX = 500;

    /** Chunk de lectura desde tabla temporal en CSVImportJob. */
    public const JOB_CHUNK_DEFAULT = 200;

    public const JOB_CHUNK_MIN = 50;

    public const JOB_CHUNK_MAX = 1000;

    /** Memoria PHP para upload/preview y job masivo. */
    public const MEMORY_LIMIT_UPLOAD = '512M';

    public const MEMORY_LIMIT_JOB = '1024M';
}
