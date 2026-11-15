/**
 * UC25 (#73): gráficos das estatísticas com Chart.js.
 * Os números vêm do servidor num <script type="application/json">; as tabelas da página
 * mostram os mesmos dados, por isso a página lê-se igual sem JavaScript.
 * O Chart.js só é descarregado nesta página (import dinâmico).
 */
const dados = document.getElementById('dados-estatisticas');

if (dados) {
    desenhar(JSON.parse(dados.textContent));
}

async function desenhar({ estados, profissionais, especialidades }) {
    const { default: Chart } = await import('chart.js/auto');

    const tinta = getComputedStyle(document.body).getPropertyValue('--bs-secondary-color').trim() || '#6c757d';
    const grelha = getComputedStyle(document.body).getPropertyValue('--bs-border-color').trim() || '#dee2e6';
    Chart.defaults.color = tinta;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

    // Contagens são inteiros: sem casas decimais no eixo.
    const eixoValores = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grelha } };
    const eixoNomes = { grid: { display: false } };
    const barra = { borderRadius: 4, borderColor: '#fff', borderWidth: 2, maxBarThickness: 28 };

    const telaEstados = document.querySelector('canvas[data-grafico="estados"]');
    if (telaEstados) {
        new Chart(telaEstados, {
            type: 'bar',
            data: {
                labels: estados.map((e) => e.nome),
                datasets: [{ data: estados.map((e) => e.total), backgroundColor: estados.map((e) => e.cor), ...barra }],
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                // Uma só série: o título da secção já diz o que é, a legenda não acrescenta nada.
                plugins: { legend: { display: false } },
                scales: { x: eixoValores, y: eixoNomes },
            },
        });
    }

    // Barras empilhadas por estado, uma barra por profissional ou por especialidade.
    const empilhado = (seletor, linhas) => {
        const tela = document.querySelector(seletor);
        if (!tela) {
            return;
        }

        new Chart(tela, {
            type: 'bar',
            data: {
                labels: linhas.map((l) => l.nome),
                datasets: estados.map((e) => ({
                    label: e.nome,
                    data: linhas.map((l) => l.estados[e.valor]),
                    backgroundColor: e.cor,
                    ...barra,
                })),
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12 } },
                    tooltip: { mode: 'index', filter: (item) => item.parsed.x > 0 },
                },
                scales: { x: { ...eixoValores, stacked: true }, y: { ...eixoNomes, stacked: true } },
            },
        });
    };

    empilhado('canvas[data-grafico="profissionais"]', profissionais);
    empilhado('canvas[data-grafico="especialidades"]', especialidades);
}
