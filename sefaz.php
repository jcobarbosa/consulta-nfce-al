<?php
declare(strict_types=1);
function chaveValida(string $chave): bool {
    if (!preg_match('/^[0-9]{44}$/D', $chave) || preg_match('/^([0-9])\1{43}$/D', $chave)) return false;
    $soma=0; $peso=2;
    for ($i=42;$i>=0;$i--) { $soma += (int)$chave[$i]*$peso; $peso=$peso===9?2:$peso+1; }
    $resto=$soma%11;
    return (int)$chave[43] === ($resto<2?0:11-$resto);
}
function consultarSefaz(string $chave): array {
    if (!extension_loaded('curl') || !class_exists('DOMDocument')) throw new RuntimeException('O servidor precisa das extensões cURL e DOM.');
    $curl=curl_init('https://nfce.sefaz.al.gov.br/consultarNFCe.htm');
    if ($curl===false) throw new RuntimeException('Não foi possível iniciar a consulta.');
    curl_setopt_array($curl,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query(['tipoAmbiente'=>'1','chaveAcesso'=>implode(' ',str_split($chave,4))],'','&',PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_MAXREDIRS=>5,
        CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>60, CURLOPT_ENCODING=>'',
        CURLOPT_USERAGENT=>'ConsultaNFCe/1.0',
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: text/html']
    ]);
    try { $html=curl_exec($curl); $erro=curl_error($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); }
    finally { curl_close($curl); }
    if ($html===false) throw new RuntimeException('Falha na conexão com a SEFAZ: '.$erro);
    if ($status!==200) throw new RuntimeException('A SEFAZ retornou HTTP '.$status.'.');
    if (trim($html)==='') throw new RuntimeException('A SEFAZ retornou uma resposta vazia.');
    $dom=new DOMDocument(); $anterior=libxml_use_internal_errors(true);
    try { $ok=$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($anterior); }
    if (!$ok) throw new RuntimeException('HTML da SEFAZ inválido.');
    $xp=new DOMXPath($dom);
    $classe=static fn(string $c):string=>"contains(concat(' ',normalize-space(@class),' '),' {$c} ')";
    $texto=static function(DOMNode $linha,string $c) use($xp,$classe):string {
        $nodes=$xp->query('.//*['.$classe($c).']',$linha);
        if ($nodes===false || $nodes->length===0) throw new RuntimeException('Campo ausente na resposta da SEFAZ: '.$c);
        return trim(preg_replace('/[\s\x{00A0}]+/u',' ',$nodes->item(0)->textContent) ?? '');
    };
    $numero=static function(string $v):string {
        if (!preg_match('/\d+(?:\.\d{3})*(?:,\d+)?/u',$v,$m)) throw new RuntimeException('Valor inválido na resposta da SEFAZ.');
        return str_replace(',','.',str_replace('.','',$m[0]));
    };
    $linhas=$xp->query('//tr[.//*['.$classe('Rqtd').']]');
    $itens=[];
    if ($linhas!==false) foreach($linhas as $linha) {
        $nome=$texto($linha,'txtTit');
        if ($nome==='') throw new RuntimeException('Descrição vazia na resposta da SEFAZ.');
        $itens[]=['item'=>$nome,'valor_unitario'=>$numero($texto($linha,'RvlUnit')),'valor_total'=>$numero($texto($linha,'valor'))];
    }
    if (!$itens) throw new RuntimeException('A SEFAZ não retornou itens. A nota pode não estar disponível ou o formato da página mudou.');
    return $itens;
}
