<?php

/**
 * Exclusiones / universo de métricas y listados de plataforma.
 * Regla operativa: docs/context/platform-shared.md y platform-users-roles.md.
 */
return [

    /**
     * Roles que nunca cuentan en métricas de producto (gestión interna / integraciones).
     * Independiente de es_cuenta_de_pruebas.
     */
    'roles_excluidos' => [
        'ADMINISTRADOR',
        'CONSTRUCC_APP',
        'ventas_purificadora_colibri',
    ],

    /**
     * Roles visibles en el listado admin de usuarios registrados (whitelist).
     * Cualquier otro rol (ADMINISTRADOR, CONSTRUCC_APP, etc.) no aparece.
     */
    'roles_listado_usuarios_admin' => [
        'GERENTE',
        'SUPERVISOR',
        'VENTAS',
        'AUXILIAR',
        'CLIENTE',
    ],

];
