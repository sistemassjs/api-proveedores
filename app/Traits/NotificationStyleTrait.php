<?php

namespace App\Traits;

trait NotificationStyleTrait
{
    use NotificationCorrelationId;

    /**
     * Obtiene los estilos de la notificación (color, icono, clase CSS)
     * 
     * @return array
     */
    protected function getNotificationStyles(): array
    {
        $tipo = $this->getNotificationTipo();
        $subtipo = $this->getNotificationSubtipo();

        // Mapeo de estilos por tipo y subtipo
        $stylesMap = $this->getStylesMapping();

        // Buscar estilo específico por tipo y subtipo
        if (isset($stylesMap[$tipo][$subtipo])) {
            return $stylesMap[$tipo][$subtipo];
        }

        // Fallback: buscar estilo genérico por tipo
        if (isset($stylesMap[$tipo]['default'])) {
            return $stylesMap[$tipo]['default'];
        }

        // Fallback final: estilo por defecto
        return [
            'color' => 'primary',
            'icon' => 'information-circle-outline',
            'style_class' => 'primary-notification',
        ];
    }

    /**
     * Obtiene el tipo de la notificación
     * Debe ser implementado por cada clase de notificación
     */
    abstract protected function getNotificationTipo(): string;

    /**
     * Obtiene el subtipo de la notificación
     * Debe ser implementado por cada clase de notificación
     */
    abstract protected function getNotificationSubtipo(): string;

    /**
     * Mapeo de estilos por tipo y subtipo
     * 
     * @return array
     */
    protected function getStylesMapping(): array
    {
        return [
            'solicitud_pago' => [
                'pagada' => [
                    'color' => 'success',
                    'icon' => 'wallet-outline',
                    'style_class' => 'payment-notification', // Siempre distintiva
                    'is_payment' => true, // Marca especial para notificaciones de pago
                ],
                // 👇 NUEVO: SP abonada (pago parcial)
                'abonada' => [
                    'color' => 'warning',                 // Visualmente distinto al pagado
                    'icon' => 'cash-outline',             // O 'wallet-outline' si prefieres uniformidad
                    'style_class' => 'payment-notification', // Mantiene estilo de notificación de pago
                    'is_payment' => true,                 // Sigue siendo notificación de pago
                    'is_partial' => true,                 // Flag opcional para diferenciar en el Front
                ],
                // 👇 NUEVO: Factura pendiente
                'factura_pendiente' => [
                    'color' => 'danger',
                    'icon' => 'document-attach-outline',
                    'style_class' => 'danger-notification',
                    'is_payment' => false,        // No es pago
                    'is_billing' => true,         // Flag semántico para el Front
                    'requires_action' => true,    // Acción requerida del proveedor
                ],
                'rechazada' => [
                    'color' => 'medium',
                    'icon' => 'close-circle-outline',
                    'style_class' => 'medium-notification',
                    'is_payment' => false,
                ],
                'rechazada-sin-autorizacion' => [
                    'color' => 'medium',
                    'icon' => 'close-circle-outline',
                    'style_class' => 'medium-notification',
                    'is_payment' => false,
                ],
                'pendiente' => [
                    'color' => 'primary',
                    'icon' => 'time-outline',
                    'style_class' => 'primary-notification',
                    'is_payment' => false,
                ],
                'autorizada' => [
                    'color' => 'success',
                    'icon' => 'checkmark-circle-outline',
                    'style_class' => 'success-notification',
                    'is_payment' => false,
                ],
                'default' => [
                    'color' => 'primary',
                    'icon' => 'document-text-outline',
                    'style_class' => 'primary-notification',
                    'is_payment' => false,
                ],

                //
                'en_proceso' => [
                    'color' => 'primary',
                    'icon' => 'hourglass-outline',
                    'style_class' => 'primary-notification',
                ],
                'cancelada' => [
                    'color' => 'medium',
                    'icon' => 'ban-outline',
                    'style_class' => 'medium-notification',
                ],


                // tipo de noitificacion empresa-proveedor:
                'nueva_asociacion' => [
                    'color' => 'primary',
                    'icon' => 'link-outline',
                    'style_class' => 'primary-notification',
                ],

            ],
            'orden_compra' => [
                'nueva' => [
                    'color' => 'primary',
                    'icon' => 'document-text-outline',
                    'style_class' => 'primary-notification',
                ],
                'aprobada' => [
                    'color' => 'success',
                    'icon' => 'checkmark-done-circle-outline',
                    'style_class' => 'success-notification',
                ],
                'rechazada' => [
                    'color' => 'medium',
                    'icon' => 'close-circle-outline',
                    'style_class' => 'medium-notification',
                ],
                'completada' => [
                    'color' => 'success',
                    'icon' => 'checkmark-circle-outline',
                    'style_class' => 'success-notification',
                ],
                'cancelada' => [
                    'color' => 'medium',
                    'icon' => 'ban-outline',
                    'style_class' => 'medium-notification',
                ],
                'default' => [
                    'color' => 'primary',
                    'icon' => 'document-text-outline',
                    'style_class' => 'primary-notification',
                ],
            ],
            'usuario' => [
                'nuevo_usuario' => [
                    'color' => 'tertiary',
                    'icon' => 'person-add-outline',
                    'style_class' => 'new-user-notification',
                ],
                'email_verificado' => [
                    'color' => 'success',
                    'icon' => 'checkmark-circle-outline',
                    'style_class' => 'success-notification',
                ],
                'reasignado' => [
                    'color' => 'primary',
                    'icon' => 'person-add-outline',
                    'style_class' => 'primary-notification',
                ],
                'creado' => [
                    'color' => 'success',
                    'icon' => 'person-outline',
                    'style_class' => 'success-notification',
                ],
                'actualizado' => [
                    'color' => 'primary',
                    'icon' => 'create-outline',
                    'style_class' => 'primary-notification',
                ],
                'default' => [
                    'color' => 'primary',
                    'icon' => 'person-outline',
                    'style_class' => 'primary-notification',
                ],
            ],
            'producto' => [
                'nuevo' => [
                    'color' => 'primary',
                    'icon' => 'cube-outline',
                    'style_class' => 'primary-notification',
                ],
                'actualizado' => [
                    'color' => 'primary',
                    'icon' => 'create-outline',
                    'style_class' => 'primary-notification',
                ],
                'rechazado' => [
                    'color' => 'medium',
                    'icon' => 'close-circle-outline',
                    'style_class' => 'medium-notification',
                ],
                'aprobado' => [
                    'color' => 'success',
                    'icon' => 'checkmark-circle-outline',
                    'style_class' => 'success-notification',
                ],
                'default' => [
                    'color' => 'primary',
                    'icon' => 'cube-outline',
                    'style_class' => 'primary-notification',
                ],
            ],
            'presupuesto' => [
                'enviado' => [
                    'color' => 'primary',
                    'icon' => 'send-outline',
                    'style_class' => 'primary-notification',
                    'is_payment' => false,
                ],
                'aceptado' => [
                    'color' => 'success',
                    'icon' => 'checkmark-circle-outline',
                    'style_class' => 'success-notification',
                    'is_payment' => false,
                ],
                'rechazado' => [
                    'color' => 'medium',
                    'icon' => 'close-circle-outline',
                    'style_class' => 'medium-notification',
                    'is_payment' => false,
                ],
                'recibido_cliente_proveedor' => [
                    'color' => 'primary',
                    'icon' => 'mail-unread-outline',
                    'style_class' => 'primary-notification',
                    'is_payment' => false,
                ],
                'cierre_pendiente' => [
                    'color' => 'warning',
                    'icon' => 'hourglass-outline',
                    'style_class' => 'warning-notification',
                    'is_payment' => false,
                ],
                'vencido' => [
                    'color' => 'medium',
                    'icon' => 'time-outline',
                    'style_class' => 'medium-notification',
                    'is_payment' => false,
                ],
                'default' => [
                    'color' => 'primary',
                    'icon' => 'document-text-outline',
                    'style_class' => 'primary-notification',
                    'is_payment' => false,
                ],
            ],
            'importacion' => [
                'nueva' => [
                    'color' => 'primary',
                    'icon' => 'cloud-upload-outline',
                    'style_class' => 'primary-notification',
                ],
                'completada' => [
                    'color' => 'success',
                    'icon' => 'checkmark-circle-outline',
                    'style_class' => 'success-notification',
                ],
                'error' => [
                    'color' => 'medium',
                    'icon' => 'alert-circle-outline',
                    'style_class' => 'medium-notification',
                ],
                'procesando' => [
                    'color' => 'primary',
                    'icon' => 'sync-outline',
                    'style_class' => 'primary-notification',
                ],
                'default' => [
                    'color' => 'primary',
                    'icon' => 'cloud-upload-outline',
                    'style_class' => 'primary-notification',
                ],
            ],
        ];
    }

    /**
     * Agrega los estilos a un array de datos de notificación
     * 
     * @param array $data
     * @return array
     */
    protected function addStylesToData(array $data): array
    {
        $styles = $this->getNotificationStyles();

        return array_merge($data, [
            'notification_correlation_id' => $this->notificationCorrelationId(),
            'color' => $styles['color'],
            'icon' => $styles['icon'],
            'style_class' => $styles['style_class'],
            'is_payment' => $styles['is_payment'] ?? false,
        ]);
    }
}
