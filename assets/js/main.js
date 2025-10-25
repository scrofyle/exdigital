/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * JavaScript Principal
 * Versão 2.0
 */

(function() {
    'use strict';

    // ============================================
    // VARIÁVEIS GLOBAIS
    // ============================================
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const menuToggle = document.getElementById('menuToggle');

    // ============================================
    // MENU SIDEBAR
    // ============================================
    if (menuToggle && sidebar && mainContent) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Salvar estado no localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Restaurar estado do sidebar
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
        if (sidebarCollapsed === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        // Fechar sidebar em mobile ao clicar fora
        if (window.innerWidth <= 1024) {
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.add('collapsed');
                }
            });
        }
    }

    // ============================================
    // DROPDOWNS
    // ============================================
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Fechar outros dropdowns
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.classList.remove('active');
                    }
                });
                
                dropdown.classList.toggle('active');
            });
        }
    });

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });

    // ============================================
    // MODAIS
    // ============================================
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            body.style.overflow = '';
        }
    };

    // Fechar modal ao clicar no overlay
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
                body.style.overflow = '';
            }
        });
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modalOverlays.forEach(overlay => {
                overlay.classList.remove('active');
            });
            body.style.overflow = '';
        }
    });

    // ============================================
    // TABS
    // ============================================
    const tabItems = document.querySelectorAll('.tab-item');
    
    tabItems.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            
            // Remover active de todas as tabs
            tabItems.forEach(t => t.classList.remove('active'));
            
            // Adicionar active na tab clicada
            this.classList.add('active');
            
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Mostrar conteúdo da tab clicada
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    // ============================================
    // ALERTAS AUTO-CLOSE
    // ============================================
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // ============================================
    // CONFIRMAÇÃO DE EXCLUSÃO
    // ============================================
    window.confirmDelete = function(message) {
        return confirm(message || 'Tem certeza que deseja excluir este item?');
    };

    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete');
            if (!confirm(message || 'Tem certeza que deseja excluir?')) {
                e.preventDefault();
                return false;
            }
        });
    });

    // ============================================
    // BUSCA NA TABELA
    // ============================================
    const tableSearch = document.getElementById('tableSearch');
    if (tableSearch) {
        tableSearch.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const table = document.querySelector('.table tbody');
            
            if (table) {
                const rows = table.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchValue)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });
    }

    // ============================================
    // FORMATAÇÃO DE INPUTS
    // ============================================
    
    // Telefone
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = '+244 ' + value;
                } else if (value.length <= 6) {
                    value = '+244 ' + value.substring(3, 6);
                } else if (value.length <= 9) {
                    value = '+244 ' + value.substring(3, 6) + ' ' + value.substring(6, 9);
                } else {
                    value = '+244 ' + value.substring(3, 6) + ' ' + value.substring(6, 9) + ' ' + value.substring(9, 12);
                }
            }
            
            e.target.value = value;
        });
    });

    // Moeda
    const moneyInputs = document.querySelectorAll('.input-money');
    moneyInputs.forEach(input => {
        input.addEventListener('blur', function() {
            let value = parseFloat(this.value.replace(/[^\d,]/g, '').replace(',', '.'));
            if (!isNaN(value)) {
                this.value = value.toFixed(2).replace('.', ',');
            }
        });
    });

    // ============================================
    // PREVIEW DE IMAGEM
    // ============================================
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewId = this.getAttribute('data-preview');
            
            if (file && previewId) {
                const preview = document.getElementById(previewId);
                if (preview) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            }
        });
    });

    // ============================================
    // COPIAR PARA CLIPBOARD
    // ============================================
    window.copyToClipboard = function(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showNotification('Copiado para a área de transferência!', 'success');
        } catch (err) {
            showNotification('Erro ao copiar', 'error');
        }
        
        document.body.removeChild(textarea);
    };

    // ============================================
    // NOTIFICAÇÕES TOAST
    // ============================================
    window.showNotification = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            z-index: 99999;
            animation: slideInRight 0.3s ease;
            min-width: 250px;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    };

    // Adicionar animações CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // ============================================
    // LOADING OVERLAY
    // ============================================
    window.showLoading = function() {
        const loading = document.createElement('div');
        loading.className = 'loading-overlay';
        loading.id = 'loadingOverlay';
        loading.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(loading);
    };

    window.hideLoading = function() {
        const loading = document.getElementById('loadingOverlay');
        if (loading) {
            loading.remove();
        }
    };

    // ============================================
    // FORMULÁRIOS - VALIDAÇÃO
    // ============================================
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validar campos obrigatórios
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Mostrar mensagem de erro
                    let errorMsg = field.parentElement.querySelector('.form-error');
                    if (!errorMsg) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'form-error';
                        errorMsg.textContent = 'Este campo é obrigatório';
                        field.parentElement.appendChild(errorMsg);
                    }
                } else {
                    field.classList.remove('error');
                    const errorMsg = field.parentElement.querySelector('.form-error');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            // Validar email
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (field.value && !emailRegex.test(field.value)) {
                    isValid = false;
                    field.classList.add('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Por favor, preencha todos os campos obrigatórios', 'error');
                return false;
            }
        });
    });

    // Remover erro ao digitar
    const inputFields = document.querySelectorAll('.form-control');
    inputFields.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
            const errorMsg = this.parentElement.querySelector('.form-error');
            if (errorMsg && !this.hasAttribute('required') || this.value.trim()) {
                errorMsg.remove();
            }
        });
    });

    // ============================================
    // IMPRESSÃO
    // ============================================
    window.printPage = function() {
        window.print();
    };

    // ============================================
    // EXPORTAR PARA CSV
    // ============================================
    window.exportTableToCSV = function(tableId, filename = 'export.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            let rowData = [];
            const cols = row.querySelectorAll('td, th');
            
            cols.forEach(col => {
                rowData.push('"' + col.textContent.trim() + '"');
            });
            
            csv.push(rowData.join(','));
        });
        
        // Download
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('Exportado com sucesso!', 'success');
    };

    // ============================================
    // CONTADOR DE CARACTERES
    // ============================================
    const textareaWithCounter = document.querySelectorAll('textarea[data-max-length]');
    textareaWithCounter.forEach(textarea => {
        const maxLength = parseInt(textarea.getAttribute('data-max-length'));
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.style.cssText = 'text-align: right; font-size: 0.813rem; color: var(--gray-medium); margin-top: 0.25rem;';
        
        const updateCounter = () => {
            const length = textarea.value.length;
            counter.textContent = `${length} / ${maxLength}`;
            
            if (length > maxLength) {
                counter.style.color = 'var(--danger-color)';
            } else if (length > maxLength * 0.9) {
                counter.style.color = 'var(--warning-color)';
            } else {
                counter.style.color = 'var(--gray-medium)';
            }
        };
        
        textarea.parentElement.appendChild(counter);
        updateCounter();
        
        textarea.addEventListener('input', updateCounter);
    });

    // ============================================
    // INICIALIZAÇÃO COMPLETA
    // ============================================
    console.log('Sistema de Gestão de Eventos - v2.0 carregado com sucesso!');
    
})();