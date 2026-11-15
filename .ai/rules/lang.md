---
paths:
  - 'lang/**'
---

# Lang

## en.json por ordem alfabética e JSON válido
As chaves de `lang/en.json` ficam por ordem alfabética. Depois de editar ou de resolver um conflito, validar: `php -r "json_decode(file_get_contents('lang/en.json'), flags: JSON_THROW_ON_ERROR); echo 'ok';"`. Dois branches que acrescentem chaves vizinhas entram em conflito: empilhar um sobre o outro.

## Nunca usar trans_choice()
Sem a chave em `pt.json`, o `trans_choice()` cai no idioma de fallback (`en`) e mostra inglês a quem tem a interface em português (D30). Usar `__()`; para plurais, duas mensagens normais.

## "Início" é a navbar
A chave `"Início"` traduz-se por "Home" (navbar). Para a hora de início de uma consulta ou período usar `"Hora de início"`. Antes de reutilizar uma chave, confirmar o sentido que já tem no `en.json`.
