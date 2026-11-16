<?php

return [

    /*
    | Dados da clínica mostrados nas páginas públicas (issue #52).
    | São de exemplo: a morada e os contactos não existem.
    */

    'nome' => 'Clínica MODSI',

    'morada' => 'Rua das Camélias, 120, 4200-000 Porto',

    'telefone' => '220 000 000',

    'email' => 'geral@clinica-modsi.example',

    // A clínica abre todos os dias, incluindo fins de semana e feriados.
    'horario' => [
        'abre' => '08:00',
        'fecha' => '20:00',
    ],

];
