(function () {
    const body = document.body;
    if (!body || body.dataset.aiEnabled !== '1') {
        return;
    }

    // Get app root from body data attribute
    let appRoot = body.dataset.appRoot || '';
    
    // If appRoot is empty, determine it from current path
    if (!appRoot) {
        const currentPath = window.location.pathname;
        if (currentPath.includes('/abbis3.2/')) {
            // Extract the base path
            const match = currentPath.match(/^(\/abbis3\.2\/)/);
            if (match) {
                appRoot = match[1];
            }
        } else if (currentPath.includes('/modules/')) {
            // We're in a module, use relative path
            appRoot = '../';
        }
    }

    const shellNodes = document.querySelectorAll('[data-ai-assistant]');
    if (!shellNodes.length) {
        return;
    }

    shellNodes.forEach(initAssistant);

    function initAssistant(shell) {
        const panel = shell.querySelector('.ai-assistant-panel');
        const toggleBtn = shell.querySelector('.ai-assistant-toggle');
        const closeBtn = shell.querySelector('.ai-assistant-close');
        const statusDot = shell.querySelector('.ai-assistant-status-dot');
        const statusLabel = shell.querySelector('.ai-assistant-status-label');
        const messagesWrap = shell.querySelector('[data-ai-messages]');
        const form = shell.querySelector('[data-ai-form]');
        const input = shell.querySelector('[data-ai-input]');
        const sendBtn = shell.querySelector('[data-ai-send]');
        const clearBtn = shell.querySelector('[data-ai-clear]');
        const contextLabelNode = shell.querySelector('[data-ai-context-label]');
        const quickButtons = shell.querySelectorAll('.ai-assistant-quick-btn');
        const contextClearBtn = shell.querySelector('.ai-assistant-context-clear');

        if (!panel || !toggleBtn || !messagesWrap || !form || !input) {
            return;
        }

        const apiPath = resolveApiPath(shell.dataset.apiPath);
        const state = {
            messages: [],
            loading: false,
            entityType: shell.dataset.entityType || null,
            entityId: shell.dataset.entityId || null,
            entityLabel: shell.dataset.entityLabel || null,
        };

        // Event listeners
        toggleBtn.addEventListener('click', () => {
            shell.classList.toggle('is-open');
            if (shell.classList.contains('is-open')) {
                input.focus();
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                shell.classList.remove('is-open');
            });
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = input.value.trim();
            if (!message || state.loading) {
                return;
            }
            appendMessage('user', message);
            input.value = '';
            await sendRequest([{ role: 'user', content: message }]);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                state.messages = [];
                messagesWrap.innerHTML = '';
                appendMessage('assistant', 'Conversation cleared. How else can I help?');
            });
        }

        quickButtons.forEach((btn) => {
            btn.addEventListener('click', async () => {
                const prompt = btn.dataset.prompt || btn.textContent;
                if (!prompt) return;
                appendMessage('user', prompt);
                await sendRequest([{ role: 'user', content: prompt }]);
            });
        });

        if (contextClearBtn) {
            contextClearBtn.addEventListener('click', () => {
                state.entityType = null;
                state.entityId = null;
                state.entityLabel = null;
                contextLabelNode.textContent = 'No context selected';
                shell.removeAttribute('data-entity-type');
                shell.removeAttribute('data-entity-id');
                shell.removeAttribute('data-entity-label');
            });
        }

        function appendMessage(role, content) {
            const bubble = document.createElement('div');
            bubble.className = `ai-assistant-message ai-assistant-message--${role}`;

            const avatar = document.createElement('div');
            avatar.className = 'ai-assistant-avatar';
            avatar.textContent = role === 'user' ? '🧑' : '🤖';

            const inner = document.createElement('div');
            inner.className = 'ai-assistant-bubble';
            inner.textContent = content;

            bubble.appendChild(avatar);

            if (role === 'assistant') {
                inner.innerHTML = renderMarkdown(content);
            }

            bubble.appendChild(inner);
            messagesWrap.appendChild(bubble);
            messagesWrap.scrollTop = messagesWrap.scrollHeight;
        }

        function setLoading(loading) {
            state.loading = loading;
            sendBtn.disabled = loading;
            if (loading) {
                statusDot.classList.add('is-busy');
                statusLabel.textContent = 'Thinking...';
            } else {
                statusDot.classList.remove('is-busy');
                statusLabel.textContent = 'Ready';
            }
        }

        async function sendRequest(newMessages) {
            setLoading(true);

            const payload = {
                action: 'assistant_chat',
                messages: [...state.messages, ...newMessages].slice(-12),
            };

            if (state.entityType && state.entityId) {
                payload.entity_type = state.entityType;
                payload.entity_id = state.entityId;
            }

            const response = await fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            }).catch((err) => {
                console.error('AI request failed', err);
                return null;
            });

            if (!response) {
                appendMessage('assistant', 'Could not reach ABBIS AI service. Please try again later.');
                setLoading(false);
                return;
            }

            let json;
            try {
                const text = await response.text();
                try {
                    json = JSON.parse(text);
                } catch (parseErr) {
                    console.error('Invalid JSON from AI', parseErr);
                    console.error('Response was:', text.substring(0, 500));
                    
                    // Check if it's an HTML error page
                    if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                        appendMessage('assistant', '❌ **AI Service Configuration Required**\n\n🔧 **Quick Setup:**\n1. Go to: `modules/admin/configure-ai-keys.php`\n2. Click "Configure AI Providers"\n3. This will set up OpenAI and DeepSeek automatically\n\n📋 **Manual Setup:**\n1. Go to: `modules/ai-governance.php`\n2. Select a provider (OpenAI, DeepSeek, Gemini, or Ollama)\n3. Enter your API key\n4. Enable the provider\n5. Save settings\n\n💡 **Need API Keys?**\n• OpenAI: https://platform.openai.com/api-keys\n• DeepSeek: https://platform.deepseek.com/\n• Gemini: https://console.cloud.google.com/apis/credentials\n• Ollama: No key needed (self-hosted)');
                    } else {
                        appendMessage('assistant', '❌ **AI Service Error**\n\nThe AI service returned an unexpected response.\n\n🔧 **Quick Fix:**\n1. Visit: `modules/admin/configure-ai-keys.php`\n2. Click "Configure AI Providers" to set up automatically\n\n📋 **Or configure manually:**\n1. Go to: `modules/ai-governance.php`\n2. Ensure at least one provider is enabled with a valid API key\n3. Check that the encryption key is set up\n\n💡 **Common Issues:**\n• No providers configured\n• API keys missing or invalid\n• Encryption key not set up\n• Provider service unavailable');
                    }
                    setLoading(false);
                    return;
                }
            } catch (err) {
                console.error('Failed to read response', err);
                appendMessage('assistant', '❌ Could not read the response from the AI service. Please check your network connection and try again.');
                setLoading(false);
                return;
            }

            if (!json.success) {
                const msg = json.message || 'AI request failed.';
                const category = json.category || 'error';
                const detail = json.detail || '';
                
                // Provide helpful messages based on error category
                let helpfulMessage = msg;
                
                if (category === 'no_providers') {
                    helpfulMessage = '❌ **No AI Providers Configured**\n\n' +
                        'No AI providers are set up in the system.\n\n' +
                        '🔧 **Quick Fix:**\n' +
                        '1. Go to: **AI Governance** (`modules/ai-governance.php`)\n' +
                        '2. Set up at least one provider:\n' +
                        '   • OpenAI (requires API key)\n' +
                        '   • DeepSeek (requires API key)\n' +
                        '   • Gemini (requires API key)\n' +
                        '   • Ollama (local, no API key needed)\n' +
                        '3. Add your API keys and enable the provider\n' +
                        '4. Try again\n\n' +
                        (detail ? '**Technical Details:** ' + detail + '\n' : '');
                } else if (category === 'service' || category === 'CODE_SERVICE') {
                    helpfulMessage = '❌ **AI Service Temporarily Unavailable**\n\n' +
                        'This usually means:\n' +
                        '• No AI providers are configured\n' +
                        '• API keys are missing or invalid\n' +
                        '• Provider service is down\n\n' +
                        '🔧 **Quick Fix:**\n' +
                        '1. Go to: **AI Governance** (`modules/ai-governance.php`)\n' +
                        '2. Check provider configuration\n' +
                        '3. Verify API keys are valid\n' +
                        '4. Ensure at least one provider is enabled\n\n' +
                        (detail ? '**Technical Details:** ' + detail + '\n' : '');
                } else if (category === 'auth' || category === 'CODE_AUTH') {
                    helpfulMessage = '❌ **AI Provider Authentication Failed**\n\n' +
                        'Your API keys may be invalid or expired.\n\n' +
                        '🔧 **Fix:**\n' +
                        '1. Go to: `modules/ai-governance.php`\n' +
                        '2. Check your API keys\n' +
                        '3. Update them if necessary\n' +
                        '4. Ensure providers are enabled\n';
                } else if (category === 'bootstrap_error' || category === 'initialization_error') {
                    helpfulMessage = '❌ **AI Service Initialization Failed**\n\n' +
                        'The AI service could not be initialized.\n\n' +
                        '🔧 **Fix:**\n' +
                        '1. Check that encryption key is set up: `modules/admin/setup-encryption-key.php`\n' +
                        '2. Verify providers are configured: `modules/ai-governance.php`\n' +
                        '3. Check server logs for details\n' +
                        (detail ? '\n**Error:** ' + detail + '\n' : '');
                } else if (msg.includes('No AI providers') || msg.includes('No providers configured')) {
                    helpfulMessage = '❌ **No AI Providers Configured**\n\n' +
                        'You need to set up at least one AI provider.\n\n' +
                        '🔧 **Quick Setup:**\n' +
                        '1. Go to: `modules/admin/configure-ai-keys.php`\n' +
                        '2. Click "Configure AI Providers"\n' +
                        '3. This will set up OpenAI and DeepSeek automatically\n\n' +
                        '📋 **Manual Setup:**\n' +
                        '1. Go to: `modules/ai-governance.php`\n' +
                        '2. Select a provider and enter API key\n' +
                        '3. Enable and save';
                }
                
                appendMessage('assistant', helpfulMessage);
                setLoading(false);
                return;
            }

            const assistantMessages = json.data?.messages || [];
            assistantMessages.forEach((message) => {
                appendMessage(message.role || 'assistant', message.content || '');
            });

            state.messages = payload.messages.concat(assistantMessages);
            setLoading(false);
        }
    }

    function resolveApiPath(dataAttr) {
        // If absolute URL provided, use it
        if (dataAttr && (dataAttr.startsWith('http://') || dataAttr.startsWith('https://'))) {
            return dataAttr;
        }
        
        // If absolute path provided, use it
        if (dataAttr && dataAttr.startsWith('/')) {
            return dataAttr;
        }
        
        // Use provided path or determine based on current location
        let relative = dataAttr;
        if (!relative) {
            // Determine if we're in a module directory
            const currentPath = window.location.pathname;
            if (currentPath.includes('/modules/')) {
                relative = '../api/ai-insights.php';
            } else {
                relative = 'api/ai-insights.php';
            }
        }
        
        // If relative path starts with ../, use it as-is (works from modules)
        if (relative.startsWith('../')) {
            return relative;
        }
        
        // If appRoot is '../', we're in a module, so prepend it to relative
        if (appRoot === '../') {
            return appRoot + relative;
        }
        
        // If appRoot is set and not '../', prepend it
        if (appRoot) {
            // Remove leading slash from relative
            const cleanRelative = relative.replace(/^\/*/, '');
            // Ensure appRoot ends with / if it's not ../
            const cleanAppRoot = appRoot.endsWith('/') ? appRoot : appRoot + '/';
            return cleanAppRoot + cleanRelative;
        }
        
        // Fallback: use relative path as-is
        return relative;
    }

    function renderMarkdown(text) {
        if (!text) return '';
        const escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        return escaped
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }
})();

