<?php

namespace app\model\enums;

enum EstadoPartida: string {

    case  CREADA = "creada";
    case EN_CURSO = 'en_curso';
    case FINALIZADA = 'finalizada';
    case ABANDONADA = 'abandonada';
}