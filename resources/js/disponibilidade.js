/**
 * UC06 — consultar disponibilidade (issue #59), com o padrão AJAX (#51).
 * Ao mudar a especialidade ou a data, pede as vagas ao servidor e mostra-as
 * agrupadas por profissional e por dia.
 *
 * Com data-selecionavel="true" (UC17, marcar consulta), cada vaga é um botão
 * que preenche os campos escondidos profissional_id e inicio do formulário
 * [data-marcacao] da mesma página.
 */
const pagina = document.querySelector('[data-pagina="disponibilidade"]');

if (pagina) {
    const filtro = pagina.querySelector('[data-filtro]');
    const resultados = pagina.querySelector('[data-resultados]');
    const locale = pagina.dataset.locale;
    const selecionavel = pagina.dataset.selecionavel === 'true';
    const marcacao = selecionavel ? pagina.querySelector('[data-marcacao]') : null;
    let pedidoAtual = 0;

    function limparEscolha() {
        if (!marcacao) return;
        marcacao.profissional_id.value = '';
        marcacao.inicio.value = '';
        marcacao.especialidade_id.value = filtro.especialidade_id.value;
        marcacao.querySelector('[data-escolha]').textContent = '';
        marcacao.querySelector('button[type="submit"]').disabled = true;
    }

    function escolher(botao, prof, iso) {
        resultados.querySelectorAll('[data-vaga].active').forEach((b) => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        botao.classList.add('active');
        botao.setAttribute('aria-pressed', 'true');

        marcacao.profissional_id.value = prof.id;
        marcacao.inicio.value = iso;
        marcacao.especialidade_id.value = filtro.especialidade_id.value;
        marcacao.querySelector('[data-escolha]').textContent =
            `${prof.nome} — ${formatarDia(iso)}, ${iso.slice(11, 16)}`;
        marcacao.querySelector('button[type="submit"]').disabled = false;
    }

    const el = (tag, classe, texto) => {
        const e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texto !== undefined) e.textContent = texto;
        return e;
    };

    // As vagas vêm como 'YYYY-MM-DDTHH:MM' (hora local da clínica); separa-se em texto
    // para não deixar o browser converter fusos horários.
    const formatarDia = (iso) => {
        const [a, m, d] = iso.slice(0, 10).split('-').map(Number);
        return new Date(a, m - 1, d).toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' });
    };

    function mostrar(dados) {
        resultados.replaceChildren();
        limparEscolha();

        for (const prof of dados.profissionais) {
            const cartao = el('section', 'mb-4');
            cartao.append(el('h2', 'h5', `${prof.nome} (${prof.tipo})`));

            if (prof.vagas.length === 0) {
                cartao.append(el('p', 'text-muted', pagina.dataset.textoSemVagas));
                resultados.append(cartao);
                continue;
            }

            const porDia = new Map();
            for (const vaga of prof.vagas) {
                const dia = vaga.slice(0, 10);
                if (!porDia.has(dia)) porDia.set(dia, []);
                porDia.get(dia).push(vaga);
            }

            for (const [dia, vagas] of porDia) {
                const linha = el('div', 'mb-2');
                linha.append(el('div', 'small fw-semibold text-capitalize', formatarDia(dia)));
                const lista = el('div', 'd-flex flex-wrap gap-1');
                for (const iso of vagas) {
                    const hora = iso.slice(11, 16);
                    if (selecionavel) {
                        const b = el('button', 'btn btn-sm btn-outline-primary', hora);
                        b.type = 'button';
                        b.dataset.vaga = iso;
                        b.setAttribute('aria-pressed', 'false');
                        b.addEventListener('click', () => escolher(b, prof, iso));
                        lista.append(b);
                    } else {
                        lista.append(el('span', 'badge text-bg-light border', hora));
                    }
                }
                linha.append(lista);
                cartao.append(linha);
            }

            resultados.append(cartao);
        }

        if (dados.profissionais.length === 0) {
            resultados.append(el('p', 'text-muted', pagina.dataset.textoSemVagas));
        }
    }

    async function carregar() {
        const especialidade = filtro.especialidade_id.value;
        if (!especialidade) {
            ++pedidoAtual;
            resultados.removeAttribute('aria-busy');
            resultados.replaceChildren();
            limparEscolha();
            return;
        }

        // Ignora respostas que cheguem fora de ordem (o utilizador mudou o filtro entretanto).
        const meu = ++pedidoAtual;
        const params = new URLSearchParams({ especialidade_id: especialidade });
        if (filtro.de.value) params.set('de', filtro.de.value);

        resultados.setAttribute('aria-busy', 'true');
        const r = await clinica.pedirJson(`${pagina.dataset.url}?${params}`);
        if (meu !== pedidoAtual) return;
        resultados.removeAttribute('aria-busy');

        if (r.ok) {
            mostrar(r.dados);
        } else {
            resultados.replaceChildren(el('p', 'text-danger', r.mensagem || pagina.dataset.textoErro));
            limparEscolha();
        }
    }

    filtro.addEventListener('change', carregar);
    filtro.addEventListener('submit', (e) => { e.preventDefault(); carregar(); });

    // Depois de um erro de validação a especialidade volta pré-selecionada: recarrega as vagas.
    if (filtro.especialidade_id.value) carregar();
}
