# ScrapCash — Consulta de materiais com QUERY e POST

Implementação da consulta com filtros do back-office do ScrapCash.

## Arquivos

| Arquivo | Função |
|---|---|
| `admin-php/materiais.php` | Página com o formulário de filtros e a tabela (renderizada no DOM) |
| `admin-php/materiais-consulta.php` | Endpoint que aceita `QUERY` e `POST`, lê `php://input` e monta o `SELECT` com prepared statements |
| `admin-php/assets/consulta-materiais.js` | Intercepta o submit (`preventDefault`), envia com `fetch()` usando `QUERY` e renderiza o resultado |

## Como funciona

1. O usuário preenche os filtros (nome da empresa, situação, ID da empresa, ordenação).
2. O JavaScript chama `event.preventDefault()` no submit e envia um `fetch()` com `method: 'QUERY'` e corpo JSON.
3. O PHP lê o corpo em `php://input`, valida cada campo contra listas brancas e monta o `SELECT`. Todos os valores do usuário entram por `:parâmetros` nomeados de PDO.
4. A resposta JSON é usada para recriar as linhas da tabela com `createElement` e `textContent`, sem recarregar a página.

## Por que QUERY e POST

- `QUERY` é um método HTTP feito para consultas com corpo: é seguro (não altera dados) e pode levar filtros complexos sem colocá-los na URL.
- `POST` é aceito pelo mesmo endpoint como alternativa. Se o servidor ou um proxy recusar `QUERY` (respostas 405 ou 501), o JavaScript reenvia a mesma consulta como `POST` com o cabeçalho `X-HTTP-Method-Override: QUERY`.

## Segurança

- Sessão de administrador verificada no servidor antes de qualquer consulta (respostas 401 sem sessão).
- Valores validados: situação e ordenação por lista branca, ID de empresa como inteiro positivo, busca limitada a 100 caracteres, corpo limitado a 4 KB.
- Busca textual com `ILIKE` e escape de `%` e `_`.
- Erros de banco vão para o log; o cliente recebe só uma mensagem genérica.
- Renderização no DOM sem `innerHTML`, evitando XSS.

## Como testar

1. Extraia os arquivos sobre a pasta `admin-php/` do projeto, mantendo a estrutura.
2. Suba o projeto (`docker compose up`) e entre no back-office com um usuário `admin`.
3. Abra `materiais.php`. A tabela é carregada ao abrir a página. Altere os filtros e clique em **Filtrar**: a URL não muda e a página não recarrega.
4. Na aba Rede (F12), a requisição aparece com o método `QUERY`.

## Observação sobre o servidor

Alguns servidores (Apache ou proxies) podem recusar métodos desconhecidos. Nesse caso o fallback para `POST` + `X-HTTP-Method-Override` entra automaticamente. Para aceitar `QUERY` diretamente no Apache, é preciso permitir o método na configuração do servidor.

## Colunas da tabela `materiais`

A consulta usa `materiais.id`, `materiais.empresa_id`, `materiais.status`, `materiais.publicado` e `empresas.nome_empresa`, as mesmas colunas já usadas em `dashboard.php`. Para mostrar mais campos (por exemplo, título ou preço), basta acrescentá-los ao `SELECT` e à tabela.
