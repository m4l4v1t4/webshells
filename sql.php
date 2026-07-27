<?php
// ==============================================================================
// CONFIGURAÇÕES DO BANCO DE DADOS - Altere de acordo com seu ambiente
// ==============================================================================
$db_host = 'localhost';
$db_user = 'ce3xl2xw_wp643';
$db_pass = 'ce3xl2xw_wp643!';
$db_name = 'ce3xl2xw_wp643';
$db_port = 3306; // Porta padrão do MySQL

$erro_conexao = null;
$resultado = null;
$query_executada = '';

// Tenta conectar ao MySQL
try {
    $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $erro_conexao = "Falha na conexão com o banco de dados: " . $e->getMessage();
}

// Processa a requisição POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_base64']) && !$erro_conexao) {
    // Decodifica a query recebida em Base64
    $query_raw = base64_decode($_POST['sql_base64']);
    $query_executada = trim($query_raw);

    if (!empty($query_executada)) {
        try {
            $stmt = $pdo->prepare($query_executada);
            $stmt->execute();

            // Verifica se o comando retorna linhas (ex: SELECT, SHOW, DESCRIBE)
            if ($stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll();
                $resultado = [
                    'tipo' => 'select',
                    'dados' => $rows,
                    'total' => count($rows)
                ];
            } else {
                // Comandos que alteram dados (ex: INSERT, UPDATE, DELETE)
                $resultado = [
                    'tipo' => 'affect',
                    'linhas' => $stmt->rowCount()
                ];
            }
        } catch (PDOException $e) {
            $resultado = [
                'tipo' => 'erro',
                'mensagem' => $e->getMessage()
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executor SQL PHP</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 20px; background-color: #f8f9fa; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #111; }
        textarea { width: 100%; height: 140px; font-family: monospace; font-size: 14px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; resize: vertical; }
        button { background-color: #0066cc; color: #fff; border: none; padding: 10px 20px; font-size: 15px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #0052a3; }
        .alert { padding: 12px 16px; border-radius: 4px; margin-top: 20px; }
        .alert-error { background-color: #fce8e6; color: #a50e0e; border: 1px solid #f5c2c7; }
        .alert-success { background-color: #e6f4ea; color: #137333; border: 1px solid #badbcc; }
        .table-container { margin-top: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; }
        th { background-color: #f1f3f4; font-weight: bold; }
        tr:nth-child(even) { background-color: #fafafa; }
        .meta { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Executor SQL MySQL</h2>

    <?php if ($erro_conexao): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erro_conexao) ?></div>
    <?php else: ?>

        <form id="sqlForm" method="POST" action="">
            <label for="sql_input"><strong>Digite o comando SQL:</strong></label><br><br>
            <textarea id="sql_input" placeholder="SELECT * FROM minha_tabela LIMIT 10;"><?= htmlspecialchars($query_executada) ?></textarea>
            
            <!-- Campo oculto que transportará a query codificada em Base64 -->
            <input type="hidden" name="sql_base64" id="sql_base64">
            
            <br>
            <button type="submit">Executar Query</button>
        </form>

        <?php if ($resultado): ?>
            <hr style="margin-top:25px; border:0; border-top:1px solid #eee;">
            
            <?php if ($resultado['tipo'] === 'erro'): ?>
                <div class="alert alert-error">
                    <strong>Erro na execução SQL:</strong><br>
                    <code><?= htmlspecialchars($resultado['mensagem']) ?></code>
                </div>

            <?php elseif ($resultado['tipo'] === 'affect'): ?>
                <div class="alert alert-success">
                    Comando executado com sucesso! <strong><?= $resultado['linhas'] ?></strong> linha(s) afetada(s).
                </div>

            <?php elseif ($resultado['tipo'] === 'select'): ?>
                <div class="alert alert-success">
                    Query executada com sucesso. Retornou <strong><?= $resultado['total'] ?></strong> registro(s).
                </div>

                <?php if ($resultado['total'] > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <?php 
                                    // Pega os nomes das colunas a partir das chaves do primeiro registro
                                    $colunas = array_keys($resultado['dados'][0]);
                                    foreach ($colunas as $coluna): 
                                    ?>
                                        <th><?= htmlspecialchars($coluna) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultado['dados'] as $linha): ?>
                                    <tr>
                                        <?php foreach ($linha as $valor): ?>
                                            <td><?= $valor === null ? '<em>NULL</em>' : htmlspecialchars($valor) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    // Captura o submit para converter o conteúdo do textarea em Base64 antes de enviar ao servidor
    document.getElementById('sqlForm').addEventListener('submit', function(e) {
        const textareaValue = document.getElementById('sql_input').value;
        
        // Trata caracteres UTF-8 corretamente ao converter para Base64 no browser
        const encoder = new TextEncoder();
        const data = encoder.encode(textareaValue);
        const base64String = btoa(String.fromCharCode.apply(null, data));
        
        document.getElementById('sql_base64').value = base64String;
    });
</script>

</body>
</html>
