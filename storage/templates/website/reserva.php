<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

if ($manutencaoAtiva) { include __DIR__ . '/includes/manutencao.php'; exit; }
if (!$reservaOnline) { header('Location: ' . langUrl('index.php')); exit; }

// Cliente logado via ajax-cliente-login.php? Expoe flags pro template
// Sessao pre-deploy nao tem email/telefone — invalida pra forcar relogin e popular
if (!empty($_SESSION['cliente_id']) && empty($_SESSION['cliente_email'])) {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_email'], $_SESSION['cliente_telefone']);
}
$clienteLogado    = !empty($_SESSION['cliente_id']);
$clienteNome      = (string) ($_SESSION['cliente_nome'] ?? '');
$clienteEmail     = (string) ($_SESSION['cliente_email'] ?? '');
$clienteTelefone  = (string) ($_SESSION['cliente_telefone'] ?? '');

$pagina = 'reserva';
$seo = $seoAll[$pagina] ?? [];
$filiais = $dados['filiais'] ?? [];
$grupos  = $dados['grupos'] ?? [];
$servicos = $dados['servicos'] ?? [];

// Flags do passo 4 (vem do endpoint /api/public/dados-site)
$cadastroSimples  = !empty($dados['cadastro_simples']);
$envioDocumentos  = !empty($dados['envio_documentos']);
$docCnhObr        = !empty($dados['doc_cnh_obrigatorio']);
$docCpfObr        = !empty($dados['doc_cpf_obrigatorio']);
$docRgObr         = !empty($dados['doc_rg_obrigatorio']);
$docCompObr       = !empty($dados['doc_comprovante_obrigatorio']);
$requerConfirmacao = !empty($dados['reserva_requer_confirmacao']);

// Pré-preenchimento vindo da query string (form de index.php)
$preLocRet   = (int) ($_GET['localRetirada'] ?? 0);
$preDataSai  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dataSaida'] ?? '') ? $_GET['dataSaida'] : '';
$preHoraSai  = preg_match('/^\d{2}:\d{2}$/', $_GET['horaSaida'] ?? '') ? $_GET['horaSaida'] : '';
$preLocDev   = (int) ($_GET['localDevolucao'] ?? 0);
$preDataPrev = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dataPrevista'] ?? '') ? $_GET['dataPrevista'] : '';
$preHoraDev  = preg_match('/^\d{2}:\d{2}$/', $_GET['horaDevolucao'] ?? '') ? $_GET['horaDevolucao'] : '';
$temPrefill  = $preLocRet && $preDataSai && $preHoraSai && $preLocDev && $preDataPrev && $preHoraDev;
?>
<!DOCTYPE html>
<html lang="<?= substr($idioma, 0, 2) ?>">
<head><?php include __DIR__ . '/includes/head.php'; ?></head>
<body>

<?php foreach ($integracoes['body_inicio'] ?? [] as $code) { echo $code['codigo']; } ?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div id="reserva" style="height: 70px"></div>

<div class="container p50">
    <main>

        <!-- PROGRESSBAR -->
        <div class="row pb-5">
            <div class="col">
                <ul class="progressbar">
                    <li class="active"><?= e(secao('reserva', 'passo_1_titulo', 'Local e data')) ?></li>
                    <li><?= e(secao('reserva', 'passo_2_titulo', 'Veículo')) ?></li>
                    <li><?= e(secao('reserva', 'passo_3_titulo', 'Adicionais')) ?></li>
                    <li><?= e(secao('reserva', 'passo_4_titulo', 'Dados cadastrais')) ?></li>
                    <li><?= e(secao('reserva', 'passo_5_titulo', 'Finalização')) ?></li>
                </ul>
            </div>
        </div>

        <div class="pb-3"></div>


        <!-- TAB 1: LOCAL E DATA -->
        <div class="pb-4 tabs_ active">
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Local de retirada</label>
                            <select class="form-control chosen-select" id="localRetirada" required
                                    data-chosen-placeholder="Selecione">
                                <option value="" disabled <?= $preLocRet ? '' : 'selected' ?>>Selecione</option>
                                <?php foreach ($filiais as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"
                                        data-local="<?= e($f['cidade'] ?? '') ?>, <?= e($f['estado'] ?? '') ?>"
                                        <?= $preLocRet === (int) $f['id'] ? 'selected' : '' ?>>
                                    <?= e($f['label'] ?? '') ?>
                                </option>
                                <?php foreach (($f['locais'] ?? []) as $l): ?>
                                <option value="<?= (int) $f['id'] ?>:<?= (int) $l['id'] ?>"
                                        data-local="<?= e($l['bairro'] ?? '') ?>, <?= e($l['cidade'] ?? '') ?>">
                                    <?= e($l['label'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Previsão de saída</label>
                            <div class="input-datetime saida">
                                <input type="date" id="dataSaida" required
                                       value="<?= e($preDataSai) ?>"
                                       <?= $preDataSai ? '' : 'disabled' ?>>
                                <select id="horaSaida" <?= $preHoraSai ? '' : 'disabled' ?>>
                                    <?php if ($preHoraSai): ?>
                                    <option value="<?= e($preHoraSai) ?>" selected><?= e($preHoraSai) ?></option>
                                    <?php else: ?>
                                    <option value="" disabled selected>--:--</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Local de devolução</label>
                            <select class="form-control chosen-select" id="localDevolucao" required
                                    data-chosen-placeholder="Selecione">
                                <option value="" disabled <?= $preLocDev ? '' : 'selected' ?>>Selecione</option>
                                <?php foreach ($filiais as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"
                                        data-local="<?= e($f['cidade'] ?? '') ?>, <?= e($f['estado'] ?? '') ?>"
                                        <?= $preLocDev === (int) $f['id'] ? 'selected' : '' ?>>
                                    <?= e($f['label'] ?? '') ?>
                                </option>
                                <?php foreach (($f['locais'] ?? []) as $l): ?>
                                <option value="<?= (int) $f['id'] ?>:<?= (int) $l['id'] ?>"
                                        data-local="<?= e($l['bairro'] ?? '') ?>, <?= e($l['cidade'] ?? '') ?>">
                                    <?= e($l['label'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Previsão de chegada</label>
                            <div class="input-datetime chegada">
                                <input type="date" id="dataPrevista" required
                                       value="<?= e($preDataPrev) ?>"
                                       <?= $preDataPrev ? '' : 'disabled' ?>>
                                <select id="horaDevolucao" <?= $preHoraDev ? '' : 'disabled' ?>>
                                    <?php if ($preHoraDev): ?>
                                    <option value="<?= e($preHoraDev) ?>" selected><?= e($preHoraDev) ?></option>
                                    <?php else: ?>
                                    <option value="" disabled selected>--:--</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-center">
                        <button id="btn_reserva_inner" type="button" class="btn btn-warning px-5" disabled data-track="reserva_step1_continuar">Continuar</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- TAB 2: VEÍCULO -->
        <div class="pb-4 tabs_">
            <h2 class="titulo-1 bold verde"><?= e(secao('reserva', 'passo_2_titulo', 'Escolha seu veículo')) ?></h2>

            <div class="col-sm-12">
                <?php if (!empty($grupos)): ?>
                <?php foreach ($grupos as $i => $grupo): ?>
                <div class="row">
                    <div class="col-sm-4 divListaGrupos">
                        <h3><?= e($grupo['nome']) ?></h3>
                        <?php if (!empty($grupo['descricao'])): ?>
                        <h4><?= e($grupo['descricao']) ?></h4>
                        <?php endif; ?>
                        <?php if (!empty($grupo['modelos'])): ?>
                        <h6><?= e($grupo['modelos']) ?></h6>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-5 text-center">
                        <?php if (!empty($grupo['foto_url'])): ?>
                        <img src="<?= e($grupo['foto_url']) ?>" class="img-fluid" alt="<?= e($grupo['nome']) ?>" style="width: 70%;">
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-3 text-right reserva-plano-col">
                        <div class="col reserva-plano">
                            <input type="radio" value="KML|<?= (int) $grupo['id'] ?>" id="plano<?= $i ?>1" name="plano">
                            <label for="plano<?= $i ?>1"><?= e(t('reserva.plano_km_livre')) ?></label>

                            <input type="radio" value="KMC|<?= (int) $grupo['id'] ?>" id="plano<?= $i ?>2" name="plano">
                            <label for="plano<?= $i ?>2"><?= e(t('reserva.plano_km_controlado')) ?></label>

                            <input type="radio" value="DIA|<?= (int) $grupo['id'] ?>" id="plano<?= $i ?>3" name="plano">
                            <label for="plano<?= $i ?>3"><?= e(t('reserva.plano_km_pago')) ?></label>
                        </div>
                        <div class="col reserva-preco"><span>selecione</span></div>
                        <button data-id="<?= (int) $grupo['id'] ?>" data-id-grupo="<?= (int) $grupo['id'] ?>" data-track="reserva_step2_selecionar_grupo" class="btnSelecionarGrupo btn btn-warning btn-block btnPlano" disabled><?= e(t('reserva.btn_selecione_plano')) ?></button>
                    </div>
                </div>
                <hr>
                <?php endforeach; ?>

                <!-- Legenda explicativa dos planos de locacao -->
                <div class="reserva-planos-legenda mt-3">
                    <p class="mb-2"><strong><?= e(t('reserva.plano_km_livre')) ?>:</strong> <?= e(t('reserva.plano_km_livre_desc')) ?></p>
                    <p class="mb-2"><strong><?= e(t('reserva.plano_km_controlado')) ?>:</strong> <?= e(t('reserva.plano_km_controlado_desc')) ?></p>
                    <p class="mb-2"><strong><?= e(t('reserva.plano_km_pago')) ?>:</strong> <?= e(t('reserva.plano_km_pago_desc')) ?></p>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <p>Nenhum grupo de veículos disponível no momento.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- TAB 3: ADICIONAIS -->
        <div class="pb-4 tabs_">
            <div class="col-sm-12">
                <h2 class="titulo-1 bold verde"><?= e(secao('reserva', 'passo_3_titulo', 'Serviços adicionais')) ?></h2>

                <div class="row">
                    <div class="col-sm-8">
                        <?php if (!empty($servicos)): ?>
                        <div id="itens-adicionais" class="col">
                            <?php foreach ($servicos as $s): ?>
                            <div class="row itens-adicionais">
                                <div class="col-sm-6 nome"><strong><?= e($s['nome']) ?></strong></div>
                                <div class="col-sm-3 preco servico-preco"
                                     data-servico-id="<?= (int) $s['id'] ?>"
                                     data-servico-nome="<?= e($s['nome']) ?>"
                                     data-tipo-valor="<?= e($s['tipo_valor'] ?? 'MON') ?>"
                                     data-base-calculo="<?= e($s['base_calculo'] ?? 'PER') ?>"
                                     data-valor-global="<?= (float)($s['valor'] ?? 0) ?>">
                                    <span class="servico-valor">—</span>
                                </div>
                                <div class="col-sm-3 text-center btn-warning">
                                    <span class="addCheck">Adicionar</span>
                                    <input type="checkbox" name="servico_<?= (int) $s['id'] ?>" value="S">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-muted">Nenhum serviço adicional disponível.</div>
                        <?php endif; ?>

                        <div class="row mt-4 d-none d-md-flex">
                            <div class="col">
                                <button type="button" class="btn btn-warning botao_" data-track="reserva_step3_proximo">Próximo</button>
                            </div>
                        </div>
                    </div>

                    <!-- RESUMO -->
                    <div class="col-sm-4">
                        <div class="resumo-titulo">Resumo</div>
                        <div class="resumo-detalhes">
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Retirada</div>
                                <div class="col-sm-12 pb-4 label">
                                    <span class="resumo-retirada-local"></span>, <span class="resumo-retirada-data"></span>
                                </div>
                                <div class="col-sm-12 titulo">Devolução</div>
                                <div class="col-sm-12 label">
                                    <span class="resumo-devolucao-local"></span>, <span class="resumo-devolucao-data"></span>
                                </div>
                            </div>
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Plano</div>
                                <div class="col-sm-8 label resumo-plano"></div>
                            </div>
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Diárias</div>
                                <div class="col-sm-8 label"><span class="dias"></span> x <span class="plano-valor"></span></div>
                                <div class="col-sm-4 text-right label"><span class="plano-soma somar"></span></div>
                            </div>
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Adicionais</div>
                                <div class="col-sm-12 resumo-adicionais"></div>
                            </div>
                        </div>
                        <div class="resumo-totais">
                            <div>Valor previsto</div>
                            <div class="total-geral"><span>0,00</span></div>
                        </div>
                        <button type="button" class="btn btn-warning botao_ btn-block btn-lg mt-3 py-4 d-flex align-items-center justify-content-center" data-track="reserva_step3_proximo">Próximo</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- TAB 4: DADOS CADASTRAIS -->
        <div class="pb-4 tabs_">
            <div class="col-sm-12">
                <h2 class="titulo-1 bold verde"><?= e(secao('reserva', 'passo_4_titulo', 'Pré-cadastro')) ?></h2>

                <div class="row">
                    <div class="col-sm-8">
                        <!-- Cliente ja logado: nao pede dados, so confirma (renderizado via PHP ou via JS pos-login) -->
                        <div id="blocoClienteLogado"<?php if (!$clienteLogado): ?> style="display:none;"<?php endif; ?>>
                            <div class="alert alert-success" id="clienteLogadoBloco">
                                <?= e(t('reserva.cliente_logado_msg')) ?> <strong id="cld_nome"><?= e($clienteNome) ?></strong>.
                                <a href="ajax-cliente-logout.php" id="btnLogoutCliente" class="ml-2"><?= e(t('reserva.sair')) ?></a>
                            </div>
                            <div class="card p-3 mb-3">
                                <div class="mb-2"><strong>Email:</strong> <span id="cld_email"><?= e($clienteEmail) ?></span></div>
                                <div><strong>Telefone:</strong> <span id="cld_telefone"><?= e($clienteTelefone) ?></span></div>
                            </div>
                        </div>

                        <!-- Form precadastro: visivel quando NAO logado -->
                        <div id="formPrecadastro"<?php if ($clienteLogado): ?> style="display:none;"<?php endif; ?>>
                        <p class="pb-4"><?= e(secao('reserva', 'passo_4_texto', 'Preencha os dados abaixo para finalizar sua reserva.')) ?></p>

                        <div class="row">
                            <div class="form-group col-sm-5">
                                <label class="col-form-label">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipo" id="cpf" value="PF" checked>
                                        <label class="form-check-label" for="cpf">CPF</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipo" id="passaport" value="ES">
                                        <label class="form-check-label" for="passaport">Passaport</label>
                                    </div>
                                </label>
                                <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj">
                            </div>
                            <div class="form-group col-sm-7">
                                <label for="nome_rsocial" class="col-form-label">Nome completo</label>
                                <input type="text" class="form-control" id="nome_rsocial" name="nome_rsocial">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="form-group col-sm-6">
                                <label for="email_reserva" class="col-form-label">Email</label>
                                <input type="email" class="form-control" id="email_reserva" name="email">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="tel_cel" class="col-form-label">Celular</label>
                                <input type="tel" class="form-control" id="tel_cel" name="tel_cel">
                            </div>
                        </div>
                        <?php if (!$cadastroSimples): ?>
                        <h4>Seu endereço</h4>

                        <div class="row">
                            <div class="form-group col-sm-3"><label for="cep">CEP</label><input type="text" class="form-control" id="cep" name="cep"></div>
                            <div class="form-group col-sm-6"><label for="rua">Rua</label><input type="text" class="form-control" id="rua" name="rua"></div>
                            <div class="form-group col-sm-3"><label for="numero">Número</label><input type="text" class="form-control" id="numero" name="numero"></div>
                        </div>

                        <div class="row mb-4">
                            <div class="form-group col-sm-3"><label for="bairro">Bairro</label><input type="text" class="form-control" id="bairro" name="bairro"></div>
                            <div class="form-group col-sm-3"><label for="cidade">Cidade</label><input type="text" class="form-control" id="cidade" name="cidade"></div>
                            <div class="form-group col-sm-3"><label for="estado">Estado</label><input type="text" class="form-control" id="estado" name="estado"></div>
                            <div class="form-group col-sm-3"><label for="pais">País</label><input type="text" class="form-control" id="pais" name="pais"></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($envioDocumentos): ?>
                        <h4><?= e(t('documentos.titulo')) ?></h4>
                        <p class="text-muted"><?= e(t('documentos.limite')) ?></p>

                        <div class="row mb-4">
                            <div class="form-group col-sm-6">
                                <label for="doc_cnh"><?= e(t('documentos.cnh')) ?>
                                    <?= $docCnhObr ? '<span class="text-danger">*</span>' : '<small class="text-muted">' . e(t('documentos.opcional')) . '</small>' ?>
                                </label>
                                <input type="file" class="form-control-file" id="doc_cnh" name="doc_cnh"
                                       data-required="<?= $docCnhObr ? '1' : '0' ?>"
                                       accept="image/*,application/pdf">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="doc_cpf"><?= e(t('documentos.cpf')) ?>
                                    <?= $docCpfObr ? '<span class="text-danger">*</span>' : '<small class="text-muted">' . e(t('documentos.opcional')) . '</small>' ?>
                                </label>
                                <input type="file" class="form-control-file" id="doc_cpf" name="doc_cpf"
                                       data-required="<?= $docCpfObr ? '1' : '0' ?>"
                                       accept="image/*,application/pdf">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="doc_rg"><?= e(t('documentos.rg')) ?>
                                    <?= $docRgObr ? '<span class="text-danger">*</span>' : '<small class="text-muted">' . e(t('documentos.opcional')) . '</small>' ?>
                                </label>
                                <input type="file" class="form-control-file" id="doc_rg" name="doc_rg"
                                       data-required="<?= $docRgObr ? '1' : '0' ?>"
                                       accept="image/*,application/pdf">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="doc_comprovante"><?= e(t('documentos.comprovante')) ?>
                                    <?= $docCompObr ? '<span class="text-danger">*</span>' : '<small class="text-muted">' . e(t('documentos.opcional')) . '</small>' ?>
                                </label>
                                <input type="file" class="form-control-file" id="doc_comprovante" name="doc_comprovante"
                                       data-required="<?= $docCompObr ? '1' : '0' ?>"
                                       accept="image/*,application/pdf">
                            </div>
                        </div>
                        <?php endif; ?>
                        </div><!-- /#formPrecadastro -->

                        <input type="hidden" id="reserva_requer_confirmacao" value="<?= $requerConfirmacao ? '1' : '0' ?>">
                        <input type="hidden" id="cliente_logado_flag" value="<?= $clienteLogado ? '1' : '0' ?>">

                        <div class="row pb-4 d-none d-md-flex">
                            <div class="col">
                                <button id="concluir_reserva" type="button" class="btn btn-warning btnConcluirReserva" data-track="reserva_step4_concluir">Concluir reserva</button>
                            </div>
                        </div>
                    </div>

                    <!-- RESUMO (duplicado como no original) -->
                    <div class="col-sm-4">
                        <div class="resumo-titulo">Resumo</div>
                        <div class="resumo-detalhes">
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Retirada</div>
                                <div class="col-sm-12 pb-4 label">
                                    <span class="resumo-retirada-local"></span>, <span class="resumo-retirada-data"></span>
                                </div>
                                <div class="col-sm-12 titulo">Devolução</div>
                                <div class="col-sm-12 label">
                                    <span class="resumo-devolucao-local"></span>, <span class="resumo-devolucao-data"></span>
                                </div>
                            </div>
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Plano</div>
                                <div class="col-sm-8 label resumo-plano"></div>
                            </div>
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Diárias</div>
                                <div class="col-sm-8 label"><span class="dias"></span> x <span class="plano-valor"></span></div>
                                <div class="col-sm-4 text-right label"><span class="plano-soma somar"></span></div>
                            </div>
                            <div class="row pb-4">
                                <div class="col-sm-12 titulo">Adicionais</div>
                                <div class="col-sm-12 resumo-adicionais"></div>
                            </div>
                        </div>
                        <div class="resumo-totais">
                            <div>Valor previsto</div>
                            <div class="total-geral"><span>0,00</span></div>
                        </div>
                        <button type="button" class="btn btn-warning btnConcluirReserva btn-block btn-lg mt-3 py-4 d-flex align-items-center justify-content-center" data-track="reserva_step4_concluir">Concluir reserva</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- TAB 5: FINALIZAÇÃO -->
        <div class="pb-4 tabs_">
            <div class="col-sm-12">
                <h2 class="titulo-1 bold verde"><?= e(secao('reserva', 'passo_5_titulo', 'Concluído')) ?></h2>

                <!-- Reserva confirmada na hora (sem pagamento antecipado, sem confirmacao manual) -->
                <div id="reservaOkBloco">
                    <div class="row justify-content-md-center">
                        <p>
                        <h5><?= e(secao('reserva', 'passo_5_texto', 'Sua pré-reserva foi finalizada com sucesso!')) ?></h5>
                        </p>
                    </div>
                    <div class="row justify-content-md-center">
                        <p>
                        <h4 class="codigo-reserva"></h4>
                        </p>
                    </div>
                </div>

                <!-- Reserva aguardando confirmacao da locadora (flag reserva_requer_confirmacao) -->
                <div id="reservaAguardandoBloco" style="display:none;">
                    <div class="row justify-content-md-center">
                        <p>
                        <h5>Sua solicitação de reserva foi enviada com sucesso!</h5>
                        </p>
                    </div>
                    <div class="row justify-content-md-center">
                        <p class="text-center px-4">
                            A locadora ainda precisa confirmar sua reserva. Assim que for aprovada, você receberá os detalhes por email, WhatsApp ou SMS.
                        </p>
                    </div>
                </div>

                <div class="pb-5"></div>

                <!-- Comprovante de impressao: visivel apenas em window.print() -->
                <div id="reservaPrintComprovante" class="d-none d-print-block">
                    <div class="print-header">
                        <?php if (!empty($logoUrl)): ?>
                        <img src="<?= e($logoUrl) ?>" alt="<?= e($config['nome_empresa'] ?? '') ?>" class="print-logo">
                        <?php endif; ?>
                        <div class="print-empresa"><?= e($config['nome_empresa'] ?? '') ?></div>
                        <h2 class="print-titulo">Comprovante de Reserva</h2>
                    </div>

                    <table class="print-table">
                        <tr><th>Código</th><td id="prt_codigo">—</td></tr>
                        <tr><th>Cliente</th><td id="prt_cliente">—</td></tr>
                        <tr><th>Retirada</th><td><span id="prt_ret_local">—</span> — <span id="prt_ret_data">—</span> às <span id="prt_ret_hora">—</span></td></tr>
                        <tr><th>Devolução</th><td><span id="prt_dev_local">—</span> — <span id="prt_dev_data">—</span> às <span id="prt_dev_hora">—</span></td></tr>
                        <tr><th>Plano</th><td id="prt_plano">—</td></tr>
                        <tr><th>Valor total</th><td id="prt_total">—</td></tr>
                    </table>

                    <div class="print-msg" id="prt_mensagem"></div>

                    <div class="print-rodape">
                        Emitido em <span id="prt_emitido_em"></span>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col d-flex justify-content-center" style="gap:10px;">
                        <a href="<?= langUrl('reserva.php') ?>" class="btn btn-warning d-inline-flex align-items-center" data-track="reserva_nova">Nova reserva</a>
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center" onclick="window.print()" data-track="reserva_imprimir"><i class="fa fa-print mr-2" aria-hidden="true"></i>Imprimir</button>
                    </div>
                </div>
            </div>
        </div>


        <input id="dias" type="hidden" name="dias" required>
    </main>
</div>

<!-- Modal de login: aparece quando o CPF informado ja eh cliente -->
<div class="modal fade" id="modalLoginCliente" tabindex="-1" role="dialog" aria-labelledby="modalLoginClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLoginClienteLabel"><?= e(t('reserva.login_titulo')) ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><?= e(t('reserva.login_texto')) ?></p>
                <div class="form-group">
                    <label for="loginSenha"><?= e(t('reserva.senha')) ?></label>
                    <input type="password" class="form-control" id="loginSenha" autocomplete="current-password">
                </div>
                <div id="loginFeedback" class="small text-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="#" id="linkEsqueciSenha" data-track="reserva_esqueci_senha"><?= e(t('reserva.esqueci_senha')) ?></a>
                <button type="button" class="btn btn-primary" id="btnLoginCliente" data-track="reserva_login_cliente"><?= e(t('reserva.entrar')) ?></button>
            </div>
        </div>
    </div>
</div>

<?php if ($temPrefill): ?>
<script>
window.RESERVA_PREFILL = {
    localRetirada: <?= $preLocRet ?>,
    dataSaida:     <?= json_encode($preDataSai) ?>,
    horaSaida:     <?= json_encode($preHoraSai) ?>,
    localDevolucao:<?= $preLocDev ?>,
    dataPrevista:  <?= json_encode($preDataPrev) ?>,
    horaDevolucao: <?= json_encode($preHoraDev) ?>
};
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php if ($whatsappFlutuante) include __DIR__ . '/includes/whatsapp-float.php'; ?>
</body>
</html>
