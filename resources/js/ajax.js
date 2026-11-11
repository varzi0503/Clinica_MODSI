/**
 * Padrão AJAX/JSON comum (issue #51).
 *
 * Convenção de respostas do servidor:
 *   2xx -> { message?, ...dados }
 *   422 -> { message, errors: { campo: [mensagens] } }   (validação do Laravel)
 *   409 -> { message }                                   (conflito de estado, ex.: pedido já tratado)
 *   403 -> sem acesso; 419 -> sessão/CSRF expirado (recarregar a página)
 *
 * Uso:
 *   const r = await clinica.pedirJson(url, { method: 'POST', body: { motivo: '...' } });
 *   if (r.ok) { ... r.dados ... } else { ... r.erros / r.mensagem ... }
 */

const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function pedirJson(url, { method = 'GET', body = null } = {}) {
    let resposta;
    try {
        resposta = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === null ? null : JSON.stringify(body),
        });
    } catch {
        // Sem rede / servidor em baixo: devolve um erro em vez de rebentar a promessa.
        return { ok: false, estado: 0, dados: {}, mensagem: '', erros: {} };
    }

    // Sessão expirada: o pedido seguinte ia falhar sempre; recarregar devolve o utilizador ao login.
    if (resposta.status === 419 || resposta.status === 401) {
        window.location.reload();

        return { ok: false, estado: resposta.status, dados: {}, mensagem: '', erros: {} };
    }

    let dados = {};
    try {
        dados = await resposta.json();
    } catch {
        // resposta sem corpo (204) ou não-JSON: segue com objeto vazio
    }

    return {
        ok: resposta.ok,
        estado: resposta.status,
        dados,
        mensagem: dados.message ?? '',
        erros: dados.errors ?? {},
    };
}

/** Mostra um alerta Bootstrap no contentor de mensagens da página. */
function mostrarAlerta(tipo, texto) {
    const contentor = document.querySelector('[data-alertas]') ?? document.querySelector('main .container');
    if (!contentor || !texto) {
        return;
    }

    const div = document.createElement('div');
    div.className = `alert alert-${tipo} alert-dismissible fade show`;
    div.setAttribute('role', 'alert');
    div.textContent = texto;

    const fechar = document.createElement('button');
    fechar.type = 'button';
    fechar.className = 'btn-close';
    fechar.dataset.bsDismiss = 'alert';
    div.appendChild(fechar);

    contentor.prepend(div);
}

window.clinica = { pedirJson, mostrarAlerta };
