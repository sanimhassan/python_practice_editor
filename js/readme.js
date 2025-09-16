document.addEventListener('DOMContentLoaded', () => {
    const readmeTab = document.querySelector('.tab[data-tab="readme"]');
    if (readmeTab) {
        readmeTab.addEventListener('click', () => {
            populateReadmeExamples();
        });
    }

    // Initial population if the tab is already active
    if (readmeTab.classList.contains('active')) {
        populateReadmeExamples();
    }
});

function populateReadmeExamples() {
    const exampleButtonsContainer = document.getElementById('example-buttons');
    const exampleCodeDisplay = document.getElementById('example-code-display');

    if (exampleButtonsContainer && exampleCodeDisplay) {
        // Clear existing buttons and code
        exampleButtonsContainer.innerHTML = '';
        exampleCodeDisplay.textContent = '';

        const examples = window.editor.examples;
        let isFirst = true;

        for (const key in examples) {
            const button = document.createElement('button');
            button.className = 'btn example-btn';
            button.textContent = key.charAt(0).toUpperCase() + key.slice(1);
            button.dataset.example = key;

            button.addEventListener('click', () => {
                // Remove active class from all buttons
                document.querySelectorAll('.example-btn').forEach(btn => btn.classList.remove('active'));
                // Add active class to the clicked button
                button.classList.add('active');
                window.editor.loadExampleCode(key);
            });

            exampleButtonsContainer.appendChild(button);

            if (isFirst) {
                button.classList.add('active');
                window.editor.loadExampleCode(key);
                isFirst = false;
            }
        }
    }
}