import { Controller } from "@hotwired/stimulus"

// Connects to data-controller="chat"
export default class extends Controller {
  static targets = ["messages", "input", "sendBtn"]

  connect() {
    this.scrollToBottom()
    this.inputTarget.focus()
    
    // Listen for Turbo Frame renders to trigger AI streaming
    document.addEventListener('turbo:frame-render', this.handleFrameRender.bind(this))
  }

  // Handle form submission
  send(event) {
    const input = this.inputTarget
    const btn = this.sendBtnTarget
    
    // Check if message is empty
    if (!input.value.trim()) {
      event.preventDefault()
      return
    }
    
    // Disable button and show sending state
    btn.disabled = true
    btn.textContent = 'Sending...'
    
    // Clear input after a brief delay to avoid race conditions
    setTimeout(() => {
      input.value = ''
    }, 100)
  }

  // Auto-scroll to bottom of messages
  scrollToBottom() {
    if (this.hasMessagesTarget) {
      this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight
    }
  }

  // Reset form after Turbo frame loads
  messagesTargetConnected() {
    this.scrollToBottom()
    this.resetSendButton()
  }

  // Reset send button state
  resetSendButton() {
    const btn = this.sendBtnTarget
    if (btn && btn.disabled) {
      btn.disabled = false
      btn.textContent = 'Send ✈️'
      
      // Refocus input for better UX
      this.inputTarget.focus()
    }
  }

  // Handle Turbo events
  turboFrameLoad(event) {
    this.scrollToBottom()
    this.resetSendButton()
  }

  // Handle Enter key submission
  keydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      
      // Use requestSubmit for better form handling
      const form = this.element.querySelector('form')
      if (form) {
        form.requestSubmit()
      }
    }
  }

  // Smooth scroll when new messages appear
  messageAdded() {
    // Use smooth scrolling
    if (this.hasMessagesTarget) {
      this.messagesTarget.scrollTo({
        top: this.messagesTarget.scrollHeight,
        behavior: 'smooth'
      })
    }
  }

  // Handle Turbo Frame render events to trigger AI streaming
  handleFrameRender(event) {
    // Only handle renders for our messages frame
    if (event.target.id === 'messages-container') {
      this.checkForAITrigger()
    }
  }

  // Check for AI trigger and start streaming if found
  checkForAITrigger() {
    const trigger = document.querySelector('[data-ai-trigger]')
    if (trigger) {
      const messageId = trigger.dataset.aiTrigger
      const streamUrl = trigger.dataset.streamUrl
      const fallbackUrl = trigger.dataset.fallbackUrl
      
      // Remove trigger to prevent double-triggering
      trigger.remove()
      
      // Initialize AI streaming
      if (window.ChatStreaming && streamUrl && fallbackUrl) {
        const streaming = new window.ChatStreaming(streamUrl, fallbackUrl)
        streaming.initializeStreaming()
      }
    }
  }

  // Disconnect cleanup
  disconnect() {
    // Clean up event listeners
    document.removeEventListener('turbo:frame-render', this.handleFrameRender.bind(this))
  }
}