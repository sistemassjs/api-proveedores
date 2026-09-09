<?php

/**
 * Matriz MVP de gestión de usuarios / roles / menú (plataforma).
 * Fuente operativa: docs/context/platform-users-roles.md
 */
return [

    /**
     * Roles con acceso a rutas `/proveedores/...` (gerente.php).
     * Temporal: acceso completo a todas las rutas del grupo.
     * Después: restringir escritura/lectura por módulo según la matriz.
     */
    'roles_acceso_rutas_proveedor' => [
        'GERENTE',
        'SUPERVISOR',
        'VENTAS',
        'AUXILIAR',
    ],

    /** Roles que gerente/supervisor pueden asignar al crear/editar usuarios de la empresa */
    'roles_asignables_empresa' => [
        'GERENTE',
        'SUPERVISOR',
        'VENTAS',
        'AUXILIAR',
    ],

    /**
     * Roles que el ADMIN puede asignar al crear/editar usuarios vinculados a un proveedor.
     * Incluye GERENTE (usuario principal) además de los operativos.
     */
    'roles_asignables_admin' => [
        'GERENTE',
        'SUPERVISOR',
        'VENTAS',
        'AUXILIAR',
        'CLIENTE',
    ],

    /**
     * Temporal: mismo acceso CRU usuarios que GERENTE (alineado a front/ops).
     * Después: volver a GERENTE + SUPERVISOR según matriz.
     */
    'roles_gestion_usuarios_cru' => [
        'GERENTE',
        'SUPERVISOR',
        'VENTAS',
        'AUXILIAR',
    ],

    /** Roles que pueden borrar usuarios (nunca al PRINCIPAL; admin siempre puede) */
    'roles_gestion_usuarios_delete' => [
        'GERENTE',
    ],

];
