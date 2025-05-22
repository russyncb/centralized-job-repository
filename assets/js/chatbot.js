class AdvancedChatbot {
    constructor() {
        this.messages = [];
        this.isOpen = false;
        this.conversationHistory = [];
        this.userContext = {};
        this.isTyping = false;
        this.sessionId = this.generateSessionId();
        this.userType = null; // 'seeker' or 'employer'
        this.lastInteraction = Date.now();
        
        // Load conversation history from localStorage
        this.loadConversationHistory();
        this.detectUserType();
    }

    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    detectUserType() {
        // Detect user type from page URL or user data
        const currentPath = window.location.pathname;
        if (currentPath.includes('employer') || currentPath.includes('company')) {
            this.userType = 'employer';
        } else if (currentPath.includes('seeker') || currentPath.includes('applicant')) {
            this.userType = 'seeker';
        } else {
            // Try to detect from localStorage or session data
            const userData = localStorage.getItem('userData');
            if (userData) {
                const parsed = JSON.parse(userData);
                this.userType = parsed.type || 'seeker';
            } else {
                this.userType = 'seeker'; // default
            }
        }
    }

    init() {
        // Create enhanced chatbot HTML
        const chatbotHTML = `
            <div class="chatbot-container">
                <div class="chatbot-icon" id="chatbot-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <div class="chatbot-notification" id="chatbot-notification" style="display: none;">1</div>
                </div>
                <div class="chatbot-window" id="chatbot-window">
                    <div class="chatbot-header">
                        <div class="chatbot-title">
                            <div class="chatbot-avatar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M12 1v6m0 6v6"></path>
                                    <path d="m15.5 8.5 4.24-4.24M4.26 19.74l4.24-4.24m0-7L4.26 4.26M19.74 19.74l-4.24-4.24"></path>
                                </svg>
                            </div>
                            <div>
                                <h3>ShaSha AI Assistant</h3>
                                <div class="chatbot-status" id="chatbot-status">Online & Ready to Help</div>
                            </div>
                        </div>
                        <button class="close-btn" id="chatbot-close">×</button>
                    </div>
                    <div class="chatbot-messages" id="chatbot-messages"></div>
                    <div class="chatbot-input">
                        <div class="input-wrapper">
                            <input type="text" id="chatbot-input" placeholder="Ask me anything about ShaSha CJRS...">
                        </div>
                        <button id="chatbot-send" disabled>
                            <svg class="send-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22,2 15,22 11,13 2,9"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Remove existing chatbot
        const existingChatbot = document.querySelector('.chatbot-container');
        if (existingChatbot) {
            existingChatbot.remove();
        }

        // Add enhanced chatbot to page
        document.body.insertAdjacentHTML('beforeend', chatbotHTML);

        // Setup event listeners
        this.setupEventListeners();

        // Show welcome message based on user type
        this.showWelcomeMessage();

        // Setup auto-suggestions
        this.setupAutoSuggestions();
    }

    setupEventListeners() {
        const icon = document.getElementById('chatbot-icon');
        const closeBtn = document.getElementById('chatbot-close');
        const sendBtn = document.getElementById('chatbot-send');
        const input = document.getElementById('chatbot-input');

        icon.addEventListener('click', () => this.toggleChat());
        closeBtn.addEventListener('click', () => this.toggleChat());
        
        sendBtn.addEventListener('click', () => this.handleUserInput());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !sendBtn.disabled) {
                this.handleUserInput();
            }
        });

        input.addEventListener('input', (e) => {
            const hasValue = e.target.value.trim().length > 0;
            sendBtn.disabled = !hasValue;
        });

        // Setup click handlers for quick replies
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-reply')) {
                this.handleQuickReply(e.target.textContent);
            }
            if (e.target.classList.contains('action-btn')) {
                this.handleActionButton(e.target);
            }
        });
    }

    toggleChat() {
        const window = document.getElementById('chatbot-window');
        const notification = document.getElementById('chatbot-notification');
        
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            window.style.display = 'flex';
            setTimeout(() => window.classList.add('show'), 10);
            document.getElementById('chatbot-input').focus();
            notification.style.display = 'none';
        } else {
            window.classList.remove('show');
            setTimeout(() => window.style.display = 'none', 300);
        }
    }

    showWelcomeMessage() {
        const welcomeMessages = {
            employer: {
                text: `Welcome! I'm your ShaSha AI Assistant for employers. I can help you with:`,
                quickReplies: [
                    'Post a new job',
                    'Manage applications',
                    'View analytics',
                    'Search candidates'
                ]
            },
            seeker: {
                text: `Hi there! I'm your ShaSha AI Assistant. I'm here to help you with your job search journey:`,
                quickReplies: [
                    'Find jobs for me',
                    'Update my profile',
                    'Application status',
                    'Interview tips'
                ]
            }
        };

        const message = welcomeMessages[this.userType] || welcomeMessages.seeker;
        
        setTimeout(() => {
            this.addMessage('bot', message.text, {
                quickReplies: message.quickReplies,
                showTime: true
            });
        }, 500);
    }

    showTypingIndicator() {
        const messagesDiv = document.getElementById('chatbot-messages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.id = 'typing-indicator';
        typingDiv.innerHTML = `
            <div class="typing-dots">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
            <span style="color: #64748b; font-size: 0.85rem;">AI Assistant is thinking...</span>
        `;
        
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        this.isTyping = true;
    }

    hideTypingIndicator() {
        const typingDiv = document.getElementById('typing-indicator');
        if (typingDiv) {
            typingDiv.remove();
        }
        this.isTyping = false;
    }

    addMessage(sender, text, options = {}) {
        const messagesDiv = document.getElementById('chatbot-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        
        const messageTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        let messageHTML = `<div class="message-bubble">${text}</div>`;
        
        if (options.showTime) {
            messageHTML += `<div class="message-time">${messageTime}</div>`;
        }

        if (options.quickReplies && options.quickReplies.length > 0) {
            const repliesHTML = options.quickReplies.map(reply => 
                `<span class="quick-reply" data-reply="${reply}">${reply}</span>`
            ).join('');
            messageHTML += `<div class="quick-replies">${repliesHTML}</div>`;
        }

        if (options.actions && options.actions.length > 0) {
            const actionsHTML = options.actions.map(action => 
                `<button class="action-btn ${action.type || ''}" data-action="${action.action}">${action.text}</button>`
            ).join('');
            messageHTML += `<div class="message-actions">${actionsHTML}</div>`;
        }

        if (options.systemInsight) {
            messageHTML += `
                <div class="system-insight">
                    <div class="insight-header">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6"></path>
                            <path d="m15.5 8.5 4.24-4.24M4.26 19.74l4.24-4.24m0-7L4.26 4.26M19.74 19.74l-4.24-4.24"></path>
                        </svg>
                        System Insight
                    </div>
                    <div class="insight-content">${options.systemInsight}</div>
                </div>
            `;
        }

        messageDiv.innerHTML = messageHTML;
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        // Store message in conversation history
        this.conversationHistory.push({
            sender,
            text,
            timestamp: Date.now(),
            options
        });

        this.saveConversationHistory();
    }

    async handleUserInput() {
        const input = document.getElementById('chatbot-input');
        const text = input.value.trim();
        
        if (text && !this.isTyping) {
            this.addMessage('user', text, { showTime: true });
            input.value = '';
            document.getElementById('chatbot-send').disabled = true;
            
            this.showTypingIndicator();
            
            try {
                const response = await this.processUserInput(text);
                
                // Simulate realistic response time
                await new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 2000));
                
                this.hideTypingIndicator();
                this.addMessage('bot', response.text, response.options || {});
                
            } catch (error) {
                this.hideTypingIndicator();
                this.addMessage('bot', 'I apologize, but I encountered an error. Please try again or contact support if the issue persists.', {
                    showTime: true
                });
            }
        }
    }

    handleQuickReply(replyText) {
        const input = document.getElementById('chatbot-input');
        input.value = replyText;
        this.handleUserInput();
    }

    handleActionButton(button) {
        const action = button.dataset.action;
        
        switch(action) {
            case 'viewJobs':
                window.location.href = '/search-jobs.php';
                break;
            case 'viewApplications':
                window.location.href = '/my-applications.php';
                break;
            case 'updateProfile':
                window.location.href = '/profile.php';
                break;
            case 'postJob':
                window.location.href = '/post-job.php';
                break;
            case 'viewAnalytics':
                window.location.href = '/analytics.php';
                break;
            default:
                this.addMessage('bot', `Action "${action}" will be implemented in the next update.`);
        }
    }

    async processUserInput(text) {
        try {
            const response = await fetch('assets/php/chatbot-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: text,
                    userType: this.userType,
                    sessionId: this.sessionId,
                    conversationHistory: this.conversationHistory.slice(-5) // Last 5 messages for context
                })
            });

            const result = await response.json();
            
            if (result.success) {
                return result.response;
            } else {
                throw new Error(result.error || 'Unknown error');
            }
        } catch (error) {
            console.error('Chatbot error:', error);
            return this.getFallbackResponse(text);
        }
    }

    getFallbackResponse(text) {
        const lowerText = text.toLowerCase();
        
        // Context-aware responses based on user type
        if (this.userType === 'employer') {
            return this.getEmployerFallbackResponse(lowerText);
        } else {
            return this.getSeekerFallbackResponse(lowerText);
        }
    }

    getEmployerFallbackResponse(lowerText) {
        if (lowerText.includes('post') && lowerText.includes('job')) {
            return {
                text: 'To post a new job, go to the Job Posting section in your dashboard. Make sure to include detailed job requirements and competitive compensation.',
                options: {
                    actions: [
                        { text: 'Post New Job', action: 'postJob', type: 'primary' }
                    ],
                    systemInsight: 'Tip: Jobs with detailed descriptions get 3x more quality applications.'
                }
            };
        }

        if (lowerText.includes('application') || lowerText.includes('candidate')) {
            return {
                text: 'You can manage all job applications in your Applications Management panel. Filter by status, skills, or experience level.',
                options: {
                    actions: [
                        { text: 'View Applications', action: 'viewApplications' }
                    ]
                }
            };
        }

        if (lowerText.includes('analytic') || lowerText.includes('report') || lowerText.includes('insight')) {
            return {
                text: 'Check your recruitment analytics to see job posting performance, application rates, and hiring funnel insights.',
                options: {
                    actions: [
                        { text: 'View Analytics', action: 'viewAnalytics' }
                    ],
                    systemInsight: 'Your job postings have received 23% more views this month compared to last month.'
                }
            };
        }

        return {
            text: 'As an employer, I can help you with job posting, application management, candidate screening, and recruitment analytics. What would you like to focus on?',
            options: {
                quickReplies: ['Post a job', 'Review applications', 'View analytics', 'Search candidates']
            }
        };
    }

    getSeekerFallbackResponse(lowerText) {
        if (lowerText.includes('job') && (lowerText.includes('search') || lowerText.includes('find'))) {
            return {
                text: 'I can help you find jobs that match your skills and preferences. Use our advanced search filters for location, salary, job type, and industry.',
                options: {
                    actions: [
                        { text: 'Search Jobs', action: 'viewJobs', type: 'primary' }
                    ],
                    systemInsight: 'Based on your profile, I found 12 new jobs that match your criteria this week.'
                }
            };
        }

        if (lowerText.includes('application') || lowerText.includes('status')) {
            return {
                text: 'Track all your job applications in one place. See application status, employer responses, and interview schedules.',
                options: {
                    actions: [
                        { text: 'My Applications', action: 'viewApplications' }
                    ]
                }
            };
        }

        if (lowerText.includes('profile') || lowerText.includes('resume')) {
            return {
                text: 'Keep your profile updated to attract better job opportunities. Add your latest experience, skills, and achievements.',
                options: {
                    actions: [
                        { text: 'Update Profile', action: 'updateProfile' }
                    ],
                    systemInsight: 'Profiles with recent updates get 40% more employer views.'
                }
            };
        }

        if (lowerText.includes('interview') || lowerText.includes('tip')) {
            return {
                text: 'Here are some interview tips: Research the company, prepare STAR method examples, dress appropriately, and prepare thoughtful questions.',
                options: {
                    quickReplies: ['Common interview questions', 'Salary negotiation', 'Follow-up tips']
                }
            };
        }

        return {
            text: 'I\'m here to help with your job search journey! I can assist with finding jobs, application tracking, profile optimization, and career advice.',
            options: {
                quickReplies: ['Find jobs', 'Application status', 'Profile tips', 'Interview help']
            }
        };
    }

    setupAutoSuggestions() {
        const input = document.getElementById('chatbot-input');
        
        const suggestions = {
            employer: [
                'How to post a job?',
                'View application analytics',
                'Best practices for job descriptions',
                'How to screen candidates?'
            ],
            seeker: [
                'Find remote jobs',
                'Update my resume',
                'Interview preparation tips',
                'Salary negotiation advice'
            ]
        };

        // You can implement autocomplete functionality here
    }

    loadConversationHistory() {
        try {
            const history = localStorage.getItem(`chatbot_history_${this.sessionId}`);
            if (history) {
                this.conversationHistory = JSON.parse(history);
            }
        } catch (error) {
            console.error('Error loading conversation history:', error);
        }
    }

    saveConversationHistory() {
        try {
            localStorage.setItem(`chatbot_history_${this.sessionId}`, JSON.stringify(this.conversationHistory));
        } catch (error) {
            console.error('Error saving conversation history:', error);
        }
    }

    showNotification() {
        if (!this.isOpen) {
            const notification = document.getElementById('chatbot-notification');
            notification.style.display = 'flex';
        }
    }
}

// Initialize enhanced chatbot when document is ready
document.addEventListener('DOMContentLoaded', () => {
    const chatbot = new AdvancedChatbot();
    chatbot.init();
    
    // Make chatbot globally accessible for debugging
    window.chatbot = chatbot;
});