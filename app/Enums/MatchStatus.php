<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Aguardando = 'aguardando';
    case EmAndamento = 'em_andamento';
    case Finalizada = 'finalizada';
    case Abandonada = 'abandonada';
    case Cancelada = 'cancelada';
}
