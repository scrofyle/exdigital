/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * JavaScript Principal - VERSÃO CORRIGIDA COM MENU MOBILE
 * Versão 2.0
 */

(function() {
    'use strict';

    // ============================================
    // VARIÁVEIS GLOBAIS
    // ============================================
    const body = document.body;
    let sidebar = null;
    let mainContent = null;
    let menuToggle = null;

    // ============================================
    // INICIALIZAÇÃO QUANDO DOM ESTIVER PRONTO
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        initMenuMobile();
        initDropdowns();
        initTabs();
        initAutoCloseAlerts();
        initFormValidation();
        initCharCounter();
        console.log('ExDigital Pro v2.0 - Sistema carregado!');
    });

    // ============================================
    // MENU SIDEBAR MOBILE/DESKTOP - CORRIGIDO
    // ============================================
    function initMenuMobile() {
        sidebar = document.getElementById('sidebar');
        mainContent = document.getElementById('mainContent');
        menuToggle = document.getElementById('menuToggle');

        if (!menuToggle || !sidebar) {
            console.warn('Menu toggle ou sidebar não encontrado');
            return;
        }

        // Adicionar classe para mobile
        if (window.innerWidth <= 1024) {
            sidebar.classList.add('mobile');
            sidebar.classList.add('collapsed');
        }

        // Click no botão de menu
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });

        // Fechar menu ao clicar fora (apenas em mobile)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (sidebar && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    closeMenu();
                }
            }
        });

        // Prevenir propagação de cliques dentro do sidebar
        if (sidebar) {
            sidebar.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Fechar menu ao clicar em link (apenas mobile)
        const sidebarLinks = sidebar ? sidebar.querySelectorAll('.sidebar-menu-link') : [];
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    setTimeout(() => closeMenu(), 300);
                }
            });
        });

        // Redimensionamento da janela
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize();
            }, 250);
        });

        // Estado inicial
        handleResize();
    }

    function toggleMenu() {
        if (!sidebar) return;

        if (sidebar.classList.contains('collapsed')) {
            openMenu();
        } else {
            closeMenu();
        }
    }

    function openMenu() {
        if (!sidebar) return;

        sidebar.classList.remove('collapsed');
        
        if (mainContent) {
            mainContent.classList.remove('expanded');
        }

        // Adicionar overlay em mobile
        if (window.innerWidth <= 1024) {
            addOverlay();
        }

        // Salvar estado
        if (window.innerWidth > 1024) {
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    }

    function closeMenu() {
        if (!sidebar) return;

        sidebar.classList.add('collapsed');
        
        if (mainContent) {
            mainContent.classList.add('expanded');
        }

        // Remover overlay
        removeOverlay();

        // Salvar estado (apenas desktop)
        if (window.innerWidth > 1024) {
            localStorage.setItem('sidebarCollapsed', 'true');
        }
    }

    function addOverlay() {
        // Verificar se já existe overlay
        let overlay = document.getElementById('sidebar-overlay');
        if (overlay) return;

        overlay = document.createElement('div');
        overlay.id = 'sidebar-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            animation: fadeIn 0.3s ease;
        `;

        overlay.addEventListener('click', closeMenu);
        document.body.appendChild(overlay);
    }

    function removeOverlay() {
        const overlay = document.getElementById('sidebar-overlay');
        if (overlay) {
            overlay.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }
    }

    function handleResize() {
        if (!sidebar) return;

        const width = window.innerWidth;

        if (width <= 1024) {
            // Mobile: sempre iniciar fechado
            sidebar.classList.add('mobile');
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.classList.add('expanded');
            }
        } else {
            // Desktop: restaurar estado salvo
            sidebar.classList.remove('mobile');
            removeOverlay();
            
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                if (mainContent) {
                    mainContent.classList.add('expanded');
                }
            } else {
                sidebar.classList.remove('collapsed');
                if (mainContent) {
                    mainContent.classList.remove('expanded');
                }
            }
        }
    }

    // ============================================
    // DROPDOWNS
    // ============================================
    function initDropdowns() {
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            
            if (toggle) {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Fechar outros dropdowns
                    dropdowns.forEach(other => {
                        if (other !== dropdown) {
                            other.classList.remove('active');
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
    }

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
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
            body.style.overflow = '';
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal-overlay.active');
            modals.forEach(modal => {
                modal.classList.remove('active');
            });
            body.style.overflow = '';
        }
    });

    // ============================================
    // TABS
    // ============================================
    function initTabs() {
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
    }

    // ============================================
    // ALERTAS AUTO-CLOSE
    // ============================================
    function initAutoCloseAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }, 5000);
        });
    }

    // ============================================
    // CONFIRMAÇÃO DE EXCLUSÃO
    // ============================================
    window.confirmDelete = function(message) {
        return confirm(message || 'Tem certeza que deseja excluir este item?');
    };

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
                    row.style.display = text.includes(searchValue) ? '' : 'none';
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
                if (toast.parentNode) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    };

    // ============================================
    // LOADING OVERLAY
    // ============================================
    window.showLoading = function() {
        let loading = document.getElementById('loadingOverlay');
        if (loading) return;
        
        loading = document.createElement('div');
        loading.className = 'loading-overlay';
        loading.id = 'loadingOverlay';
        loading.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(loading);
    };

    window.hideLoading = function() {
        const loading = document.getElementById('loadingOverlay');
        if (loading && loading.parentNode) {
            loading.parentNode.removeChild(loading);
        }
    };

    // ============================================
    // FORMULÁRIOS - VALIDAÇÃO
    // ============================================
    function initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                const requiredFields = form.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                        
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

        const inputFields = document.querySelectorAll('.form-control');
        inputFields.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
                const errorMsg = this.parentElement.querySelector('.form-error');
                if (errorMsg && (!this.hasAttribute('required') || this.value.trim())) {
                    errorMsg.remove();
                }
            });
        });
    }

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
    function initCharCounter() {
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
    }

    // ============================================
    // ADICIONAR ANIMAÇÕES CSS
    // ============================================
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        /* Menu Mobile Fix */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1000;
                transition: transform 0.3s ease;
                transform: translateX(0);
            }

            .sidebar.collapsed {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0 !important;
                transition: none;
            }
        }
    `;
    document.head.appendChild(style);

})();