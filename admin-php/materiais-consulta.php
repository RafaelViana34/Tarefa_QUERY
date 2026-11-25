<?php
/**
 * materiais-consulta.php — Endpoint de consulta (backend) da listagem de materiais.
 *
 * Aceita dois métodos HTTP para a MESMA consulta:
 *   - QUERY : método de consulta com corpo (rascunho IETF "safe method with body").
 *   - POST  : alternativa, usada quando o servidor/proxy não aceita QUERY.
 *             O navegador pode enviar POST + "X-HTTP-Method-Override: QUERY".
 *
 * O corpo da requisição é JSON, lido de php://input. Nenhum valor do usuário é
 * concatenado no SQL: os valores entram por prepared statements (PDO), e a parte
 * variável do SQL é montada apenas com fragmentos fixos escolhidos por lista branca.
 */
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/** Envia a resposta JSON e encerra. */
function responder(int $status, array $corpo, array $extra = []): void
{
    http_response_code($status);
    foreach ($extra as $nome => $valor) {
        header($nome . ': ' . $valor);
    }
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

// Só consultas: QUERY e POST. Qualquer outro método recebe 405 com a lista aceita.
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
if ($metodo !== 'QUERY' && $metodo !== 'POST') {
    responder(405, ['ok' => false, 'erro' => 'Método não permitido.'], ['Allow' => 'QUERY, POST']);
}

// Sessão de administrador exigida no servidor.
if (empty($_SESSION['admin'])) {
    responder(401, ['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.']);
}

// ---------- Leitura do corpo (php://input) ----------
$bruto = file_get_contents('php://input', false, null, 0, 4097);
if ($bruto === false || strlen($bruto) > 4096) {
    responder(413, ['ok' => false, 'erro' => 'Requisição grande demais.']);
}
$entrada = $bruto === '' ? [] : json_decode($bruto, true);
if (!is_array($entrada)) {
    responder(400, ['ok' => false, 'erro' => 'Corpo da requisição inválido (esperado JSON).']);
}

// ---------- Validação (listas brancas e limites) ----------
$busca = trim((string) ($entrada['busca'] ?? ''));
// Limita a 100 caracteres (UTF-8) sem depender da extensão mbstring.
if (preg_match('/^.{0,100}/us', $busca, $m)) {
    $busca = $m[0];
}

// Situação: valor vindo do cliente nunca vira SQL; só escolhe um fragmento fixo.
$fragmentosSituacao = [
    'todos'   => null,
    'ativo'   => "m.publicado = TRUE AND m.status <> 'vendido'",
    'vendido' => "m.status = 'vendido'",
];
$situacao = (string) ($entrada['situacao'] ?? 'todos');
if (!array_key_exists($situacao, $fragmentosSituacao)) {
    responder(422, ['ok' => false, 'erro' => 'Situação inválida.']);
}

$ordenacoes = [
    'recentes' => 'm.id DESC',
    'empresa'  => 'e.nome_empresa ASC, m.id DESC',
];
$ordem = (string) ($entrada['ordem'] ?? 'recentes');
if (!array_key_exists($ordem, $ordenacoes)) {
    responder(422, ['ok' => false, 'erro' => 'Ordenação inválida.']);
}

$empresaId = null;
if (isset($entrada['empresa_id']) && $entrada['empresa_id'] !== '') {
    $empresaId = filter_var($entrada['empresa_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($empresaId === false) {
        responder(422, ['ok' => false, 'erro' => 'ID de empresa inválido.']);
    }
}

// ---------- Montagem do SELECT ----------
$condicoes = [];
$params    = [];

if ($fragmentosSituacao[$situacao] !== null) {
    $condicoes[] = $fragmentosSituacao[$situacao];
}
if ($busca !== '') {
    // Escapa % e _ para que a busca seja literal.
    $condicoes[]      = 'e.nome_empresa ILIKE :busca';
    $params[':busca'] = '%' . addcslashes($busca, '%_\\') . '%';
}
if ($empresaId !== null) {
    $condicoes[]              = 'm.empresa_id = :empresa_id';
    $params[':empresa_id']    = $empresaId;
}

$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$sql = "
    SELECT
        m.id,
        m.empresa_id,
        e.nome_empresa,
        m.status,
        CASE
            WHEN m.status = 'vendido' THEN 'vendido'
            WHEN m.publicado          THEN 'ativo'
            ELSE 'pausado'
        END AS situacao,
        COUNT(*) OVER() AS total
    FROM materiais m
    JOIN empresas e ON e.id = m.empresa_id
    {$where}
    ORDER BY {$ordenacoes[$ordem]}
    LIMIT 100
";

try {
    $stmt = conectar()->prepare($sql);
    $stmt->execute($params);
    $linhas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('materiais-consulta: ' . $e->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível consultar os materiais agora.']);
}

$total = $linhas ? (int) $linhas[0]['total'] : 0;
$itens = array_map(static fn(array $l): array => [
    'id'           => (int) $l['id'],
    'empresa_id'   => (int) $l['empresa_id'],
    'empresa'      => (string) $l['nome_empresa'],
    'status'       => (string) $l['status'],
    'situacao'     => (string) $l['situacao'],
], $linhas);

responder(200, ['ok' => true, 'total' => $total, 'itens' => $itens]);
