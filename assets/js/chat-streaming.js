/**
 * Chat streaming functionality for real-time AI responses
 */

export class ChatStreaming {
    constructor(streamUrl, fallbackUrl) {
        this.streamUrl = streamUrl;
        this.fallbackUrl = fallbackUrl;
        this.eventSource = null;
    }

    /**
     * Initialize streaming for a new AI response
     */
    initializeStreaming() {
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
        
        // Remove cursor temporarily to add token, but keep reference
        let cursorWasVisible = false;
        if (cursor && cursor.parentNode) {
            cursorWasVisible = true;
            cursor.remove();
        }
        
        // Create text node and append to existing content
        const tokenSpan = document.createElement('span');
        tokenSpan.textContent = data.text;
        contentElement.appendChild(tokenSpan);
        
        // Re-create and add cursor back if it was visible
        if (cursorWasVisible) {
            cursor = document.createElement('span');
            cursor.id = 'cursor';
            cursor.textContent = '▋';
            cursor.style.display = 'inline';
            contentElement.appendChild(cursor);
        }
        
        this.scrollToBottom();
    }

    /**
     * Handle completion of streaming
     */
    handleComplete() {
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
        
        // Close connection and re-enable form
        this.eventSource.close();
        this.enableForm();
        this.scrollToBottom();
    }

    /**
     * Handle streaming error
     */
    handleError() {
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
     * Re-enable form
     */
    enableForm() {
        const btn = document.getElementById('send-btn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Send 📨';
        }
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