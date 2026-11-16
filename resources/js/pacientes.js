/**
 * UC17/UC19 (#58): na página de marcar consulta, o paciente escolhe-se por pesquisa
 * (nome ou nº de utente) em vez de uma lista com todas as fichas.
 */
const caixa = document.querySelector('[data-pesquisa-paciente]');

if (caixa) {
    const campo = caixa.querySelector('input[type="search"]');
    const escondido = caixa.querySelector('input[name="paciente_id"]');
    const resultados = caixa.querySelector('[data-resultados-paciente]');
    const escolhido = caixa.querySelector('[data-paciente-escolhido]');
    let pedidoAtual = 0;
    let espera = null;

    const rotulo = (p) => `${p.nome} (${caixa.dataset.textoUtente} ${p.num_utente})`;

    function escolher(paciente) {
        escondido.value = paciente.id;
        escolhido.textContent = rotulo(paciente);
        resultados.replaceChildren();
        campo.value = '';
        campo.classList.remove('is-invalid');
    }

    async function pesquisar() {
        const texto = campo.value.trim();
        const meu = ++pedidoAtual;

        if (texto.length < 2) {
            resultados.replaceChildren();
            return;
        }

        const r = await clinica.pedirJson(`${caixa.dataset.url}?${new URLSearchParams({ q: texto })}`);
        // Ignora respostas que cheguem fora de ordem (o utilizador continuou a escrever).
        if (meu !== pedidoAtual) return;

        resultados.replaceChildren();
        const pacientes = r.ok ? r.dados.pacientes : [];

        if (pacientes.length === 0) {
            const vazio = document.createElement('div');
            vazio.className = 'list-group-item text-muted small';
            vazio.textContent = caixa.dataset.textoNenhum;
            resultados.append(vazio);
            return;
        }

        for (const paciente of pacientes) {
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'list-group-item list-group-item-action';
            botao.textContent = rotulo(paciente);
            botao.addEventListener('click', () => escolher(paciente));
            resultados.append(botao);
        }
    }

    campo.addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(pesquisar, 250);
    });

    // Enter no campo de pesquisa não deve submeter a marcação a meio.
    campo.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(espera);
            pesquisar();
        }
    });
}
