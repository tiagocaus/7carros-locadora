/**
 * Nestable List - Drag & Drop Hierárquico em JavaScript Puro
 * Baseado no conceito do Nestable jQuery plugin
 */

class NestableList {
    constructor(containerId, textareaId, options = {}) {
        this.container = document.getElementById(containerId);
        this.textarea = document.getElementById(textareaId);
        if (!this.container || !this.textarea) {
            console.error('Container ou textarea não encontrado');
            return;
        }
        this.list = this.container.querySelector('.nestable-list');
        if (!this.list) {
            console.error('Lista não encontrada no container');
            return;
        }
        this.draggedElement = null;
        this.dragOverElement = null;
        this.nextId = 1;
        this.currentEditItem = null;
        this.currentDeleteItem = null;
        this.currentRecordId = null;
        this.currentRecordName = null;
        this.currentConfirmType = 'text';
        this.currentExpectedText = '';
        this.CONFIRM_TEXT = 'EXCLUIR';
        this.nameField = 'name'; // Campo usado para nome: 'name' ou 'content'

        // Configurar profundidade máxima (0 a N, onde null/undefined = ilimitado)
        if (options.maxDepth !== undefined && options.maxDepth !== null) {
            const maxDepth = parseInt(options.maxDepth);
            if (isNaN(maxDepth) || maxDepth < 0) {
                console.warn('maxDepth deve ser um número >= 0. Usando valor padrão: 10');
                this.maxDepth = 10;
            } else {
                this.maxDepth = maxDepth;
            }
        } else {
            // Se não especificado, usar 10 como padrão (comportamento anterior)
            this.maxDepth = 10;
        }

        this.currentDropZone = null; // Zona de drop atual
        this.init();
    }

    init() {
        this.loadFromTextarea();
        this.textarea.addEventListener('input', () => this.loadFromTextarea());

        const btnLoadJson = document.getElementById('btnLoadJsonNestable');
        const btnClearJson = document.getElementById('btnClearJsonNestable');
        const btnAddItem = document.getElementById('btnAddItemNestable');

        if (btnLoadJson) btnLoadJson.addEventListener('click', () => this.loadFromTextarea());
        if (btnClearJson) btnClearJson.addEventListener('click', () => {
            this.textarea.value = '';
            this.list.innerHTML = '';
            this.updateTextarea();
        });
        if (btnAddItem) btnAddItem.addEventListener('click', () => this.addItem());

        // Listener no container para detectar drag fora dos itens (outdentação)
        if (this.container) {
            this.container.addEventListener('dragover', (e) => {
                if (this.draggedElement && !e.target.closest('.nestable-item')) {
                    // Se não está sobre um item específico, verificar outdentação
                    const currentDepth = this.getItemDepth(this.draggedElement);
                    if (this.isOutsideParentArea(this.draggedElement, e.clientX, e.clientY) && currentDepth > 0) {
                        this.clearAllDropZones();
                        this.draggedElement.classList.add('nestable-drop-outdent');
                        this.currentDropZone = 'drop-outdent';
                    }
                }
            });

            this.container.addEventListener('drop', (e) => {
                // Se soltou no container (não em um item específico) e está fora da área do pai
                if (this.draggedElement && !e.target.closest('.nestable-item')) {
                    const currentDepth = this.getItemDepth(this.draggedElement);
                    if (this.isOutsideParentArea(this.draggedElement, e.clientX, e.clientY) && currentDepth > 0) {
                        e.preventDefault();
                        e.stopPropagation();
                        const parentItem = this.findParentItem(this.draggedElement);
                        if (parentItem) {
                            const parentList = parentItem.parentNode;
                            parentList.insertBefore(this.draggedElement, parentItem.nextSibling);

                            const parentChildrenList = parentItem.querySelector('.nestable-list.nestable-children');
                            if (parentChildrenList && parentChildrenList.children.length === 0) {
                                parentChildrenList.remove();
                            }

                            this.updateTextarea();
                        }
                    }
                }
            });
        }

        const editModal = document.getElementById('editModalNestable');
        const deleteModal = document.getElementById('meuModalExclusaoNestable');
        if (editModal) editModal.addEventListener('click', (e) => {
            if (e.target.id === 'editModalNestable') this.closeEditModal();
        });
        if (deleteModal) deleteModal.addEventListener('click', (e) => {
            if (e.target.id === 'meuModalExclusaoNestable') this.closeDeleteModal();
        });
    }

    generateId() {
        return this.nextId++;
    }

    createItemElement(item) {
        const li = document.createElement('li');
        li.className = 'nestable-item';
        li.dataset.id = item.id;
        li.draggable = true;

        // Determinar qual campo usar (content tem prioridade sobre name)
        const itemName = item.content !== undefined ? item.content : (item.name !== undefined ? item.name : `Item ${item.id}`);

        const handle = document.createElement('div');
        handle.className = 'nestable-handle';

        const dragIcon = document.createElement('i');
        dragIcon.className = 'fas fa-grip-vertical drag-icon';

        const content = document.createElement('div');
        content.className = 'item-content';

        const nameSpan = document.createElement('span');
        nameSpan.className = 'item-name';
        nameSpan.textContent = itemName;

        const actions = document.createElement('div');
        actions.className = 'item-actions';

        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'item-action-btn edit';
        editBtn.innerHTML = '<i class="fas fa-edit"></i>';
        editBtn.title = 'Editar';
        editBtn.onclick = (e) => {
            e.stopPropagation();
            e.preventDefault();
            this.openEditModal(item.id, itemName);
        };

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'item-action-btn delete';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.title = 'Excluir';
        deleteBtn.onclick = (e) => {
            e.stopPropagation();
            e.preventDefault();
            this.openDeleteModal(item.id, itemName, 'item', 'text');
        };

        actions.appendChild(editBtn);
        actions.appendChild(deleteBtn);
        content.appendChild(nameSpan);
        content.appendChild(actions);
        handle.appendChild(dragIcon);
        handle.appendChild(content);
        li.appendChild(handle);

        if (item.children && item.children.length > 0) {
            const childrenList = document.createElement('ol');
            childrenList.className = 'nestable-list nestable-children';
            item.children.forEach(child => {
                childrenList.appendChild(this.createItemElement(child));
            });
            li.appendChild(childrenList);
        }

        li.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            this.handleDragStart(e, li);
        });
        li.addEventListener('dragover', (e) => {
            // Encontrar o elemento nestable-item mais próximo ao mouse
            const target = document.elementFromPoint(e.clientX, e.clientY);
            const closestItem = target ? target.closest('.nestable-item') : null;

            // Só processar se este elemento é o mais próximo ao mouse
            // Isso evita que múltiplos elementos processem o mesmo evento
            if (closestItem === li) {
                this.handleDragOver(e, li);
            }
        });
        li.addEventListener('drop', (e) => {
            e.stopPropagation();
            this.handleDrop(e, li);
        });
        li.addEventListener('dragend', (e) => {
            e.stopPropagation();
            this.handleDragEnd(e, li);
        });
        li.addEventListener('dragleave', (e) => {
            this.handleDragLeave(e, li);
        });

        return li;
    }

    renderList(data) {
        if (!this.list) return;
        this.list.innerHTML = '';
        if (!data || data.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'nestable-empty';
            empty.textContent = 'Nenhum item. Clique em "Adicionar Item" para começar.';
            this.list.appendChild(empty);
            return;
        }
        data.forEach(item => this.list.appendChild(this.createItemElement(item)));
    }

    addItem(name = null) {
        if (!this.list) return;
        // Atualizar nextId antes de criar o item
        this.updateNextIdFromList();
        const itemName = name || `Novo Item ${this.nextId - 1}`;
        const newItem = { id: this.generateId() };
        // Usar o campo correto baseado no que está sendo usado no sistema
        if (this.nameField === 'content') {
            newItem.content = itemName;
        } else {
            newItem.name = itemName;
        }
        const element = this.createItemElement(newItem);
        this.list.appendChild(element);
        const empty = this.list.querySelector('.nestable-empty');
        if (empty) empty.remove();
        this.updateTextarea();
    }

    updateNextIdFromList() {
        const items = Array.from(this.list.querySelectorAll('.nestable-item'));
        let maxNum = 0;
        items.forEach(item => {
            const id = item.dataset.id;
            if (id) {
                const idNum = parseInt(id);
                if (!isNaN(idNum)) {
                    maxNum = Math.max(maxNum, idNum);
                }
            }
        });
        this.nextId = maxNum + 1;
    }

    handleDragStart(e, element) {
        this.draggedElement = element;
        element.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', element.outerHTML);
        // Não propagar para evitar que seja capturado por elementos pais
        e.stopPropagation();
    }

    handleDragOver(e, element) {
        e.preventDefault();
        e.stopPropagation(); // Evitar que seja capturado por elementos filhos
        e.dataTransfer.dropEffect = 'move';
        if (this.draggedElement === element) return;
        if (this.isDescendant(this.draggedElement, element)) return;

        // Se o elemento de destino é filho do elemento arrastado, não processar
        // (isso evita que elementos filhos processem o evento quando arrastamos sobre o pai)
        if (this.draggedElement && this.isDescendant(element, this.draggedElement)) return;

        const currentDepth = this.getItemDepth(this.draggedElement);

        // Limpar todas as classes de zona de todos os itens primeiro
        this.clearAllDropZones();

        // Verificar se podemos outdentar para este item (quando arrastamos filho sobre item de nível superior)
        if (currentDepth > 0 && this.canOutdentTo(this.draggedElement, element)) {
            // Detectar zona de drop para determinar onde será inserido após outdentar
            const dropZone = this.getDropZone(element, e.clientY);

            if (dropZone === 'drop-above' || dropZone === 'drop-below') {
                // Se for acima ou abaixo, mostrar zona de outdentação no item de destino
                element.classList.add('nestable-drop-outdent-target');
                this.currentDropZone = 'drop-outdent';
            } else {
                // Se for sobre o handle, pode tornar filho ou outdentar dependendo do contexto
                // Por padrão, mostrar como tornar filho, mas também mostrar opção de outdentar
                element.classList.add('nestable-drop-child');
                this.currentDropZone = 'drop-child';
            }
            return;
        }

        // Detectar zona de drop atual
        const dropZone = this.getDropZone(element, e.clientY);
        this.currentDropZone = dropZone;

        if (!dropZone) {
            element.classList.add('drag-over');
            return;
        }

        // Calcular profundidade final que será alcançada
        const finalDepth = this.getFinalDepth(this.draggedElement, element, dropZone);

        // Aplicar classe de zona correspondente
        if (dropZone === 'drop-child') {
            // Verificar se pode tornar filho (profundidade final não excede máximo)
            if (finalDepth <= this.maxDepth) {
                element.classList.add('nestable-drop-child');
            } else {
                element.classList.add('drag-over');
            }
        } else if (dropZone === 'drop-above') {
            // Verificar se pode inserir como irmão acima (profundidade final não excede máximo)
            if (finalDepth <= this.maxDepth) {
                element.classList.add('nestable-drop-above');
            } else {
                element.classList.add('drag-over');
            }
        } else if (dropZone === 'drop-below') {
            // Verificar se pode inserir como irmão abaixo (profundidade final não excede máximo)
            if (finalDepth <= this.maxDepth) {
                element.classList.add('nestable-drop-below');
            } else {
                element.classList.add('drag-over');
            }
        }
    }

    // Limpar todas as classes de zona de drop de todos os itens
    clearAllDropZones() {
        if (this.list) {
            this.list.querySelectorAll('.nestable-item').forEach(el => {
                el.classList.remove(
                    'nestable-drop-above',
                    'nestable-drop-child',
                    'nestable-drop-below',
                    'nestable-drop-outdent',
                    'nestable-drop-outdent-target',
                    'drag-over',
                    'nestable-indent-zone',
                    'nestable-outdent-zone'
                );
                // Limpar estilos inline também
                el.style.borderTopWidth = '';
                el.style.borderBottomWidth = '';
                el.style.borderLeftWidth = '';
                el.style.borderLeftColor = '';
            });
        }
    }

    handleDragLeave(e, element) {
        if (!element.contains(e.relatedTarget)) {
            element.classList.remove(
                'drag-over',
                'nestable-drop-above',
                'nestable-drop-child',
                'nestable-drop-below',
                'nestable-drop-outdent-target',
                'nestable-indent-zone',
                'nestable-outdent-zone'
            );
            element.style.borderTopWidth = '';
            element.style.borderBottomWidth = '';
            element.style.borderLeftWidth = '';
        }
    }

    handleDrop(e, element) {
        e.preventDefault();
        e.stopPropagation();
        if (!this.draggedElement || this.draggedElement === element) return;
        if (this.isDescendant(this.draggedElement, element)) return;

        const currentDepth = this.getItemDepth(this.draggedElement);

        // Verificar se podemos outdentar para este item (quando arrastamos filho sobre item de nível superior)
        if (currentDepth > 0 && this.canOutdentTo(this.draggedElement, element)) {
            // Primeiro, remover do pai atual
            const parentItem = this.findParentItem(this.draggedElement);
            if (parentItem) {
                // Remover da lista de filhos do pai
                const parentChildrenList = parentItem.querySelector('.nestable-list.nestable-children');
                if (parentChildrenList) {
                    this.draggedElement.remove();

                    // Se o pai não tem mais filhos, remover a lista vazia
                    if (parentChildrenList.children.length === 0) {
                        parentChildrenList.remove();
                    }
                }
            }

            // Usar a zona de drop detectada ou detectar novamente
            let dropZone = this.currentDropZone || this.getDropZone(element, e.clientY);

            // Calcular profundidade final após outdentação
            const finalDepth = this.getFinalDepth(this.draggedElement, element, dropZone);

            if (dropZone === 'drop-child') {
                // Tornar filho do item de destino (após outdentar)
                if (finalDepth <= this.maxDepth) {
                    let childrenList = element.querySelector('.nestable-list.nestable-children');
                    if (!childrenList) {
                        childrenList = document.createElement('ol');
                        childrenList.className = 'nestable-list nestable-children';
                        element.appendChild(childrenList);
                    }
                    childrenList.appendChild(this.draggedElement);
                }
            } else if (dropZone === 'drop-above') {
                // Inserir como irmão antes do item (após outdentar)
                if (finalDepth <= this.maxDepth) {
                    const parentList = element.parentNode;
                    parentList.insertBefore(this.draggedElement, element);
                }
            } else if (dropZone === 'drop-below') {
                // Inserir como irmão depois do item (após outdentar)
                if (finalDepth <= this.maxDepth) {
                    const parentList = element.parentNode;
                    parentList.insertBefore(this.draggedElement, element.nextSibling);
                }
            } else {
                // Fallback: inserir como irmão abaixo
                const parentList = element.parentNode;
                parentList.insertBefore(this.draggedElement, element.nextSibling);
            }

            this.updateTextarea();
            return;
        }

        // Verificar se está fora da área do pai (outdentação para área vazia)
        if (this.isOutsideParentArea(this.draggedElement, e.clientX, e.clientY) && currentDepth > 0) {
            const parentItem = this.findParentItem(this.draggedElement);
            if (parentItem) {
                // Tornar irmão do pai (remover do nível atual)
                const parentList = parentItem.parentNode;
                parentList.insertBefore(this.draggedElement, parentItem.nextSibling);

                // Se o pai não tem mais filhos, remover a lista vazia
                const parentChildrenList = parentItem.querySelector('.nestable-list.nestable-children');
                if (parentChildrenList && parentChildrenList.children.length === 0) {
                    parentChildrenList.remove();
                }

                this.updateTextarea();
                return;
            }
        }

        // Usar a zona de drop detectada ou detectar novamente
        let dropZone = this.currentDropZone || this.getDropZone(element, e.clientY);

        if (!dropZone) {
            // Fallback: inserir como irmão abaixo
            const parentList = element.parentNode;
            parentList.insertBefore(this.draggedElement, element.nextSibling);
            this.updateTextarea();
            return;
        }

        // Calcular profundidade final que será alcançada
        const finalDepth = this.getFinalDepth(this.draggedElement, element, dropZone);

        // Executar ação baseada na zona de drop
        if (dropZone === 'drop-child') {
            // Tornar filho do item de destino (verificar profundidade final)
            if (finalDepth <= this.maxDepth) {
                let childrenList = element.querySelector('.nestable-list.nestable-children');
                if (!childrenList) {
                    childrenList = document.createElement('ol');
                    childrenList.className = 'nestable-list nestable-children';
                    element.appendChild(childrenList);
                }
                childrenList.appendChild(this.draggedElement);
                this.updateTextarea();
            }
        } else if (dropZone === 'drop-above') {
            // Inserir como irmão antes do item (verificar profundidade final)
            if (finalDepth <= this.maxDepth) {
                const parentList = element.parentNode;
                parentList.insertBefore(this.draggedElement, element);
                this.updateTextarea();
            }
        } else if (dropZone === 'drop-below') {
            // Inserir como irmão depois do item (verificar profundidade final)
            if (finalDepth <= this.maxDepth) {
                const parentList = element.parentNode;
                parentList.insertBefore(this.draggedElement, element.nextSibling);
                this.updateTextarea();
            }
        }
    }

    handleDragEnd(e, element) {
        // Limpar todas as classes de drag de todos os itens
        this.clearAllDropZones();

        if (this.list) {
            this.list.querySelectorAll('.nestable-item').forEach(el => {
                el.classList.remove('dragging');
            });
        }

        this.draggedElement = null;
        this.dragOverElement = null;
        this.currentDropZone = null;
    }

    isDescendant(parent, child) {
        if (!parent || !child) return false;
        let node = child.parentNode;
        while (node != null) {
            if (node === parent) return true;
            node = node.parentNode;
        }
        return false;
    }

    // Calcular profundidade (nível de aninhamento) de um item
    getItemDepth(element) {
        let depth = 0;
        let node = element;
        while (node && node !== this.list) {
            if (node.classList && node.classList.contains('nestable-children')) {
                depth++;
            }
            node = node.parentNode;
        }
        return depth;
    }

    // Calcular profundidade final que será alcançada após inserção
    getFinalDepth(draggedElement, targetElement, dropZone) {
        const targetDepth = this.getItemDepth(targetElement);

        if (dropZone === 'drop-child') {
            // Se tornar filho, profundidade final = profundidade do destino + 1
            return targetDepth + 1;
        } else if (dropZone === 'drop-above' || dropZone === 'drop-below') {
            // Se inserir como irmão, profundidade final = profundidade do destino
            return targetDepth;
        }

        // Fallback: retornar profundidade atual do item arrastado
        return this.getItemDepth(draggedElement);
    }

    // Encontrar o item pai (elemento li que contém uma lista de filhos)
    findParentItem(element) {
        let node = element.parentNode;
        while (node && node !== this.list) {
            if (node.classList && node.classList.contains('nestable-item')) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    // Encontrar o item acima na hierarquia
    findPreviousSibling(element) {
        const parentList = element.parentNode;
        const currentIndex = Array.from(parentList.children).indexOf(element);
        if (currentIndex > 0) {
            return parentList.children[currentIndex - 1];
        }
        return null;
    }

    // Detectar qual zona de drop está sendo apontada baseado na posição Y do mouse
    getDropZone(element, clientY) {
        if (!element) return null;

        const handle = element.querySelector('.nestable-handle');
        if (!handle) return null;

        const handleRect = handle.getBoundingClientRect();
        const relativeY = clientY - handleRect.top;
        const handleHeight = handleRect.height;

        // Quando maxDepth = 0, não permitir tornar filho (apenas reordenar)
        if (this.maxDepth === 0) {
            // Dividir em 2 zonas: superior (50%) e inferior (50%)
            if (relativeY < handleHeight * 0.5) {
                return 'drop-above';
            } else {
                return 'drop-below';
            }
        }

        // Modo normal com hierarquia: dividir em 3 zonas
        // Zona superior (25%): inserir como irmão acima
        if (relativeY < handleHeight * 0.25) {
            return 'drop-above';
        }
        // Zona inferior (25%): inserir como irmão abaixo
        if (relativeY > handleHeight * 0.75) {
            return 'drop-below';
        }
        // Zona central (50%): tornar filho
        return 'drop-child';
    }

    // Verificar se o mouse está fora da área do pai durante o drag
    isOutsideParentArea(element, clientX, clientY) {
        if (!element) return false;

        const parentItem = this.findParentItem(element);
        if (!parentItem) return false; // Não tem pai, não pode estar fora

        // Calcular área visual do pai (handle + children)
        const parentRect = parentItem.getBoundingClientRect();
        const parentHandle = parentItem.querySelector('.nestable-handle');
        const childrenList = parentItem.querySelector('.nestable-list.nestable-children');

        if (!parentHandle) return false;

        const handleRect = parentHandle.getBoundingClientRect();

        // Área do pai = handle + children (se existirem)
        let parentAreaBottom = handleRect.bottom;
        if (childrenList && childrenList.children.length > 0) {
            const childrenRect = childrenList.getBoundingClientRect();
            parentAreaBottom = Math.max(parentAreaBottom, childrenRect.bottom);
        }

        // Verificar se está fora da área horizontal ou vertical do pai
        const isOutsideHorizontally = clientX < parentRect.left - 50 || clientX > parentRect.right + 50;
        const isOutsideVertically = clientY < parentRect.top - 20 || clientY > parentAreaBottom + 20;

        // Considerar fora se estiver significativamente fora em qualquer direção
        return isOutsideHorizontally || isOutsideVertically;
    }

    // Verificar se podemos outdentar para um item específico
    // Retorna true se o item de destino está em nível superior ao pai do item arrastado
    canOutdentTo(draggedElement, targetElement) {
        if (!draggedElement || !targetElement) return false;

        const draggedDepth = this.getItemDepth(draggedElement);
        if (draggedDepth === 0) return false; // Já está no nível raiz, não pode outdentar

        const targetDepth = this.getItemDepth(targetElement);
        const draggedParent = this.findParentItem(draggedElement);

        if (!draggedParent) return false; // Não tem pai, não pode outdentar

        const draggedParentDepth = this.getItemDepth(draggedParent);

        // Podemos outdentar se o alvo está em nível superior ao pai
        // (não no mesmo nível, pois isso seria apenas mover entre irmãos)
        // E não estamos tentando colocar dentro de nós mesmos ou nossos descendentes
        if (targetDepth < draggedParentDepth && !this.isDescendant(draggedElement, targetElement)) {
            return true;
        }

        return false;
    }

    serialize() {
        if (!this.list) return [];
        const items = Array.from(this.list.querySelectorAll('.nestable-item:not(.nestable-dragel)'));
        return items.length === 0 ? [] : items.map(item => this.serializeItem(item));
    }

    serializeItem(itemElement) {
        const id = itemElement.dataset.id;
        const nameElement = itemElement.querySelector('.item-name');
        const nameValue = nameElement ? nameElement.textContent : `Item ${id}`;
        const childrenList = itemElement.querySelector('.nestable-list.nestable-children');
        const children = childrenList ? Array.from(childrenList.querySelectorAll('.nestable-item')).map(child => this.serializeItem(child)) : null;
        const result = { id: id };
        // Manter o campo original (name ou content)
        if (this.nameField === 'content') {
            result.content = nameValue;
        } else {
            result.name = nameValue;
        }
        if (children && children.length > 0) result.children = children;
        return result;
    }

    updateTextarea() {
        if (!this.textarea) return;
        const data = this.serialize();
        this.textarea.value = JSON.stringify(data, null, 2);
    }

    loadFromTextarea() {
        if (!this.textarea || !this.list) return;
        const jsonText = this.textarea.value.trim();
        if (!jsonText) {
            this.renderList([]);
            return;
        }
        try {
            const data = JSON.parse(jsonText);
            if (Array.isArray(data)) {
                // Detectar qual campo está sendo usado (content tem prioridade)
                const detectNameField = (items) => {
                    for (const item of items) {
                        if (item.content !== undefined) {
                            return 'content';
                        }
                        if (item.name !== undefined) {
                            return 'name';
                        }
                        if (item.children) {
                            const childField = detectNameField(item.children);
                            if (childField) return childField;
                        }
                    }
                    return 'name'; // padrão
                };
                this.nameField = detectNameField(data);
                this.renderList(data);
                this.updateNextId(data);
            } else {
                throw new Error('JSON deve ser um array');
            }
        } catch (error) {
            console.error('Erro ao carregar JSON:', error);
        }
    }

    updateNextId(data) {
        let maxNum = 0;
        const extractIds = (items) => {
            items.forEach(item => {
                if (item.id) {
                    // Tentar converter o ID para número
                    const idNum = parseInt(item.id);
                    if (!isNaN(idNum)) {
                        maxNum = Math.max(maxNum, idNum);
                    } else {
                        // Se não for número puro, tentar extrair números da string
                        const match = String(item.id).match(/\d+/);
                        if (match) {
                            maxNum = Math.max(maxNum, parseInt(match[0]));
                        }
                    }
                }
                if (item.children) extractIds(item.children);
            });
        };
        extractIds(data);
        this.nextId = maxNum + 1;
    }

    openEditModal(itemId, currentName) {
        this.currentEditItem = itemId;
        const editInput = document.getElementById('editItemNameNestable');
        const editModal = document.getElementById('editModalNestable');
        if (editInput && editModal) {
            editInput.value = currentName;
            editModal.classList.add('open');
            setTimeout(() => editInput.focus(), 100);
        }
    }

    closeEditModal() {
        const editModal = document.getElementById('editModalNestable');
        const editInput = document.getElementById('editItemNameNestable');
        if (editModal) editModal.classList.remove('open');
        if (editInput) editInput.value = '';
        this.currentEditItem = null;
    }

    saveEditItem() {
        if (!this.currentEditItem) return;
        const editInput = document.getElementById('editItemNameNestable');
        if (!editInput) return;
        const newName = editInput.value.trim();
        if (!newName) {
            alert('Por favor, digite um nome para o item.');
            return;
        }
        this.updateItemName(this.currentEditItem, newName);
        this.closeEditModal();
    }

    // Atualizar nome de um item pelo ID (pode ser chamado externamente)
    updateItemName(itemId, newName) {
        if (!itemId || !newName) return;
        const itemElement = this.list.querySelector(`[data-id="${itemId}"]`);
        if (itemElement) {
            const nameElement = itemElement.querySelector('.item-name');
            if (nameElement) {
                nameElement.textContent = newName;
                this.updateTextarea();
            }
        }
    }

    // Excluir um item pelo ID (pode ser chamado externamente)
    deleteItem(itemId) {
        if (!itemId) return;
        const itemElement = this.list.querySelector(`[data-id="${itemId}"]`);
        if (itemElement) {
            itemElement.remove();
            const remainingItems = this.list.querySelectorAll('.nestable-item');
            if (remainingItems.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'nestable-empty';
                empty.textContent = 'Nenhum item. Clique em "Adicionar Item" para começar.';
                this.list.appendChild(empty);
            }
            this.updateTextarea();
        }
    }

    openDeleteModal(recordId, recordName, recordType = 'registro', confirmType = 'text') {
        this.currentRecordId = recordId;
        this.currentRecordName = recordName;
        this.currentDeleteItem = recordId;
        this.currentConfirmType = confirmType;
        const modal = document.getElementById('meuModalExclusaoNestable');
        const modalTitle = document.getElementById('deleteModalTitleNestable');
        const modalMessage = document.getElementById('deleteModalMessageNestable');
        const confirmSection = document.getElementById('confirmDeleteSectionNestable');
        const confirmInput = document.getElementById('confirmDeleteInputNestable');
        const confirmText = document.getElementById('confirmDeleteTextNestable');
        const confirmButton = document.getElementById('confirmDeleteButtonNestable');
        const confirmHint = document.getElementById('confirmDeleteHintNestable');
        if (!modal || !modalTitle || !modalMessage) return;
        modalTitle.textContent = 'Confirmar Exclusão';
        modalMessage.textContent = `Deseja realmente excluir o ${recordType} "${recordName}" (ID: ${recordId})?`;
        if (confirmType === 'none') {
            if (confirmSection) confirmSection.style.display = 'none';
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.style.opacity = '1';
                confirmButton.style.cursor = 'pointer';
            }
            if (confirmInput) confirmInput.removeEventListener('input', this.validateDeleteConfirmation.bind(this));
            modal.classList.add('open');
            return;
        }
        if (confirmSection) confirmSection.style.display = 'block';
        if (confirmType === 'name') {
            this.currentExpectedText = recordName.trim();
            if (confirmText) confirmText.textContent = `"${recordName}"`;
            if (confirmInput) confirmInput.placeholder = `Digite "${recordName}" para confirmar`;
            if (confirmHint) confirmHint.textContent = 'Digite exatamente o nome do registro para confirmar a exclusão.';
        } else {
            this.currentExpectedText = this.CONFIRM_TEXT;
            if (confirmText) confirmText.textContent = this.CONFIRM_TEXT;
            if (confirmInput) confirmInput.placeholder = `Digite ${this.CONFIRM_TEXT} para confirmar`;
        }
        if (confirmInput) confirmInput.value = '';
        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';
        }
        if (confirmInput) confirmInput.removeEventListener('input', this.validateDeleteConfirmation.bind(this));
        modal.classList.add('open');
        setTimeout(() => { if (confirmInput) confirmInput.focus(); }, 100);
        if (confirmInput) confirmInput.addEventListener('input', this.validateDeleteConfirmation.bind(this));
    }

    validateDeleteConfirmation() {
        const confirmInput = document.getElementById('confirmDeleteInputNestable');
        const confirmButton = document.getElementById('confirmDeleteButtonNestable');
        if (!confirmInput || !confirmButton) return;
        const inputValue = confirmInput.value.trim();
        let matches = false;
        if (this.currentConfirmType === 'name') {
            matches = inputValue.toLowerCase() === this.currentExpectedText.toLowerCase();
        } else {
            matches = inputValue.toLowerCase() === this.currentExpectedText.toLowerCase();
        }
        if (matches) {
            confirmButton.disabled = false;
            confirmButton.style.opacity = '1';
            confirmButton.style.cursor = 'pointer';
        } else {
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';
        }
    }

    closeDeleteModal() {
        const modal = document.getElementById('meuModalExclusaoNestable');
        const confirmSection = document.getElementById('confirmDeleteSectionNestable');
        const confirmInput = document.getElementById('confirmDeleteInputNestable');
        if (confirmInput) {
            confirmInput.removeEventListener('input', this.validateDeleteConfirmation.bind(this));
            confirmInput.value = '';
        }
        if (confirmSection) confirmSection.style.display = 'none';
        if (modal) modal.classList.remove('open');
        this.currentRecordId = null;
        this.currentRecordName = null;
        this.currentDeleteItem = null;
        this.currentConfirmType = 'text';
        this.currentExpectedText = '';
    }

    confirmDelete() {
        if (!this.currentDeleteItem) return;
        const itemElement = this.list.querySelector(`[data-id="${this.currentDeleteItem}"]`);
        if (itemElement) {
            itemElement.remove();
            const remainingItems = this.list.querySelectorAll('.nestable-item');
            if (remainingItems.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'nestable-empty';
                empty.textContent = 'Nenhum item. Clique em "Adicionar Item" para começar.';
                this.list.appendChild(empty);
            }
            this.updateTextarea();
        }
        this.closeDeleteModal();
    }
}

// Inicializar Nestable List
let nestableInstanceNestable = null;
function initNestableList() {
    const container = document.getElementById('nestableContainer');
    const textarea = document.getElementById('jsonTextareaNestable');
    if (container && textarea && !nestableInstanceNestable) {
        nestableInstanceNestable = new NestableList('nestableContainer', 'jsonTextareaNestable');
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNestableList);
} else {
    initNestableList();
}

// Funções globais para os modais
function closeEditModalNestable() {
    if (nestableInstanceNestable) {
        nestableInstanceNestable.closeEditModal();
    }
}

function saveEditItemNestable() {
    if (nestableInstanceNestable) {
        nestableInstanceNestable.saveEditItem();
    }
}

function openDeleteModalNestable(recordId, recordName, recordType, confirmType) {
    if (nestableInstanceNestable) {
        nestableInstanceNestable.openDeleteModal(recordId, recordName, recordType, confirmType);
    }
}

function closeDeleteModalNestable() {
    if (nestableInstanceNestable) {
        nestableInstanceNestable.closeDeleteModal();
    }
}

function confirmDeleteNestable() {
    if (nestableInstanceNestable) {
        nestableInstanceNestable.confirmDelete();
    }
}
