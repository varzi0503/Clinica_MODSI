/**
 * UC16 com o padrão AJAX (#51): confirmar/rejeitar pedidos sem recarregar a página.
 * Os formulários continuam a funcionar sem JavaScript; isto apenas intercepta o submit.
 */
document.addEventListener('submit', async (evento) => {
    const form = evento.target.closest('form[data-ajax="pedido"]');
    if (!form || !window.clinica) {
        return;
    }

    evento.preventDefault();

    const pagina = document.querySelector('[data-pagina="rececao-pedidos"]');
    const botao = form.querySelector('button[type="submit"]');
    const motivo = form.querySelector('input[name="motivo"]');
    botao.disabled = true;

    const r = await clinica.pedirJson(form.action, {
        method: 'POST',
        body: motivo ? { motivo: motivo.value } : {},
    });

    if (r.ok || r.estado === 409) {
        // 409 = outro utilizador tratou o pedido entretanto; nos dois casos a linha sai da lista.
        const id = form.closest('tr')?.dataset.pedido;
        document.querySelectorAll(`tr[data-pedido="${id}"]`).forEach((tr) => tr.remove());
        clinica.mostrarAlerta(r.ok ? 'success' : 'warning', r.mensagem);

        if (!document.querySelector('tr[data-pedido]')) {
            pagina?.querySelector('.table-responsive')?.remove();
            pagina?.querySelector('[data-vazio]')?.classList.remove('d-none');
        }

        return;
    }

    if (r.estado === 422 && motivo) {
        motivo.classList.add('is-invalid');
    }

    clinica.mostrarAlerta('danger', r.mensagem || pagina?.dataset.textoErro || 'Erro.');
    botao.disabled = false;
});
