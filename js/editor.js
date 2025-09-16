// Manages CodeMirror editor instances and mode switching

let editorInstance = null;

// Example code snippets for quick loading - optimized for hybrid engine
const examples = {
    hello: `# This runs a simple hello example
print("Hello, World!")
print("Welcome to Python Practice!")
name = input("What's your name? ")
print("Nice to meet you, " + name + "!")`,
    
    math: `import math

# Basic math operations with f-strings 
a, b = 10, 3
print(f"{a} + {b} = {a + b}")
print(f"{a} - {b} = {a - b}")
print(f"{a} * {b} = {a * b}")
print(f"{a} / {b} = {a / b:.2f}")
print(f"{a} ** {b} = {a ** b}")

# Math module functions
print(f"sqrt(16) = {math.sqrt(16)}")
print(f"sin(π/2) = {math.sin(math.pi/2):.2f}")
print(f"factorial(5) = {math.factorial(5)}")`,

    loops: `# List comprehension with f-strings 
numbers = list(range(1, 11))
print("Numbers:", numbers)

squares = [x**2 for x in numbers]
print("Squares:", squares)

# For loop with conditions
print("\\nEven numbers:")
for num in numbers:
    if num % 2 == 0:
        print(f"  {num}")

# While loop example
count = 0
factorial = 1
n = 5
while count < n:
    count += 1
    factorial *= count
print(f"{n}! = {factorial}")`,

    functions: `# Functions without f-strings 
def greet(name, age=None):
    """A simple greeting function with optional parameter"""
    if age:
        return "Hello " + name + ", you are " + str(age) + " years old!"
    return "Hello " + name + "!"

def calculate_area(shape, **kwargs):
    """Calculate area of different shapes"""
    if shape == "rectangle":
        return kwargs["width"] * kwargs["height"]
    elif shape == "circle":
        import math
        return math.pi * kwargs["radius"] ** 2
    elif shape == "triangle":
        return 0.5 * kwargs["base"] * kwargs["height"]
    return 0

# Test functions
print(greet("Alice"))
print(greet("Bob", 25))

rect_area = calculate_area("rectangle", width=5, height=3)
circle_area = calculate_area("circle", radius=4)
triangle_area = calculate_area("triangle", base=6, height=4)

print("Rectangle area:", rect_area)
print("Circle area:", round(circle_area, 2))
print("Triangle area:", triangle_area)

# Lambda functions
numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
evens = list(filter(lambda x: x % 2 == 0, numbers))
doubled = list(map(lambda x: x * 2, numbers))

print("Even numbers:", evens)
print("Doubled numbers:", doubled)`,

    Fibonacci: `#fibonacci demo - runs instantly!
def fibonacci(n):
    if n <= 1:
        return n
    return fibonacci(n-1) + fibonacci(n-2)

print("Fibonacci sequence :")
for i in range(10):
    print("F(" + str(i) + ") =", fibonacci(i))

name = input("Enter your name: ")
print("Hello, " + name + "! This is a fibonacci demo.")
print("Check this out. Try to use it in the editor and see the result")`,

    input_with_fstring: `# Input with f-strings
name = input("Enter your name: ")
age = int(input("Enter your age: "))
print(f"Welcome {name}, your age is {age}")

# More f-string examples  
city = input("Enter your city: ")
print(f"You live in {city}")
print(f"Next year you'll be {age + 1} years old!")

# This demonstrates the hybrid engine's smart usage of f-strings`
};

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Classic Editor
    editorInstance = CodeMirror(document.getElementById('editor'), {
        mode: 'python',
        lineNumbers: true,
        indentUnit: 4,
        tabSize: 4,
        lineWrapping: true,
        autoCloseBrackets: true,
        matchBrackets: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-lint-markers", "CodeMirror-foldgutter"],
        lint: true,
        foldGutter: true,
        extraKeys: {
            "Ctrl-Space": "autocomplete",
            "Cmd-Space": "autocomplete",
            "Ctrl-Enter": function(cm) {
                document.getElementById('run-btn').click();
            },
            "Cmd-Enter": function(cm) {
                document.getElementById('run-btn').click();
            },
            "Tab": function(cm) {
                if (cm.somethingSelected()) {
                    cm.indentSelection("add");
                } else {
                    cm.replaceSelection(cm.getOption("indentWithTabs")? "\t":
                        Array(cm.getOption("indentUnit") + 1).join(" "), "end", "+input");
                }
            },
            "Shift-Tab": function(cm) {
                cm.indentSelection("subtract");
            },
            "Cmd-/": "toggleComment",
            "Ctrl-/": "toggleComment"
        },
        value: ''
    });

    // Add this event listener to trigger autocomplete on input
    editorInstance.on('inputRead', function(cm, change) {
        // Only trigger hints if it's not a space or a common statement terminator
        if (change.text[0] && !/^[ ;\n\r]$/.test(change.text[0])) {
            cm.showHint({ completeSingle: false });
        }
    });

    setupTabs();
    setupRunButton();
    setupResetButton();
    setupClearOutputButtons();
    setupEditorThemeToggle();
    loadAndApplyEditorTheme();
    setupAutocompleteButton();

    setTimeout(() => {
        if(editorInstance) editorInstance.refresh();
    }, 100);

    const currentUser = typeof window.auth !== 'undefined' ? window.auth.getCurrentUser() : null;
    if (!currentUser) {
        const executionCount = parseInt(localStorage.getItem('guestExecutionCount') || '0');
        updateExecutionCountUI(executionCount);
    }
});

function setupTabs() {
    const tabs = document.querySelectorAll('.tabs .tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.dataset.tab;

            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            document.querySelector(`.tab-content[data-tab-content="${targetTab}"]`).classList.add('active');

            if (targetTab === 'classic-editor') {
                editorInstance.refresh();
            }
        });
    });
}

// Function to set up Run button
function setupRunButton() {
    const runBtn = document.getElementById('run-btn');
    if (runBtn) {
        runBtn.addEventListener('click', async () => {
            const currentUser = typeof window.auth !== 'undefined' ? window.auth.getCurrentUser() : null;

            if (!currentUser) {
                let executionCount = parseInt(localStorage.getItem('guestExecutionCount') || '0');
                if (executionCount >= 10) {
                    if (typeof showToast === 'function') {
                        showToast('You have reached the maximum of 10 free executions. Please log in to continue.', 'info');
                    }
                    return;
                }
                executionCount++;
                localStorage.setItem('guestExecutionCount', executionCount);
                updateExecutionCountUI(executionCount);
            }

            if (!editorInstance) {
                console.error("Editor not initialized");
                if(typeof showToast === 'function') showToast('Editor not ready.', 'error');
                return;
            }
            const code = editorInstance.getValue();
            // Always use the full python engine now
            if (typeof window.hybridPythonEngine !== 'undefined' && typeof window.hybridPythonEngine.runPythonCode === 'function') {
                try {
                    await window.hybridPythonEngine.runPythonCode(code, 'output');
                } catch (error) {
                    console.error('Error running Python code:', error);
                    if(typeof showToast === 'function') showToast('Error running code: ' + error.message, 'error');
                }
            } else {
                console.error("No Python engine available");
                if(typeof showToast === 'function') showToast('Python engine not ready.', 'error');
            }
        });
    }
}

// Function to set up Reset button
function updateExecutionCountUI(count) {
    const executionCountEl = document.getElementById('execution-count');
    if (executionCountEl) {
        const remaining = 10 - count;
        executionCountEl.textContent = `Free Executions Remaining: ${remaining}`;
        executionCountEl.style.display = 'block';
    }
}

function setupResetButton() {
    const resetBtn = document.getElementById('reset-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (editorInstance) {
                editorInstance.setValue('');
                document.getElementById('output').textContent = 'Run your Python code to see output here';
                if(typeof showToast === 'function') showToast('Editor reset', 'success');
            }
        });
    }

}

// Function to set up Clear Output buttons
function setupClearOutputButtons() {
    // Toolbar clear button for basic mode
    const clearOutputBtn = document.getElementById('clear-output-btn');
    if (clearOutputBtn) {
        clearOutputBtn.addEventListener('click', () => {
            const outputElement = document.getElementById('output');
            if (outputElement) {
                outputElement.textContent = 'Run your Python code to see output here';
                if (typeof showToast === 'function') showToast('Output cleared', 'success');
            }
        });
    }

    // Output panel clear button for basic mode
    const clearOutput = document.getElementById('clear-output');
    if (clearOutput) {
        clearOutput.addEventListener('click', () => {
            const outputElement = document.getElementById('output');
            if (outputElement) {
                outputElement.textContent = 'Run your Python code to see output here';
                if (typeof showToast === 'function') {
                    showToast('Output cleared', 'success');
                }
            }
        });
    }

}

// Get current editor content
function getEditorContent() {
    return editorInstance ? editorInstance.getValue() : '';
}

// Set editor content
function setEditorContent(code) {
    if (editorInstance) {
        editorInstance.setValue(code);
        setTimeout(() => editorInstance.refresh(), 10);
    }
}

// Function to set up editor theme toggle buttons
function setupEditorThemeToggle() {
    const themeToggleBtn = document.getElementById('editor-theme-toggle-btn');
    
    const toggleEditorTheme = () => {
        const currentTheme = localStorage.getItem('editor-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setEditorTheme(newTheme);
        if (typeof showToast === 'function') {
            showToast(`Editor ${newTheme} theme applied`, 'info');
        }
    };
    
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', toggleEditorTheme);
    }
}

// Function to set editor theme
function setEditorTheme(theme) {
    // Save theme preference to localStorage
    localStorage.setItem('editor-theme', theme);
    
    // Apply theme to the editor
    if (editorInstance) {
        const wrapper = editorInstance.getWrapperElement();
        wrapper.classList.remove('cm-theme-dark', 'cm-theme-light');
        wrapper.classList.add(`cm-theme-${theme}`);
    }
    
    // Apply theme to all editor containers for consistent styling
    const editorContainers = document.querySelectorAll('.editor-container');
    editorContainers.forEach(container => {
        container.classList.remove('cm-theme-dark', 'cm-theme-light');
        container.classList.add(`cm-theme-${theme}`);
    });
    
    // Apply theme to the editor element itself
    const editorElement = document.getElementById('editor');
    if (editorElement) {
        editorElement.classList.remove('cm-theme-dark', 'cm-theme-light');
        editorElement.classList.add(`cm-theme-${theme}`);
    }
    
    // Force a refresh to ensure the theme is fully applied
    setTimeout(() => {
        if (editorInstance) editorInstance.refresh();
    }, 10);
}

// Function to load and apply saved editor theme
function loadAndApplyEditorTheme() {
    // Get saved theme or default to dark
    const savedTheme = localStorage.getItem('editor-theme') || 'dark';
    // Apply the theme
    setEditorTheme(savedTheme);
}

// Function to add example code buttons functionality
function addExampleCodeButtons() {
    const exampleButtons = document.querySelectorAll('.example-btn');
    
    exampleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const exampleName = this.dataset.example;
            loadExampleCode(exampleName);
        });
    });
}

// Function to load example code
function loadExampleCode(exampleName) {
    const codeDisplay = document.getElementById('example-code-display');
    if (examples[exampleName] && codeDisplay) {
        codeDisplay.textContent = examples[exampleName];
        // Also set the content in the main editor
        if (editorInstance) {
            editorInstance.setValue(examples[exampleName]);
        }
        if (typeof showToast === 'function') {
            showToast(`Loaded ${exampleName} example into editor`, 'success');
        }
    }
}

// Function to load default example
function loadDefaultExample() {
    // Load the Skulpt demo by default to show fast execution
    if (typeof showToast === 'function') {
        showToast('Loaded basic editor', 'success');
    }
}

// Function to set up the autocomplete button
function setupAutocompleteButton() {
    const autocompleteBtn = document.getElementById('autocomplete-btn');
    if (autocompleteBtn) {
        autocompleteBtn.addEventListener('click', () => {
            if (editorInstance) {
                editorInstance.execCommand('autocomplete');
            }
        });
    }
}

// Export functions
window.editor = {
    getEditorContent: () => {
        return editorInstance ? editorInstance.getValue() : '';
    },
    setEditorContent: (code) => {
        if (editorInstance) {
            editorInstance.setValue(code);
            editorInstance.refresh();
        }
    },
    setEditorTheme,
    loadExampleCode,
    examples
};
