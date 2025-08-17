/**
 * Chat streaming functionality for real-time AI responses
 */

export class ChatStreaming {
    constructor(streamUrl, fallbackUrl) {
        this.streamUrl = streamUrl;
        this.fallbackUrl = fallbackUrl;
        this.eventSource = null;
        this.rawText = ''; // Accumulate raw text for proper markdown processing
    }

    /**
     * Initialize streaming for a new AI response
     */
    initializeStreaming() {
        // Reset accumulated text for new conversation
        this.rawText = '';
        
        setTimeout(() => {
            this.showTypingIndicator();
            this.createStreamingMessage();
            this.setupEventSource();
        }, 100);
    }

    /**
     * Show typing indicator
     */
    showTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.style.display = 'flex';
        }
        
        // Scroll to show typing indicator
        const container = document.querySelector('.chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    /**
     * Create streaming message bubble with typing animation
     */
    createStreamingMessage() {
        const currentFrame = document.querySelector('turbo-frame[id="messages-container"]');
        if (!currentFrame) return;

        // Remove any existing streaming message first
        const existingStreamingMessage = document.getElementById('streaming-message');
        if (existingStreamingMessage) {
            existingStreamingMessage.remove();
        }

        const aiMessageHtml = `
            <article class="grid grid-cols-[auto_1fr] gap-3 items-start max-w-[85%] justify-self-start" id="streaming-message">
                <div class="w-9 h-9 grid place-items-center bg-blue-500 text-white rounded-full shadow-lg text-sm font-semibold flex-shrink-0">🤖</div>
                <div class="p-3.5 rounded-xl shadow-lg bg-white dark:bg-gray-800 border border-gray-200/20 dark:border-gray-600/20 text-gray-900 dark:text-gray-100 relative" id="streaming-content">
                    <span class="typing-dots-inline">
                        <span class="dot">●</span>
                        <span class="dot">●</span>
                        <span class="dot">●</span>
                    </span>
                    <span id="cursor" style="display: none;">▋</span>
                </div>
            </article>
        `;
        
        // Use insertAdjacentHTML to properly append without overwriting
        currentFrame.insertAdjacentHTML('beforeend', aiMessageHtml);
        
        this.addTypingAnimationCSS();
        this.scrollToBottom();
    }

    /**
     * Add CSS for typing animation if not already present
     */
    addTypingAnimationCSS() {
        if (document.getElementById('typing-animation-style')) return;

        const style = document.createElement('style');
        style.id = 'typing-animation-style';
        style.textContent = `
            .typing-dots-inline {
                display: inline-block;
                color: #666;
            }
            .typing-dots-inline .dot {
                animation: typing-pulse 1.4s infinite;
                opacity: 0.4;
            }
            .typing-dots-inline .dot:nth-child(1) { animation-delay: 0s; }
            .typing-dots-inline .dot:nth-child(2) { animation-delay: 0.2s; }
            .typing-dots-inline .dot:nth-child(3) { animation-delay: 0.4s; }
            @keyframes typing-pulse {
                0%, 60%, 100% { opacity: 0.4; }
                30% { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Setup EventSource for streaming
     */
    setupEventSource() {
        this.eventSource = new EventSource(this.streamUrl);
        
        this.eventSource.addEventListener('ready', () => {
            // Keep showing typing animation until first token
        });
        
        this.eventSource.addEventListener('start', () => {
            const typingIndicator = document.getElementById('typing-indicator');
            if (typingIndicator) {
                typingIndicator.style.display = 'none';
            }
        });
        
        this.eventSource.addEventListener('token', (event) => {
            this.handleToken(event);
        });
        
        this.eventSource.addEventListener('complete', () => {
            this.handleComplete();
        });
        
        this.eventSource.addEventListener('error', () => {
            this.handleError();
        });
        
        this.eventSource.onerror = () => {
            this.handleFallback();
        };
        
        // Add timeout protection for EventSource
        this.setupEventSourceTimeout();
    }

    /**
     * Format text for display with comprehensive markdown support
     */
    formatText(text) {
        // Escape HTML entities for security
        const escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        
        let formatted = escaped;
        
        // Headers - process BEFORE converting newlines to <br>
        formatted = formatted.replace(/^### (.*?)$/gm, '<h3 style="font-size: 1.25rem; font-weight: bold; margin: 0.5rem 0;">$1</h3>');
        formatted = formatted.replace(/^## (.*?)$/gm, '<h2 style="font-size: 1.5rem; font-weight: bold; margin: 0.75rem 0;">$1</h2>');
        formatted = formatted.replace(/^# (.*?)$/gm, '<h1 style="font-size: 1.75rem; font-weight: bold; margin: 1rem 0;">$1</h1>');
        
        // Convert newlines to <br> tags (matches Twig's nl2br filter)
        formatted = formatted.replace(/\n/g, '<br>');
        
        // Bold: **text** -> <strong>text</strong>
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic: *text* -> <em>text</em> (but not inside ** bold text)
        formatted = formatted.replace(/(?<!\*)\*([^*<>]+?)\*(?!\*)/g, '<em>$1</em>');
        
        // Code: `code` -> <code>code</code>
        formatted = formatted.replace(/`([^`]+)`/g, '<code style="background-color: #f3f4f6; padding: 0.125rem 0.25rem; border-radius: 0.25rem; font-family: monospace;">$1</code>');
        
        // Unordered lists: - item or * item
        formatted = formatted.replace(/^[-*] (.*?)(<br>|$)/gm, '<li style="margin-left: 1rem;">$1</li>$2');
        
        // Ordered lists: 1. item, 2. item etc.
        formatted = formatted.replace(/^\d+\. (.*?)(<br>|$)/gm, '<li style="margin-left: 1rem; list-style-type: decimal;">$1</li>$2');
        
        // Links: [text](url) -> <a href="url">text</a>
        formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" style="color: #3b82f6; text-decoration: underline;">$1</a>');
        
        // Wrap consecutive list items in ul/ol tags
        formatted = formatted.replace(/(<li[^>]*>.*?<\/li>(<br>)?)+/g, (match) => {
            if (match.includes('list-style-type: decimal')) {
                return `<ol style="margin: 0.5rem 0;">${match.replace(/<br>/g, '')}</ol>`;
            } else {
                return `<ul style="margin: 0.5rem 0; list-style-type: disc;">${match.replace(/<br>/g, '')}</ul>`;
            }
        });
        
        return formatted;
    }

    /**
     * Handle incoming token
     */
    handleToken(event) {
        const data = JSON.parse(event.data);
        const contentElement = document.getElementById('streaming-content');
        let cursor = document.getElementById('cursor');
        const typingDots = contentElement.querySelector('.typing-dots-inline');
        
        if (!contentElement) return;

        // On first token, switch from typing animation to streaming cursor
        if (typingDots) {
            typingDots.remove();
            if (cursor) cursor.style.display = 'inline';
        }
        
        // Accumulate raw text for proper markdown processing
        this.rawText += data.text;
        
        // Format the entire accumulated text
        const formattedText = this.formatText(this.rawText);
        
        // Clear content and add the newly formatted text
        const existingContent = contentElement.innerHTML;
        const cursorPattern = /<span[^>]*id="cursor"[^>]*>.*?<\/span>/;
        const contentWithoutCursor = existingContent.replace(cursorPattern, '');
        
        // Replace content with formatted text
        contentElement.innerHTML = formattedText;
        
        // Add cursor back at the end
        const cursor2 = document.createElement('span');
        cursor2.id = 'cursor';
        cursor2.textContent = '▋';
        cursor2.style.display = 'inline';
        contentElement.appendChild(cursor2);
        
        this.scrollToBottom();
    }

    /**
     * Handle completion of streaming
     */
    handleComplete() {
        // Clear timeout
        this.clearEventSourceTimeout();
        
        // Remove cursor
        const cursor = document.getElementById('cursor');
        if (cursor) {
            cursor.remove();
        }
        
        // Add model indicator
        const contentElement = document.getElementById('streaming-content');
        if (contentElement) {
            const modelInfo = document.createElement('div');
            modelInfo.style.cssText = 'font-size: 12px; opacity: 0.7; margin-top: 8px;';
            modelInfo.innerHTML = '🧠 Detailed response';
            contentElement.appendChild(modelInfo);
        }
        
        // Refresh sidebar to show updated conversation title
        this.refreshSidebar();
        
        // Close connection and re-enable form
        this.eventSource.close();
        this.enableForm();
        this.scrollToBottom();
    }

    /**
     * Handle streaming error
     */
    handleError() {
        // Clear timeout
        this.clearEventSourceTimeout();
        
        // Remove streaming elements
        const streamingMessage = document.getElementById('streaming-message');
        if (streamingMessage) {
            streamingMessage.remove();
        }
        
        // Hide typing indicator
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.style.display = 'none';
        }
        
        // Show error message
        this.showErrorMessage();
        
        // Close connection and re-enable form
        this.eventSource.close();
        this.enableForm();
    }

    /**
     * Handle fallback when EventSource fails
     */
    handleFallback() {
        // Clear timeout
        this.clearEventSourceTimeout();
        
        this.eventSource.close();
        
        // Fallback to non-streaming approach
        fetch(this.fallbackUrl, {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            // Replace the entire frame with fallback response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newFrame = doc.querySelector('turbo-frame[id="messages-container"]');
            const currentFrame = document.querySelector('turbo-frame[id="messages-container"]');
            if (newFrame && currentFrame) {
                currentFrame.innerHTML = newFrame.innerHTML;
            }
        })
        .catch(error => {
            console.error('Fallback also failed:', error);
            this.showErrorMessage();
        })
        .finally(() => {
            this.hideTypingIndicator();
            this.enableForm();
            this.scrollToBottom();
        });
    }

    /**
     * Show error message
     */
    showErrorMessage() {
        const currentFrame = document.querySelector('turbo-frame[id="messages-container"]');
        if (!currentFrame) return;

        const errorHtml = `
            <article class="grid grid-cols-[auto_1fr] gap-3 items-start max-w-[85%] justify-self-start">
                <div class="w-9 h-9 grid place-items-center bg-blue-500 text-white rounded-full shadow-lg text-sm font-semibold flex-shrink-0">🤖</div>
                <div class="p-3.5 rounded-xl shadow-lg bg-white dark:bg-gray-800 border border-gray-200/20 dark:border-gray-600/20 text-gray-900 dark:text-gray-100 relative">
                    <div class="text-[15px] leading-6">Sorry, I encountered an error processing your request. Please try again.</div>
                </div>
            </article>
        `;
        currentFrame.innerHTML += errorHtml;
    }

    /**
     * Hide typing indicator
     */
    hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.style.display = 'none';
        }
    }

    /**
     * Setup timeout protection for EventSource
     */
    setupEventSourceTimeout() {
        // Set a timeout to handle cases where EventSource hangs
        this.eventSourceTimeout = setTimeout(() => {
            if (this.eventSource && this.eventSource.readyState !== EventSource.CLOSED) {
                console.warn('EventSource timeout, falling back to regular request');
                this.handleFallback();
            }
        }, 30000); // 30 second timeout
    }

    /**
     * Clear EventSource timeout
     */
    clearEventSourceTimeout() {
        if (this.eventSourceTimeout) {
            clearTimeout(this.eventSourceTimeout);
            this.eventSourceTimeout = null;
        }
    }

    /**
     * Re-enable form
     */
    enableForm() {
        const btn = document.getElementById('send-btn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Send ✈️';
        }
    }

    /**
     * Refresh sidebar to show updated conversation title
     */
    refreshSidebar() {
        // Extract current conversation ID from URL
        const currentPath = window.location.pathname;
        const conversationMatch = currentPath.match(/\/chat\/conversation\/([a-f0-9-]+)/);
        const conversationId = conversationMatch ? conversationMatch[1] : null;
        
        // Build sidebar refresh URL
        const sidebarUrl = conversationId 
            ? `/chat/sidebar?currentConversationId=${conversationId}`
            : '/chat/sidebar';
        
        // Fetch and update sidebar
        fetch(sidebarUrl, {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newSidebar = doc.querySelector('turbo-frame[id="sidebar-conversations"]');
            const currentSidebar = document.querySelector('turbo-frame[id="sidebar-conversations"]');
            
            if (newSidebar && currentSidebar) {
                currentSidebar.innerHTML = newSidebar.innerHTML;
            }
        })
        .catch(error => {
            console.error('Failed to refresh sidebar:', error);
        });
    }

    /**
     * Scroll to bottom of chat container
     */
    scrollToBottom() {
        const container = document.querySelector('.chat-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
}

// Make available globally for template usage
window.ChatStreaming = ChatStreaming;