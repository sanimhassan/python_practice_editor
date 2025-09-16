// Main application controller and orchestrator

// Global variable to track currently loaded code for update functionality
let currentlyLoadedCode = null;

document.addEventListener('DOMContentLoaded', async () => {
    console.log("App controller initialized.");

    // Wait for scripts to load
    await new Promise(resolve => setTimeout(resolve, 1500));

    

    // The hybrid engine initializes itself, just check if it's ready
    if (typeof window.hybridPythonEngine !== 'undefined') {
        console.log("Hybrid Python engine is available");
    } else if (typeof window.pythonEngine !== 'undefined') {
        // Fallback to legacy engine
        try {
            await window.pythonEngine.initPyodide();
            console.log("Legacy Pyodide initialization completed");
        } catch (error) {
            console.error("Failed to initialize Pyodide:", error);
            if (typeof showToast === 'function') {
                showToast('Python engine initialization failed', 'error');
            }
        }
    }

    // Initialize modules (order might matter for dependencies)
    // uiHelpers, editor, and readme modules do not have an explicit init function exported.
    // Their DOMContentLoaded listeners handle their initialization.
    if (typeof window.theme !== 'undefined') window.theme.init();
    
    // Initialize auth and wait for it to complete
    let authInitialized = false;
    if (typeof window.auth !== 'undefined') {
        try {
            authInitialized = await window.auth.init();
        } catch (e) {
            console.error("Auth initialization error:", e);
        }
    }

    // Set up initial UI state based on auth status
    await updateUIBasedOnAuth();


    // Set up clear output button listeners
    const clearOutputBtn = document.getElementById('clear-output');
    if (clearOutputBtn) {
        clearOutputBtn.addEventListener('click', function() {
            const outputElement = document.getElementById('output');
            if (outputElement) {
                outputElement.textContent = 'Run your Python code to see output here';
                if (typeof showToast === 'function') {
                    showToast('Output cleared', 'success');
                }
            }
        });
    }

    // Set up auth button listeners - ensure this runs after DOM is fully loaded
    setTimeout(() => {
        console.log('Setting up auth button listeners after delay');
        setupAuthButtonListeners();
    }, 100);

    updateUIBasedOnMode('classic-editor');
});

// Also set up listeners when the page is fully loaded (not just DOM)
window.addEventListener('load', function() {
    console.log('Window fully loaded, setting up auth button listeners again');
    
    // Setup auth button listeners after a short delay to ensure everything is properly loaded
    setTimeout(function() {
        setupAuthButtonListeners();
        
        // Force re-attachment of handlers to auth buttons as a final fallback
        const loginBtn = document.getElementById('login-btn');
        const registerBtn = document.getElementById('register-btn');
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        
        if (loginBtn && loginModal) {
            console.log('Adding guaranteed login button handler');
            // Remove any existing handlers
            loginBtn.removeAttribute('onclick');
            // Use addEventListener to avoid overriding
            loginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Login button click - guaranteed handler');
                loginModal.classList.add('show');
                loginModal.style.display = 'flex';
            });
        }
        
        if (registerBtn && registerModal) {
            console.log('Adding guaranteed register button handler');
            // Remove any existing handlers
            registerBtn.removeAttribute('onclick');
            // Use addEventListener to avoid overriding
            registerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Register button click - guaranteed handler');
                registerModal.classList.add('show');
                registerModal.style.display = 'flex';
            });
        }
    }, 500); // Increased delay for better reliability
});

// Update UI elements based on authentication status
async function updateUIBasedOnAuth() {
    const currentUser = typeof window.auth !== 'undefined' && window.auth ? window.auth.currentUser : null;
    const authButtons = document.querySelector('.auth-buttons');
    const loginModal = document.getElementById('login-modal');
    const registerModal = document.getElementById('register-modal');
    const saveCodeBtn = document.getElementById('save-code-btn'); // Assuming these exist in HTML
    const myCodesBtn = document.getElementById('my-codes-btn'); // Assuming these exist in HTML

    if (currentUser) {
        console.log("User is logged in:", currentUser.username);
        const executionCountEl = document.getElementById('execution-count');
        if (executionCountEl) {
            executionCountEl.style.display = 'none';
        }
        // Update header buttons to show user profile/logout/save/my codes
        if (authButtons) {
             // Save the theme toggle button if it exists
             const themeToggleBtn = document.getElementById('theme-toggle-btn');
             
             authButtons.innerHTML = `
                <div class="user-profile">
                    <button class="user-profile-button">
                        <i class="fas fa-user-circle"></i>
                        ${currentUser.username}
                    </button>
                    <div class="user-dropdown">
                        <a href="#" id="save-code-btn"><i class="fas fa-save"></i> Save Code</a>
                        <a href="#" id="my-codes-btn"><i class="fas fa-folder"></i> My Codes</a>
                        <a href="#" id="new-code-btn"><i class="fas fa-file-plus"></i> New Code</a>
                        <a href="#" id="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            `;
             
             // Restore the theme toggle button if it existed
             if (themeToggleBtn) {
                 authButtons.appendChild(themeToggleBtn);
             } else {
                 // Create a new theme toggle button if it didn't exist
                 const newThemeToggleBtn = document.createElement('button');
                 newThemeToggleBtn.id = 'theme-toggle-btn';
                 newThemeToggleBtn.className = 'theme-btn';
                 newThemeToggleBtn.setAttribute('aria-label', 'Toggle dark/light mode');
                 newThemeToggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
                 authButtons.appendChild(newThemeToggleBtn);
             }
             
             // Re-attach auth button listeners for the new elements
             setupAuthButtonListeners();
        }


        // Hide login/register modals if open
        if (loginModal) {
            loginModal.classList.remove('show');
            loginModal.style.display = 'none';
        }
        if (registerModal) {
            registerModal.classList.remove('show');
            registerModal.style.display = 'none';
        }

    } else {
        console.log("User is not logged in (Guest).");
        const executionCountEl = document.getElementById('execution-count');
        if (executionCountEl) {
            let executionCount = parseInt(localStorage.getItem('guestExecutionCount') || '0');
            const remaining = 10 - executionCount;
            if (remaining > 0) {
                executionCountEl.textContent = `Free Executions Remaining: ${remaining}`;
                executionCountEl.style.display = 'block';
            } else {
                executionCountEl.style.display = 'none';
            }
        }
        // Update header buttons to show login/register
        if (authButtons) {
            // Save the theme toggle button if it exists
            const themeToggleBtn = document.getElementById('theme-toggle-btn');
            
            authButtons.innerHTML = `
                <button id="login-btn" class="btn-auth"><i class="fas fa-sign-in-alt"></i> Login</button>
                <button id="register-btn" class="btn-auth"><i class="fas fa-user-plus"></i> Register</button>
            `;
            
            // Restore the theme toggle button if it existed
            if (themeToggleBtn) {
                authButtons.appendChild(themeToggleBtn);
            } else {
                // Create a new theme toggle button if it didn't exist
                const newThemeToggleBtn = document.createElement('button');
                newThemeToggleBtn.id = 'theme-toggle-btn';
                newThemeToggleBtn.className = 'theme-btn';
                newThemeToggleBtn.setAttribute('aria-label', 'Toggle dark/light mode');
                newThemeToggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
                authButtons.appendChild(newThemeToggleBtn);
            }
            
            // Re-attach auth button listeners for login/register
            setupAuthButtonListeners();
        }

    }
}

function updateUIBasedOnMode(mode) {
    // This function can be expanded later if mode-specific UI changes are needed
    console.log(`UI updated for ${mode}`);
}

// Function to load saved code into the editor
async function loadCodeToEditor(codeId) {
    try {
        const response = await fetch(`${window.auth.apiBaseUrl}/get_codes.php?id=${codeId}`);
        if (!response.ok) {
            throw new Error('Failed to fetch code');
        }
        
        const data = await response.json();
        if (data.success && data.data) {
            const code = data.data;
            
            // Set the code content in the editor
            // The code_content field contains the actual code to load
            window.editor.setEditorContent(code.code_content);
            
            // Track the currently loaded code for update functionality
            currentlyLoadedCode = {
                id: code.id,
                title: code.title,
                originalContent: code.code_content
            };
            
            // Close the modal
            const myCodesModal = document.getElementById('my-codes-modal');
            if (myCodesModal) {
                myCodesModal.classList.remove('show');
                setTimeout(() => {
                    myCodesModal.style.display = 'none';
                }, 300);
            }
            
            // Show success message with update option
            showToast(`Code "${code.title}" loaded successfully! You can now edit and update it.`);
            
            // Update the save button text to indicate update mode
            updateSaveButtonState();
        } else {
            throw new Error(data.message || 'Failed to load code');
        }
    } catch (error) {
        console.error('Error loading code:', error);
        showToast('Failed to load code. Please try again.', 'error');
    }
}

// Set up event listeners for auth-related buttons
function setupAuthButtonListeners() {
    console.log('Setting up auth button listeners');
    
    // Find the buttons
    const loginBtn = document.getElementById('login-btn');
    const registerBtn = document.getElementById('register-btn');
    const showRegisterLink = document.getElementById('show-register');
    const showLoginLink = document.getElementById('show-login');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const closeButtons = document.querySelectorAll('.modal .close');
    const userProfileBtn = document.querySelector('.user-profile-button');
    const logoutBtn = document.getElementById('logout-btn');
    const saveCodeBtn = document.getElementById('save-code-btn');
    const myCodesBtn = document.getElementById('my-codes-btn');
    const newCodeBtn = document.getElementById('new-code-btn');
    const loginModal = document.getElementById('login-modal');
    const registerModal = document.getElementById('register-modal');
    
    // Login button click handler
    if (loginBtn) {
        loginBtn.removeEventListener('click', openLoginModal);
        loginBtn.addEventListener('click', openLoginModal);
    }
    
    // Register button click handler
    if (registerBtn) {
        registerBtn.removeEventListener('click', openRegisterModal);
        registerBtn.addEventListener('click', openRegisterModal);
    }
    
    // Show register link click handler (inside login modal)
    if (showRegisterLink) {
        showRegisterLink.onclick = function(e) {
            e.preventDefault();
            if (loginModal) {
                loginModal.classList.remove('show');
                setTimeout(() => {
                    loginModal.style.display = 'none';
                    if (registerModal) {
                        registerModal.classList.add('show');
                        registerModal.style.display = 'flex';
                    }
                }, 300);
            }
        };
    }
    
    // Show login link click handler (inside register modal)
    if (showLoginLink) {
        showLoginLink.onclick = function(e) {
            e.preventDefault();
            if (registerModal) {
                registerModal.classList.remove('show');
                setTimeout(() => {
                    registerModal.style.display = 'none';
                    if (loginModal) {
                        loginModal.classList.add('show');
                        loginModal.style.display = 'flex';
                    }
                }, 300);
            }
        };
    }
    
    // Login form submit handler
    if (loginForm) {
        loginForm.onsubmit = handleLoginFormSubmit;
    }
    
    // Register form submit handler
    if (registerForm) {
        registerForm.onsubmit = handleRegisterFormSubmit;
    }
    
    // Close button handlers for modals
    closeButtons.forEach(button => {
        button.onclick = handleModalClose;
    });
    
    // Click outside modal to close
    window.onclick = handleWindowModalClose;
    
    // User profile button click handler (if logged in)
    if (userProfileBtn) {
        userProfileBtn.onclick = handleUserProfileToggle;
    }
    
    // Logout button click handler
    if (logoutBtn) {
        logoutBtn.onclick = handleLogoutButtonClick;
    }
    
    // Save code button click handler
    if (saveCodeBtn) {
        saveCodeBtn.onclick = handleSaveCodeButtonClick;
    }
    
    // My codes button click handler
    if (myCodesBtn) {
        myCodesBtn.onclick = handleMyCodesButtonClick;
    }
    
    // New code button click handler
    if (newCodeBtn) {
        newCodeBtn.onclick = handleNewCodeButtonClick;
    }

    // Add listener for user profile dropdown toggle (if it exists)
    const userProfileButton = document.querySelector('.user-profile-button');
    const userProfile = document.querySelector('.user-profile');
    
    if(userProfileButton && userProfile) {
        // Remove existing listeners
        userProfileButton.removeEventListener('click', handleUserProfileToggle);
        userProfileButton.removeEventListener('mouseenter', handleUserProfileHover);
        userProfile.removeEventListener('mouseleave', handleUserProfileLeave);
        
        // Add new listeners
        userProfileButton.addEventListener('click', handleUserProfileToggle);
        userProfileButton.addEventListener('mouseenter', handleUserProfileHover);
        userProfile.addEventListener('mouseleave', handleUserProfileLeave);
        
        console.log('User profile hover and click listeners attached');
    }
}

// Handler functions for modal display
function openLoginModal(e) {
    e.preventDefault();
    e.stopPropagation();
    const loginModal = document.getElementById('login-modal');
    console.log('Login button clicked, attempting to open modal.');
    if (loginModal) {
        loginModal.classList.add('show');
        loginModal.style.display = 'flex';
    } else {
        console.error('Login modal not found!');
    }
}

function openRegisterModal(e) {
    e.preventDefault();
    e.stopPropagation();
    const registerModal = document.getElementById('register-modal');
    console.log('Register button clicked, attempting to open modal.');
    if (registerModal) {
        registerModal.classList.add('show');
        registerModal.style.display = 'flex';
    } else {
        console.error('Register modal not found!');
    }
}

// Handlers for auth button events - these are kept for backward compatibility
// but we now use direct handler functions in setupAuthButtonListeners
function handleLoginButtonClick() {
    console.log('Legacy login button handler called');
    // Hide register modal if open
    const registerModal = document.getElementById('register-modal');
    if (registerModal) {
        registerModal.classList.remove('show');
        registerModal.style.display = 'none';
    }
    // Show login modal
    const loginModal = document.getElementById('login-modal');
    if (loginModal) {
        loginModal.classList.add('show');
        loginModal.style.display = 'flex';
    }
} 
function handleRegisterButtonClick() {
    console.log('Legacy register button handler called');
    // Hide login modal if open
    const loginModal = document.getElementById('login-modal');
    if (loginModal) {
        loginModal.classList.remove('show');
        loginModal.style.display = 'none';
    }
    // Show register modal
    const registerModal = document.getElementById('register-modal');
    if (registerModal) {
        registerModal.classList.add('show');
        registerModal.style.display = 'flex';
    }
}
async function handleLogoutButtonClick(e) {
    e.preventDefault();
    if (typeof window.auth !== 'undefined') {
        await window.auth.logout();
        updateUIBasedOnAuth(); // Update UI after logout
    }
}
async function handleSaveCodeButtonClick(e) {
    e.preventDefault();
    if (typeof window.auth !== 'undefined' && typeof window.editor !== 'undefined') {
        const code = window.editor.getEditorContent();
        
        // Check if we're updating existing code or saving new code
        if (currentlyLoadedCode) {
            // Check if the code has been modified
            if (code === currentlyLoadedCode.originalContent) {
                showToast('No changes detected in the code.', 'info');
                return;
            }
            
            // Ask user if they want to update existing code or save as new
            const action = confirm(
                `You are currently editing "${currentlyLoadedCode.title}".\n\n` +
                `Click OK to UPDATE the existing code, or Cancel to save as a NEW code.`
            );
            
            if (action) {
                // Update existing code
                try {
                    await window.auth.updateCode(currentlyLoadedCode.id, code, currentlyLoadedCode.title);
                    showToast(`Code "${currentlyLoadedCode.title}" updated successfully!`, 'success');
                    
                    // Update the original content to reflect the new state
                    currentlyLoadedCode.originalContent = code;
                } catch (error) {
                    console.error('Update error:', error);
                    showToast('Failed to update code: ' + (error.message || 'Unknown error'), 'error');
                }
            } else {
                // Save as new code
                const title = prompt("Enter a title for your new code:");
                if (title) {
                    try {
                        await window.auth.saveCode(code, title);
                        showToast(`New code "${title}" saved successfully!`, 'success');
                        // Clear the currently loaded code since we saved as new
                        currentlyLoadedCode = null;
                        updateSaveButtonState();
                    } catch (error) {
                        console.error('Save error:', error);
                        showToast('Failed to save code: ' + (error.message || 'Unknown error'), 'error');
                    }
                }
            }
        } else {
            // Save new code
            const title = prompt("Enter a title for your code:");
            if (title) {
                try {
                    await window.auth.saveCode(code, title);
                    showToast(`Code "${title}" saved successfully!`, 'success');
                } catch (error) {
                    console.error('Save error:', error);
                    showToast('Failed to save code: ' + (error.message || 'Unknown error'), 'error');
                }
            }
        }
    }
}
async function handleMyCodesButtonClick(e) {
    e.preventDefault();
    if (typeof window.auth !== 'undefined') {
        const myCodesModal = document.getElementById('my-codes-modal');
        const savedCodesList = document.getElementById('saved-codes-list');
        
        if (myCodesModal && savedCodesList) {
            try {
                // Show loading state
                savedCodesList.innerHTML = '<p class="loading">Loading your saved codes...</p>';
                myCodesModal.classList.add('show');
                myCodesModal.style.display = 'flex';

                const result = await window.auth.loadSavedCodes();
                console.log("Loaded codes:", result.data);

                if (result.data && result.data.length > 0) {
                    // Display the codes with enhanced actions
                    const codesHtml = result.data.map(code => `
                        <div class="saved-code-item" data-code-id="${code.id}">
                            <div class="saved-code-header">
                                <h3>${escapeHtml(code.title)}</h3>
                                <span class="saved-code-date">Last modified: ${new Date(code.last_modified).toLocaleDateString()}</span>
                            </div>
                            <pre class="saved-code-content">${escapeHtml(code.code_content)}</pre>
                            <div class="saved-code-actions">
                                <button class="btn-load" onclick="loadCodeToEditor('${code.id}')">
                                    <i class="fas fa-download"></i> Load to Editor
                                </button>
                                <button class="btn-update" onclick="updateCodeInline('${code.id}', '${escapeHtml(code.title)}')">
                                    <i class="fas fa-edit"></i> Quick Update
                                </button>
                                <button class="btn-delete" onclick="deleteCode('${code.id}', '${escapeHtml(code.title)}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    `).join('');
                    savedCodesList.innerHTML = codesHtml;
                } else {
                    savedCodesList.innerHTML = '<p class="no-codes">No saved codes found. Start coding and save your work!</p>';
                }
            } catch (error) {
                console.error("Failed to load codes:", error);
                savedCodesList.innerHTML = `
                    <p class="error">Error: Failed to load saved codes</p>
                    <p>Please try again later or contact support.</p>
                `;
            }
        }
    }
}

async function handleNewCodeButtonClick(e) {
    e.preventDefault();
    
    // Check if there are unsaved changes
    if (currentlyLoadedCode && typeof window.editor !== 'undefined') {
        const currentContent = window.editor.getEditorContent();
        if (currentContent !== currentlyLoadedCode.originalContent) {
            const saveChanges = confirm(
                `You have unsaved changes to "${currentlyLoadedCode.title}".\n\n` +
                `Do you want to save your changes before starting a new code?`
            );
            
            if (saveChanges) {
                try {
                    await window.auth.updateCode(currentlyLoadedCode.id, currentContent, currentlyLoadedCode.title);
                    showToast(`Changes to "${currentlyLoadedCode.title}" saved successfully!`, 'success');
                } catch (error) {
                    console.error('Save error:', error);
                    showToast('Failed to save changes: ' + (error.message || 'Unknown error'), 'error');
                    return; // Don't proceed if save failed
                }
            }
        }
    }
    
    // Clear the editor and reset state
    if (typeof window.editor !== 'undefined') {
        window.editor.setEditorContent('# Welcome to Python Practice!\n# Start coding here...\n\nprint("Hello, World!")');
    }
    
    // Clear currently loaded code state
    clearCurrentlyLoadedCode();
    
    showToast('Started new code. Ready to create something amazing!', 'success');
}

function handleShowRegisterClick(e) {
    e.preventDefault();
    const loginModal = document.getElementById('login-modal');
    const registerModal = document.getElementById('register-modal');
    
    loginModal.classList.remove('show');
    setTimeout(() => {
        loginModal.style.display = 'none';
        registerModal.classList.add('show');
        registerModal.style.display = 'flex';
    }, 300);
}
function handleShowLoginClick(e) {
    e.preventDefault();
    const registerModal = document.getElementById('register-modal');
    const loginModal = document.getElementById('login-modal');
    
    registerModal.classList.remove('show');
    setTimeout(() => {
        registerModal.style.display = 'none';
        loginModal.classList.add('show');
        loginModal.style.display = 'flex';
    }, 300);
}
async function handleLoginFormSubmit(e) {
    e.preventDefault();
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    const messageEl = document.getElementById('login-message');
    messageEl.textContent = ''; messageEl.className = 'form-message'; messageEl.style.display = 'block';
    messageEl.textContent = 'Logging in...';

    if (typeof window.auth !== 'undefined') {
        try {
            const response = await window.auth.login(username, password);
            if (response.success) {
                messageEl.textContent = 'Login successful!'; messageEl.classList.add('success');
                setTimeout(() => {
                    const loginModal = document.getElementById('login-modal');
                    loginModal.classList.remove('show');
                    setTimeout(() => {
                        loginModal.style.display = 'none';
                        updateUIBasedOnAuth(); // Update UI after login
                    }, 300);
                }, 500);
            } else {
                 throw new Error(response.message || 'Login failed');
            }
        } catch (error) {
            messageEl.textContent = error.message || 'Invalid username or password';
            messageEl.classList.add('error');
        }
    }
}
async function handleRegisterFormSubmit(e) {
    e.preventDefault();
    const username = document.getElementById('register-username').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;
    const confirmPassword = document.getElementById('register-confirm-password').value;
    const messageEl = document.getElementById('register-message');
    messageEl.textContent = ''; messageEl.className = 'form-message'; messageEl.style.display = 'block';

    if (password !== confirmPassword) {
        messageEl.textContent = 'Passwords do not match'; messageEl.classList.add('error'); return;
    }
    messageEl.textContent = 'Creating account...';

    if (typeof window.auth !== 'undefined') {
        try {
            const response = await window.auth.register(username, email, password);
             if (response.success) {
                messageEl.textContent = 'Registration successful! Please log in.'; messageEl.classList.add('success');
                document.getElementById('register-form').reset();
                setTimeout(() => {
                    const registerModal = document.getElementById('register-modal');
                    const loginModal = document.getElementById('login-modal');
                    
                    registerModal.classList.remove('show');
                    setTimeout(() => {
                        registerModal.style.display = 'none';
                        loginModal.classList.add('show');
                        loginModal.style.display = 'flex'; // Show login modal
                    }, 300);
                }, 1000);
            } else {
                 throw new Error(response.message || 'Registration failed');
            }
        } catch (error) {
            messageEl.textContent = error.message || 'Registration failed';
            messageEl.classList.add('error');
        }
    }
}
function handleModalClose() {
    const modal = this.closest('.modal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300); // Match the transition time in CSS
}
function handleWindowModalClose(e) {
    const loginModal = document.getElementById('login-modal');
    const registerModal = document.getElementById('register-modal');
    if (e.target === loginModal) {
        loginModal.classList.remove('show');
        setTimeout(() => {
            loginModal.style.display = 'none';
        }, 300); // Match the transition time in CSS
    }
    if (e.target === registerModal) {
        registerModal.classList.remove('show');
        setTimeout(() => {
            registerModal.style.display = 'none';
        }, 300); // Match the transition time in CSS
    }
}
function handleUserProfileToggle(e) {
    e.preventDefault();
    e.stopPropagation();
    console.log('User profile button clicked - toggling dropdown');
    const userProfile = this.closest('.user-profile');
    if (userProfile) {
        userProfile.classList.toggle('active');
        console.log('User profile active state:', userProfile.classList.contains('active'));
    } else {
        console.error('User profile container not found');
    }
}

function handleUserProfileHover(e) {
    console.log('User profile button hovered - showing dropdown');
    const userProfile = this.closest('.user-profile');
    if (userProfile) {
        userProfile.classList.add('active');
    }
}

function handleUserProfileLeave(e) {
    console.log('User profile area left - hiding dropdown');
    const userProfile = this;
    if (userProfile) {
        // Add a small delay to prevent flickering when moving between button and dropdown
        setTimeout(() => {
            if (!userProfile.matches(':hover')) {
                userProfile.classList.remove('active');
            }
        }, 100);
    }
}

// Set up theme toggle listener (needs to be called after auth buttons are potentially re-rendered)
function setupThemeToggleListener() {
     const themeToggleBtn = document.getElementById('theme-toggle-btn');
     if (themeToggleBtn) {
         console.log('Setting up theme toggle button click handler');
         // Remove existing listener before adding to prevent duplicates
         themeToggleBtn.removeEventListener('click', handleThemeToggleClick);
         themeToggleBtn.addEventListener('click', handleThemeToggleClick);
         
         // Also initialize it in the theme object if theme.js is loaded
         if (typeof window.theme !== 'undefined') {
             window.theme.themeToggleBtn = themeToggleBtn;
             console.log('Registered theme toggle button with theme.js');
         }
     } else {
         console.error('Theme toggle button not found in setupThemeToggleListener');
     }
}

// Handler for theme toggle button click (delegated to theme.js)
function handleThemeToggleClick() {
    console.log('Theme toggle button clicked');
    if (typeof window.theme !== 'undefined' && typeof window.theme.toggleTheme === 'function') {
        console.log('Calling theme.toggleTheme()...');
        window.theme.toggleTheme(); // Call the toggleTheme function from theme.js
    } else {
        console.error("Theme module or toggleTheme function not available.");
        // Fallback implementation if theme.js isn't loaded or working properly
        const body = document.documentElement;
        const isDark = body.classList.contains('dark-mode');
        
        // Toggle classes
        body.classList.remove('light-mode', 'dark-mode');
        body.classList.add(isDark ? 'light-mode' : 'dark-mode');
        
        // Update the icon
        const icon = document.querySelector('#theme-toggle-btn i');
        if (icon) {
            icon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
        }
        
        // Save to localStorage
        localStorage.setItem('theme', isDark ? 'light-mode' : 'dark-mode');
    }
}

// Helper function to escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to update save button state based on whether code is loaded for editing
function updateSaveButtonState() {
    const saveCodeBtn = document.getElementById('save-code-btn');
    if (saveCodeBtn) {
        if (currentlyLoadedCode) {
            saveCodeBtn.innerHTML = '<i class="fas fa-save"></i> Update/Save Code';
            saveCodeBtn.title = `Currently editing: ${currentlyLoadedCode.title}`;
        } else {
            saveCodeBtn.innerHTML = '<i class="fas fa-save"></i> Save Code';
            saveCodeBtn.title = 'Save new code';
        }
    }
}

// Function to clear currently loaded code (useful when starting fresh)
function clearCurrentlyLoadedCode() {
    currentlyLoadedCode = null;
    updateSaveButtonState();
    showToast('Switched to new code mode', 'info');
}

// Function for quick inline update of code title and content
async function updateCodeInline(codeId, currentTitle) {
    try {
        // Get the current code content from the saved codes list
        const codeElement = document.querySelector(`[data-code-id="${codeId}"] .saved-code-content`);
        if (!codeElement) {
            throw new Error('Code element not found');
        }
        
        const currentContent = codeElement.textContent;
        
        // Prompt for new title
        const newTitle = prompt(`Update title for "${currentTitle}":`, currentTitle);
        if (!newTitle || newTitle === currentTitle) {
            // If cancelled or no change, ask if they want to update content
            const updateContent = confirm('Do you want to update the code content with what\'s currently in the editor?');
            if (!updateContent) {
                return;
            }
        }
        
        // Get content from editor or keep current
        const newContent = window.editor ? window.editor.getEditorContent() : currentContent;
        const finalTitle = newTitle || currentTitle;
        
        // Confirm the update
        const confirmUpdate = confirm(
            `Update code "${finalTitle}"?\n\n` +
            `This will replace the saved version with the current content.`
        );
        
        if (!confirmUpdate) {
            return;
        }
        
        // Perform the update
        await window.auth.updateCode(codeId, newContent, finalTitle);
        showToast(`Code "${finalTitle}" updated successfully!`, 'success');
        
        // Refresh the codes list
        handleMyCodesButtonClick({ preventDefault: () => {} });
        
        // Update currently loaded code if it matches
        if (currentlyLoadedCode && currentlyLoadedCode.id == codeId) {
            currentlyLoadedCode.title = finalTitle;
            currentlyLoadedCode.originalContent = newContent;
            updateSaveButtonState();
        }
        
    } catch (error) {
        console.error('Update error:', error);
        showToast('Failed to update code: ' + (error.message || 'Unknown error'), 'error');
    }
}

// Function to delete a saved code
async function deleteCode(codeId, title) {
    try {
        // Confirm deletion
        const confirmDelete = confirm(
            `Are you sure you want to delete "${title}"?\n\n` +
            `This action cannot be undone.`
        );
        
        if (!confirmDelete) {
            return;
        }
        
        // Perform the deletion
        await window.auth.deleteCode(codeId);
        showToast(`Code "${title}" deleted successfully!`, 'success');
        
        // Clear currently loaded code if it matches the deleted one
        if (currentlyLoadedCode && currentlyLoadedCode.id == codeId) {
            currentlyLoadedCode = null;
            updateSaveButtonState();
        }
        
        // Refresh the codes list
        handleMyCodesButtonClick({ preventDefault: () => {} });
        
    } catch (error) {
        console.error('Delete error:', error);
        showToast('Failed to delete code: ' + (error.message || 'Unknown error'), 'error');
    }
}

// Initial setup of theme toggle listener
document.addEventListener('DOMContentLoaded', () => {
    console.log('Setting up theme toggle listener on DOMContentLoaded');
    setupThemeToggleListener();
});

// Also set up theme toggle listener when the page is fully loaded
window.addEventListener('load', function() {
    console.log('Setting up theme toggle listener on window load');
    setupThemeToggleListener();
});
