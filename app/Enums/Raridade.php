<?php

namespace App\Enums;

enum Raridade: string
{
    case Comum = 'comum';
    case Rara = 'rara';
    case Epica = 'epica';
    case Lendaria = 'lendaria';
}
