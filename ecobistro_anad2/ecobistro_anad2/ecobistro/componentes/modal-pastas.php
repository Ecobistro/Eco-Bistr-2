<?php
// components/modal-pastas.php
// Este arquivo deve ser incluído apenas para usuários logados
if (!$usuarioLogado) return;
?>
<!-- Modal para adicionar receita às pastas -->
<div id="modalPastas" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; backdrop-filter: blur(2px);">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow); max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; color: var(--text-dark);">Adicionar à Pasta</h3>
            <button onclick="fecharModalPastas()" style="background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer; padding: 0.25rem; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">×</button>
        </div>
        
        <div id="listaPastas" style="max-height: 400px; overflow-y: auto;">
            <p style="text-align: center; color: var(--text-light);">Carregando pastas...</p>
        </div>
        
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--background-light);">
            <button onclick="mostrarFormNovaPasta()" class="btn btn-secondary" style="width: 100%;">
                + Criar Nova Pasta
            </button>
        </div>
        
        <!-- Formulário para criar nova pasta -->
        <div id="formNovaPasta" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--background-light); border-radius: 10px;">
            <div class="form-group">
                <label class="form-label">Nome da pasta</label>
                <input type="text" id="novaPastaNome" class="form-control" placeholder="Digite o nome da pasta..." maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">Descrição (opcional)</label>
                <textarea id="novaPastaDescricao" class="form-control" rows="2" placeholder="Descreva sua pasta..."></textarea>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="criarNovaPasta()" class="btn btn-primary" style="flex: 1;">Criar</button>
                <button onclick="ocultarFormNovaPasta()" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<style>
.pasta-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    border: 1px solid var(--background-light);
    border-radius: 10px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.pasta-item:hover {
    background: var(--background-light);
    border-color: var(--primary-color);
}

.pasta-item.loading {
    opacity: 0.6;
    cursor: not-allowed;
}

.pasta-info {
    flex: 1;
}

.pasta-nome {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.pasta-meta {
    font-size: 0.85rem;
    color: var(--text-light);
}

.pasta-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-right: 1rem;
}

.pasta-especial {
    background: var(--primary-color);
    color: var(--text-dark);
}

.pasta-personalizada {
    background: var(--secondary-color);
    color: var(--text-dark);
}

/* Animações */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pasta-item {
    animation: slideIn 0.3s ease-out;
}

/* Loading spinner */
.loading-spinner {
    border: 2px solid var(--background-light);
    border-top: 2px solid var(--primary-color);
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
let receitaAtualPastas = null;

// Função para abrir modal de pastas
async function abrirModalPastas(receitaId) {
    receitaAtualPastas = receitaId;
    document.getElementById('modalPastas').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Carregar pastas
    await carregarPastas();
}

// Função para fechar modal
function fecharModalPastas() {
    document.getElementById('modalPastas').style.display = 'none';
    document.body.style.overflow = '';
    receitaAtualPastas = null;
    ocultarFormNovaPasta();
}

// Função para carregar lista de pastas
async function carregarPastas() {
    const listaPastas = document.getElementById('listaPastas');
    listaPastas.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loading-spinner" style="margin: 0 auto;"></div><p style="margin-top: 1rem; color: var(--text-light);">Carregando pastas...</p></div>';
    
    try {
        const response = await fetch('ajax/listar-pastas.php');
        const result = await response.json();
        
        if (result.success) {
            renderizarPastas(result);
        } else {
            listaPastas.innerHTML = '<p style="text-align: center; color: var(--accent-color);">Erro ao carregar pastas</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar pastas:', error);
        listaPastas.innerHTML = '<p style="text-align: center; color: var(--accent-color);">Erro de conexão</p>';
    }
}

// Função para renderizar lista de pastas
function renderizarPastas(dados) {
    const listaPastas = document.getElementById('listaPastas');
    let html = '';
    
    // Pasta Favoritos
    html += `
        <div class="pasta-item" onclick="adicionarReceitaPasta('favoritos')">
            <div class="pasta-icon pasta-especial">⭐</div>
            <div class="pasta-info">
                <div class="pasta-nome">Favoritos</div>
                <div class="pasta-meta">${dados.favoritos} receitas</div>
            </div>
        </div>
    `;
    
    // Pasta Fazer Mais Tarde
    html += `
        <div class="pasta-item" onclick="adicionarReceitaPasta('fazer-mais-tarde')">
            <div class="pasta-icon pasta-especial">📅</div>
            <div class="pasta-info">
                <div class="pasta-nome">Fazer Mais Tarde</div>
                <div class="pasta-meta">${dados.fazerMaisTarde} receitas</div>
            </div>
        </div>
    `;
    
    // Separador
    if (dados.pastas.length > 0) {
        html += '<hr style="margin: 1rem 0; border: none; border-top: 1px solid var(--background-light);">';
    }
    
    // Pastas personalizadas
    dados.pastas.forEach(pasta => {
        html += `
            <div class="pasta-item" onclick="adicionarReceitaPasta(${pasta.id})">
                <div class="pasta-icon pasta-personalizada">📁</div>
                <div class="pasta-info">
                    <div class="pasta-nome">${escapeHtml(pasta.nome)}</div>
                    <div class="pasta-meta">${pasta.total_receitas} receitas</div>
                </div>
            </div>
        `;
    });
    
    if (html === '') {
        html = '<p style="text-align: center; color: var(--text-light); padding: 2rem;">Nenhuma pasta encontrada</p>';
    }
    
    listaPastas.innerHTML = html;
}

// Função para adicionar receita à pasta
async function adicionarReceitaPasta(pastaId) {
    if (!receitaAtualPastas) return;
    
    // Mostrar loading no item clicado
    const pastaItem = event.currentTarget;
    const originalContent = pastaItem.innerHTML;
    pastaItem.classList.add('loading');
    pastaItem.innerHTML = `
        <div class="pasta-icon pasta-especial">
            <div class="loading-spinner"></div>
        </div>
        <div class="pasta-info">
            <div class="pasta-nome">Adicionando...</div>
        </div>
    `;
    
    try {
        const response = await fetch('ajax/adicionar-pasta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                receita_id: receitaAtualPastas,
                pasta_id: pastaId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Mostrar sucesso
            pastaItem.style.background = 'var(--primary-color)';
            pastaItem.innerHTML = `
                <div class="pasta-icon pasta-especial">✅</div>
                <div class="pasta-info">
                    <div class="pasta-nome">Adicionado!</div>
                </div>
            `;
            
            // Fechar modal após 1.5 segundos
            setTimeout(() => {
                fecharModalPastas();
                
                // Mostrar notificação de sucesso
                mostrarNotificacao(result.message, 'success');
            }, 1500);
            
        } else {
            // Restaurar conteúdo original e mostrar erro
            pastaItem.classList.remove('loading');
            pastaItem.innerHTML = originalContent;
            mostrarNotificacao(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao adicionar receita:', error);
        pastaItem.classList.remove('loading');
        pastaItem.innerHTML = originalContent;
        mostrarNotificacao('Erro ao adicionar receita', 'error');
    }
}

// Função para mostrar formulário de nova pasta
function mostrarFormNovaPasta() {
    document.getElementById('formNovaPasta').style.display = 'block';
    document.getElementById('novaPastaNome').focus();
}

// Função para ocultar formulário de nova pasta
function ocultarFormNovaPasta() {
    document.getElementById('formNovaPasta').style.display = 'none';
    document.getElementById('novaPastaNome').value = '';
    document.getElementById('novaPastaDescricao').value = '';
}

// Função para criar nova pasta
async function criarNovaPasta() {
    const nome = document.getElementById('novaPastaNome').value.trim();
    const descricao = document.getElementById('novaPastaDescricao').value.trim();
    
    if (!nome) {
        mostrarNotificacao('Nome da pasta é obrigatório', 'error');
        return;
    }
    
    try {
        const response = await fetch('ajax/criar-pasta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome, descricao })
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarNotificacao('Pasta criada com sucesso!', 'success');
            ocultarFormNovaPasta();
            await carregarPastas(); // Recarregar lista
        } else {
            mostrarNotificacao(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao criar pasta:', error);
        mostrarNotificacao('Erro ao criar pasta', 'error');
    }
}

// Função para mostrar notificações
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    
    // Definir cor baseada no tipo
    switch (tipo) {
        case 'success':
            notificacao.style.background = '#10b981';
            break;
        case 'error':
            notificacao.style.background = '#ef4444';
            break;
        default:
            notificacao.style.background = 'var(--primary-color)';
            notificacao.style.color = 'var(--text-dark)';
    }
    
    notificacao.textContent = mensagem;
    document.body.appendChild(notificacao);
    
    // Animar entrada
    setTimeout(() => {
        notificacao.style.opacity = '1';
        notificacao.style.transform = 'translateX(0)';
    }, 100);
    
    // Remover após 4 segundos
    setTimeout(() => {
        notificacao.style.opacity = '0';
        notificacao.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (document.body.contains(notificacao)) {
                document.body.removeChild(notificacao);
            }
        }, 300);
    }, 4000);
}

// Função auxiliar para escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Fechar modal clicando fora dele
document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalPastas');
    if (e.target === modal) {
        fecharModalPastas();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('modalPastas').style.display === 'block') {
        fecharModalPastas();
    }
});
</script>