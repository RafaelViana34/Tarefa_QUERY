// Consulta de materiais: intercepta o submit, envia com fetch() usando QUERY
// e renderiza o resultado no DOM sem recarregar a página.
(function () {
  'use strict';

  var form      = document.getElementById('form-filtros');
  var corpo     = document.getElementById('corpo-resultados');
  var statusEl  = document.getElementById('status-consulta');
  var limpar    = document.getElementById('limpar-filtros');
  var botao     = form.querySelector('button[type="submit"]');
  var endpoint  = form.getAttribute('data-endpoint');

  var ultimaRequisicao = 0; // evita que uma resposta antiga sobrescreva a mais recente

  /** Lê os campos do formulário e devolve o objeto que vai no corpo JSON. */
  function coletarFiltros() {
    var dados = new FormData(form);
    var filtros = {
      busca:    (dados.get('busca') || '').trim(),
      situacao: dados.get('situacao') || 'todos',
      ordem:    dados.get('ordem') || 'recentes'
    };
    var empresa = (dados.get('empresa_id') || '').trim();
    if (empresa !== '') {
      filtros.empresa_id = empresa; // validado como inteiro no servidor
    }
    return filtros;
  }

  /** Envia a consulta. Se o servidor recusar QUERY (405/501), reenvia como POST com override. */
  function enviar(filtros, metodo, usarOverride) {
    var cabecalhos = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (usarOverride) {
      cabecalhos['X-HTTP-Method-Override'] = 'QUERY';
    }
    return fetch(endpoint, {
      method: metodo,
      headers: cabecalhos,
      body: JSON.stringify(filtros),
      credentials: 'same-origin',
      cache: 'no-store'
    });
  }

  function consultar() {
    var filtros = coletarFiltros();
    var requisicao = ++ultimaRequisicao;
    definirCarregando(true);

    enviar(filtros, 'QUERY', false)
      .then(function (resp) {
        if (resp.status === 405 || resp.status === 501) {
          return enviar(filtros, 'POST', true);
        }
        return resp;
      })
      .then(function (resp) {
        return resp.json().catch(function () { return {}; }).then(function (json) {
          return { ok: resp.ok, json: json };
        });
      })
      .then(function (r) {
        if (requisicao !== ultimaRequisicao) return; // resposta antiga, ignora
        if (!r.ok) throw new Error(r.json.erro || 'Falha ao consultar os materiais.');
        renderizar(r.json);
      })
      .catch(function (erro) {
        if (requisicao !== ultimaRequisicao) return;
        mostrarErro(erro.message || 'Erro de conexão.');
      })
      .then(function () {
        if (requisicao === ultimaRequisicao) definirCarregando(false);
      });
  }

  /** Cria as linhas da tabela com createElement/textContent (nunca innerHTML). */
  function renderizar(dados) {
    corpo.textContent = '';

    if (!dados.itens || dados.itens.length === 0) {
      var vazio = document.createElement('tr');
      var celula = document.createElement('td');
      celula.colSpan = 3;
      celula.className = 'vazio';
      celula.textContent = 'Nenhum material encontrado com esses filtros.';
      vazio.appendChild(celula);
      corpo.appendChild(vazio);
    } else {
      dados.itens.forEach(function (item) {
        var linha = document.createElement('tr');
        linha.appendChild(celulaTexto(String(item.id)));
        linha.appendChild(celulaTexto(item.empresa));
        linha.appendChild(celulaSelo(item.situacao));
        corpo.appendChild(linha);
      });
    }

    var texto = dados.total === 1 ? '1 material encontrado.' : dados.total + ' materiais encontrados.';
    if (dados.total > dados.itens.length) {
      texto += ' Mostrando os ' + dados.itens.length + ' mais recentes; refine os filtros.';
    }
    statusEl.className = 'sub';
    statusEl.textContent = texto;
  }

  function celulaTexto(valor) {
    var td = document.createElement('td');
    td.textContent = valor;
    return td;
  }

  var ROTULOS = { ativo: 'Ativo', vendido: 'Vendido', pausado: 'Pausado' };
  var CLASSES = { ativo: 'selo selo-ativo', vendido: 'selo selo-vendido', pausado: 'selo selo-pausado' };

  function celulaSelo(situacao) {
    var td = document.createElement('td');
    var selo = document.createElement('span');
    selo.className = CLASSES[situacao] || 'selo';
    selo.textContent = ROTULOS[situacao] || situacao;
    td.appendChild(selo);
    return td;
  }

  function mostrarErro(mensagem) {
    corpo.textContent = '';
    statusEl.className = 'alerta alerta-erro';
    statusEl.textContent = mensagem;
  }

  function definirCarregando(carregando) {
    botao.disabled = carregando;
    botao.textContent = carregando ? 'Consultando…' : 'Filtrar';
  }

  // Interceptação do submit: sem recarregar a página.
  form.addEventListener('submit', function (evento) {
    evento.preventDefault();
    consultar();
  });

  limpar.addEventListener('click', function () {
    form.reset();
    consultar();
  });

  // Carga inicial com os filtros padrão.
  consultar();
})();
