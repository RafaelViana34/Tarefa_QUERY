<?php
/**
 * materiais.php — Página de consulta de materiais com filtros.
 * O formulário é enviado por fetch() com o método QUERY (ver assets/consulta-materiais.js);
 * a página não recarrega ao filtrar.
 */
require_once __DIR__ . '/includes/auth.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$admin = exigirLogin();

$tituloPagina = 'Consulta de materiais';
require __DIR__ . '/includes/header.php';
?>

<h1>Consulta de materiais</h1>

<section class="cartao">
  <form id="form-filtros" class="formulario filtros" data-endpoint="materiais-consulta.php" novalidate>
    <label>Empresa (nome)
      <input type="search" name="busca" maxlength="100" placeholder="Ex.: Sucatas do Sul" autocomplete="off">
    </label>
    <label>Situação
      <select name="situacao">
        <option value="todos">Todos</option>
        <option value="ativo">Ativos</option>
        <option value="vendido">Vendidos</option>
      </select>
    </label>
    <label>ID da empresa
      <input type="number" name="empresa_id" min="1" step="1" inputmode="numeric" placeholder="Opcional">
    </label>
    <label>Ordenar por
      <select name="ordem">
        <option value="recentes">Mais recentes</option>
        <option value="empresa">Empresa (A–Z)</option>
      </select>
    </label>
    <div class="botoes">
      <button type="submit" class="botao">Filtrar</button>
      <button type="button" class="botao secundario" id="limpar-filtros">Limpar</button>
    </div>
  </form>

  <p id="status-consulta" class="sub" role="status" aria-live="polite">Carregando…</p>

  <div class="tabela-wrap">
    <table>
      <thead>
        <tr><th>ID</th><th>Empresa</th><th>Situação</th></tr>
      </thead>
      <tbody id="corpo-resultados"></tbody>
    </table>
  </div>
</section>

<script src="assets/consulta-materiais.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
