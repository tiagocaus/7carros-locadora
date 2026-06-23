@extends('layouts.iframe')

@section('title', '<?= t("modules.website.activate_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.website.activate_title') ?></h2>
    </div>

    <!-- Estado: Pendente -->
    <div id="estadoPendente" class="hidden">
        <div class="bg-white shadow-md rounded-lg p-8 text-center max-w-lg mx-auto">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clock text-yellow-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold mb-2"><?= t('modules.website.waiting_activation') ?></h3>
            <p class="text-slate-500"><?= t('modules.website.waiting_message') ?></p>
            <p class="text-sm text-slate-400 mt-4" id="pendenteDominio"></p>
        </div>
    </div>

    <!-- Estado: Formulario de Ativacao (2 colunas) -->
    <div id="estadoFormulario" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- COLUNA ESQUERDA: Formulario -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-globe text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold"><?= t('modules.website.activate_description') ?></h3>
                </div>

                <form id="formAtivar">
                    @csrf

                    <!-- Registro de dominio -->
                    <div class="p-4 bg-slate-50 rounded-lg mb-5">
                        <p class="font-medium mb-3"><i class="fas fa-globe mr-2 text-slate-400"></i><?= t('modules.website.domain') ?></p>
                        <label class="flex items-center mb-2 cursor-pointer">
                            <input type="radio" name="quer_registro" value="1" class="mr-2 text-blue-600">
                            <span class="text-sm"><?= t('modules.website.want_domain') ?></span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="quer_registro" value="0" class="mr-2 text-blue-600">
                            <span class="text-sm"><?= t('modules.website.have_domain') ?></span>
                        </label>
                    </div>

                    <!-- Dominio -->
                    <div class="form-input-group mb-5">
                        <label for="dominio" class="form-label-group"><?= t('modules.website.domain') ?></label>
                        <div class="flex gap-2">
                            <input type="text" id="dominio" name="dominio" class="form-input-group-field flex-1" placeholder="<?= t('modules.website.domain_placeholder') ?>" pattern="[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+" title="<?= t('modules.website.domain_invalid') ?>" required>
                            <button type="button" id="btnVerificarDns" class="bg-purple-600 text-white hover:bg-purple-700 py-2 px-4 rounded-md text-sm font-medium whitespace-nowrap hidden">
                                <i class="fas fa-search mr-1"></i> Verificar
                            </button>
                        </div>
                    </div>
                    <p id="dnsResult" class="text-sm mb-5 ml-2 hidden"></p>

                    <!-- Botao -->
                    <div class="flex justify-center">
                        <button type="submit" id="btnAtivar" class="btn-blue py-3 px-8 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-rocket mr-2"></i> <?= t('modules.website.activate_button') ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- COLUNA DIREITA: Informacoes -->
            <div class="space-y-6">
                <!-- Card Valores -->
                <div class="bg-blue-100 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-3 text-blue-600">Valores</h3>
                    <p class="text-sm text-slate-700 mb-1">O site é <strong>Grátis!</strong></p>
                    <p class="text-sm text-slate-600 mb-3">Única coisa que cobramos "caso não tenha" é o registro do domínio e a hospedagem do site.</p>
                    <ul class="text-sm text-slate-700 space-y-1 ml-1">
                        <li>- Registro brasileiro (.com.br): <strong>R$60,00</strong> por ano.</li>
                        <li>- Registro internacional (.com): <strong>R$80,00</strong> por ano.</li>
                        <li>- Hospedagem: <strong>R$29,90</strong> por mês.</li>
                    </ul>
                </div>

                <!-- Card Explicacao -->
                <div class="bg-blue-100 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-3 text-blue-600">Explicação</h3>
                    <p class="text-sm text-slate-700 mb-3">
                        <strong>Domínio:</strong> É o nome utilizado para acessar um site, como, por exemplo, www.NomeEscolhido.com.br.
                    </p>
                    <p class="text-sm text-slate-700 mb-3">
                        <strong>Hospedagem:</strong> Após adquirir um domínio, ele é vinculado a um serviço de hospedagem. Isso permite que, ao acessar o seu domínio, o site possa carregar os arquivos armazenados na hospedagem. Mesmo princípio para o email, que terá direito.
                    </p>
                    <p class="text-sm text-slate-700">
                        <strong>Resumo:</strong> Um depende do outro; não é possível ter um site online apenas com o domínio ou apenas com a hospedagem.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    pageLoading.start();

    API.get('/api/website/config').then(result => {
        if (result.success && result.data) {
            const status = result.data.status;

            if (status === 'pendente') {
                document.getElementById('estadoPendente').classList.remove('hidden');
                document.getElementById('pendenteDominio').textContent = result.data.dominio || '';
            } else {
                document.getElementById('estadoFormulario').classList.remove('hidden');
            }
        } else {
            document.getElementById('estadoFormulario').classList.remove('hidden');
        }
    }).catch(() => {
        document.getElementById('estadoFormulario').classList.remove('hidden');
    }).finally(() => {
        pageLoading.done();
    });

    const dominioRegex = /^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/;
    const btnVerificar = document.getElementById('btnVerificarDns');
    const btnAtivar = document.getElementById('btnAtivar');
    const resultEl = document.getElementById('dnsResult');
    let dominioDisponivel = false;

    function atualizarBotaoAtivar() {
        const radio = document.querySelector('input[name="quer_registro"]:checked');
        if (!radio) {
            btnAtivar.disabled = true;
            return;
        }
        if (radio.value === '1') {
            btnAtivar.disabled = !dominioDisponivel;
        } else {
            btnAtivar.disabled = false;
        }
    }

    // Mostrar/esconder botao Verificar e controlar estado do Ativar
    document.querySelectorAll('input[name="quer_registro"]').forEach(radio => {
        radio.addEventListener('change', function() {
            dominioDisponivel = false;
            resultEl.classList.add('hidden');
            if (this.value === '1') {
                btnVerificar.classList.remove('hidden');
            } else {
                btnVerificar.classList.add('hidden');
            }
            atualizarBotaoAtivar();
        });
    });

    // Reset verificacao ao alterar dominio
    document.getElementById('dominio').addEventListener('input', function() {
        dominioDisponivel = false;
        resultEl.classList.add('hidden');
        atualizarBotaoAtivar();
    });

    // Verificar disponibilidade do dominio
    btnVerificar.addEventListener('click', async function() {
        const dominio = document.getElementById('dominio').value.trim();
        if (!dominio) return;
        if (!dominioRegex.test(dominio)) {
            toast.error('<?= t("modules.website.domain_invalid") ?>');
            return;
        }

        dominioDisponivel = false;
        atualizarBotaoAtivar();
        resultEl.classList.remove('hidden', 'text-green-600', 'text-red-600');
        resultEl.textContent = 'Verificando...';
        resultEl.classList.add('text-slate-500');

        btnVerificar.disabled = true;
        btnVerificar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Verificando...';

        try {
            const result = await API.get('/api/website/verificar-dominio', { dominio });
            if (result.success && result.data) {
                resultEl.classList.remove('text-slate-500');
                if (result.data.valido) {
                    resultEl.textContent = '<?= t("modules.website.domain_taken") ?>';
                    resultEl.classList.add('text-red-600');
                    dominioDisponivel = false;
                } else {
                    resultEl.textContent = '<?= t("modules.website.domain_available") ?>';
                    resultEl.classList.add('text-green-600');
                    dominioDisponivel = true;
                }
                atualizarBotaoAtivar();
            }
        } catch (error) {
            resultEl.textContent = 'Erro ao verificar';
            resultEl.classList.remove('text-slate-500');
            resultEl.classList.add('text-red-600');
        } finally {
            btnVerificar.disabled = false;
            btnVerificar.innerHTML = '<i class="fas fa-search mr-1"></i> Verificar';
        }
    });

    // Escutar confirmacao do modal
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'genericConfirmed') {
            enviarAtivacao();
        }
    });

    // Submit ativacao — abre modal de confirmacao
    document.getElementById('formAtivar').addEventListener('submit', function(e) {
        e.preventDefault();

        const dominio = document.getElementById('dominio').value.trim();
        const radio = document.querySelector('input[name="quer_registro"]:checked');
        if (!radio) {
            toast.error(<?= js_t("modules.website.select_domain_option") ?>);
            return;
        }
        if (!dominio) {
            toast.error('<?= t("modules.website.domain_required") ?>');
            return;
        }
        if (!dominioRegex.test(dominio)) {
            toast.error('<?= t("modules.website.domain_invalid") ?>');
            return;
        }

        const querRegistro = radio.value === '1';
        let msg = '<?= t("modules.website.confirm_domain") ?>: ' + dominio + '\n\n';
        if (querRegistro) {
            msg += '<?= t("modules.website.confirm_charge_domain") ?>\n';
        }
        msg += <?= js_t("modules.website.confirm_charge_hosting") ?>;

        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: '<?= t("modules.website.confirm_activation_title") ?>',
            message: msg,
            confirmText: '<?= t("modules.website.confirm_activate") ?>'
        }, '*');
    });

    async function enviarAtivacao() {
        const dominio = document.getElementById('dominio').value.trim();
        const querRegistro = document.querySelector('input[name="quer_registro"]:checked')?.value === '1';

        try {
            const result = await API.post('/api/website/ativar', {
                dominio,
                empresa: '<?= $_SESSION["nome_fantasia"] ?? "" ?>',
                plano: '<?= $_SESSION["plano"] ?? "" ?>',
                quer_registro: querRegistro,
            });

            if (result.success) {
                toast.success(result.message);
                document.getElementById('estadoFormulario').classList.add('hidden');
                document.getElementById('estadoPendente').classList.remove('hidden');
                document.getElementById('pendenteDominio').textContent = dominio;
            } else {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    }

    // Estado inicial: botao desabilitado ate selecionar radio
    btnAtivar.disabled = true;
})();
</script>
@endsection
