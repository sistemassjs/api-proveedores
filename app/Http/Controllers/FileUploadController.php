<?php

namespace App\Http\Controllers;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/upload",
     *     summary="Subir archivos",
     *     tags={"Archivos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="archivos[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Archivos subidos exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="path", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        /**
         * Campos para subida de foto de perfil
         *  Usermodel
         */
        $request->validate([
            'archivos' => 'required|array',
            'archivos.*' => 'file|max:20480|mimes:jpg,jpeg,png,pdf,docx',
        ]);

        $urls = [];

        foreach ($request->file('archivos') as $archivo) {
            $nombre = uniqid().'.'.$archivo->getClientOriginalExtension();
            $path = $archivo->storeAs('uploads', $nombre, 'public');
            $urls[] = PublicStorageUrl::make($path);
        }

        return $this->success(
            ['path' => $urls],
            'Archivo subido con éxito',
            201
        );
    }
}
