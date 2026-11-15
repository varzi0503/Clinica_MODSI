<?php

return [

    // Guidelines do Boost que não se aplicam a este projeto:
    // - deployments: o projeto não é publicado no Laravel Cloud;
    // - pest/core: manda criar testes com sintaxe Pest, e aqui os testes são classes PHPUnit
    //   (ver .ai/rules/tests.md). Como correr os testes está em .ai/guidelines/clinica.md.
    'guidelines' => [
        'exclude' => ['deployments', 'pest/core'],
    ],

    'skills' => [
        'exclude' => ['deploying-to-cloud'],
    ],

];
