<?php
declare(strict_types=1);
require_once __DIR__.'/banco.php';
require_once __DIR__.'/sefaz.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function responder(array $dados,int $status=200):void {
    http_response_code($status);
    echo json_encode($dados,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    $acao=$_GET['acao']??'';
    if (!is_string($acao)) throw new InvalidArgumentException('Ação inválida.');
    $metodo=$_SERVER['REQUEST_METHOD']??'';
    if ($acao==='buscar' || $acao==='status') {
        if ($metodo!=='GET') { header('Allow: GET'); responder(['erro'=>'Utilize GET.'],405); }
        $db=banco();
        if ($acao==='status') responder(['ok'=>true,'armazenamento'=>'SQLite']);
        $termo=$_GET['termo']??''; $pagina=$_GET['pagina']??'1';
        if (!is_string($termo) || strlen($termo)>300 || !is_string($pagina) || !ctype_digit($pagina) || (int)$pagina<1 || (int)$pagina>1000000) throw new InvalidArgumentException('Pesquisa ou página inválida.');
        responder(buscar($db,trim($termo),(int)$pagina));
    }
    if ($acao==='importar') {
        if ($metodo!=='POST') { header('Allow: POST'); responder(['erro'=>'Utilize POST.'],405); }
        try { $dados=json_decode(file_get_contents('php://input') ?: '',true,32,JSON_THROW_ON_ERROR); }
        catch(JsonException $e) { throw new InvalidArgumentException('JSON inválido.'); }
        $chave=is_array($dados)?($dados['chaveNfce']??null):null;
        if (!is_string($chave) || !chaveValida($chave)) throw new InvalidArgumentException('Chave de acesso inválida.');
        $db=banco(); // Falha antes de consultar a SEFAZ se não houver armazenamento.
        $itens=consultarSefaz($chave);
        $incluidos=gravar($db,$chave,$itens);
        responder(['ok'=>true,'incluidos'=>$incluidos,'mensagem'=>'Nota consultada e processada com sucesso.']);
    }
    throw new InvalidArgumentException('Ação desconhecida.');
} catch(InvalidArgumentException $e) { responder(['erro'=>$e->getMessage()],400); }
catch(PDOException $e) {
    error_log('NFCe SQLite: '.$e->getMessage());
    responder(['erro'=>'Falha no armazenamento. Verifique PDO_SQLite e a permissão de escrita na pasta dados. Nenhuma inclusão parcial foi confirmada.'],500);
} catch(Throwable $e) { responder(['erro'=>$e->getMessage()],502); }
