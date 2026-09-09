<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactoRequest;
use App\Mail\ContactoMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactoController extends Controller
{
    /**
     * Enviar correo de contacto
     *
     * @param ContactoRequest $request
     * @return JsonResponse
     */
    public function enviarContacto(ContactoRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $files = $request->hasFile('files')
                ? (array) $request->file('files')
                : [];

            // Obtener destinatarios de contacto desde configuración (env: MAIL_CONTACT_RECIPIENTS)
            $destinatarios = config('mail.contact_recipients', []);

            if (empty($destinatarios)) {
                Log::error('MAIL_CONTACT_RECIPIENTS no está configurado');

                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo enviar el mensaje por configuración de correo incompleta.'
                ], 500);
            }

            // Usar BCC para que los destinatarios no vean los correos de los demás
            $mail = new ContactoMail(
                $validated['nombre'],
                $validated['email'] ?? '',
                $validated['telefono'] ?? '',
                $validated['empresa'] ?? '',
                $validated['mensaje'] ?? '',
                $files // 👈 aquí
            );
            
            Mail::to(config('mail.from.address'))->bcc($destinatarios)->send($mail);

            // Log del envío exitoso
            Log::info('Correo de contacto enviado', [
                'nombre' => $validated['nombre'],
                'email' => $validated['email'],
                'destinatarios_count' => count($destinatarios),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto.'
            ], 200);
        } catch (\Exception $e) {
            // Log del error
            Log::error('Error al enviar correo de contacto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al enviar el mensaje. Por favor, intenta nuevamente más tarde.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
