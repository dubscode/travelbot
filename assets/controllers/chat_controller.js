import { Controller } from "@hotwired/stimulus"

// Connects to data-controller="chat"
export default class extends Controller {
  static targets = ["messages", "input", "sendBtn"]

  connect() {
    this.scrollToBottom()
    this.inputTarget.focus()
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

  // Disconnect cleanup
  disconnect() {
    // Clean up any event listeners if needed
  }
}