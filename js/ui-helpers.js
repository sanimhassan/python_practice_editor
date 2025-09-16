// Utility functions for UI interactions (toasts, modals, etc.)

// Show a toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = 'toast';
    toast.classList.add(type);

    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Export showToast for use in other modules
window.showToast = showToast;

// Function to clear the content of an output panel
function clearOutputPanel(outputId) {
    console.log(`Attempting to clear output panel: ${outputId}`);
    const outputElement = document.getElementById(outputId);
    if (outputElement) {
        // Reset to the default placeholder text
        outputElement.textContent = 'Run your Python code to see output here';
        showToast(`Output cleared for ${outputId}`, 'success');
    } else {
        console.error(`Output element with ID '${outputId}' not found.`);
        showToast(`Error: Output panel '${outputId}' not found.`, 'error');
    }
}

// Export clearOutputPanel for use in other modules (e.g., app-controller.js)
window.clearOutputPanel = clearOutputPanel;
