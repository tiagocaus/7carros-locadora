// Executa as funcoes reais do modal com DOM/API simulados; sem requests externos.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../app/Views/layouts/app.php', 'utf8');
const section = (start, end) => source.slice(source.indexOf(start), source.indexOf(end, source.indexOf(start)));
const elements = new Map();
function element(id) {
    if (!elements.has(id)) {
        const classes = new Set(), listeners = new Map();
        elements.set(id, {style: {}, value: '', disabled: false, textContent: '',
            classList: {add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value)},
            addEventListener(type, fn) { if (!listeners.has(type)) listeners.set(type, []); listeners.get(type).push(fn); },
            click() { if (!this.disabled) for (const fn of listeners.get('click') || []) fn(); },
            querySelector() { return null; }, querySelectorAll() { return []; },
            focus() {}, removeEventListener() {}});
    }
    return elements.get(id);
}
const posts = [], alerts = [], messages = [], ready = [];
const iframe = {postMessage: message => messages.push(message)};
let nextResponse = {success: true, notificacao: {status: 'queued'}, message: 'queued'};
const ctx = vm.createContext({document: {getElementById: element, querySelector: () => null, querySelectorAll: () => [],
        addEventListener(type, fn) { if (type === 'DOMContentLoaded') ready.push(fn); }}, console,
    DateHelper: {timestamp: () => 1},
    localStorage: {getItem: () => null}, setInterval() {}, clearInterval() {},
    iframe,
    API: {get: async () => ({success: true, data: {}}), post: async (url, payload) => {posts.push({url, payload}); return nextResponse;}},
    exclusaoFinanceiroT: {processing: 'processing', preview_error: 'error'},
    openAlertModal: message => alerts.push(message)});
ctx.addEventListener = () => {};
ctx.window = ctx; ctx.location = {origin: 'https://local.test'};
vm.runInContext(`let globalRecordId, globalRecordName, globalRecordType, globalCustomAction, globalSourceWindow,
    globalConfirmType, globalExpectedText, globalDeleteOptions, globalFinanceiroDelete;
    ${section('window.closeGlobalDeleteModal = function()', '/**\n         *',)}
`, ctx);
// Separar fechamento pelo delimitador real para nao incluir outras funcoes.
vm.runInContext(section('function validateGlobalDeleteConfirmation()', '/**\n         * Confirma a exclusão'), ctx);
vm.runInContext(section('window.confirmGlobalDelete = function()', "document.getElementById('deleteFinanceiroReason').addEventListener"),ctx);
vm.runInContext(section('function setDeleteNotificationStep(show)', '// ===== MODAL DE CONFIRMAÇÃO GENÉRICO'),ctx);
// O onclick do layout existe antes dos listeners registrados no DOMContentLoaded.
element('confirmDeleteButton').addEventListener('click', () => ctx.confirmGlobalDelete());
element('cancelDeleteButton').addEventListener('click', () => ctx.closeGlobalDeleteModal());
vm.runInContext(fs.readFileSync(__dirname + '/../public/assets/js/' + (process.env.DASHBOARD_TEST_ASSET || 'dashboard.js'), 'utf8'), ctx);
ready.forEach(fn => fn());
const isOpen = () => element('deleteConfirmationModal').classList.contains('open');
function reset(eligible=true, modulo='locacoes') {
    posts.length=0;alerts.length=0;messages.length=0;
    element('deleteConfirmationModal').classList.add('open');
    element('confirmDeleteInput').value='EXCLUIR';element('deleteFinanceiroReason').value='';
    vm.runInContext(`globalRecordId=123;globalExpectedText='EXCLUIR';globalFinanceiroDelete={id:123,modulo:'${modulo}',busy:false,source:iframe,preview:{referencia:'token',pode_notificar_cliente:${eligible},exige_motivo:false}};validateGlobalDeleteConfirmation();`,ctx);
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
    reset();element('confirmDeleteButton').click();assert.equal(posts.length,0);assert.equal(element('deleteNotifyYes').style.display,'');assert.equal(isOpen(),true);
    element('cancelDeleteButton').click();assert.equal(isOpen(),false);ctx.answerDeleteNotification(true);await tick();assert.equal(posts.length,0);
    console.log('OK Cancelar fecha sem excluir ou notificar');
    for(const notify of [true,false]) {
        reset();element('confirmDeleteButton').click();ctx.answerDeleteNotification(notify);ctx.answerDeleteNotification(notify);await tick();
        assert.equal(posts.length,1);assert.equal(posts[0].payload.notificar_cliente,notify);
        assert.equal(isOpen(),false);assert.equal(messages.length,1);assert.equal(messages[0].action,'financeiroVinculoExcluido');
        console.log('OK escolha '+notify+' e bloqueio de clique duplicado');
    }
    reset(false);element('confirmDeleteButton').click();await tick();assert.equal(posts.length,1);assert.equal(posts[0].payload.notificar_cliente,false);
    reset(false,'contratos');element('confirmDeleteButton').click();await tick();assert.equal('notificar_cliente' in posts[0].payload,false);
    console.log('OK outros status e contratos preservados');
    let resolvePost;
    nextResponse = new Promise(resolve => {resolvePost = resolve;});
    reset(false);element('confirmDeleteButton').click();element('confirmDeleteButton').click();
    element('cancelDeleteButton').click();ctx.closeGlobalDeleteModal();
    assert.equal(isOpen(),true);assert.equal(posts.length,1);
    resolvePost({success:false,message:'Falha simulada'});await tick();
    assert.equal(isOpen(),true);assert.equal(messages.length,0);
    assert.equal(element('deleteFinanceiroError').textContent,'Falha simulada');
    console.log('OK modal permanece aberto durante POST e em erro');
    nextResponse={success:false,refresh_preview:true,message:'Status mudou'};
    reset();element('confirmDeleteButton').click();ctx.answerDeleteNotification(true);await tick();
    assert.equal(element('confirmDeleteInput').value,'');assert.equal(element('deleteNotifyYes').style.display,'none');
    assert.equal(element('confirmDeleteButton').disabled,true);assert.equal(alerts.length,0);assert.equal(isOpen(),true);
    console.log('OK conflito exige nova previa e confirmacao');
    reset();
    vm.runInContext("globalFinanceiroDelete=null;globalSourceWindow=iframe;",ctx);
    element('confirmDeleteButton').click();
    assert.equal(posts.length,0);assert.equal(messages.length,1);
    assert.equal(messages[0].action,'confirmDelete');assert.equal(isOpen(),false);
    console.log('OK exclusao generica preservada');
    console.log('PASS modal reserva nao confirmada');
})().catch(e=>{console.error(e);process.exitCode=1;});
