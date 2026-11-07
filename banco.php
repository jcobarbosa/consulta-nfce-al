<?php
declare(strict_types=1);
function banco(): PDO {
    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('SQLite indisponível: solicite PDO_SQLite à hospedagem. Nenhum dado foi gravado.');
    }
    $db = new PDO('sqlite:' . __DIR__ . '/dados/notas.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=10000');
    $db->exec('CREATE TABLE IF NOT EXISTS itens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chaveNfce TEXT NOT NULL,
        item TEXT NOT NULL,
        valor_unitario TEXT NOT NULL,
        valor_total TEXT NOT NULL,
        dataInclusao TEXT NOT NULL,
        UNIQUE(chaveNfce, item)
    )');
    return $db;
}
function gravar(PDO $db, string $chave, array $itens): int {
    $data = (new DateTimeImmutable('now', new DateTimeZone('America/Maceio')))->format('d/m/Y');
    $db->beginTransaction();
    try {
        // Compatível também com versões antigas de SQLite.
        $sql = $db->prepare('INSERT OR IGNORE INTO itens (chaveNfce,item,valor_unitario,valor_total,dataInclusao) VALUES (?,?,?,?,?)');
        $incluidos = 0;
        foreach ($itens as $item) {
            $sql->execute([$chave,$item['item'],$item['valor_unitario'],$item['valor_total'],$data]);
            $incluidos += $sql->rowCount();
        }
        $db->commit();
        return $incluidos;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
function buscar(PDO $db, string $termo, int $pagina): array {
    // Pesquisa literal por trecho: % e _ digitados pelo usuário não são curingas.
    $like = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $termo) . '%';
    $where = " FROM itens WHERE item LIKE :termo ESCAPE '!'";
    $count = $db->prepare('SELECT COUNT(*)' . $where);
    $count->execute([':termo'=>$like]);
    $total = (int)$count->fetchColumn();
    // DD/MM/YYYY não pode ser ordenado diretamente como texto.
    $sql = $db->prepare('SELECT chaveNfce,item,valor_unitario,valor_total,dataInclusao' . $where .
        ' ORDER BY substr(dataInclusao,7,4) DESC, substr(dataInclusao,4,2) DESC, substr(dataInclusao,1,2) DESC, id DESC LIMIT 50 OFFSET :offset');
    $sql->bindValue(':termo', $like, PDO::PARAM_STR);
    $sql->bindValue(':offset', ($pagina-1)*50, PDO::PARAM_INT);
    $sql->execute();
    return ['itens'=>$sql->fetchAll(), 'total'=>$total, 'pagina'=>$pagina, 'porPagina'=>50];
}
