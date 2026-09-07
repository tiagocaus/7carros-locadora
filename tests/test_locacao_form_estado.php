<?php
/** Executa as funcoes reais do formulario em Node com DOM/API simulados. */
$source = file_get_contents(__DIR__ . '/../app/Views/pages/locacoes/adicionar.php');
$inicio = strpos($source, '        let devolucaoCreditoPendente = null;');
$fim = strpos($source, "        document.getElementById('formLocacao')?.addEventListener('submit'", $inicio);
$functions = preg_replace('/<\?php.*?\?>|<\?=.*?\?>/s', '"traducao"', substr($source, $inicio, $fim - $inicio));
$script = <<<'JS'
const assert = require('node:assert/strict');
const nodes = {btnSalvar: {disabled:false, innerHTML:''}, formLocacao:{}};
const document = {getElementById: id => nodes[id] || null};
let captures=0, options=0, visibility=0;
const window = {parent:{postMessage(){}}, FormAudit:{recapture(){captures++;}}};
const isEditing = true;
const locacaoData = {id:10,status:'R'};
const i18n = {saving:'saving',updated:'updated',saveError:'error'};
const calls=[];
let response={success:true,data:{status:'A',id_veiculo:25}};
const API={post:async(url,data)=>{calls.push({...data});return response;}};
function atualizarStatusOpcoes(){options++;}
function atualizarVisibilidadePorStatus(){visibility++;}
function navegarPara(){}
async function carregarParcelas(){}
JS;
$script .= "\n" . $functions . "\n";
$script .= <<<'JS'
(async()=>{
 await enviarLocacao({status:'A'});
 assert.equal(calls[0].status_original,'R');
 assert.equal(locacaoData.status,'A');
 assert.equal(captures,1);
 assert.equal(options,1);
 assert.equal(visibility,1);
 await enviarLocacao({status:'A'});
 assert.equal(calls[1].status_original,'A');
 response={success:false,code:'status_conflict',message:'conflito'};
 await enviarLocacao({status:'A'});
 assert.equal(nodes.btnSalvar.disabled,true);
 const n=calls.length;
 await enviarLocacao({status:'R'});
 assert.equal(calls.length,n);
 assert.equal(captures,2);
 assert.equal(locacaoData.status,'A');
 console.log('PASS formulario usa status salvo, recaptura auditoria e bloqueia reenvio apos conflito');
})().catch(e=>{console.error(e);process.exitCode=1;});
JS;
$process = proc_open(['node'], [['pipe','r'], STDOUT, STDERR], $pipes);
fwrite($pipes[0], $script);
fclose($pipes[0]);
exit(proc_close($process));
