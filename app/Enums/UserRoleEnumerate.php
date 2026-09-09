<?php

namespace App\Enums;

enum UserRoleEnumerate: string
{
    case ADMINISTRADOR = 'ADMINISTRADOR';
    case GERENTE = 'GERENTE';
    case SUPERVISOR = 'SUPERVISOR';
    case VENTAS = 'VENTAS';
    case AUXILIAR = 'AUXILIAR';
    case USUARIO = 'USUARIO';
    case CLIENTE = 'CLIENTE';
    case CONSTUCC_APP = 'CONSTRUCC_APP';
    case VENTAS_PURIFICADORA_COLIBRI = 'ventas_purificadora_colibri';
}
