<!-- Chat Assistant Modal -->
<div id="chatAssistantModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Chat Assistant</h3>
            <span class="close-modal">&times;</span>
        </div>
        
        <div class="chat-container">
            <div class="chat-main">
                <div class="chat-messages" id="chatMessages">
                    <div class="message assistant-message">
                        <div class="message-content">
                            Hello! I'm your ShaSha assistant. How can I help you today? You can ask me questions about:
                            <ul>
                                <li>Posting and managing jobs</li>
                                <li>Handling applications</li>
                                <li>Company verification</li>
                                <li>Profile management</li>
                                <li>Or submit queries to admin</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input">
                    <input type="text" id="messageInput" placeholder="Type your message here...">
                    <button onclick="sendMessage()">Send</button>
                </div>
            </div>
            
            <div class="chat-sidebar">
                <div class="chat-header">
                    <h3>Submit Query to Admin</h3>
                </div>
                
                <form id="queryForm" class="query-form">
                    <div class="form-group">
                        <label for="query_type">Query Type</label>
                        <select id="query_type" name="query_type" required>
                            <option value="">Select query type</option>
                            <option value="new_category">Add New Job Category</option>
                            <option value="technical_issue">Technical Issue</option>
                            <option value="account_support">Account Support</option>
                            <option value="feature_request">Feature Request</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="query_text">Your Query</label>
                        <textarea id="query_text" name="query_text" required placeholder="Describe your query in detail..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Submit Query</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    position: relative;
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    width: 90%;
    max-width: 1200px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.modal-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close-modal {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-modal:hover {
    color: #555;
}

/* Chat styles */
.chat-container {
    display: flex;
    gap: 20px;
    padding: 20px;
    height: 80vh;
    max-height: 700px;
}

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 12px;
    border: 1px solid #dee2e6;
}

.chat-sidebar {
    width: 300px;
    background: white;
    border-radius: 12px;
    border: 1px solid #dee2e6;
    padding: 15px;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.chat-input {
    padding: 15px;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 10px;
}

.chat-input input {
    flex: 1;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.chat-input button {
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.message {
    margin-bottom: 15px;
    max-width: 80%;
}

.user-message {
    margin-left: auto;
}

.assistant-message {
    margin-right: auto;
}

.message-content {
    padding: 12px 15px;
    border-radius: 12px;
    background: #f8f9fa;
}

.user-message .message-content {
    background: #007bff;
    color: white;
}

/* Form styles */
.query-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group select,
.form-group textarea {
    padding: 8px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.form-group textarea {
    height: 100px;
    resize: vertical;
}

.btn-submit {
    padding: 10px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.btn-submit:hover {
    background: #218838;
}
</style>

<script>
// Load chat history when modal opens
function loadChatHistory() {
    fetch('<?php echo SITE_URL; ?>/api/chat-history.php')
        .then(response => response.json())
        .then(data => {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = ''; // Clear existing messages
            
            data.forEach(chat => {
                addMessage(chat.message, chat.is_assistant ? 'assistant' : 'user');
            });
        })
        .catch(error => console.error('Error loading chat history:', error));
}

function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if(message) {
        // Add user message to UI
        addMessage(message, 'user');
        
        // Clear input
        input.value = '';
        
        // Send message to server
        fetch('<?php echo SITE_URL; ?>/api/chat-message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                addMessage(data.response, 'assistant');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            addMessage('Sorry, there was an error processing your message. Please try again.', 'assistant');
        });
    }
}

function addMessage(text, sender) {
    const messagesDiv = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}-message`;
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.textContent = text;
    
    messageDiv.appendChild(contentDiv);
    messagesDiv.appendChild(messageDiv);
    
    // Scroll to bottom
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

// Handle query form submission
document.getElementById('queryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?php echo SITE_URL; ?>/api/submit-query.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('Query submitted successfully!');
            this.reset();
        } else {
            alert('Error submitting query. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting query. Please try again.');
    });
});

// Allow sending message with Enter key
document.getElementById('messageInput').addEventListener('keypress', function(e) {
    if(e.key === 'Enter') {
        sendMessage();
    }
});
</script> 