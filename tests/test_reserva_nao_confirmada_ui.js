// Executa as funcoes reais do modal com DOM/API simulados; sem requests externos.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../app/Views/layouts/app.php', 'utf8');
const section = (start, end) => source.slice(source.indexOf(start), source.indexOf(end, source.indexOf(start)));
const elements = new Map();
function element(id) {
    if (!elements.has(id)) elements.set(id, {style: {}, value: '', disabled: false, textContent: '',
        classList: {remove() {}}, focus() {}, removeEventListener() {}});
    return elements.get(id);
}
const posts = [], alerts = [];
let nextResponse = {success: true, notificacao: {status: 'queued'}, message: 'queued'};
const ctx = vm.createContext({document: {getElementById: element}, console,
    API: {post: async (url, payload) => {posts.push({url, payload}); return nextResponse;}},
    exclusaoFinanceiroT: {processing: 'processing', preview_error: 'error'},
    openAlertModal: message => alerts.push(message)});
ctx.window = ctx; ctx.location = {origin: 'https://local.test'};
vm.runInContext(`let globalRecordId, globalRecordName, globalRecordType, globalCustomAction, globalSourceWindow,
    globalConfirmType, globalExpectedText, globalDeleteOptions, globalFinanceiroDelete;
    ${section('window.closeGlobalDeleteModal = function()', '/**\n         *',)}
`, ctx);
// Separar fechamento pelo delimitador real para nao incluir outras funcoes.
vm.runInContext(section('function validateGlobalDeleteConfirmation()', '/**\n         * Confirma a exclusão'), ctx);
vm.runInContext(section('window.confirmGlobalDelete = function()', "document.getElementById('deleteFinanceiroReason').addEventListener"),ctx);
vm.runInContext(section('function setDeleteNotificationStep(show)', '// ===== MODAL DE CONFIRMAÇÃO GENÉRICO'),ctx);
function reset(eligible=true, modulo='locacoes') {
    posts.length=0;alerts.length=0;
    element('confirmDeleteInput').value='EXCLUIR';element('deleteFinanceiroReason').value='';
    vm.runInContext(`globalRecordId=123;globalExpectedText='EXCLUIR';globalFinanceiroDelete={id:123,modulo:'${modulo}',busy:false,preview:{referencia:'token',pode_notificar_cliente:${eligible},exige_motivo:false}};validateGlobalDeleteConfirmation();`,ctx);
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
    reset();ctx.confirmGlobalDelete();assert.equal(posts.length,0);assert.equal(element('deleteNotifyYes').style.display,'');
    ctx.closeGlobalDeleteModal();ctx.answerDeleteNotification(true);await tick();assert.equal(posts.length,0);
    console.log('OK Cancelar fecha sem excluir ou notificar');
    for(const notify of [true,false]) {
        reset();ctx.confirmGlobalDelete();ctx.answerDeleteNotification(notify);ctx.answerDeleteNotification(notify);await tick();
        assert.equal(posts.length,1);assert.equal(posts[0].payload.notificar_cliente,notify);
        console.log('OK escolha '+notify+' e bloqueio de clique duplicado');
    }
    reset(false);ctx.confirmGlobalDelete();await tick();assert.equal(posts.length,1);assert.equal(posts[0].payload.notificar_cliente,false);
    reset(false,'contratos');ctx.confirmGlobalDelete();await tick();assert.equal('notificar_cliente' in posts[0].payload,false);
    console.log('OK outros status e contratos preservados');
    nextResponse={success:false,refresh_preview:true,message:'Status mudou'};
    reset();ctx.confirmGlobalDelete();ctx.answerDeleteNotification(true);await tick();
    assert.equal(element('confirmDeleteInput').value,'');assert.equal(element('deleteNotifyYes').style.display,'none');
    assert.equal(element('confirmDeleteButton').disabled,true);assert.equal(alerts.length,0);
    console.log('OK conflito exige nova previa e confirmacao');
    console.log('PASS modal reserva nao confirmada');
})().catch(e=>{console.error(e);process.exitCode=1;});
