class Chatbot {
    constructor() {
        this.messages = [];
        this.isOpen = false;
    }

    init() {
        // Create chatbot HTML
        const chatbotHTML = `
            <div class="chatbot-container">
                <div class="chatbot-icon" id="chatbot-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <div class="chatbot-window" id="chatbot-window">
                    <div class="chatbot-header">
                        <h3>ShaSha Assistant</h3>
                        <button class="close-btn" id="chatbot-close">×</button>
                    </div>
                    <div class="chatbot-messages" id="chatbot-messages"></div>
                    <div class="chatbot-input">
                        <input type="text" id="chatbot-input" placeholder="Type your message...">
                        <button id="chatbot-send">Send</button>
                    </div>
                </div>
            </div>
        `;

        // Remove existing chatbot if present
        const existingChatbot = document.querySelector('.chatbot-container');
        if (existingChatbot) {
            existingChatbot.remove();
        }

        // Add chatbot to page
        document.body.insertAdjacentHTML('beforeend', chatbotHTML);

        // Add event listeners
        this.setupEventListeners();

        // Add welcome message
        this.addMessage('bot', 'Hello! I\'m your ShaSha assistant. How can I help you today?');
    }

    setupEventListeners() {
        const icon = document.getElementById('chatbot-icon');
        const closeBtn = document.getElementById('chatbot-close');
        const sendBtn = document.getElementById('chatbot-send');
        const input = document.getElementById('chatbot-input');
        const window = document.getElementById('chatbot-window');

        icon.addEventListener('click', () => this.toggleChat());
        closeBtn.addEventListener('click', () => this.toggleChat());
        
        sendBtn.addEventListener('click', () => this.handleUserInput());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleUserInput();
            }
        });
    }

    toggleChat() {
        const window = document.getElementById('chatbot-window');
        this.isOpen = !this.isOpen;
        window.style.display = this.isOpen ? 'flex' : 'none';
        if (this.isOpen) {
            document.getElementById('chatbot-input').focus();
        }
    }

    addMessage(sender, text) {
        const messagesDiv = document.getElementById('chatbot-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        messageDiv.textContent = text;
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        this.messages.push({ sender, text });
    }

    async handleUserInput() {
        const input = document.getElementById('chatbot-input');
        const text = input.value.trim();
        
        if (text) {
            this.addMessage('user', text);
            input.value = '';
            
            // Process user input and get response
            const response = await this.processUserInput(text);
            this.addMessage('bot', response);
        }
    }

    async processUserInput(text) {
        // Simple response logic - can be expanded
        const lowerText = text.toLowerCase();
        
        // Job search related
        if (lowerText.includes('job') && (lowerText.includes('search') || lowerText.includes('find') || lowerText.includes('look'))) {
            return 'You can search for jobs by clicking on "Search Jobs" in the sidebar. You can filter by location, job type, and category.';
        }
        
        // Application related
        if (lowerText.includes('application') || lowerText.includes('apply')) {
            return 'You can view all your job applications in the "My Applications" section. To apply for a new job, find an interesting position in the job search and click "Apply Now".';
        }
        
        // Profile related
        if (lowerText.includes('profile') || lowerText.includes('resume')) {
            return 'You can update your profile information, including your resume and professional headline, in the "My Profile" section.';
        }
        
        // Saved jobs related
        if (lowerText.includes('save') && lowerText.includes('job')) {
            return 'You can save interesting jobs by clicking the "Save Job" button. View all your saved jobs in the "Saved Jobs" section.';
        }

        // Default response
        return 'I\'m here to help you with job searching, applications, and profile management. What would you like to know more about?';
    }
}

// Initialize chatbot when document is ready
document.addEventListener('DOMContentLoaded', () => {
    const chatbot = new Chatbot();
    chatbot.init();
});